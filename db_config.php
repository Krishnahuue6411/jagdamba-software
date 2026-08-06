<?php
// Jagdamba Electrical - Centralized Database Configuration (db_config.php)

define('LOGIN_PASSWORD', getenv('LOGIN_PASSWORD')); // Custom password for system access

// -------------------------------------------------------------------------
// AUTO-DETECTION: Local Host vs InfinityFree Hosting Environment
// -------------------------------------------------------------------------
$isLocalhost = false;

if (isset($_SERVER['SERVER_NAME'])) {
    $host = $_SERVER['SERVER_NAME'];
} elseif (isset($_SERVER['HTTP_HOST'])) {
    $host = parse_url($_SERVER['HTTP_HOST'], PHP_URL_HOST) ?: $_SERVER['HTTP_HOST'];
} else {
    $host = '';
}

if ($host) {
    $hostLower = strtolower($host);
    if (
        $hostLower === 'localhost' ||
        $hostLower === '127.0.0.1' ||
        $hostLower === '::1' ||
        strpos($hostLower, '.') === false || // Local hostnames without dots (e.g. jagdamba, apexx, electrical)
        strpos($hostLower, '192.168.') === 0 ||
        strpos($hostLower, '10.') === 0 ||
        strpos($hostLower, '172.') === 0 ||
        substr($hostLower, -6) === '.local' ||
        substr($hostLower, -5) === '.test'
    ) {
        $isLocalhost = true;
    }
}

if (!$isLocalhost && isset($_SERVER['SERVER_ADDR'])) {
    $addr = $_SERVER['SERVER_ADDR'];
    if (
        $addr === '127.0.0.1' ||
        $addr === '::1' ||
        strpos($addr, '192.168.') === 0 ||
        strpos($addr, '10.') === 0 ||
        (strpos($addr, '172.') === 0 && intval(explode('.', $addr)[1]) >= 16 && intval(explode('.', $addr)[1]) <= 31)
    ) {
        $isLocalhost = true;
    }
}

if (php_sapi_name() === 'cli') {
    $isLocalhost = true;
}

if ($isLocalhost) {
    // -------------------------------------------------------------------------
    // LOCALHOST CONFIGURATION (XAMPP / WAMP / MAMP)
    // -------------------------------------------------------------------------
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'inventory_db');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
} else {
    // -------------------------------------------------------------------------
    // INFINITYFREE PRODUCTION CONFIGURATION
    // -------------------------------------------------------------------------
    define('DB_HOST',getenv('DB_HOST'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASS'));
    define('DB_NAME', getenv('DB_NAME'));
}

define('M314_ONLY', false); // Flag for dedicated M314 environment

function runMigrations($pdo) {
    try {
        // 1. Add part_no to inward_transaction
        $cols = $pdo->query("DESCRIBE `inward_transaction`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('part_no', $cols)) {
            $pdo->exec("ALTER TABLE `inward_transaction` ADD `part_no` VARCHAR(50) NULL");
        }
        if (!in_array('wip_count', $cols)) {
            $pdo->exec("ALTER TABLE `inward_transaction` ADD `wip_count` INT DEFAULT 0");
        }
        $mcNoInfo = $pdo->query("DESCRIBE `inward_transaction` `mc_no`")->fetch();
        if ($mcNoInfo && strpos(strtolower($mcNoInfo['Type']), 'varchar') !== false) {
            $pdo->exec("ALTER TABLE `inward_transaction` MODIFY `mc_no` TEXT");
        }

        // 2. Add part_no to remaining_machines
        $colsRm = $pdo->query("DESCRIBE `remaining_machines`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('part_no', $colsRm)) {
            $pdo->exec("ALTER TABLE `remaining_machines` ADD `part_no` VARCHAR(50) NULL");
        }
        if (!in_array('mode', $colsRm)) {
            $pdo->exec("ALTER TABLE `remaining_machines` ADD `mode` VARCHAR(20) DEFAULT 'variable'");
        }

        // 3. Add part_no to tax_invoice
        $colsTi = $pdo->query("DESCRIBE `tax_invoice`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('part_no', $colsTi)) {
            $pdo->exec("ALTER TABLE `tax_invoice` ADD `part_no` VARCHAR(50) NULL");
        }
        if (!in_array('include_sign', $colsTi)) {
            $pdo->exec("ALTER TABLE `tax_invoice` ADD `include_sign` TINYINT DEFAULT 1");
        }
        if (!in_array('show_asn', $colsTi)) {
            $pdo->exec("ALTER TABLE `tax_invoice` ADD `show_asn` TINYINT DEFAULT 0");
        }


        // 4. Create invoice_consumption_log table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `invoice_consumption_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `inv_no` VARCHAR(50) NOT NULL,
            `inward_id` INT NOT NULL,
            `m_code` VARCHAR(50) NOT NULL,
            `qty_used` DECIMAL(10,2) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`inv_no`),
            INDEX (`inward_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 4b. Add bom_tier to bom table
        $colsBom = $pdo->query("DESCRIBE `bom`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('bom_tier', $colsBom)) {
            $pdo->exec("ALTER TABLE `bom` ADD `bom_tier` ENUM('PRIMARY', 'SECONDARY') NOT NULL DEFAULT 'PRIMARY'");
        }

        // 5. Add columns to workers table
        $colsW = $pdo->query("DESCRIBE `workers`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('designation', $colsW)) {
            $pdo->exec("ALTER TABLE `workers` ADD `designation` VARCHAR(50) NOT NULL DEFAULT 'experience'");
        }
        if (!in_array('hourly_salary', $colsW)) {
            $pdo->exec("ALTER TABLE `workers` ADD `hourly_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        }
        if (!in_array('aadhar_no', $colsW)) {
            $pdo->exec("ALTER TABLE `workers` ADD `aadhar_no` VARCHAR(20) NULL");
        }
        if (!in_array('doc_id_card', $colsW)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_id_card` VARCHAR(255) NULL");
        }
        if (!in_array('doc_tax_card', $colsW)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_tax_card` VARCHAR(255) NULL");
        }
        if (!in_array('doc_passbook', $colsW)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_passbook` VARCHAR(255) NULL");
        }
        if (!in_array('doc_photo', $colsW)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_photo` VARCHAR(255) NULL");
        }
        if (!in_array('doc_address', $colsW)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_address` VARCHAR(255) NULL");
        }
        if (!in_array('doc_other', $colsW)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_other` VARCHAR(255) NULL");
        }

        // Add part_no to raw_material table
        $colsRaw = $pdo->query("DESCRIBE `raw_material`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('part_no', $colsRaw)) {
            $pdo->exec("ALTER TABLE `raw_material` ADD `part_no` VARCHAR(50) NULL");
        }

        // 6. Add columns to daily_work table
        $colsDw = $pdo->query("DESCRIBE `daily_work`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('attendance', $colsDw)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `attendance` VARCHAR(20) DEFAULT 'Present'");
        }
        if (!in_array('status', $colsDw)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `status` VARCHAR(20) DEFAULT 'Present'");
        }
        if (!in_array('work_done', $colsDw)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `work_done` TEXT NULL");
        }
        if (!in_array('remarks', $colsDw)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `remarks` TEXT NULL");
        }

        // 7. Add part_no to po_master and po_master_copy
        $colsPo = $pdo->query("DESCRIBE `po_master`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('part_no', $colsPo)) {
            $pdo->exec("ALTER TABLE `po_master` ADD `part_no` VARCHAR(50) NULL");
        }
        $colsPoc = $pdo->query("DESCRIBE `po_master_copy`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('part_no', $colsPoc)) {
            $pdo->exec("ALTER TABLE `po_master_copy` ADD `part_no` VARCHAR(50) NULL");
        }

        // 8. Add party_id to transport_master
        $colsTm = $pdo->query("DESCRIBE `transport_master`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('party_id', $colsTm)) {
            $pdo->exec("ALTER TABLE `transport_master` ADD `party_id` INT NULL");
        }

        // 9. Add bal_qty to challan_inward
        $colsCi = $pdo->query("DESCRIBE `challan_inward`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('bal_qty', $colsCi)) {
            $pdo->exec("ALTER TABLE `challan_inward` ADD `bal_qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            $pdo->exec("UPDATE `challan_inward` SET `bal_qty` = `qty` WHERE `bal_qty` = 0.00");
        }

        // 10. Add inward_challan_id to nfp_outward
        $colsNo = $pdo->query("DESCRIBE `nfp_outward`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('inward_challan_id', $colsNo)) {
            $pdo->exec("ALTER TABLE `nfp_outward` ADD `inward_challan_id` INT NULL");
        }

        // 11. Create manual_alerts table (for manually entered aged stock alerts)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `manual_alerts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ch_no` VARCHAR(50) NOT NULL,
            `ch_date` DATE NOT NULL,
            `m_code` VARCHAR(50) NULL,
            `m_description` TEXT NULL,
            `material_type` VARCHAR(50) NULL,
            `in_qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `bal_qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `unit` VARCHAR(20) DEFAULT 'NOS',
            `party_id` INT NULL,
            `remark` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`ch_no`),
            INDEX (`m_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 12. Create mo_jobs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `mo_jobs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `machine_no` VARCHAR(50) NOT NULL UNIQUE,
            `working_process` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`machine_no`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    } catch (Exception $e) {
        // Silently log or ignore to not break connection
        error_log("Migration error: " . $e->getMessage());
    }
}

/**
 * Creates and returns a unified PDO connection instance.
 *
 * @return PDO
 * @throws PDOException
 */
function getDatabaseConnection() {
    $charset = 'utf8mb4';
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        runMigrations($pdo);
        return $pdo;
    } catch (PDOException $e) {
        throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
    }
}
