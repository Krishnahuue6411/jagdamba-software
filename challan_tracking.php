<?php
// Jagdamba Electrical - Company Challan Tracking System (challan_tracking.php)
require_once 'sidebar.php';
renderHeader("Company Challan Tracking System", "challan_tracking");
?>

<!-- Include Libraries for Offline / Online Exports -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<style>
    .expanded-row-details {
        background-color: rgba(255, 255, 255, 0.015);
        border-left: 3px solid var(--primary);
    }

    .nested-consumption-table {
        width: 100%;
        margin: 12px 0 16px;
        border-collapse: collapse;
        font-size: 0.8rem;
        background: rgba(0, 0, 0, 0.15);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        overflow: hidden;
    }

    .nested-consumption-table th {
        background-color: rgba(255, 255, 255, 0.03);
        color: var(--text-muted);
        text-align: left;
        padding: 8px 12px;
        font-weight: 600;
        border-bottom: 1px solid var(--border-color);
    }

    .nested-consumption-table td {
        padding: 8px 12px;
        border-bottom: 1px solid rgba(255,255,255,0.03);
        color: var(--text-highlight);
    }

    .nested-consumption-table tr:last-child td {
        border-bottom: none;
    }

    .toggle-expand-btn {
        background: none;
        border: none;
        color: var(--primary);
        cursor: pointer;
        padding: 4px;
        font-size: 1rem;
        transition: transform 0.2s ease;
    }

    .toggle-expand-btn.active {
        transform: rotate(90deg);
        color: var(--secondary);
    }

    .usage-progress-bar {
        height: 6px;
        background-color: rgba(255,255,255,0.05);
        border-radius: 3px;
        overflow: hidden;
        margin-top: 4px;
        width: 120px;
    }

    .usage-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        border-radius: 3px;
    }

    /* Custom Export Modal Overlay Styling */
    .custom-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(7, 10, 18, 0.85);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        animation: fadeInModal 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .custom-modal-content {
        background-color: var(--bg-sidebar);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        width: 100%;
        max-width: 520px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);
        overflow: hidden;
        animation: slideUpModal 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes fadeInModal {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUpModal {
        from { transform: translateY(20px) scale(0.97); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }

    .custom-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        background-color: rgba(255, 255, 255, 0.01);
    }

    .custom-modal-header h3 {
        margin: 0;
        font-size: 1.15rem;
        font-family: var(--font-header);
        color: var(--text-highlight);
    }

    .modal-close-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 1.6rem;
        cursor: pointer;
        line-height: 1;
        transition: var(--transition);
    }

    .modal-close-btn:hover {
        color: var(--error);
    }

    .custom-modal-body {
        padding: 24px;
        max-height: 65vh;
        overflow-y: auto;
    }

    .column-selection-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .column-checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background-color: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        cursor: pointer;
        transition: var(--transition);
        user-select: none;
    }

    .column-checkbox-label:hover {
        background-color: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.15);
    }

    .column-checkbox-label input[type="checkbox"] {
        accent-color: var(--primary);
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .column-checkbox-label span {
        font-size: 0.85rem;
        color: var(--text-main);
    }

    .custom-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 18px 24px;
        border-top: 1px solid var(--border-color);
        background-color: rgba(255, 255, 255, 0.01);
    }

    .btn-link {
        background: none;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--primary);
        text-decoration: none;
    }

    .btn-link:hover {
        text-decoration: underline !important;
    }
</style>

<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header" style="background-color: rgba(255, 255, 255, 0.01);">
        <span class="panel-title"><i class="fa-solid fa-filter" style="color: var(--primary);"></i> Audit Filters</span>
        <span class="panel-subtitle">Filter challan logs by part configuration, date range, or keyword</span>
    </div>
    <div class="panel-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: flex-end;">
            <div class="form-group">
                <label class="form-label" for="filter-part">Part Number</label>
                <select id="filter-part" class="form-control" onchange="applyFilters()" style="background-color: var(--bg-main);">
                    <option value="">-- All Parts --</option>
                    <option value="M311">M311</option>
                    <option value="M314">M314</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-status">Status</label>
                <select id="filter-status" class="form-control" onchange="applyFilters()" style="background-color: var(--bg-main);">
                    <option value="">-- All Statuses --</option>
                    <option value="Open">Open (In Stock)</option>
                    <option value="Closed">Closed (Consumed)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-start-date">Start Date</label>
                <input type="date" id="filter-start-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-end-date">End Date</label>
                <input type="date" id="filter-end-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group" style="flex: 2;">
                <label class="form-label" for="filter-search">Challan / Material Search</label>
                <div class="topbar-search" style="display: flex; width: 100%; margin: 0;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px;"></i>
                    <input type="text" id="filter-search" placeholder="Search by Challan, Material Code..." style="width: 100%; padding-left: 40px;" oninput="applyFilters()">
                </div>
            </div>

            <div>
                <button class="btn btn-secondary" onclick="resetFilters()" style="width: 100%;"><i class="fa-solid fa-xmark"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="fa-solid fa-table-list" style="color: var(--secondary);"></i> Inward Challans & Consumption Log</span>
        <span class="panel-subtitle" id="record-count-label">0 Challans</span>
    </div>
    <div class="panel-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <div style="font-size: 0.8rem; color: var(--text-muted);">
                💡 Click the <i class="fa-solid fa-chevron-right" style="color: var(--primary);"></i> arrow to expand and view exactly which invoices consumed stock from each Challan.
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn btn-secondary" onclick="exportToExcel()" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600;"><i class="fa-solid fa-file-excel" style="color: #16a34a;"></i> Export Excel</button>
                <button class="btn btn-secondary" onclick="exportToPDF()" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600;"><i class="fa-solid fa-file-pdf" style="color: #dc2626;"></i> Export PDF</button>
                <button class="btn btn-primary" onclick="openExportModal()" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600;"><i class="fa-solid fa-file-export"></i> Custom Export</button>
            </div>
        </div>

        <div class="table-container">
            <table class="custom-table" id="challans-list-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"></th>
                        <th>Challan No</th>
                        <th>Challan Date</th>
                        <th>Part No</th>
                        <th>Material Code</th>
                        <th>Material Description</th>
                        <th style="text-align: right;">Received Qty</th>
                        <th style="text-align: right;">Consumed Qty</th>
                        <th style="text-align: right;">Remaining Qty</th>
                        <th>Status</th>
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
    let inwardsList = [];
    let consumptionLogs = [];
    let expandedChallans = new Set(); // Store expanded inward record IDs
    let currentFilteredList = []; // Track currently filtered records for exports

    document.addEventListener('DOMContentLoaded', async () => {
        App.showLoading('Loading audit registers...');
        try {
            await loadAuditData();
        } catch (err) {
            console.error("Audit load error:", err);
        } finally {
            App.hideLoading();
        }
    });

    async function loadAuditData() {
        // 1. Fetch inwards list
        const resInw = await App.api('list', { table: 'inward_transaction' });
        inwardsList = resInw.rows || [];

        const resLogs = await App.api('query', {
            sql: "SELECT l.*, i.inv_date, i.wound_code FROM invoice_consumption_log l JOIN (SELECT TRIM(inv_no) AS inv_no, MIN(inv_date) AS inv_date, GROUP_CONCAT(DISTINCT wound_code ORDER BY wound_code SEPARATOR ', ') AS wound_code FROM tax_invoice GROUP BY TRIM(inv_no)) i ON TRIM(l.inv_no) = i.inv_no ORDER BY i.inv_date DESC"
        });
        consumptionLogs = resLogs.rows || [];

        applyFilters();
    }

    function applyFilters() {
        const partVal = document.getElementById('filter-part').value;
        const statusVal = document.getElementById('filter-status').value;
        const startVal = document.getElementById('filter-start-date').value;
        const endVal = document.getElementById('filter-end-date').value;
        const searchVal = document.getElementById('filter-search').value.toLowerCase().trim();

        const startDate = startVal ? new Date(startVal) : null;
        const endDate = endVal ? new Date(endVal) : null;
        if (startDate) startDate.setHours(0,0,0,0);
        if (endDate) endDate.setHours(23,59,59,999);

        const filtered = inwardsList.filter(item => {
            // Part filter
            if (partVal && item.part_no !== partVal) return false;

            // Status filter
            const balQty = parseFloat(item.bal_qty);
            const status = (balQty > 0) ? 'Open' : 'Closed';
            if (statusVal && status !== statusVal) return false;

            // Date filter
            const chDate = new Date(item.ch_date);
            chDate.setHours(0,0,0,0);
            if (startDate && chDate < startDate) return false;
            if (endDate && chDate > endDate) return false;

            // Keyword search
            if (searchVal) {
                const matchCh = item.ch_no && item.ch_no.toLowerCase().includes(searchVal);
                const matchCode = item.m_code && item.m_code.toLowerCase().includes(searchVal);
                const matchDesc = item.m_description && item.m_description.toLowerCase().includes(searchVal);
                return matchCh || matchCode || matchDesc;
            }

            return true;
        });

        currentFilteredList = filtered;
        populateGrid(filtered);
    }

    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Challan(s)`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="10" style="text-align:center; color:var(--text-muted); padding:32px;">No matching challan records found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const inQty = parseFloat(item.in_qty);
            const balQty = parseFloat(item.bal_qty);
            const consumedQty = inQty - balQty;
            const status = (balQty > 0) ? 'Open' : 'Closed';
            const statusBadge = (status === 'Open') ? '<span class="badge badge-pending">Open</span>' : '<span class="badge badge-success">Closed</span>';
            const isExpanded = expandedChallans.has(item.id);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="text-align: center; font-size:1.1rem;">
                    <button class="toggle-expand-btn ${isExpanded ? 'active' : ''}" onclick="toggleRowExpand(this, ${item.id})">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </td>
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.ch_no)}</strong></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(item.ch_date)}</td>
                <td><span class="badge" style="background: rgba(255,255,255,0.05); color: var(--text-highlight); border:1px solid var(--border-color);">${escapeHtml(item.part_no || '-')}</span></td>
                <td><span style="font-family: monospace; font-size:0.85rem;">${escapeHtml(item.m_code)}</span></td>
                <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(item.m_description || '')}">${escapeHtml(item.m_description || '-')}</td>
                <td style="text-align: right; font-weight:700;">${App.formatDecimal(inQty)}</td>
                <td style="text-align: right; color: var(--primary);">${App.formatDecimal(consumedQty)}</td>
                <td style="text-align: right; font-weight:700; color: var(--success);">${App.formatDecimal(balQty)}</td>
                <td>${statusBadge}</td>
            `;
            body.appendChild(tr);

            // If expanded, insert details row immediately below
            if (isExpanded) {
                const detailsTr = document.createElement('tr');
                detailsTr.className = 'expanded-row-details';

                // Find all invoice consumptions for this inward transaction ID
                const relatedCons = consumptionLogs.filter(log => log.inward_id == item.id);

                let nestedContent = '';
                if (relatedCons.length === 0) {
                    nestedContent = `<div style="padding: 12px; color: var(--text-muted); font-size: 0.8rem;"><i class="fa-solid fa-circle-info"></i> No stock has been deducted from this Challan yet. Full balance intact.</div>`;
                } else {
                    nestedContent = `
                        <div style="padding: 4px 16px 12px;">
                            <h4 style="margin: 8px 0; font-size:0.85rem; color: var(--primary);"><i class="fa-solid fa-calculator"></i> Associated Invoice Consumption Log</h4>
                            <table class="nested-consumption-table">
                                <thead>
                                    <tr>
                                        <th>Invoice No</th>
                                        <th>Invoice Date</th>
                                        <th>Winding Pack Name</th>
                                        <th style="text-align: right;">Quantity Used</th>
                                        <th style="width: 150px;">Usage Percent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${relatedCons.map(c => {
                                        const cQty = parseFloat(c.qty_used);
                                        const percent = (cQty / inQty * 100).toFixed(1);
                                        return `
                                            <tr>
                                                <td><strong style="color:var(--success); font-family: monospace;">${escapeHtml(c.inv_no)}</strong></td>
                                                <td>${App.formatDate(c.inv_date)}</td>
                                                <td><span style="font-family: monospace;">${escapeHtml(c.wound_code || '-')}</span></td>
                                                <td style="text-align: right; font-weight: 700;">${App.formatDecimal(cQty)}</td>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <div class="usage-progress-bar">
                                                            <div class="usage-progress-fill" style="width: ${percent}%;"></div>
                                                        </div>
                                                        <span style="font-size:0.75rem; color:var(--text-muted);">${percent}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }

                detailsTr.innerHTML = `
                    <td colspan="10">${nestedContent}</td>
                `;
                body.appendChild(detailsTr);
            }
        });
    }

    function toggleRowExpand(btn, id) {
        if (expandedChallans.has(id)) {
            expandedChallans.delete(id);
        } else {
            expandedChallans.add(id);
        }
        applyFilters();
    }

    function resetFilters() {
        document.getElementById('filter-part').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-start-date').value = '';
        document.getElementById('filter-end-date').value = '';
        document.getElementById('filter-search').value = '';
        applyFilters();
    }

    // Excel Export
    function exportToExcel() {
        const dataToExport = [];
        inwardsList.forEach(item => {
            const inQty = parseFloat(item.in_qty);
            const balQty = parseFloat(item.bal_qty);
            const consumedQty = inQty - balQty;
            const status = (balQty > 0) ? 'Open' : 'Closed';

            // Master Row
            dataToExport.push({
                'Record Type': 'Challan Record',
                'Challan/Invoice No': item.ch_no,
                'Date': App.formatDate(item.ch_date),
                'Part No': item.part_no || '-',
                'Material Code': item.m_code,
                'Description': item.m_description || '-',
                'Received Qty': inQty,
                'Consumed Qty': consumedQty,
                'Remaining Qty': balQty,
                'Status': status,
                'Winding Pack': '-'
            });

            // Child Rows
            const related = consumptionLogs.filter(log => log.inward_id == item.id);
            related.forEach(c => {
                dataToExport.push({
                    'Record Type': '  L-> Consumption Detail',
                    'Challan/Invoice No': '  ' + c.inv_no,
                    'Date': App.formatDate(c.inv_date),
                    'Part No': item.part_no || '-',
                    'Material Code': item.m_code,
                    'Description': 'Stock consumption deduction mapping',
                    'Received Qty': 0,
                    'Consumed Qty': parseFloat(c.qty_used),
                    'Remaining Qty': 0,
                    'Status': 'Deducted',
                    'Winding Pack': c.wound_code || '-'
                });
            });
        });

        if (dataToExport.length === 0) {
            App.showToast('No records to export.', 'warning');
            return;
        }

        const worksheet = XLSX.utils.json_to_sheet(dataToExport);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Challan Audit Tracking");

        // Autofit columns
        const max_len = dataToExport.reduce((prev, toex) => {
            Object.keys(toex).forEach((k, i) => {
                const val = String(toex[k]);
                prev[i] = Math.max(prev[i] || 0, val.length, k.length);
            });
            return prev;
        }, []);
        worksheet['!cols'] = max_len.map(w => ({ wch: w + 2 }));

        XLSX.writeFile(workbook, "Company_Challan_Tracking_Report.xlsx");
        App.showToast("Challan report exported to Excel successfully.");
    }

    // PDF Export using jsPDF and AutoTable
    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4'); // landscape orientation

        doc.setFont("Helvetica", "bold");
        doc.setFontSize(16);
        doc.text("JAGDAMBA ELECTRICAL - COMPANY CHALLAN TRACKING SYSTEM", 14, 15);
        doc.setFontSize(10);
        doc.setFont("Helvetica", "normal");
        doc.text("Audit Report Generated: " + new Date().toLocaleString(), 14, 21);

        const rows = [];
        inwardsList.forEach(item => {
            const inQty = parseFloat(item.in_qty);
            const balQty = parseFloat(item.bal_qty);
            const consumedQty = inQty - balQty;
            const status = (balQty > 0) ? 'Open' : 'Closed';

            rows.push([
                item.ch_no,
                App.formatDate(item.ch_date),
                item.part_no || '-',
                item.m_code,
                item.m_description || '-',
                inQty.toFixed(2),
                consumedQty.toFixed(2),
                balQty.toFixed(2),
                status
            ]);

            // Add inner consumptions indented
            const related = consumptionLogs.filter(log => log.inward_id == item.id);
            related.forEach(c => {
                rows.push([
                    "  └-> " + c.inv_no,
                    App.formatDate(c.inv_date),
                    "",
                    "",
                    "  [Consumed in: " + (c.wound_code || '-') + "]",
                    "",
                    c.qty_used,
                    "",
                    "Deducted"
                ]);
            });
        });

        doc.autoTable({
            head: [['Challan/Inv No', 'Date', 'Part No', 'Material', 'Description', 'Rec. Qty', 'Cons. Qty', 'Rem. Qty', 'Status']],
            body: rows,
            startY: 26,
            theme: 'striped',
            headStyles: { fillColor: [30, 41, 59] },
            columnStyles: {
                0: { fontStyle: 'bold' },
                5: { halign: 'right' },
                6: { halign: 'right' },
                7: { halign: 'right' }
            },
            styles: { fontSize: 8 }
        });

        doc.save("Challan_Tracking_Report.pdf");
        App.showToast("Challan report exported to PDF successfully.");
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

    // Modal Control Functions
    function openExportModal() {
        document.getElementById('exportModal').style.display = 'flex';
    }

    // Close Modal Function
    function closeExportModal() {
        document.getElementById('exportModal').style.display = 'none';
    }

    // Select/Deselect All Columns
    function toggleAllColumns(checked) {
        const checkIds = [
            'col-ch-no', 'col-ch-date', 'col-part-no', 'col-m-code',
            'col-m-desc', 'col-in-qty', 'col-consumed', 'col-bal-qty', 'col-status'
        ];
        checkIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.checked = checked;
        });
    }

    // Custom Export Execution Trigger
    function triggerCustomExport() {
        const columnsConfig = [
            { id: 'col-ch-no', label: 'Challan/Inv No', key: 'ch_no' },
            { id: 'col-ch-date', label: 'Date', key: 'ch_date' },
            { id: 'col-part-no', label: 'Part No', key: 'part_no' },
            { id: 'col-m-code', label: 'Material Code', key: 'm_code' },
            { id: 'col-m-desc', label: 'Description', key: 'm_description' },
            { id: 'col-in-qty', label: 'Rec. Qty', key: 'in_qty', isNumeric: true },
            { id: 'col-consumed', label: 'Cons. Qty', key: 'consumed', isNumeric: true },
            { id: 'col-bal-qty', label: 'Rem. Qty', key: 'bal_qty', isNumeric: true },
            { id: 'col-status', label: 'Status', key: 'status' }
        ];

        // Gather checked columns
        const selectedCols = columnsConfig.filter(col => {
            const el = document.getElementById(col.id);
            return el && el.checked;
        });

        if (selectedCols.length === 0) {
            App.showToast('Please select at least one column to export.', 'warning');
            return;
        }

        const includeConsumption = document.getElementById('export-include-consumption').checked;
        const format = document.querySelector('input[name="export-format"]:checked').value;

        closeExportModal();

        if (format === 'excel') {
            exportCustomExcel(selectedCols, includeConsumption);
        } else {
            exportCustomPDF(selectedCols, includeConsumption);
        }
    }

    // Custom Excel Exporter
    function exportCustomExcel(selectedCols, includeConsumption) {
        const dataToExport = [];

        currentFilteredList.forEach(item => {
            const inQty = parseFloat(item.in_qty);
            const balQty = parseFloat(item.bal_qty);
            const consumedQty = inQty - balQty;
            const status = (balQty > 0) ? 'Open' : 'Closed';

            // Master Row
            const masterRow = {};
            if (includeConsumption) {
                masterRow['Record Type'] = 'Challan Record';
            }

            selectedCols.forEach(col => {
                let val = '';
                if (col.key === 'consumed') {
                    val = consumedQty;
                } else if (col.key === 'status') {
                    val = status;
                } else if (col.key === 'in_qty') {
                    val = inQty;
                } else if (col.key === 'bal_qty') {
                    val = balQty;
                } else {
                    val = item[col.key] || '-';
                }

                if (col.key === 'ch_date') {
                    val = App.formatDate(val);
                }
                masterRow[col.label] = val;
            });

            if (includeConsumption) {
                masterRow['Winding Pack'] = '-';
            }
            dataToExport.push(masterRow);

            // Child Rows
            if (includeConsumption) {
                const related = consumptionLogs.filter(log => log.inward_id == item.id);
                related.forEach(c => {
                    const childRow = {};
                    childRow['Record Type'] = '  └-> Consumption Detail';

                    selectedCols.forEach(col => {
                        let val = '';
                        if (col.key === 'ch_no') {
                            val = '  ' + c.inv_no;
                        } else if (col.key === 'ch_date') {
                            val = App.formatDate(c.inv_date);
                        } else if (col.key === 'm_description') {
                            val = 'Stock consumption deduction mapping';
                        } else if (col.key === 'consumed') {
                            val = parseFloat(c.qty_used);
                        } else if (col.key === 'status') {
                            val = 'Deducted';
                        } else if (col.isNumeric) {
                            val = 0;
                        } else {
                            val = '';
                        }
                        childRow[col.label] = val;
                    });

                    childRow['Winding Pack'] = c.wound_code || '-';
                    dataToExport.push(childRow);
                });
            }
        });

        if (dataToExport.length === 0) {
            App.showToast('No records to export.', 'warning');
            return;
        }

        const worksheet = XLSX.utils.json_to_sheet(dataToExport);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Custom Export");

        // Autofit columns
        const max_len = dataToExport.reduce((prev, toex) => {
            Object.keys(toex).forEach((k, i) => {
                const val = String(toex[k]);
                prev[i] = Math.max(prev[i] || 0, val.length, k.length);
            });
            return prev;
        }, []);
        worksheet['!cols'] = max_len.map(w => ({ wch: w + 2 }));

        XLSX.writeFile(workbook, "Challan_Tracking_Custom_Report.xlsx");
        App.showToast("Custom report exported to Excel successfully.");
    }

    // Custom PDF Exporter
    function exportCustomPDF(selectedCols, includeConsumption) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4');

        doc.setFont("Helvetica", "bold");
        doc.setFontSize(16);
        doc.text("JAGDAMBA ELECTRICAL - COMPANY CHALLAN TRACKING SYSTEM", 14, 15);
        doc.setFontSize(10);
        doc.setFont("Helvetica", "normal");
        doc.text("Custom Audit Report Generated: " + new Date().toLocaleString(), 14, 21);

        const headers = selectedCols.map(col => col.label);
        const rows = [];

        currentFilteredList.forEach(item => {
            const inQty = parseFloat(item.in_qty);
            const balQty = parseFloat(item.bal_qty);
            const consumedQty = inQty - balQty;
            const status = (balQty > 0) ? 'Open' : 'Closed';

            const masterRow = selectedCols.map(col => {
                if (col.key === 'ch_no') return item.ch_no;
                if (col.key === 'ch_date') return App.formatDate(item.ch_date);
                if (col.key === 'part_no') return item.part_no || '-';
                if (col.key === 'm_code') return item.m_code;
                if (col.key === 'm_description') return item.m_description || '-';
                if (col.key === 'in_qty') return inQty.toFixed(2);
                if (col.key === 'consumed') return consumedQty.toFixed(2);
                if (col.key === 'bal_qty') return balQty.toFixed(2);
                if (col.key === 'status') return status;
                return '';
            });
            rows.push(masterRow);

            if (includeConsumption) {
                const related = consumptionLogs.filter(log => log.inward_id == item.id);
                related.forEach(c => {
                    const childRow = selectedCols.map(col => {
                        if (col.key === 'ch_no') return "  └-> " + c.inv_no;
                        if (col.key === 'ch_date') return App.formatDate(c.inv_date);
                        if (col.key === 'm_description') return "  [Consumed in: " + (c.wound_code || '-') + "]";
                        if (col.key === 'consumed') return parseFloat(c.qty_used).toFixed(2);
                        if (col.key === 'status') return "Deducted";
                        return "";
                    });
                    rows.push(childRow);
                });
            }
        });

        const columnStyles = {};
        selectedCols.forEach((col, idx) => {
            if (col.isNumeric) {
                columnStyles[idx] = { halign: 'right' };
            }
            if (col.key === 'ch_no') {
                columnStyles[idx] = { fontStyle: 'bold' };
            }
        });

        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 26,
            theme: 'striped',
            headStyles: { fillColor: [30, 41, 59] },
            columnStyles: columnStyles,
            styles: { fontSize: 8 }
        });

        doc.save("Challan_Custom_Report.pdf");
        App.showToast("Custom report exported to PDF successfully.");
    }
</script>

<!-- Custom Export Modal HTML Structure -->
<div id="exportModal" class="custom-modal-overlay" style="display: none;">
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <h3><i class="fa-solid fa-file-export" style="color: var(--primary);"></i> Custom Export Settings</h3>
            <button class="modal-close-btn" onclick="closeExportModal()">&times;</button>
        </div>
        <div class="custom-modal-body">
            <p style="margin-bottom: 16px; font-size: 0.85rem; color: var(--text-muted);">
                Select the columns you wish to include in the exported file.
            </p>

            <div class="column-selection-grid">
                <label class="column-checkbox-label">
                    <input type="checkbox" id="col-ch-no" checked>
                    <span>Challan No</span>
                </label>
                <label class="column-checkbox-label">
                    <input type="checkbox" id="col-ch-date" checked>
                    <span>Challan Date</span>
                </label>
                <label class="column-checkbox-label">
                    <input type="checkbox" id="col-part-no" checked>
                    <span>Part No</span>
                </label>
                <label class="column-checkbox-label">
                    <input type="checkbox" id="col-m-code" checked>
                    <span>Material Code</span>
                </label>
                <label class="column-checkbox-label">
                    <input type="checkbox" id="col-m-desc" checked>
                    <span>Description</span>
                </label>
                <label class="column-checkbox-label">
                    <input type="checkbox" id="col-in-qty" checked>
                    <span>Received Qty</span>
                </label>
                <label class="column-checkbox-label">
                    <input type="checkbox" id="col-consumed" checked>
                    <span>Consumed Qty</span>
                </label>
                <label class="column-checkbox-label">
                    <input type="checkbox" id="col-bal-qty" checked>
                    <span>Remaining Qty</span>
                </label>
                <label class="column-checkbox-label">
                    <input type="checkbox" id="col-status" checked>
                    <span>Status</span>
                </label>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 12px; margin-bottom: 24px;">
                <button type="button" class="btn-link" onclick="toggleAllColumns(true)">Select All</button>
                <span style="color: var(--border-color);">|</span>
                <button type="button" class="btn-link" onclick="toggleAllColumns(false)" style="color: var(--text-muted);">Deselect All</button>
            </div>

            <div style="border-top: 1px solid var(--border-color); padding-top: 16px; margin-bottom: 12px;">
                <h4 style="margin-bottom: 12px; font-size: 0.9rem; color: var(--text-highlight);">Options</h4>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="column-checkbox-label" style="background: none; border: none; padding: 0;">
                        <input type="checkbox" id="export-include-consumption" checked>
                        <span>Include Consumption Details (Indented Rows)</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Export Format</label>
                    <div style="display: flex; gap: 24px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem;">
                            <input type="radio" name="export-format" value="excel" checked style="accent-color: var(--primary); cursor: pointer;">
                            <span>Excel (.xlsx)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem;">
                            <input type="radio" name="export-format" value="pdf" style="accent-color: var(--primary); cursor: pointer;">
                            <span>PDF (.pdf)</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="custom-modal-footer">
            <button class="btn btn-secondary" onclick="closeExportModal()">Cancel</button>
            <button class="btn btn-primary" onclick="triggerCustomExport()" style="font-weight: 600;">Export</button>
        </div>
    </div>
</div>

<?php
renderFooter();
?>
