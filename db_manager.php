<?php
// Jagdamba Electrical - Database Manager (db_manager.php)
require_once 'sidebar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_authorized = $_SESSION['db_manager_authorized'] ?? false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_password'])) {
    if ($_POST['db_password'] === getenv('DB_MANAGER_PASSWORD')) {
        $_SESSION['db_manager_authorized'] = true;
        $db_authorized = true;
    } else {
        $error = "Incorrect DB Manager Password!";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'db_logout') {
    unset($_SESSION['db_manager_authorized']);
    header("Location: db_manager.php");
    exit;
}

renderHeader("Database Manager", "db_manager");
?>

<?php if (!$db_authorized): ?>
<div style="display: flex; justify-content: center; align-items: center; min-height: 60vh; padding: 20px;">
    <div class="panel" style="width: 100%; max-width: 450px; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.4); border: 1px solid var(--border-color); background: rgba(30, 27, 75, 0.2); backdrop-filter: blur(12px);">
        <div class="panel-header" style="text-align: center; border-bottom: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center; padding: 24px 20px;">
            <div style="background: rgba(139, 92, 246, 0.15); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; border: 1px solid rgba(139, 92, 246, 0.3);">
                <i class="fa-solid fa-lock" style="color: var(--secondary); font-size: 1.8rem;"></i>
            </div>
            <span class="panel-title" style="font-size: 1.3rem; font-weight: 700; color: var(--text-primary);">Restricted Access Area</span>
            <span class="panel-subtitle" style="font-size: 0.85rem; margin-top: 4px;">Enter password to unlock database management tools</span>
        </div>
        <div class="panel-body" style="padding: 24px;">
            <?php if ($error): ?>
                <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--error); color: #fca5a5; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            <form method="POST" action="db_manager.php">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="db_password">Database Manager Password</label>
                    <input type="password" name="db_password" id="db_password" class="form-control" placeholder="••••••••" style="padding: 12px; font-size: 1rem; text-align: center; background: var(--bg-main);" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.9rem; font-weight: bold; background: linear-gradient(135deg, var(--secondary), #8b5cf6); border: none; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.25);">
                    <i class="fa-solid fa-unlock" style="margin-right: 6px;"></i> Unlock Manager
                </button>
            </form>
        </div>
    </div>
</div>
<?php else: ?>
<?php
// Define allowed tables to display and query
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

$pdo = getSidebarConnection();
$tableInfo = [];
if ($pdo) {
    foreach ($allowedTables as $t) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$t`");
            $count = $stmt->fetchColumn();
            $tableInfo[$t] = [
                'count' => intval($count),
                'name' => str_replace('_', ' ', $t)
            ];
        } catch (Exception $e) {
            $tableInfo[$t] = [
                'count' => 0,
                'name' => str_replace('_', ' ', $t)
            ];
        }
    }
}
?>

<div class="module-container" style="grid-template-columns: 350px 1fr; gap: 16px;">
    <!-- Left: List of tables -->
    <div class="panel" style="max-height: 80vh; overflow-y: auto;">
        <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span class="panel-title"><i class="fa-solid fa-server" style="color: var(--primary);"></i> Database Tables</span>
                <span class="panel-subtitle">Select a table to manage rows</span>
            </div>
            <a href="db_manager.php?action=db_logout" class="btn btn-secondary" style="font-size: 0.75rem; padding: 4px 8px; border-color: rgba(239, 68, 68, 0.2); color: var(--error); background: rgba(239, 68, 68, 0.05);" title="Lock session">
                <i class="fa-solid fa-lock"></i> Lock
            </a>
        </div>
        <div class="panel-body" style="padding: 12px; display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($tableInfo as $key => $info): ?>
                <div class="table-card" id="card-<?php echo $key; ?>" onclick="selectTable('<?php echo $key; ?>')" style="border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; background: rgba(255,255,255,0.01); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;">
                    <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1;">
                        <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-primary); text-transform: capitalize; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($info['name']); ?></span>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;"><?php echo htmlspecialchars($key); ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="menu-badge" style="background-color: var(--primary); font-size: 0.75rem; padding: 2px 8px; color: white; border-radius: 10px; font-weight: bold;"><?php echo $info['count']; ?> rows</span>
                        <button type="button" class="btn btn-icon" onclick="deleteAllRows(event, '<?php echo $key; ?>')" style="background: rgba(239, 68, 68, 0.1); color: var(--error); border: 1px solid rgba(239, 68, 68, 0.2); padding: 4px 6px; border-radius: 4px; font-size: 0.75rem; cursor: pointer;" title="Delete all rows">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right: Rows inspector -->
    <div class="panel" style="display: flex; flex-direction: column; max-height: 80vh; min-height: 50vh;">
        <div class="panel-header" id="inspector-header">
            <span class="panel-title"><i class="fa-solid fa-magnifying-glass-chart" style="color: var(--secondary);"></i> Data Inspector</span>
            <span class="panel-subtitle">Select a table from the left to inspect its rows</span>
        </div>
        <div class="panel-body" id="inspector-body" style="padding: 16px; overflow: auto; flex: 1; display: flex; flex-direction: column;">
            <div id="inspector-empty-state" style="margin: auto; text-align: center; color: var(--text-muted); padding: 48px;">
                <i class="fa-solid fa-database" style="font-size: 3rem; color: var(--border-color); margin-bottom: 16px; display: block;"></i>
                <p style="font-size: 0.95rem; margin-bottom: 0;">Select a table from the left panel to inspect its content and delete individual rows.</p>
            </div>

            <div id="inspector-content" style="display: none; flex-direction: column; flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <h3 id="inspector-table-name" style="margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--text-primary); text-transform: capitalize;">Table Name</h3>
                        <span id="inspector-table-badge" style="background-color: var(--secondary); font-size: 0.75rem; padding: 2px 8px; color: white; border-radius: 10px; font-weight: bold;">0 rows</span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <div class="topbar-search" style="width: 220px; position: relative;">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="row-search" placeholder="Search rows..." oninput="filterRows()">
                        </div>
                        <button type="button" class="btn btn-secondary btn-icon" onclick="reloadTableData()" title="Reload data">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                    </div>
                </div>
                <div style="overflow: auto; flex: 1; border: 1px solid var(--border-color); border-radius: 8px; background: rgba(0,0,0,0.1);">
                    <table class="data-table" id="rows-table" style="width: 100%; border-collapse: collapse; margin: 0; font-size: 0.8rem;">
                        <thead id="rows-thead">
                            <!-- Columns loaded dynamically -->
                        </thead>
                        <tbody id="rows-tbody">
                            <!-- Rows loaded dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .table-card:hover {
        border-color: var(--primary) !important;
        background: rgba(255,255,255,0.03) !important;
    }
    .table-card.active-table {
        border-color: var(--secondary) !important;
        background: rgba(139, 92, 246, 0.08) !important;
        box-shadow: 0 0 10px rgba(139, 92, 246, 0.15);
    }
    .data-table th {
        position: sticky;
        top: 0;
        background: var(--bg-surface, #1e1b4b);
        z-index: 2;
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
        font-weight: 700;
        color: var(--text-primary);
    }
    .data-table td {
        padding: 8px 10px;
        border-bottom: 1px dashed var(--border-color);
        color: var(--text-secondary);
        white-space: nowrap;
        max-width: 250px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .data-table tr:hover td {
        background: rgba(255,255,255,0.02);
        color: var(--text-primary);
    }
</style>

<script>
    let currentSelectedTable = '';
    let tableColumns = [];
    let tableRows = [];

    function updateCardCount(tableName, count) {
        const card = document.getElementById(`card-${tableName}`);
        if (card) {
            const badge = card.querySelector('.menu-badge');
            if (badge) {
                badge.innerText = `${count} rows`;
            }
        }
    }

    async function selectTable(tableName) {
        currentSelectedTable = tableName;

        // Update active card styling
        document.querySelectorAll('.table-card').forEach(c => c.classList.remove('active-table'));
        const activeCard = document.getElementById(`card-${tableName}`);
        if (activeCard) {
            activeCard.classList.add('active-table');
        }

        // Show loading
        App.showLoading(`Loading data from ${tableName}...`);

        try {
            await reloadTableData();

            document.getElementById('inspector-empty-state').style.display = 'none';
            document.getElementById('inspector-content').style.display = 'flex';

            document.getElementById('inspector-table-name').innerText = tableName.replace(/_/g, ' ');
        } catch (err) {
            console.error("Select table error:", err);
        } finally {
            App.hideLoading();
        }
    }

    async function reloadTableData() {
        if (!currentSelectedTable) return;

        try {
            const res = await App.api('list', { table: currentSelectedTable });
            tableColumns = res.columns || [];
            tableRows = res.rows || [];

            document.getElementById('inspector-table-badge').innerText = `${tableRows.length} rows`;
            updateCardCount(currentSelectedTable, tableRows.length);

            renderTableData(tableColumns, tableRows);
        } catch (err) {
            console.error("Reload table data error:", err);
            App.showToast("Failed to load table data: " + err.message, "error");
        }
    }

    function renderTableData(columns, rows) {
        const thead = document.getElementById('rows-thead');
        const tbody = document.getElementById('rows-tbody');

        thead.innerHTML = '';
        tbody.innerHTML = '';

        if (columns.length === 0) return;

        // 1. Build header
        const trHead = document.createElement('tr');
        columns.forEach(col => {
            const th = document.createElement('th');
            th.innerText = col;
            trHead.appendChild(th);
        });
        // Action column header
        const thAction = document.createElement('th');
        thAction.innerText = 'Actions';
        thAction.style.textAlign = 'center';
        trHead.appendChild(thAction);
        thead.appendChild(trHead);

        // 2. Build rows
        if (rows.length === 0) {
            const trEmpty = document.createElement('tr');
            const tdEmpty = document.createElement('td');
            tdEmpty.colSpan = columns.length + 1;
            tdEmpty.innerText = 'No rows found in this table.';
            tdEmpty.style.textAlign = 'center';
            tdEmpty.style.padding = '32px';
            tdEmpty.style.color = 'var(--text-muted)';
            trEmpty.appendChild(tdEmpty);
            tbody.appendChild(trEmpty);
            return;
        }

        rows.forEach(row => {
            const tr = document.createElement('tr');
            columns.forEach(col => {
                const td = document.createElement('td');
                const val = row[col];
                td.innerText = val !== null ? val : 'NULL';
                td.title = val !== null ? val : 'NULL';
                tr.appendChild(td);
            });

            // Action Cell
            const tdAction = document.createElement('td');
            tdAction.style.textAlign = 'center';

            const btnDelete = document.createElement('button');
            btnDelete.type = 'button';
            btnDelete.className = 'btn';
            btnDelete.style.background = 'var(--error)';
            btnDelete.style.color = 'white';
            btnDelete.style.border = 'none';
            btnDelete.style.padding = '4px 8px';
            btnDelete.style.borderRadius = '4px';
            btnDelete.style.fontSize = '0.7rem';
            btnDelete.style.cursor = 'pointer';
            btnDelete.style.display = 'inline-flex';
            btnDelete.style.alignItems = 'center';
            btnDelete.style.gap = '4px';
            btnDelete.innerHTML = '<i class="fa-solid fa-trash-can"></i> Delete';
            const rowId = row.id !== undefined ? row.id : (row.ID !== undefined ? row.ID : row.Id);
            btnDelete.onclick = () => deleteRow(currentSelectedTable, rowId);

            tdAction.appendChild(btnDelete);
            tr.appendChild(tdAction);
            tbody.appendChild(tr);
        });
    }

    function filterRows() {
        const query = document.getElementById('row-search').value.toLowerCase().trim();
        if (!query) {
            renderTableData(tableColumns, tableRows);
            return;
        }

        const filtered = tableRows.filter(row => {
            return Object.values(row).some(val =>
                String(val !== null ? val : '').toLowerCase().includes(query)
            );
        });

        renderTableData(tableColumns, filtered);
    }

    async function deleteRow(tableName, rowId) {
        if (!confirm(`Are you sure you want to delete row ID ${rowId} from table "${tableName}"?`)) {
            return;
        }

        App.showLoading(`Deleting row ${rowId} from ${tableName}...`);

        try {
            const res = await App.api('delete', { table: tableName, id: rowId });
            if (res.ok) {
                App.showToast("Row deleted successfully.", "success");
                await reloadTableData();
            } else {
                App.showToast(res.error || "Failed to delete row.", "error");
            }
        } catch (err) {
            console.error("Delete row error:", err);
            App.showToast("Error deleting row: " + err.message, "error");
        } finally {
            App.hideLoading();
        }
    }

    async function deleteAllRows(event, tableName) {
        // Prevent trigger parent card onclick
        event.stopPropagation();

        if (!confirm(`WARNING: Are you sure you want to delete ALL rows from table "${tableName}"? This action cannot be undone.`)) {
            return;
        }

        App.showLoading(`Deleting all rows from ${tableName}...`);

        try {
            const res = await App.api('delete_all_rows', { table: tableName });
            if (res.ok) {
                App.showToast(`All rows deleted from "${tableName}".`, "success");
                if (currentSelectedTable === tableName) {
                    await reloadTableData();
                } else {
                    // Update the count badge on the left card
                    updateCardCount(tableName, 0);
                }
            } else {
                App.showToast(res.error || "Failed to delete table rows.", "error");
            }
        } catch (err) {
            console.error("Delete all rows error:", err);
            App.showToast("Error: " + err.message, "error");
        } finally {
            App.hideLoading();
        }
    }
</script>
<?php endif; ?>

<?php
renderFooter();
?>
