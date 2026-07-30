<?php
// Sahara Electrical - Challan Inward (Non-Payment) Entry (challan_inward.php)
require_once 'sidebar.php';

// Auto-create table if not exists
$pdo = getSidebarConnection();
if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `challan_inward` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `ch_no` VARCHAR(50) NOT NULL,
            `ch_date` DATE NOT NULL,
            `m_code` VARCHAR(50) NOT NULL,
            `m_description` TEXT,
            `vehicle_no` VARCHAR(50),
            `qty` DECIMAL(10,2) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`ch_no`),
            INDEX (`m_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Auto-add party_id column if missing
        try {
            $pdo->exec("ALTER TABLE `challan_inward` ADD COLUMN `party_id` INT NULL");
        } catch (Exception $e2) { /* column already exists */ }
    } catch (Exception $e) {
        // Table may already exist
    }
}

renderHeader("Challan Inward Entry", "challan_inward");
?>

<div class="module-container" style="grid-template-columns: 420px 1fr;">
    <!-- Form Panel (Left) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-file-circle-check" style="color: var(--primary);"></i> Challan Inward Form</span>
            <span class="panel-subtitle">Add / Edit challan-based inward records</span>
        </div>
        <div class="panel-body">
            <form id="challan-form" onsubmit="handleFormSubmit(event)">
                <!-- Hidden Edit ID -->
                <input type="hidden" id="edit-id" value="">

                <div class="form-grid">
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label" for="ch_no">Challan No <span style="color:var(--error);">*</span></label>
                            <input type="text" id="ch_no" class="form-control" placeholder="e.g. CH-4455" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="ch_date">Challan Date <span style="color:var(--error);">*</span></label>
                            <input type="date" id="ch_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="party_id">Party</label>
                        <select id="party_id" class="form-control" style="background-color: var(--bg-main);">
                            <option value="">-- Select Party --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="m_code">Material Code <span style="color:var(--error);">*</span></label>
                        <input type="text" id="m_code" class="form-control" placeholder="Enter material code" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="m_description">Material Description</label>
                        <textarea id="m_description" class="form-control" placeholder="Enter description..." rows="2"></textarea>
                    </div>

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label" for="vehicle_no">Vehicle No</label>
                            <input type="text" id="vehicle_no" class="form-control" placeholder="e.g. MH-17-AB-1234">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="qty">Quantity <span style="color:var(--error);">*</span></label>
                            <input type="number" id="qty" class="form-control" placeholder="0.00" step="0.01" min="0.01" required>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" id="btn-save" style="flex: 1;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Record
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()" style="flex: 0.5;">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Panel (Right) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <i class="fa-solid fa-table-list" style="color: var(--secondary);"></i>
                Challan Inward Records
                <span id="record-count" style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400; margin-left: 8px;"></span>
            </span>
            <div style="display: flex; gap: 8px; align-items: center;">
                <div class="topbar-search" style="width: 220px;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="filter-search" placeholder="Search records..." oninput="applyFilter()">
                </div>
                <button class="btn btn-secondary btn-icon" onclick="exportToExcel()" title="Download Excel">
                    <i class="fa-solid fa-file-excel"></i>
                </button>
            </div>
        </div>
        <div class="panel-body" style="padding: 0; overflow: auto; max-height: 75vh;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Challan No</th>
                        <th>Date</th>
                        <th>Party</th>
                        <th>Material Code</th>
                        <th>Description</th>
                        <th>Vehicle No</th>
                        <th style="text-align: right;">Qty</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <!-- Populated dynamically -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Load Excel Export Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
    let recordsCache = [];
    let filteredRecords = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        // Set default date to today
        document.getElementById('ch_date').value = App.formatDate(new Date());
        await loadParties();
        loadRecords();
    });

    async function loadParties() {
        try {
            const res = await App.api('list', { table: 'parties' });
            partiesList = res.rows || [];
            const select = document.getElementById('party_id');
            select.innerHTML = '<option value="">-- Select Party --</option>';
            partiesList.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.innerText = p.party_name;
                select.appendChild(opt);
            });
        } catch (err) {
            console.error("Parties load error:", err);
        }
    }

    function getPartyName(partyId) {
        if (!partyId) return '-';
        const p = partiesList.find(x => x.id == partyId);
        return p ? p.party_name : '-';
    }

    // Load all records from database
    async function loadRecords() {
        try {
            const res = await App.api('list', { table: 'challan_inward' });
            recordsCache = res.rows || [];
            applyFilter();
        } catch (err) {
            console.error("Load error:", err);
        }
    }

    // Filter records by search keyword
    function applyFilter() {
        const searchVal = document.getElementById('filter-search').value.toLowerCase().trim();

        if (!searchVal) {
            filteredRecords = [...recordsCache];
        } else {
            filteredRecords = recordsCache.filter(item => {
                return (item.ch_no || '').toLowerCase().includes(searchVal) ||
                       (item.m_code || '').toLowerCase().includes(searchVal) ||
                       (item.m_description || '').toLowerCase().includes(searchVal) ||
                       (item.vehicle_no || '').toLowerCase().includes(searchVal);
            });
        }

        renderGrid(filteredRecords);
    }

    // Render table rows
    function renderGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count').innerText = `(${data.length} records)`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:32px;">No challan inward records found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong style="color:var(--secondary);">${escapeHtml(item.ch_no)}</strong></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(item.ch_date)}</td>
                <td style="font-size:0.85rem;">${escapeHtml(getPartyName(item.party_id))}</td>
                <td><span style="font-family: monospace; font-size:0.85rem; background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px; border:1px solid var(--border-color);">${escapeHtml(item.m_code)}</span></td>
                <td style="font-size:0.85rem; max-width:200px; white-space:normal;">${escapeHtml(item.m_description || '-')}</td>
                <td>${escapeHtml(item.vehicle_no || '-')}</td>
                <td style="text-align: right; font-weight:700;">${App.formatDecimal(item.qty)}</td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn" style="background:var(--primary); color:white; border:none; padding:4px 8px; border-radius:4px; font-size:0.7rem; font-weight:bold; cursor:pointer;" onclick="editRecord(${item.id})" title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn" style="background:var(--error); color:white; border:none; padding:4px 8px; border-radius:4px; font-size:0.7rem; font-weight:bold; cursor:pointer;" onclick="deleteRecord(${item.id})" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            body.appendChild(tr);
        });
    }

    // Submit form (insert or update)
    async function handleFormSubmit(e) {
        e.preventDefault();

        const editId = document.getElementById('edit-id').value;
        const ch_no = document.getElementById('ch_no').value.trim();
        const ch_date = document.getElementById('ch_date').value;
        const m_code = document.getElementById('m_code').value.trim();
        const m_description = document.getElementById('m_description').value.trim();
        const vehicle_no = document.getElementById('vehicle_no').value.trim();
        const qty = parseFloat(document.getElementById('qty').value);

        if (!ch_no || !ch_date || !m_code || !qty || qty <= 0) {
            App.showToast('Please fill all required fields (Challan No, Date, Material Code, Qty).', 'error');
            return;
        }

        const party_id = document.getElementById('party_id').value || null;

        const rowData = {
            ch_no,
            ch_date,
            m_code,
            m_description,
            vehicle_no,
            qty,
            party_id
        };

        App.showLoading('Saving challan inward record...');
        try {
            if (editId) {
                // Update existing record
                await App.api('update', {
                    table: 'challan_inward',
                    id: parseInt(editId),
                    data: rowData
                });
                App.showToast('Record updated successfully!', 'success');
            } else {
                // Insert new record
                rowData.bal_qty = qty;
                await App.api('insert', {
                    table: 'challan_inward',
                    data: rowData
                });
                App.showToast('Challan inward record saved!', 'success');
            }

            resetForm();
            await loadRecords();
        } catch (err) {
            console.error("Save error:", err);
        } finally {
            App.hideLoading();
        }
    }

    // Load a record into the form for editing
    function editRecord(id) {
        const record = recordsCache.find(r => r.id == id);
        if (!record) return;

        document.getElementById('edit-id').value = record.id;
        document.getElementById('ch_no').value = record.ch_no;
        document.getElementById('ch_date').value = record.ch_date;
        document.getElementById('party_id').value = record.party_id || '';
        document.getElementById('m_code').value = record.m_code;
        document.getElementById('m_description').value = record.m_description || '';
        document.getElementById('vehicle_no').value = record.vehicle_no || '';
        document.getElementById('qty').value = parseFloat(record.qty);

        // Highlight the save button to indicate edit mode
        const btnSave = document.getElementById('btn-save');
        btnSave.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Update Record';
        btnSave.style.background = 'var(--warning)';
        btnSave.style.color = '#000';

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Delete a record
    async function deleteRecord(id) {
        if (!confirm('Are you sure you want to delete this challan inward record?')) return;

        App.showLoading('Deleting record...');
        try {
            await App.api('delete', {
                table: 'challan_inward',
                id: id
            });
            App.showToast('Record deleted successfully.', 'success');
            await loadRecords();
        } catch (err) {
            console.error("Delete error:", err);
        } finally {
            App.hideLoading();
        }
    }

    // Reset form to insert mode
    function resetForm() {
        document.getElementById('challan-form').reset();
        document.getElementById('edit-id').value = '';
        document.getElementById('ch_date').value = App.formatDate(new Date());

        const btnSave = document.getElementById('btn-save');
        btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Record';
        btnSave.style.background = '';
        btnSave.style.color = '';
    }

    // Export to Excel
    function exportToExcel() {
        if (filteredRecords.length === 0) {
            alert("No records found to export.");
            return;
        }

        const data = filteredRecords.map(item => ({
            "Challan No": item.ch_no,
            "Date": item.ch_date,
            "Party": getPartyName(item.party_id),
            "Material Code": item.m_code,
            "Description": item.m_description || '',
            "Vehicle No": item.vehicle_no || '',
            "Quantity": parseFloat(item.qty || 0)
        }));

        const worksheet = XLSX.utils.json_to_sheet(data);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Challan Inward");

        worksheet['!cols'] = [
            { wch: 15 },
            { wch: 12 },
            { wch: 20 },
            { wch: 18 },
            { wch: 30 },
            { wch: 18 },
            { wch: 12 }
        ];

        XLSX.writeFile(workbook, 'Challan_Inward_Records.xlsx');
    }

    // HTML escape
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
</script>

<?php
renderFooter();
?>
