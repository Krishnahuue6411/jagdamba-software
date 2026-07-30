<?php
// Sahara Electrical - Unified Database API (api.php)

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['logged_in'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: Please log in first.']);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
function getDbConnection() {
    require_once __DIR__ . '/db_config.php';
    return getDatabaseConnection();
}


function deleteInvoice($inv_no, $pdo) {
    $inv_no = trim($inv_no);
    // 1. Try to revert stock based on exact consumption log if records exist
    $stmtLog = $pdo->prepare("SELECT `inward_id`, `m_code`, `qty_used` FROM `invoice_consumption_log` WHERE TRIM(`inv_no`) = :inv_no");
    $stmtLog->execute([':inv_no' => $inv_no]);
    $logs = $stmtLog->fetchAll();

    if (!empty($logs)) {
        foreach ($logs as $log) {
            $inward_id = $log['inward_id'];
            $rm_code = $log['m_code'];
            $qty_used = floatval($log['qty_used']);

            // Restore inward_transaction balance
            $stmtRestoreInw = $pdo->prepare("UPDATE `inward_transaction` SET `bal_qty` = `bal_qty` + :qty WHERE `id` = :id");
            $stmtRestoreInw->execute([':qty' => $qty_used, ':id' => $inward_id]);

            // Restore raw_material master balance
            $stmtRestoreRm = $pdo->prepare("UPDATE `raw_material` SET `bal_qty` = `bal_qty` + :qty WHERE `m_code` = :m_code");
            $stmtRestoreRm->execute([':qty' => $qty_used, ':m_code' => $rm_code]);
        }

        // Delete consumption log
        $stmtDelLog = $pdo->prepare("DELETE FROM `invoice_consumption_log` WHERE TRIM(`inv_no`) = :inv_no");
        $stmtDelLog->execute([':inv_no' => $inv_no]);
    } else {
        // Fallback: Restore using LIFO heuristic for backward compatibility (older invoices)
        $stmt = $pdo->prepare("SELECT `wound_code`, `qty` FROM `tax_invoice` WHERE TRIM(`inv_no`) = :inv_no");
        $stmt->execute([':inv_no' => $inv_no]);
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            $wound_code = $item['wound_code'];
            $qty = floatval($item['qty']);

            // Fetch BOM requirements
            $stmtBom = $pdo->prepare("SELECT `rm_code`, `req_qty` FROM `bom` WHERE `wound_code` = :wound_code");
            $stmtBom->execute([':wound_code' => $wound_code]);
            $bomItems = $stmtBom->fetchAll();

            foreach ($bomItems as $bomItem) {
                $rm_code = $bomItem['rm_code'];
                $req_qty = floatval($bomItem['req_qty']);
                $total_req_qty = $req_qty * $qty;

                // Restore master raw_material balance
                $stmtRm = $pdo->prepare("UPDATE `raw_material` SET `bal_qty` = `bal_qty` + :quantity WHERE `m_code` = :m_code");
                $stmtRm->execute([':quantity' => $total_req_qty, ':m_code' => $rm_code]);

                // Restore inward_transaction balances (LIFO - reverse of FIFO)
                $remainingToRestore = $total_req_qty;
                $stmtInwSelect = $pdo->prepare("SELECT `id`, `in_qty`, `bal_qty` FROM `inward_transaction`
                                                WHERE `m_code` = :m_code AND `bal_qty` < `in_qty`
                                                ORDER BY `ch_date` DESC, `id` DESC");
                $stmtInwSelect->execute([':m_code' => $rm_code]);
                $inwRows = $stmtInwSelect->fetchAll();

                $stmtInwUpdate = $pdo->prepare("UPDATE `inward_transaction` SET `bal_qty` = :bal_qty WHERE `id` = :id");

                foreach ($inwRows as $row) {
                    if ($remainingToRestore <= 0) {
                        break;
                    }
                    $rowId = $row['id'];
                    $inQty = floatval($row['in_qty']);
                    $balQty = floatval($row['bal_qty']);
                    $room = $inQty - $balQty;

                    if ($room >= $remainingToRestore) {
                        $newBal = $balQty + $remainingToRestore;
                        $remainingToRestore = 0;
                    } else {
                        $newBal = $inQty;
                        $remainingToRestore -= $room;
                    }

                    $stmtInwUpdate->execute([':bal_qty' => $newBal, ':id' => $rowId]);
                }
            }
        }
    }

    // 2. Revert machines status
    $stmtMc = $pdo->prepare("SELECT `machine_no` FROM `invoice_machines` WHERE TRIM(`inv_no`) = :inv_no");
    $stmtMc->execute([':inv_no' => $inv_no]);
    $machines = $stmtMc->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($machines)) {
        $stmtUpdateMc = $pdo->prepare("UPDATE `remaining_machines` SET `status` = 'pending' WHERE `machine_no` = :machine_no AND `status` = 'invoiced'");
        foreach ($machines as $mc) {
            $stmtUpdateMc->execute([':machine_no' => $mc]);
        }
    }

    // 3. Delete mappings
    $stmtDelMap = $pdo->prepare("DELETE FROM `invoice_machines` WHERE TRIM(`inv_no`) = :inv_no");
    $stmtDelMap->execute([':inv_no' => $inv_no]);

    // 4. Delete tax_invoice rows
    $stmtDelInv = $pdo->prepare("DELETE FROM `tax_invoice` WHERE TRIM(`inv_no`) = :inv_no");
    $stmtDelInv->execute([':inv_no' => $inv_no]);
}

// Helper to return JSON error response
function respondError($message) {
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

// Helper to return JSON success response
function respondSuccess($data = []) {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

// 2. Parse JSON Input
$inputData = json_decode(file_get_contents('php://input'), true);
if (!$inputData) {
    // Fallback to POST variables for simple forms if needed
    $inputData = $_POST;
}

$action = $inputData['action'] ?? $_GET['action'] ?? null;
if (!$action) {
    respondError('Action parameter is missing.');
}

// Allowed tables list to prevent arbitrary table querying
$allowedTables = [
    'raw_material',
    'transport_master',
    'bom',
    'po_master',
    'po_master_copy',
    'inward_transaction',
    'remaining_machines',
    'tax_invoice',
    'invoice_machines',
    'workers',
    'machines',
    'daily_work',
    'parties',
    'challan_inward',
    'nfp_outward',
    'invoice_consumption_log'
];

try {
    $pdo = getDbConnection();

    switch ($action) {
        // --- 1. list ---
        case 'list':
            $table = $inputData['table'] ?? null;
            if (!in_array($table, $allowedTables)) {
                respondError("Invalid or restricted table: $table");
            }
            $limit = intval($inputData['limit'] ?? 100000);

            // Get columns of the table
            $stmtCol = $pdo->prepare("DESCRIBE `$table`");
            $stmtCol->execute();
            $columns = [];
            while ($col = $stmtCol->fetch()) {
                $columns[] = $col['Field'];
            }

            // Get rows
            $queryStr = "SELECT * FROM `$table` ORDER BY id DESC LIMIT :limit";
            $stmt = $pdo->prepare($queryStr);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            respondSuccess([
                'table' => $table,
                'columns' => $columns,
                'rows' => $rows
            ]);
            break;

        // --- 2. insert ---
        case 'insert':
            $table = $inputData['table'] ?? null;
            if (!in_array($table, $allowedTables)) {
                respondError("Invalid or restricted table: $table");
            }
            $data = $inputData['data'] ?? $inputData['row'] ?? null;
            if (!is_array($data) || empty($data)) {
                respondError('Insert data is missing or empty.');
            }

            $fields = array_keys($data);
            $placeholders = array_map(function($f) { return ":$f"; }, $fields);

            $sql = "INSERT INTO `$table` (`" . implode("`, `", $fields) . "`) VALUES (" . implode(", ", $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            $insertId = $pdo->lastInsertId();

            respondSuccess([
                'id' => $insertId
            ]);
            break;

        // --- 2b. insert_batch ---
        case 'insert_batch':
            $table = $inputData['table'] ?? null;
            if (!in_array($table, $allowedTables)) {
                respondError("Invalid or restricted table: $table");
            }
            $rows = $inputData['rows'] ?? null;
            if (!is_array($rows) || empty($rows)) {
                respondError('Insert rows are missing or empty.');
            }

            $pdo->beginTransaction();
            try {
                $firstRow = $rows[0];
                $fields = array_keys($firstRow);
                $placeholders = array_map(function($f) { return ":$f"; }, $fields);
                $sql = "INSERT INTO `$table` (`" . implode("`, `", $fields) . "`) VALUES (" . implode(", ", $placeholders) . ")";
                $stmt = $pdo->prepare($sql);

                foreach ($rows as $row) {
                    $rowData = [];
                    foreach ($fields as $field) {
                        $rowData[":$field"] = isset($row[$field]) ? $row[$field] : null;
                    }
                    $stmt->execute($rowData);
                }
                $pdo->commit();
                respondSuccess(['count' => count($rows)]);
            } catch (Exception $e) {
                $pdo->rollBack();
                respondError('Batch insert failed: ' . $e->getMessage());
            }
            break;

        // --- 3. update ---
        case 'update':
            $table = $inputData['table'] ?? null;
            if (!in_array($table, $allowedTables)) {
                respondError("Invalid or restricted table: $table");
            }
            $data = $inputData['data'] ?? $inputData['row'] ?? null;
            $id = $inputData['id'] ?? $inputData['original']['id'] ?? $data['id'] ?? null;
            if (!$id || !is_array($data) || empty($data)) {
                respondError('Update ID or data is missing.');
            }

            $updateParts = [];
            foreach ($data as $key => $val) {
                $updateParts[] = "`$key` = :$key";
            }

            $sql = "UPDATE `$table` SET " . implode(", ", $updateParts) . " WHERE `id` = :__update_id";
            $stmt = $pdo->prepare($sql);

            // Bind fields
            foreach ($data as $key => $val) {
                $stmt->bindValue(":$key", $val);
            }
            $stmt->bindValue(":__update_id", $id, PDO::PARAM_INT);
            $stmt->execute();

            respondSuccess();
            break;

        // --- 4. delete ---
        case 'delete':
            $table = $inputData['table'] ?? null;
            if (!in_array($table, $allowedTables)) {
                respondError("Invalid or restricted table: $table");
            }
            $id = $inputData['id'] ?? null;
            if (!$id) {
                respondError('Delete ID is missing.');
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $sql = "DELETE FROM `$table` WHERE `id` = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            respondSuccess();
            break;

        // --- 5. truncate ---
        case 'truncate':
            $table = $inputData['table'] ?? null;
            // Restrict truncate for safety
            if (!in_array($table, ['po_master', 'po_master_copy'])) {
                respondError("Truncate action is restricted for this table.");
            }

            $sql = "TRUNCATE TABLE `$table`";
            $pdo->exec($sql);
            respondSuccess();
            break;

        // --- 5b. delete_all_rows ---
        case 'delete_all_rows':
            $table = $inputData['table'] ?? null;
            if (!in_array($table, $allowedTables)) {
                respondError("Invalid or restricted table: $table");
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $sql = "DELETE FROM `$table`";
            $pdo->exec($sql);
            try {
                $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            } catch (Exception $e) {
                // Silently ignore if auto-increment reset fails
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            respondSuccess();
            break;

        // --- 6. count ---
        case 'count':
            $table = $inputData['table'] ?? null;
            if (!in_array($table, $allowedTables)) {
                respondError("Invalid or restricted table: $table");
            }

            $sql = "SELECT COUNT(*) as cnt FROM `$table`";
            $stmt = $pdo->query($sql);
            $row = $stmt->fetch();

            respondSuccess(['count' => intval($row['cnt'])]);
            break;

        // --- 7. query ---
        case 'query':
            $sql = $inputData['sql'] ?? null;
            $params = $inputData['params'] ?? [];
            if (!$sql) {
                respondError('Query SQL is missing.');
            }

            // Base64 decode SQL queries if encoded by the client
            if (!empty($inputData['is_encoded'])) {
                $decoded = base64_decode($sql, true);
                if ($decoded !== false) {
                    $sql = $decoded;
                }
            } else {
                // Fallback: check if the string does not start with standard SQL keywords but is valid Base64
                $trimmed = ltrim($sql);
                if (!preg_match('/^\s*(SELECT|SHOW|DESCRIBE|DELETE)/i', $trimmed)) {
                    $decoded = base64_decode($sql, true);
                    if ($decoded !== false) {
                        $sql = $decoded;
                    }
                }
            }

            // Tighten security on client-supplied SQL
            $trimmedSql = ltrim($sql);

            // 1. Strict keyword checks (no comments, no unions, no multiple queries)
            if (preg_match('/(--|\/\*|\*\/|#)/', $sql)) {
                respondError('SQL comments are not allowed.');
            }
            if (strpos($sql, ';') !== false) {
                respondError('Multiple SQL statements are not allowed.');
            }
            if (preg_match('/\bUNION\b/i', $sql)) {
                respondError('UNION queries are not allowed.');
            }

            // 2. Only allow specific safe SQL operations
            $isSelect = stripos($trimmedSql, 'SELECT') === 0;
            $isShow = stripos($trimmedSql, 'SHOW') === 0;
            $isDescribe = stripos($trimmedSql, 'DESCRIBE') === 0;
            $isSafeDelete = stripos($trimmedSql, 'DELETE FROM `nfp_outward`') === 0;

            if (!$isSelect && !$isShow && !$isDescribe && !$isSafeDelete) {
                respondError('Only SELECT, SHOW, DESCRIBE, or specific safe DELETE queries are allowed.');
            }

            // 3. For SELECT/DESCRIBE/DELETE queries, ensure they only reference allowed tables
            if ($isSelect || $isDescribe || $isSafeDelete) {
                // Extract all words/identifiers that could represent a table name
                // Looking for words following FROM or JOIN, optionally enclosed in backticks
                if (preg_match_all('/\b(?:FROM|JOIN)\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $matches)) {
                    $referencedTables = $matches[1];
                    foreach ($referencedTables as $refTable) {
                        if (!in_array(strtolower($refTable), $allowedTables)) {
                            respondError('Query references an unauthorized table: ' . htmlspecialchars($refTable));
                        }
                    }
                }
                // Also double check for any other disallowed table names in the raw query
                // To be completely safe, we shouldn't have any table names not in the allowed list
                if (preg_match('/\b(information_schema|mysql|performance_schema|sys)\b/i', $sql)) {
                    respondError('Access to system schemas is strictly prohibited.');
                }
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            respondSuccess(['rows' => $rows]);
            break;

        // ==========================================
        // CUSTOM TRANSACTION ENDPOINTS
        // ==========================================

        // --- A. Archive PO (po_master to po_master_copy) ---
        case 'archive_po':
            $pdo->beginTransaction();
            try {
                // Copy all records
                $copySql = "INSERT INTO `po_master_copy` (`pono`, `po_date`, `item_no`, `wound_code`, `description`, `drg_no`, `rate`, `unit`)
                            SELECT `pono`, `po_date`, `item_no`, `wound_code`, `description`, `drg_no`, `rate`, `unit` FROM `po_master`";
                $pdo->exec($copySql);

                // Truncate po_master
                $pdo->exec("TRUNCATE TABLE `po_master`");

                $pdo->commit();
                respondSuccess();
            } catch (\Exception $ex) {
                $pdo->rollBack();
                respondError("Archive failed: " . $ex->getMessage());
            }
            break;

        // --- B. Generate Machines and Insert (remaining_machines) ---
        case 'generate_machines':
            $ch_no = $inputData['ch_no'] ?? null;
            $ch_date = $inputData['ch_date'] ?? null;
            $mode = $inputData['mode'] ?? 'variable'; // 'variable' or 'standard'

            if (!$ch_no || !$ch_date || !$mode) {
                respondError('Challan Number, Challan Date, and Mode are required.');
            }

            $machines = [];

            if ($mode === 'variable') {
                $start = $inputData['start_no'] ?? '';
                $end = $inputData['end_no'] ?? '';

                if (!$start || !$end) {
                    respondError('Starting and Ending machine numbers are required for variable mode.');
                }

                // Parse components: prefix, digits, suffix
                // Example: NADN14023AV -> prefix: NADN, digits: 14023, suffix: AV
                $pattern = '/^([a-zA-Z\-_]*?)(\d+)([a-zA-Z\-_]*)$/';
                if (preg_match($pattern, $start, $matchStart) && preg_match($pattern, $end, $matchEnd)) {
                    $prefix = $matchStart[1];
                    $suffix = $matchStart[3];
                    $startNum = intval($matchStart[2]);
                    $endNum = intval($matchEnd[2]);
                    $digitLen = strlen($matchStart[2]);

                    if ($matchStart[1] !== $matchEnd[1] || $matchStart[3] !== $matchEnd[3]) {
                        respondError('Prefix or suffix mismatch between start and end machine number.');
                    }
                    if ($startNum > $endNum) {
                        respondError('Start number cannot be greater than end number.');
                    }
                    if (($endNum - $startNum) > 1000) {
                        respondError('Range is too large. Maximum 1000 machines allowed.');
                    }

                    for ($i = $startNum; $i <= $endNum; $i++) {
                        $paddedNum = str_pad($i, $digitLen, '0', STR_PAD_LEFT);
                        $machines[] = $prefix . $paddedNum . $suffix;
                    }
                } else {
                    respondError('Could not parse numeric range from machine serial numbers. Ensure they contain digits.');
                }
            } else if ($mode === 'standard') {
                $base = $inputData['base_no'] ?? '';
                $count = intval($inputData['count'] ?? 0);

                if (!$base || $count <= 0) {
                    respondError('Base machine number and count greater than 0 are required.');
                }
                if ($count > 1000) {
                    respondError('Count is too large. Maximum 1000 machines allowed.');
                }

                // If base contains digits, increment them; otherwise append suffix numbers
                $pattern = '/^([a-zA-Z\-_]*?)(\d+)([a-zA-Z\-_]*)$/';
                if (preg_match($pattern, $base, $match)) {
                    $prefix = $match[1];
                    $suffix = $match[3];
                    $startNum = intval($match[2]);
                    $digitLen = strlen($match[2]);

                    for ($i = 0; $i < $count; $i++) {
                        $paddedNum = str_pad($startNum + $i, $digitLen, '0', STR_PAD_LEFT);
                        $machines[] = $prefix . $paddedNum . $suffix;
                    }
                } else {
                    // Fallback if no digits: just append numbers
                    for ($i = 1; $i <= $count; $i++) {
                        $machines[] = $base . '-' . $i;
                    }
                }
            } else {
                respondError('Invalid generation mode.');
            }

            if (empty($machines)) {
                respondError('No machines generated.');
            }

            $party_id = isset($inputData['party_id']) && $inputData['party_id'] !== '' ? intval($inputData['party_id']) : null;
            $part_no = $inputData['part_no'] ?? null;

            $pdo->beginTransaction();
            try {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `remaining_machines` WHERE `machine_no` = :machine_no");
                $sqlInsert = "INSERT INTO `remaining_machines` (`ch_no`, `ch_date`, `machine_no`, `status`, `party_id`, `part_no`, `mode`) VALUES (:ch_no, :ch_date, :machine_no, 'pending', :party_id, :part_no, :mode)";
                $stmt = $pdo->prepare($sqlInsert);

                foreach ($machines as $mc) {
                    $stmtCheck->execute([':machine_no' => $mc]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        continue; // Skip insertion, no error
                    }
                    $stmt->execute([
                        ':ch_no' => $ch_no,
                        ':ch_date' => $ch_date,
                        ':machine_no' => $mc,
                        ':party_id' => $party_id,
                        ':part_no' => $part_no,
                        ':mode' => $mode
                    ]);
                }
                $pdo->commit();

                respondSuccess(['machines' => $machines]);
            } catch (\Exception $ex) {
                $pdo->rollBack();
                respondError("Failed to save machines: " . $ex->getMessage());
            }
            break;

        // --- C. Save Tax Invoice with Multi-table Updates ---
        case 'save_tax_invoice':
            $invoiceHeader = $inputData['invoiceHeader'] ?? null;
            $items = $inputData['items'] ?? [];

            if (!$invoiceHeader || empty($items)) {
                respondError('Invoice header and at least one item are required.');
            }

            $inv_no = $invoiceHeader['inv_no'] ?? null;
            $inv_date = $invoiceHeader['inv_date'] ?? null;
            $vehicle_no = $invoiceHeader['vehicle_no'] ?? null;
            $asn_no = $invoiceHeader['asn_no'] ?? '9988';
            $hsn_code = $invoiceHeader['hsn_code'] ?? '9988';
            $party_id = isset($invoiceHeader['party_id']) && $invoiceHeader['party_id'] !== '' ? intval($invoiceHeader['party_id']) : null;
            $part_no = $invoiceHeader['part_no'] ?? null;
            $include_sign = isset($invoiceHeader['include_sign']) ? intval($invoiceHeader['include_sign']) : 1;

            if (!$inv_no || !$inv_date || !$vehicle_no) {
                respondError('Invoice Number, Invoice Date, and Vehicle Number are required.');
            }
            // Part Number is optional
            // if (!$part_no) {
            //     respondError('Part Number is required.');
            // }

            $pdo->beginTransaction();
            try {
                $is_edit = isset($inputData['is_edit']) && $inputData['is_edit'] === true;
                $original_inv_no = $invoiceHeader['original_inv_no'] ?? $inputData['original_inv_no'] ?? $inv_no;
                if ($is_edit) {
                    deleteInvoice($original_inv_no, $pdo);
                }

                $processedInvoiceMachines = [];

                // 1. First Pass: Validate BOM existence and stock availability for ALL items
                foreach ($items as $itemIndex => $item) {
                    $wound_code = $item['wound_code'] ?? null;
                    $qty = floatval($item['qty'] ?? 0);

                    if (!$wound_code || $qty <= 0) {
                        throw new \Exception("Item " . ($itemIndex + 1) . ": Wound Code and positive Quantity are required.");
                    }

                    // Fetch BOM requirements
                    $sqlBom = "SELECT `rm_code`, `req_qty` FROM `bom` WHERE `wound_code` = :wound_code";
                    $stmtBom = $pdo->prepare($sqlBom);
                    $stmtBom->execute([':wound_code' => $wound_code]);
                    $bomItems = $stmtBom->fetchAll();

                    if (empty($bomItems)) {
                        throw new \Exception("Item " . ($itemIndex + 1) . ": Wound Code '$wound_code' does not exist in the Bill of Materials (BOM) registry.");
                    }

                    // Check stock (isolated by part_no)
                    foreach ($bomItems as $bomItem) {
                        $rm_code = $bomItem['rm_code'];
                        $req_qty_per_unit = floatval($bomItem['req_qty']);
                        $total_req_qty = $req_qty_per_unit * $qty;

                        if ($part_no) {
                            $sqlStock = "SELECT COALESCE(SUM(bal_qty), 0) as total_bal FROM `inward_transaction` WHERE `m_code` = :m_code AND `part_no` = :part_no AND `bal_qty` > 0";
                            $stmtStock = $pdo->prepare($sqlStock);
                            $stmtStock->execute([':m_code' => $rm_code, ':part_no' => $part_no]);
                        } else {
                            $sqlStock = "SELECT COALESCE(SUM(bal_qty), 0) as total_bal FROM `inward_transaction` WHERE `m_code` = :m_code AND `bal_qty` > 0";
                            $stmtStock = $pdo->prepare($sqlStock);
                            $stmtStock->execute([':m_code' => $rm_code]);
                        }
                        $stockRow = $stmtStock->fetch();
                        $availableStock = floatval($stockRow['total_bal'] ?? 0);

                        if ($availableStock < $total_req_qty) {
                            throw new \Exception("Item " . ($itemIndex + 1) . " ($wound_code): Insufficient stock for raw material '$rm_code'. Required: $total_req_qty, Available: $availableStock.");
                        }
                    }
                }

                // 2. Second Pass: Perform DB inserts and stock deductions
                foreach ($items as $item) {
                    $wound_code = $item['wound_code'];
                    $qty = floatval($item['qty']);
                    $rate = floatval($item['rate'] ?? 0);
                    $uom = $item['uom'] ?? 'NOS';
                    $po_no = $item['po_no'] ?? null;
                    $po_date = $item['po_date'] ?? null;
                    $item_sr_no = $item['item_sr_no'] ?? null;
                    $drg_no = $item['drg_no'] ?? '9988';
                    $selectedMachines = $item['machines'] ?? [];

                    $amount = $qty * $rate;
                    $cgst = $amount * 0.09;
                    $sgst = $amount * 0.09;
                    $net_total = $amount + $cgst + $sgst;

                    // Insert into tax_invoice (including part_no)
                    $sqlInv = "INSERT INTO `tax_invoice` (
                        `inv_no`, `inv_date`, `po_no`, `po_date`, `asn_no`, `vehicle_no`,
                        `description`, `item_sr_no`, `drg_no`, `hsn_code`, `qty`, `uom`,
                        `rate`, `amount`, `sub_total`, `cgst`, `sgst`, `net_total`, `wound_code`, `party_id`, `part_no`, `include_sign`
                    ) VALUES (
                        :inv_no, :inv_date, :po_no, :po_date, :asn_no, :vehicle_no,
                        :description, :item_sr_no, :drg_no, :hsn_code, :qty, :uom,
                        :rate, :amount, :sub_total, :cgst, :sgst, :net_total, :wound_code, :party_id, :part_no, :include_sign
                    )";
                    $stmtInv = $pdo->prepare($sqlInv);
                    $stmtInv->execute([
                        ':inv_no' => $inv_no,
                        ':inv_date' => $inv_date,
                        ':po_no' => $po_no,
                        ':po_date' => $po_date,
                        ':asn_no' => $asn_no,
                        ':vehicle_no' => $vehicle_no,
                        ':description' => $wound_code,
                        ':item_sr_no' => $item_sr_no,
                        ':drg_no' => $drg_no,
                        ':hsn_code' => $hsn_code,
                        ':qty' => $qty,
                        ':uom' => $uom,
                        ':rate' => $rate,
                        ':amount' => $amount,
                        ':sub_total' => $amount,
                        ':cgst' => $cgst,
                        ':sgst' => $sgst,
                        ':net_total' => $net_total,
                        ':wound_code' => $wound_code,
                        ':party_id' => $party_id,
                        ':part_no' => $part_no,
                        ':include_sign' => $include_sign
                    ]);

                    // Insert into invoice_machines & Update remaining_machines status to 'invoiced'
                    if (!empty($selectedMachines)) {
                        $sqlMap = "INSERT INTO `invoice_machines` (`inv_no`, `machine_no`) VALUES (:inv_no, :machine_no)";
                        $stmtMap = $pdo->prepare($sqlMap);

                        $sqlUpdateMc = "UPDATE `remaining_machines` SET `status` = 'invoiced' WHERE `machine_no` = :machine_no AND `status` = 'pending'";
                        $stmtUpdateMc = $pdo->prepare($sqlUpdateMc);

                        foreach ($selectedMachines as $mc) {
                            if (!in_array($mc, $processedInvoiceMachines)) {
                                $stmtMap->execute([
                                    ':inv_no' => $inv_no,
                                    ':machine_no' => $mc
                                ]);
                                $stmtUpdateMc->execute([
                                    ':machine_no' => $mc
                                ]);
                                $processedInvoiceMachines[] = $mc;
                            }
                        }
                    }

                    // Stock FIFO Deductions for this item
                    $sqlBom = "SELECT `rm_code`, `req_qty` FROM `bom` WHERE `wound_code` = :wound_code";
                    $stmtBom = $pdo->prepare($sqlBom);
                    $stmtBom->execute([':wound_code' => $wound_code]);
                    $bomItems = $stmtBom->fetchAll();

                    foreach ($bomItems as $bomItem) {
                        $rm_code = $bomItem['rm_code'];
                        $req_qty_per_unit = floatval($bomItem['req_qty']);
                        $total_req_qty = $req_qty_per_unit * $qty;

                        // Subtract from master raw_material first
                        $sqlRmUpdate = "UPDATE `raw_material` SET `bal_qty` = `bal_qty` - :deduction WHERE `m_code` = :m_code";
                        $stmtRmUpdate = $pdo->prepare($sqlRmUpdate);
                        $stmtRmUpdate->execute([
                            ':deduction' => $total_req_qty,
                            ':m_code' => $rm_code
                        ]);

                        // Deduct from inward_transactions using FIFO (isolated by part_no)
                        $remainingToDeduct = $total_req_qty;

                        if ($part_no) {
                            $sqlInwardSelect = "SELECT `id`, `bal_qty` FROM `inward_transaction`
                                                WHERE `m_code` = :m_code AND `part_no` = :part_no AND `bal_qty` > 0
                                                ORDER BY `ch_date` ASC, `id` ASC";
                            $stmtInwardSelect = $pdo->prepare($sqlInwardSelect);
                            $stmtInwardSelect->execute([':m_code' => $rm_code, ':part_no' => $part_no]);
                        } else {
                            $sqlInwardSelect = "SELECT `id`, `bal_qty` FROM `inward_transaction`
                                                WHERE `m_code` = :m_code AND `bal_qty` > 0
                                                ORDER BY `ch_date` ASC, `id` ASC";
                            $stmtInwardSelect = $pdo->prepare($sqlInwardSelect);
                            $stmtInwardSelect->execute([':m_code' => $rm_code]);
                        }
                        $inwardRows = $stmtInwardSelect->fetchAll();

                        $sqlInwardUpdate = "UPDATE `inward_transaction` SET `bal_qty` = :bal_qty WHERE `id` = :id";
                        $stmtInwardUpdate = $pdo->prepare($sqlInwardUpdate);

                        foreach ($inwardRows as $row) {
                            if ($remainingToDeduct <= 0) {
                                break;
                            }

                            $rowId = $row['id'];
                            $currentBal = floatval($row['bal_qty']);
                            $deducted = 0;

                            if ($currentBal >= $remainingToDeduct) {
                                $newBal = $currentBal - $remainingToDeduct;
                                $deducted = $remainingToDeduct;
                                $remainingToDeduct = 0;
                            } else {
                                $newBal = 0;
                                $deducted = $currentBal;
                                $remainingToDeduct -= $currentBal;
                            }

                            $stmtInwardUpdate->execute([
                                ':bal_qty' => $newBal,
                                ':id' => $rowId
                            ]);

                            // Log consumption into invoice_consumption_log
                            if ($deducted > 0) {
                                $stmtLogCons = $pdo->prepare("INSERT INTO `invoice_consumption_log` (`inv_no`, `inward_id`, `m_code`, `qty_used`) VALUES (:inv_no, :inward_id, :m_code, :qty_used)");
                                $stmtLogCons->execute([
                                    ':inv_no' => $inv_no,
                                    ':inward_id' => $rowId,
                                    ':m_code' => $rm_code,
                                    ':qty_used' => $deducted
                                ]);
                            }
                        }

                        // Apply remaining deficit to the latest inward row if any
                        if ($remainingToDeduct > 0) {
                            $sqlLastInward = "SELECT `id`, `bal_qty` FROM `inward_transaction` WHERE `m_code` = :m_code AND `part_no` = :part_no ORDER BY `ch_date` DESC, `id` DESC LIMIT 1";
                            $stmtLastInward = $pdo->prepare($sqlLastInward);
                            $stmtLastInward->execute([':m_code' => $rm_code, ':part_no' => $part_no]);
                            $lastRow = $stmtLastInward->fetch();
                            if ($lastRow) {
                                $newBal = floatval($lastRow['bal_qty']) - $remainingToDeduct;
                                $stmtInwardUpdate->execute([
                                    ':bal_qty' => $newBal,
                                    ':id' => $lastRow['id']
                                ]);

                                // Log consumption for deficit
                                $stmtLogCons = $pdo->prepare("INSERT INTO `invoice_consumption_log` (`inv_no`, `inward_id`, `m_code`, `qty_used`) VALUES (:inv_no, :inward_id, :m_code, :qty_used)");
                                $stmtLogCons->execute([
                                    ':inv_no' => $inv_no,
                                    ':inward_id' => $lastRow['id'],
                                    ':m_code' => $rm_code,
                                    ':qty_used' => $remainingToDeduct
                                ]);
                            }
                        }
                    }
                }

                $pdo->commit();
                respondSuccess(['inv_no' => $inv_no]);
            } catch (\Exception $ex) {
                $pdo->rollBack();
                respondError("Failed to save tax invoice: " . $ex->getMessage());
            }
            break;

        // --- D. Save Inward Transaction (handles Insert & Edit with stock adjustment) ---
        case 'save_inward_transaction':
            $id = isset($inputData['id']) && $inputData['id'] !== '' ? intval($inputData['id']) : null;
            $data = $inputData['data'] ?? null;
            if (!$data) {
                respondError('Inward data is missing.');
            }

            $ch_no = $data['ch_no'] ?? null;
            $ch_date = $data['ch_date'] ?? null;
            $m_code = $data['m_code'] ?? null;
            $in_qty = floatval($data['in_qty'] ?? 0);
            $party_id = isset($data['party_id']) && $data['party_id'] !== '' ? intval($data['party_id']) : null;

            if (!$ch_no || !$ch_date || !$m_code || $in_qty <= 0) {
                respondError('Challan No, Date, Material Code, and positive quantity are required.');
            }

            $pdo->beginTransaction();
            try {
                // Fetch the new material master info
                $stmtNewRm = $pdo->prepare("SELECT `id`, `bal_qty` FROM `raw_material` WHERE `m_code` = :m_code");
                $stmtNewRm->execute([':m_code' => $m_code]);
                $newRm = $stmtNewRm->fetch();
                if (!$newRm) {
                    throw new \Exception("Material Code '$m_code' does not exist in master registry.");
                }

                if ($id) {
                    // --- EDIT MODE ---
                    // 1. Fetch original inward transaction
                    $stmtOrig = $pdo->prepare("SELECT `ch_no`, `m_code`, `in_qty`, `bal_qty`, `party_id`, `ch_date` FROM `inward_transaction` WHERE `id` = :id");
                    $stmtOrig->execute([':id' => $id]);
                    $orig = $stmtOrig->fetch();
                    if (!$orig) {
                        throw new \Exception("Original inward transaction not found.");
                    }

                    $origChNo = $orig['ch_no'];
                    $origMCode = $orig['m_code'];
                    $origInQty = floatval($orig['in_qty']);
                    $origBalQty = floatval($orig['bal_qty']);

                    // 2. If changing material code or reducing quantity, check consumption first!
                    // Calculate how much has been consumed: consumed = in_qty - bal_qty
                    $consumedQty = $origInQty - $origBalQty;

                    if ($m_code !== $origMCode) {
                        // Changing material code completely!
                        if ($consumedQty > 0) {
                            throw new \Exception("Cannot edit: This inward transaction's stock has already been partially consumed.");
                        }

                        // Revert old material master stock
                        $stmtOldRm = $pdo->prepare("SELECT `id`, `bal_qty` FROM `raw_material` WHERE `m_code` = :m_code");
                        $stmtOldRm->execute([':m_code' => $origMCode]);
                        $oldRm = $stmtOldRm->fetch();
                        if ($oldRm) {
                            $oldRmBal = floatval($oldRm['bal_qty']);
                            if ($oldRmBal < $origInQty) {
                                throw new \Exception("Cannot edit: Insufficient stock of original material '$origMCode' to revert.");
                            }
                            $stmtOldRmUpdate = $pdo->prepare("UPDATE `raw_material` SET `bal_qty` = `bal_qty` - :qty WHERE `id` = :id");
                            $stmtOldRmUpdate->execute([':qty' => $origInQty, ':id' => $oldRm['id']]);
                        }

                        // Add new material stock
                        $stmtNewRmUpdate = $pdo->prepare("UPDATE `raw_material` SET `bal_qty` = `bal_qty` + :qty WHERE `id` = :id");
                        $stmtNewRmUpdate->execute([':qty' => $in_qty, ':id' => $newRm['id']]);

                        $newBalQty = $in_qty;
                    } else {
                        // Same material code, quantity change
                        // If new quantity is less than what has already been consumed, block it!
                        if ($in_qty < $consumedQty) {
                            throw new \Exception("Cannot edit: New inward quantity ($in_qty) cannot be less than already consumed quantity ($consumedQty).");
                        }

                        $qtyDiff = $in_qty - $origInQty;

                        // If reducing, check if master stock is sufficient
                        if ($qtyDiff < 0) {
                            $absDiff = abs($qtyDiff);
                            $stmtRmStock = $pdo->prepare("SELECT `bal_qty` FROM `raw_material` WHERE `m_code` = :m_code");
                            $stmtRmStock->execute([':m_code' => $m_code]);
                            $rmStockRow = $stmtRmStock->fetch();
                            if (floatval($rmStockRow['bal_qty']) < $absDiff) {
                                throw new \Exception("Cannot edit: Insufficient master stock to deduct the difference.");
                            }
                        }

                        // Update master raw_material stock
                        $stmtRmUpdate = $pdo->prepare("UPDATE `raw_material` SET `bal_qty` = `bal_qty` + :qty_diff WHERE `m_code` = :m_code");
                        $stmtRmUpdate->execute([':qty_diff' => $qtyDiff, ':m_code' => $m_code]);

                        $newBalQty = $origBalQty + $qtyDiff;
                    }

                    // 3. Update inward_transaction record
                    $updateFields = [];
                    $updateParams = [':id' => $id];
                    foreach ($data as $key => $val) {
                        if ($key === 'id' || $key === 'bal_qty') continue;
                        $updateFields[] = "`$key` = :$key";
                        $updateParams[":$key"] = $val;
                    }
                    $updateFields[] = "`bal_qty` = :bal_qty";
                    $updateParams[':bal_qty'] = $newBalQty;

                    $sqlUpd = "UPDATE `inward_transaction` SET " . implode(", ", $updateFields) . " WHERE `id` = :id";
                    $stmtUpd = $pdo->prepare($sqlUpd);
                    $stmtUpd->execute($updateParams);

                    // 4. Update ch_no/ch_date and party_id in remaining_machines if changed
                    if ($origChNo !== $ch_no || $orig['ch_date'] !== $ch_date || $orig['party_id'] !== $party_id) {
                        $stmtMcUpd = $pdo->prepare("UPDATE `remaining_machines` SET `ch_no` = :new_ch, `ch_date` = :new_date, `party_id` = :new_party WHERE `ch_no` = :old_ch");
                        $stmtMcUpd->execute([
                            ':new_ch' => $ch_no,
                            ':new_date' => $ch_date,
                            ':new_party' => $party_id,
                            ':old_ch' => $origChNo
                        ]);
                    }
                } else {
                    // --- INSERT MODE ---
                    $fields = array_keys($data);
                    $placeholders = array_map(function($f) { return ":$f"; }, $fields);

                    // Set bal_qty equal to in_qty
                    if (!in_array('bal_qty', $fields)) {
                        $fields[] = 'bal_qty';
                        $placeholders[] = ':bal_qty';
                        $data['bal_qty'] = $in_qty;
                    }

                    $sql = "INSERT INTO `inward_transaction` (`" . implode("`, `", $fields) . "`) VALUES (" . implode(", ", $placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($data);

                    // Add new stock to raw_material balance
                    $stmtRmUpdate = $pdo->prepare("UPDATE `raw_material` SET `bal_qty` = `bal_qty` + :qty WHERE `id` = :id");
                    $stmtRmUpdate->execute([':qty' => $in_qty, ':id' => $newRm['id']]);
                }

                $pdo->commit();
                respondSuccess();
            } catch (\Exception $ex) {
                $pdo->rollBack();
                respondError($ex->getMessage());
            }
            break;

        // --- E. Delete Inward Transaction with Stock rollback and machine deletion ---
        case 'delete_inward_transaction':
            $id = intval($inputData['id'] ?? 0);
            if (!$id) {
                respondError('Inward ID is required.');
            }

            $pdo->beginTransaction();
            try {
                // 1. Fetch the inward record
                $stmt = $pdo->prepare("SELECT `ch_no`, `m_code`, `in_qty`, `bal_qty` FROM `inward_transaction` WHERE `id` = :id");
                $stmt->execute([':id' => $id]);
                $inward = $stmt->fetch();
                if (!$inward) {
                    throw new \Exception("Inward transaction record not found.");
                }

                $ch_no = $inward['ch_no'];
                $m_code = $inward['m_code'];
                $in_qty = floatval($inward['in_qty']);
                $bal_qty = floatval($inward['bal_qty']);

                // 2. Check if machines generated from this challan are already invoiced
                $stmtMc = $pdo->prepare("SELECT COUNT(*) as cnt FROM `remaining_machines` WHERE `ch_no` = :ch_no AND `status` = 'invoiced'");
                $stmtMc->execute([':ch_no' => $ch_no]);
                $mcRow = $stmtMc->fetch();
                if (intval($mcRow['cnt'] ?? 0) > 0) {
                    throw new \Exception("Cannot delete: Machines generated from this Challan have already been invoiced.");
                }

                // 3. Check if raw material stock is sufficient to deduct the inward quantity
                $stmtRm = $pdo->prepare("SELECT `id`, `bal_qty` FROM `raw_material` WHERE `m_code` = :m_code");
                $stmtRm->execute([':m_code' => $m_code]);
                $rm = $stmtRm->fetch();
                if (!$rm) {
                    throw new \Exception("Raw material '$m_code' not found in master registry.");
                }

                $rm_id = $rm['id'];
                $rm_bal = floatval($rm['bal_qty']);

                if ($rm_bal < $in_qty) {
                    throw new \Exception("Cannot delete: Master stock balance for '$m_code' is insufficient to roll back (Current balance: $rm_bal, Required: $in_qty).");
                }

                // 4. Deduct in_qty from raw_material master stock
                $newRmBal = $rm_bal - $in_qty;
                $stmtRmUpdate = $pdo->prepare("UPDATE `raw_material` SET `bal_qty` = :bal_qty WHERE `id` = :id");
                $stmtRmUpdate->execute([':bal_qty' => $newRmBal, ':id' => $rm_id]);

                // 5. Delete generated machines from remaining_machines
                $stmtDelMc = $pdo->prepare("DELETE FROM `remaining_machines` WHERE `ch_no` = :ch_no");
                $stmtDelMc->execute([':ch_no' => $ch_no]);

                // 6. Delete inward_transaction row
                $stmtDelInw = $pdo->prepare("DELETE FROM `inward_transaction` WHERE `id` = :id");
                $stmtDelInw->execute([':id' => $id]);

                $pdo->commit();
                respondSuccess();
            } catch (\Exception $ex) {
                $pdo->rollBack();
                respondError($ex->getMessage());
            }
            break;

        case 'delete_invoice':
            $inv_no = $inputData['inv_no'] ?? null;
            if (!$inv_no) {
                respondError('Invoice Number is required.');
            }
            $pdo->beginTransaction();
            try {
                deleteInvoice($inv_no, $pdo);
                $pdo->commit();
                respondSuccess();
            } catch (\Exception $ex) {
                $pdo->rollBack();
                respondError("Failed to delete invoice: " . $ex->getMessage());
            }
            break;

        case 'wip_report':
            try {
                // 1. Fetch all machines with status and party information
                $sqlMachines = "SELECT rm.machine_no, rm.ch_no, rm.ch_date, rm.status, rm.party_id, rm.part_no, p.party_name
                                FROM `remaining_machines` rm
                                LEFT JOIN `parties` p ON rm.party_id = p.id
                                ORDER BY rm.ch_date ASC, rm.machine_no ASC";
                $stmtMc = $pdo->query($sqlMachines);
                $machines = $stmtMc->fetchAll();

                // 2. Fetch all inward transactions with linked machines (mc_no is not null and not empty)
                $sqlInwards = "SELECT id, ch_no, ch_date, m_code, in_qty, material_type, mc_no, wip_count
                               FROM `inward_transaction`
                               WHERE `mc_no` IS NOT NULL AND `mc_no` != ''";
                $stmtIn = $pdo->query($sqlInwards);
                $inwards = $stmtIn->fetchAll();

                // 3. Initialize dynamic material types tracking
                $materialTypesMap = [];

                // 4. Initialize allocation map
                $allocationMap = [];
                foreach ($machines as $m) {
                    $mNo = $m['machine_no'];
                    $allocationMap[$mNo] = [
                        'machine_no' => $mNo,
                        'ch_no' => $m['ch_no'],
                        'ch_date' => $m['ch_date'],
                        'status' => $m['status'],
                        'party_id' => $m['party_id'],
                        'party_name' => $m['party_name'] ?? '-',
                        'part_no' => $m['part_no'] ?? '-',
                        'materials' => []
                    ];
                }

                // 5. Calculate allocations dynamically
                foreach ($inwards as $inw) {
                    $mc_no_str = $inw['mc_no'];
                    $mc_nos = array_filter(array_map('trim', explode(',', $mc_no_str)));
                    if (empty($mc_nos)) continue;

                    $mc_count = count($mc_nos);
                    $wip_count = intval($inw['wip_count'] ?? 0);

                    // Determine divisor
                    $divisor = ($wip_count > 0) ? $wip_count : $mc_count;
                    if ($divisor <= 0) continue;

                    // Calculate quantity per machine
                    $qtyPerMc = floatval($inw['in_qty']) / $divisor;

                    // Allocation limit
                    $limit = ($wip_count > 0) ? min($wip_count, $mc_count) : $mc_count;

                    $mType = strtoupper(trim($inw['material_type'] ?? ''));
                    if ($mType === '') {
                        $mType = 'OTHER';
                    }

                    $materialTypesMap[$mType] = true;

                    for ($i = 0; $i < $limit; $i++) {
                        $mNo = $mc_nos[$i];
                        if (isset($allocationMap[$mNo])) {
                            if (!isset($allocationMap[$mNo]['materials'][$mType])) {
                                $allocationMap[$mNo]['materials'][$mType] = 0.0;
                            }
                            $allocationMap[$mNo]['materials'][$mType] += $qtyPerMc;
                        }
                    }
                }

                $uniqueMaterialTypes = array_keys($materialTypesMap);
                sort($uniqueMaterialTypes);

                respondSuccess([
                    'allocations' => array_values($allocationMap),
                    'material_types' => $uniqueMaterialTypes
                ]);
            } catch (\Exception $ex) {
                respondError("WIP aggregation failed: " . $ex->getMessage());
            }
            break;

        case 'save_nfp_challan':
            $challan_no = $inputData['challan_no'] ?? null;
            $challan_date = $inputData['challan_date'] ?? null;
            $party_id = $inputData['party_id'] ?? null;
            $vehicle_no = $inputData['vehicle_no'] ?? null;
            $rows = $inputData['rows'] ?? [];

            if (!$challan_no || !$challan_date) {
                respondError('Challan number and date are required.');
            }

            $pdo->beginTransaction();
            try {
                // 1. Revert previous stock deductions for this challan (if editing/re-saving)
                $stmtOld = $pdo->prepare("SELECT `inward_challan_id`, `qty` FROM `nfp_outward` WHERE `challan_no` = :challan_no");
                $stmtOld->execute([':challan_no' => $challan_no]);
                $oldRows = $stmtOld->fetchAll();

                foreach ($oldRows as $oldRow) {
                    if (!empty($oldRow['inward_challan_id'])) {
                        $stmtRevert = $pdo->prepare("UPDATE `challan_inward` SET `bal_qty` = `bal_qty` + :qty WHERE `id` = :id");
                        $stmtRevert->execute([':qty' => $oldRow['qty'], ':id' => $oldRow['inward_challan_id']]);
                    }
                }

                // Delete old records
                $stmtDel = $pdo->prepare("DELETE FROM `nfp_outward` WHERE `challan_no` = :challan_no");
                $stmtDel->execute([':challan_no' => $challan_no]);

                // 2. Insert new rows and apply new deductions
                $stmtIns = $pdo->prepare("INSERT INTO `nfp_outward`
                    (`challan_no`, `challan_date`, `party_id`, `vehicle_no`, `sr_no`, `wound_code`, `description`, `m_code`, `hsn_code`, `qty`, `uom`, `rate`, `amount`, `inward_challan_id`)
                    VALUES (:challan_no, :challan_date, :party_id, :vehicle_no, :sr_no, :wound_code, :description, :m_code, :hsn_code, :qty, :uom, :rate, :amount, :inward_challan_id)");

                foreach ($rows as $row) {
                    $inwardChallanId = !empty($row['inward_challan_id']) ? intval($row['inward_challan_id']) : null;
                    $qty = floatval($row['qty']);

                    // If linked to inward challan, check stock limit
                    if ($inwardChallanId) {
                        $stmtStock = $pdo->prepare("SELECT `bal_qty`, `ch_no` FROM `challan_inward` WHERE `id` = :id");
                        $stmtStock->execute([':id' => $inwardChallanId]);
                        $stockRow = $stmtStock->fetch();

                        if (!$stockRow) {
                            throw new \Exception("Inward challan reference not found.");
                        }
                        if (floatval($stockRow['bal_qty']) < $qty) {
                            throw new \Exception("Insufficient stock in Inward Challan '{$stockRow['ch_no']}'. Available: {$stockRow['bal_qty']}, Requested: {$qty}");
                        }

                        // Deduct stock
                        $stmtDeduct = $pdo->prepare("UPDATE `challan_inward` SET `bal_qty` = `bal_qty` - :qty WHERE `id` = :id");
                        $stmtDeduct->execute([':qty' => $qty, ':id' => $inwardChallanId]);
                    }

                    // Save outward challan row
                    $stmtIns->execute([
                        ':challan_no' => $challan_no,
                        ':challan_date' => $challan_date,
                        ':party_id' => $party_id,
                        ':vehicle_no' => $vehicle_no,
                        ':sr_no' => intval($row['sr_no']),
                        ':wound_code' => $row['wound_code'] ?? '',
                        ':description' => $row['description'] ?? '',
                        ':m_code' => $row['m_code'] ?? '',
                        ':hsn_code' => $row['hsn_code'] ?? '9988',
                        ':qty' => $qty,
                        ':uom' => $row['uom'] ?? 'NOS',
                        ':rate' => floatval($row['rate'] ?? 0),
                        ':amount' => floatval($row['amount'] ?? 0),
                        ':inward_challan_id' => $inwardChallanId
                    ]);
                }

                $pdo->commit();
                respondSuccess();
            } catch (\Exception $ex) {
                $pdo->rollBack();
                respondError($ex->getMessage());
            }
            break;

        case 'delete_nfp_challan':
            $challan_no = $inputData['challan_no'] ?? null;
            if (!$challan_no) {
                respondError('Challan number is required.');
            }

            $pdo->beginTransaction();
            try {
                // Revert stock deductions
                $stmtOld = $pdo->prepare("SELECT `inward_challan_id`, `qty` FROM `nfp_outward` WHERE `challan_no` = :challan_no");
                $stmtOld->execute([':challan_no' => $challan_no]);
                $oldRows = $stmtOld->fetchAll();

                foreach ($oldRows as $oldRow) {
                    if (!empty($oldRow['inward_challan_id'])) {
                        $stmtRevert = $pdo->prepare("UPDATE `challan_inward` SET `bal_qty` = `bal_qty` + :qty WHERE `id` = :id");
                        $stmtRevert->execute([':qty' => $oldRow['qty'], ':id' => $oldRow['inward_challan_id']]);
                    }
                }

                // Delete records
                $stmtDel = $pdo->prepare("DELETE FROM `nfp_outward` WHERE `challan_no` = :challan_no");
                $stmtDel->execute([':challan_no' => $challan_no]);

                $pdo->commit();
                respondSuccess();
            } catch (\Exception $ex) {
                $pdo->rollBack();
                respondError($ex->getMessage());
            }
            break;

        case 'get_next_invoice_no':
            try {
                $stmt = $pdo->query("SELECT `inv_no` FROM `tax_invoice` ORDER BY `id` DESC LIMIT 1");
                $row = $stmt->fetch();
                $nextNo = 'INV-1001';
                if ($row) {
                    $lastNo = $row['inv_no'];
                    // Find trailing number using regex
                    if (preg_match('/^(.*??)(\d+)$/', $lastNo, $matches)) {
                        $prefix = $matches[1];
                        $num = intval($matches[2]) + 1;
                        $digitLen = strlen($matches[2]);
                        $nextNo = $prefix . str_pad($num, $digitLen, '0', STR_PAD_LEFT);
                    } else {
                        $nextNo = $lastNo . '-1';
                    }
                }
                respondSuccess(['next_no' => $nextNo]);
            } catch (\Exception $ex) {
                respondError($ex->getMessage());
            }
            break;

        default:
            respondError("Action '$action' not implemented.");
            break;
    }

} catch (\PDOException $e) {
    respondError("Database error: " . $e->getMessage());
} catch (\Exception $e) {
    respondError("General error: " . $e->getMessage());
}
