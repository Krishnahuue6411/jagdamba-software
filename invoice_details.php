<?php
// Jagdamba Electrical - Invoice Details / Work Order Tracker (invoice_details.php)
require_once 'sidebar.php';
renderHeader("Invoice Details & Dispatch Tracker", "invoice_details");
?>

<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header" style="background-color: rgba(255, 255, 255, 0.01);">
        <span class="panel-title"><i class="fa-solid fa-magnifying-glass-chart" style="color: var(--primary);"></i> Search Dispatch Items</span>
        <span class="panel-subtitle">Filter work orders and items</span>
    </div>
    <div class="panel-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
            <div class="form-group">
                <label class="form-label" for="filter-invoice">Filter by Invoice No</label>
                <input type="text" id="filter-invoice" class="form-control" placeholder="Type Invoice No..." oninput="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-wound">Filter by Wound Code</label>
                <input type="text" id="filter-wound" class="form-control" placeholder="Type Wound Code..." oninput="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-party">Filter by Party</label>
                <select id="filter-party" class="form-control" onchange="applyFilters()" style="background-color: var(--bg-main);">
                    <option value="">-- All Parties --</option>
                </select>
            </div>

            <div class="form-group">
                <button class="btn btn-secondary" onclick="resetFilters()" style="width: 100%; height: 42px;"><i class="fa-solid fa-xmark"></i> Clear Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Details Grid -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="fa-solid fa-gears" style="color: var(--secondary);"></i> Production & Work Order Logs</span>
        <div style="display:flex; align-items:center; gap:12px;">
            <span class="panel-subtitle" id="record-count-label">0 Records</span>
            <button class="btn btn-secondary" id="btn-export" onclick="exportToExcel()" title="Export visible rows to Excel" style="display:flex; align-items:center; gap:6px; padding:6px 14px; font-size:0.85rem;">
                <i class="fa-solid fa-file-excel" style="color:#4ade80;"></i> Export Excel
            </button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-container">
            <table class="custom-table" id="details-table">
                <thead>
                    <tr>
                        <th>Invoice Date</th>
                        <th>Invoice No</th>
                        <th>Wound Code</th>
                        <th>Drawing No (DRG)</th>
                        <th>Machine(s) Used / Dispatched</th>
                        <th style="text-align: right;">Quantity</th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <!-- Populated dynamically -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let dispatchRecords = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        await loadParties();
        loadDispatchDetails();
    });

    async function loadParties() {
        try {
            const res = await App.api('list', { table: 'parties' });
            partiesList = res.rows || [];
            const select = document.getElementById('filter-party');
            select.innerHTML = '<option value="">-- All Parties --</option>';
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

    // Fetch details using single joined GROUP_CONCAT query
    async function loadDispatchDetails() {
        App.showLoading('Loading dispatch ledger...');
        try {
            const res = await App.api('query', {
                sql: `
                    SELECT
                        i.inv_no,
                        i.inv_date,
                        i.wound_code,
                        i.drg_no,
                        i.qty,
                        i.uom,
                        i.party_id,
                        GROUP_CONCAT(m.machine_no ORDER BY m.machine_no ASC SEPARATOR ', ') as machines_list
                    FROM tax_invoice i
                    LEFT JOIN invoice_machines m ON i.inv_no = m.inv_no
                    GROUP BY i.id
                    ORDER BY i.id DESC
                `
            });
            dispatchRecords = res.rows || [];
            applyFilters();
        } catch (err) {
            console.error("Query failed:", err);
        } finally {
            App.hideLoading();
        }
    }

    // Apply filters
    function applyFilters() {
        const invQuery = document.getElementById('filter-invoice').value.toLowerCase().trim();
        const woundQuery = document.getElementById('filter-wound').value.toLowerCase().trim();
        const partyVal = document.getElementById('filter-party').value;

        const filtered = dispatchRecords.filter(item => {
            if (partyVal && item.party_id != partyVal) return false;
            const matchInv = !invQuery || (item.inv_no && item.inv_no.toLowerCase().includes(invQuery));
            const matchWound = !woundQuery || (item.wound_code && item.wound_code.toLowerCase().includes(woundQuery));
            return matchInv && matchWound;
        });

        populateGrid(filtered);
    }

    // Populate Grid
    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Records`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:32px;">No matching dispatch details found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.onclick = () => {
                window.open(`print.php?inv_no=${encodeURIComponent(item.inv_no)}`, '_blank');
            };
            tr.title = "View Invoice Layout";

            const machinesDisplay = item.machines_list ?
                `<span style="font-family:monospace; font-size:0.85rem; color:var(--text-highlight);">${escapeHtml(item.machines_list)}</span>` :
                `<span style="color:var(--text-muted); font-style:italic;">No serials mapped</span>`;

            tr.innerHTML = `
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(item.inv_date)}</td>
                <td><strong style="color:var(--success);">${escapeHtml(item.inv_no)}</strong></td>
                <td><span style="font-family: monospace; font-size:0.85rem; background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px; border:1px solid var(--border-color);">${escapeHtml(item.wound_code)}</span></td>
                <td><span class="badge badge-pending">${escapeHtml(item.drg_no || '9988')}</span></td>
                <td style="white-space: normal; max-width: 320px; line-height: 1.4;">${machinesDisplay}</td>
                <td style="text-align: right; font-weight:700; color:var(--text-highlight);">${App.formatDecimal(item.qty)} ${escapeHtml(item.uom || 'NOS')}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Reset filters
    function resetFilters() {
        document.getElementById('filter-invoice').value = '';
        document.getElementById('filter-wound').value = '';
        document.getElementById('filter-party').value = '';
        applyFilters();
    }

    // Export currently visible / filtered rows to Excel (CSV)
    function exportToExcel() {
        const invQuery  = document.getElementById('filter-invoice').value.toLowerCase().trim();
        const woundQuery = document.getElementById('filter-wound').value.toLowerCase().trim();
        const partyVal  = document.getElementById('filter-party').value;

        const filtered = dispatchRecords.filter(item => {
            if (partyVal && item.party_id != partyVal) return false;
            const matchInv   = !invQuery   || (item.inv_no    && item.inv_no.toLowerCase().includes(invQuery));
            const matchWound = !woundQuery || (item.wound_code && item.wound_code.toLowerCase().includes(woundQuery));
            return matchInv && matchWound;
        });

        if (filtered.length === 0) {
            App.showToast('No records to export.', 'warning');
            return;
        }

        const headers = ['Invoice Date', 'Invoice No', 'Wound Code', 'Drawing No (DRG)', 'Machine(s) Used / Dispatched', 'Quantity', 'UOM'];

        const csvRows = [headers.join(',')];

        filtered.forEach(item => {
            const row = [
                csvCell(App.formatDate(item.inv_date)),
                csvCell(item.inv_no),
                csvCell(item.wound_code),
                csvCell(item.drg_no || '9988'),
                csvCell(item.machines_list || ''),
                csvCell(item.qty),
                csvCell(item.uom || 'NOS')
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = '\uFEFF' + csvRows.join('\n'); // BOM for Excel UTF-8
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const timestamp = new Date().toISOString().slice(0,10);
        link.href = url;
        link.download = `Invoice_Details_${timestamp}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    // Wrap value for CSV cell (escapes quotes, wraps in quotes)
    function csvCell(val) {
        const str = (val === null || val === undefined) ? '' : String(val);
        return '"' + str.replace(/"/g, '""') + '"';
    }

    // HTML escape helper
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
