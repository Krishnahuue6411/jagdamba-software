<?php
// Sahara Electrical - Party Directory (parties.php)
require_once 'sidebar.php';

// Auto-migration check: create parties table and alter relational tables if needed
$pdo = getSidebarConnection();
$migrationError = null;
if ($pdo) {
    try {
        // Create Parties Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `parties` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `party_name` VARCHAR(100) UNIQUE NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Alter po_master
        $poCols = $pdo->query("DESCRIBE `po_master`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('party_id', $poCols)) {
            $pdo->exec("ALTER TABLE `po_master` ADD COLUMN `party_id` INT NULL");
        }

        // Alter po_master_copy
        $pocCols = $pdo->query("DESCRIBE `po_master_copy`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('party_id', $pocCols)) {
            $pdo->exec("ALTER TABLE `po_master_copy` ADD COLUMN `party_id` INT NULL");
        }

        // Alter inward_transaction
        $inwCols = $pdo->query("DESCRIBE `inward_transaction`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('party_id', $inwCols)) {
            $pdo->exec("ALTER TABLE `inward_transaction` ADD COLUMN `party_id` INT NULL");
        }

        // Alter tax_invoice
        $taxCols = $pdo->query("DESCRIBE `tax_invoice`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('party_id', $taxCols)) {
            $pdo->exec("ALTER TABLE `tax_invoice` ADD COLUMN `party_id` INT NULL");
        }

        // Alter remaining_machines
        $rmcCols = $pdo->query("DESCRIBE `remaining_machines`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('party_id', $rmcCols)) {
            $pdo->exec("ALTER TABLE `remaining_machines` ADD COLUMN `party_id` INT NULL");
        }
    } catch (Exception $e) {
        $migrationError = $e->getMessage();
    }
}

renderHeader("Party Directory", "parties");
?>

<style>
    .btn-delete-row {
        background: none;
        border: none;
        color: var(--error);
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: var(--transition);
    }
    .btn-delete-row:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #ff5f5f;
    }
</style>

<?php if ($migrationError): ?>
    <div style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid var(--error); color: var(--error); padding: 16px; border-radius: 8px; margin-bottom: 24px;">
        <strong>Database Migration Failed:</strong> <?php echo htmlspecialchars($migrationError); ?>. Please review database logs.
    </div>
<?php endif; ?>

<div class="module-container" style="grid-template-columns: 360px 1fr;">
    <!-- Form Panel (Left) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-square-plus" style="color: var(--primary);"></i> Add New Party</span>
            <span class="panel-subtitle">Create unique party mapping</span>
        </div>
        <div class="panel-body">
            <form id="party-form" onsubmit="handleFormSubmit(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="party_name">Party Name <span style="color:var(--error);">*</span></label>
                        <input type="text" id="party_name" class="form-control" placeholder="e.g. M-1, Siemens, ABC Corp" required>
                    </div>
                </div>

                <div class="btn-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-check"></i> Submit</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fa-solid fa-xmark"></i> Clear</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid List Panel (Right) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-briefcase" style="color: var(--secondary);"></i> Parties Registry</span>
            <span class="panel-subtitle" id="record-count-label">0 Parties</span>
        </div>
        <div class="panel-body">
            <!-- Search field -->
            <div class="search-box-wrapper" style="margin-bottom: 16px;">
                <div class="topbar-search" style="flex: 1; display: flex;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px;"></i>
                    <input type="text" id="grid-search" placeholder="Search by name..." style="width: 100%;" oninput="filterGrid()">
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="custom-table" id="parties-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Party Name</th>
                            <th style="width: 100px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadParties();
    });

    async function loadParties() {
        App.showLoading('Loading party directory...');
        try {
            const res = await App.api('list', { table: 'parties' });
            partiesList = res.rows || [];
            populateGrid(partiesList);
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Parties`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:32px;">No parties registered in system.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.id}</td>
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.party_name)}</strong></td>
                <td style="text-align: center;">
                    <button class="btn-delete-row" onclick="handleDelete(${item.id}, '${escapeHtml(item.party_name)}')" title="Delete Party">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });
    }

    function filterGrid() {
        const query = document.getElementById('grid-search').value.toLowerCase().trim();
        if (!query) {
            populateGrid(partiesList);
            return;
        }
        const filtered = partiesList.filter(item => item.party_name.toLowerCase().includes(query));
        populateGrid(filtered);
    }

    async function handleFormSubmit(e) {
        e.preventDefault();
        const party_name = document.getElementById('party_name').value.trim();

        if (!party_name) {
            App.showToast('Please enter a party name.', 'error');
            return;
        }

        const exists = partiesList.some(p => p.party_name.toLowerCase() === party_name.toLowerCase());
        if (exists) {
            App.showToast(`Party "${party_name}" already exists!`, 'error');
            return;
        }

        App.showLoading('Adding party...');
        try {
            await App.api('insert', {
                table: 'parties',
                data: { party_name }
            });
            App.showToast('Party added successfully.');
            resetForm();
            await loadParties();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    async function handleDelete(id, name) {
        if (!confirm(`Are you sure you want to delete party "${name}"? This will set all references in PO Master, Inwards, and Tax Invoices to null.`)) {
            return;
        }

        App.showLoading('Deleting party...');
        try {
            await App.api('delete', { table: 'parties', id });
            App.showToast('Party deleted successfully.');
            await loadParties();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    function resetForm() {
        document.getElementById('party-form').reset();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php
renderFooter();
?>
