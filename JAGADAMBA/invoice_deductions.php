<?php
// Sahara Electrical - Invoice Stock Deductions Ledger (invoice_deductions.php)
require_once 'sidebar.php';
renderHeader("Invoice Material Deductions Ledger", "invoice_deductions");
?>

<!-- Statistics counters -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Invoices Tracked</span>
            <span class="stat-value" id="stats-total-inv" style="color: var(--primary); text-shadow: 0 0 10px rgba(255,159,0,0.15);">0</span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Raw Materials Consumed</span>
            <span class="stat-value" id="stats-total-items">0</span>
        </div>
        <div class="stat-icon-box blue">
            <i class="fa-solid fa-gears"></i>
        </div>
    </div>

    <div class="stat-card" style="grid-column: span 2;">
        <div class="stat-info">
            <span class="stat-label">Inventory Status</span>
            <span class="stat-value" style="color: var(--success); font-size: 1.5rem;"><i class="fa-solid fa-circle-check"></i> Stock Updated (FIFO)</span>
        </div>
        <div class="stat-icon-box green">
            <i class="fa-solid fa-warehouse"></i>
        </div>
    </div>
</div>

<!-- Search & Date Filter Panel -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header" style="background-color: rgba(255, 255, 255, 0.01); border-bottom: 1px solid var(--border-color);">
        <span class="panel-title"><i class="fa-solid fa-filter" style="color: var(--primary);"></i> Filter Material Consumption</span>
        <span class="panel-subtitle">Filter stock deductions by date range or keyword</span>
    </div>
    <div class="panel-body" style="padding: 24px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
            <div class="form-group">
                <label class="form-label" for="filter-start-date">Invoice Date From</label>
                <input type="date" id="filter-start-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-end-date">Invoice Date To</label>
                <input type="date" id="filter-end-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-party">Filter by Party</label>
                <select id="filter-party" class="form-control" onchange="applyFilters()" style="background-color: var(--bg-main); height: 42px;">
                    <option value="">-- All Parties --</option>
                </select>
            </div>

            <div class="form-group" style="flex: 2;">
                <label class="form-label" for="filter-search">Search Keyword</label>
                <div class="topbar-search" style="display: flex; width: 100%;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px; top: 50%; transform: translateY(-50%);"></i>
                    <input type="text" id="filter-search" placeholder="Search by Invoice No, Wound Code, Material Code, Material Type..." style="width: 100%; padding-left: 40px; height: 42px;" oninput="applyFilters()">
                </div>
            </div>

            <div>
                <button class="btn btn-secondary" onclick="resetFilters()" style="width: 100%; height: 42px;"><i class="fa-solid fa-rotate-left"></i> Clear Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Registry Table Panel -->
<div class="panel">
    <div class="panel-header" style="flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color);">
        <span class="panel-title"><i class="fa-solid fa-calculator" style="color: var(--primary);"></i> Stock Deductions Ledger</span>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span class="panel-subtitle" id="record-count-label" style="font-size: 0.85rem; font-weight: 500; margin: 0;">0 Mappings</span>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-success" onclick="exportToExcel()" style="padding: 6px 14px; font-size: 0.85rem; height: 34px; margin: 0;">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button class="btn btn-danger" onclick="exportToPDF()" style="padding: 6px 14px; font-size: 0.85rem; height: 34px; margin: 0;">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
            </div>
        </div>
    </div>
    <div class="panel-body" style="padding: 24px;">
        <div class="table-container" style="max-height: 600px;">
            <table class="custom-table" id="deductions-ledger-table">
                <thead>
                    <tr>
                        <th>Invoice Date</th>
                        <th>Invoice Number</th>
                        <th style="text-align: right;">Invoice Qty</th>
                        <th>Wound Code</th>
                        <th>Material Code</th>
                        <th>Material Description</th>
                        <th>Material Type</th>
                        <th style="text-align: right; color: var(--error);">Deducted Qty</th>
                    </tr>
                </thead>
                <tbody id="detailed-grid-body">
                    <!-- Populated dynamically -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Load Excel & PDF Export Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.ajax.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

<script>
    let deductionsCache = [];
    let filteredDeductions = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        await loadParties();
        loadDeductionsData();
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

    // Query deductions from database
    async function loadDeductionsData() {
        App.showLoading('Retrieving material consumption records...');
        try {
            const res = await App.api('query', {
                sql: `
                    SELECT
                        ti.inv_no,
                        ti.inv_date,
                        ti.qty as invoice_qty,
                        ti.uom as invoice_uom,
                        ti.wound_code,
                        ti.party_id,
                        b.rm_code,
                        b.m_description,
                        b.material_type,
                        (b.req_qty * ti.qty) as deducted_qty,
                        rm.unit as material_unit
                    FROM tax_invoice ti
                    LEFT JOIN bom b ON ti.wound_code = b.wound_code
                    LEFT JOIN raw_material rm ON b.rm_code = rm.m_code
                    ORDER BY ti.inv_date DESC, ti.inv_no DESC, b.rm_code ASC
                `
            });
            deductionsCache = res.rows || [];
            applyFilters();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Apply Filter values (Date + Keyword)
    function applyFilters() {
        const startVal = document.getElementById('filter-start-date').value;
        const endVal = document.getElementById('filter-end-date').value;
        const partyVal = document.getElementById('filter-party').value;
        const searchVal = document.getElementById('filter-search').value.toLowerCase().trim();

        const startDate = startVal ? new Date(startVal) : null;
        const endDate = endVal ? new Date(endVal) : null;

        if (startDate) startDate.setHours(0,0,0,0);
        if (endDate) endDate.setHours(23,59,59,999);

        filteredDeductions = deductionsCache.filter(item => {
            // 1. Party filter
            if (partyVal && item.party_id != partyVal) return false;

            // 2. Date filter
            if (item.inv_date) {
                const invDate = new Date(item.inv_date);
                invDate.setHours(0,0,0,0);
                if (startDate && invDate < startDate) return false;
                if (endDate && invDate > endDate) return false;
            } else if (startDate || endDate) {
                return false;
            }

            // 2. Keyword filter
            if (searchVal) {
                const invNo = (item.inv_no || '').toLowerCase();
                const wound = (item.wound_code || '').toLowerCase();
                const rmCode = (item.rm_code || '').toLowerCase();
                const desc = (item.m_description || '').toLowerCase();
                const type = (item.material_type || '').toLowerCase();

                return invNo.includes(searchVal) || wound.includes(searchVal) || rmCode.includes(searchVal) || desc.includes(searchVal) || type.includes(searchVal);
            }

            return true;
        });

        populateGrid(filteredDeductions);
        updateStatistics(filteredDeductions);
    }

    // Populate Detailed Grid rows
    function populateGrid(data) {
        const body = document.getElementById('detailed-grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Mappings`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:32px;">No material stock deductions matching the filter criteria.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.title = "View Invoice Layout";
            tr.style.cursor = 'pointer';
            tr.onclick = () => {
                window.open(`print.php?inv_no=${encodeURIComponent(item.inv_no)}`, '_blank');
            };

            const invQty = parseFloat(item.invoice_qty || 0);
            const dedQty = parseFloat(item.deducted_qty || 0);
            const mCode = item.rm_code ?
                `<span style="font-family: monospace; font-size:0.85rem; background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px; border:1px solid var(--border-color); color:var(--text-highlight);">${escapeHtml(item.rm_code)}</span>` :
                `<span style="color:var(--text-muted); font-style:italic;">No BOM mapping</span>`;
            const mDesc = item.m_description || `<span style="color:var(--text-muted); font-style:italic;">N/A</span>`;
            const mType = item.material_type ?
                `<span class="badge" style="background-color:rgba(139, 92, 246, 0.15); color:var(--pending); padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;">${escapeHtml(item.material_type)}</span>` :
                `<span style="color:var(--text-muted); font-style:italic;">N/A</span>`;

            tr.innerHTML = `
                <td style="font-size:0.85rem; color:var(--text-muted);">${item.inv_date ? App.formatDate(item.inv_date) : 'N/A'}</td>
                <td><strong style="color:var(--secondary);">${escapeHtml(item.inv_no)}</strong></td>
                <td style="text-align: right; font-weight:500;">${App.formatDecimal(invQty)} ${escapeHtml(item.invoice_uom || 'NOS')}</td>
                <td><span style="font-family: monospace; font-size:0.85rem;">${escapeHtml(item.wound_code || 'N/A')}</span></td>
                <td>${mCode}</td>
                <td style="white-space:normal; max-width:220px; font-size:0.85rem;">${mDesc}</td>
                <td>${mType}</td>
                <td style="text-align: right; font-weight:700; color:var(--error); font-size:0.95rem;">- ${item.rm_code ? App.formatDecimal(dedQty) : '0.00'} ${escapeHtml(item.material_unit || 'NOS')}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Update Top Stat cards
    function updateStatistics(data) {
        // Unique invoices
        const uniqueInvoices = new Set(data.map(item => item.inv_no).filter(Boolean));
        document.getElementById('stats-total-inv').innerText = uniqueInvoices.size;

        // Total raw materials referenced
        const rawMaterialsUsed = data.filter(item => item.rm_code).length;
        document.getElementById('stats-total-items').innerText = rawMaterialsUsed;
    }

    // Reset Filter Controls
    function resetFilters() {
        document.getElementById('filter-start-date').value = '';
        document.getElementById('filter-end-date').value = '';
        document.getElementById('filter-party').value = '';
        document.getElementById('filter-search').value = '';
        applyFilters();
    }

    // Export current filtered rows to Excel
    function exportToExcel() {
        if (filteredDeductions.length === 0) {
            alert("No records found to export.");
            return;
        }

        const data = filteredDeductions.map(item => ({
            "Invoice Date": item.inv_date || 'N/A',
            "Invoice Number": item.inv_no,
            "Invoice Qty": parseFloat(item.invoice_qty || 0),
            "Wound Code": item.wound_code || 'N/A',
            "Material Code": item.rm_code || 'N/A',
            "Material Description": item.m_description || 'N/A',
            "Material Type": item.material_type || 'N/A',
            "Deducted Quantity": item.rm_code ? -parseFloat(item.deducted_qty || 0) : 0,
            "Material Unit": item.material_unit || 'NOS'
        }));

        const worksheet = XLSX.utils.json_to_sheet(data);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Material Deductions");

        worksheet['!cols'] = [
            { wch: 15 }, // Invoice Date
            { wch: 18 }, // Invoice No
            { wch: 12 }, // Invoice Qty
            { wch: 15 }, // Wound Code
            { wch: 18 }, // Material Code
            { wch: 25 }, // Description
            { wch: 15 }, // Type
            { wch: 18 }, // Deducted Qty
            { wch: 12 }  // Unit
        ];

        const startVal = document.getElementById('filter-start-date').value || 'all';
        const endVal = document.getElementById('filter-end-date').value || 'all';
        XLSX.writeFile(workbook, `Material_Deductions_${startVal}_to_${endVal}.xlsx`);
    }

    // Export current filtered rows to PDF
    function exportToPDF() {
        if (filteredDeductions.length === 0) {
            alert("No records found to export.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4'); // Landscape for many columns

        doc.setFont("Helvetica", "bold");
        doc.setFontSize(16);
        doc.text("SAHARA ELECTRICAL", 14, 20);

        doc.setFont("Helvetica", "normal");
        doc.setFontSize(10);
        doc.text("Invoice Material Stock Deductions Ledger (FIFO Mapping)", 14, 26);

        const startVal = document.getElementById('filter-start-date').value || 'Beginning';
        const endVal = document.getElementById('filter-end-date').value || 'Present';
        doc.text(`Filing Period: ${startVal} to ${endVal}`, 14, 31);

        const columns = [
            { header: "Date", dataKey: "inv_date" },
            { header: "Invoice No", dataKey: "inv_no" },
            { header: "Inv Qty", dataKey: "invoice_qty" },
            { header: "Wound Code", dataKey: "wound_code" },
            { header: "Material Code", dataKey: "rm_code" },
            { header: "Description", dataKey: "m_description" },
            { header: "Material Type", dataKey: "material_type" },
            { header: "Deducted Qty", dataKey: "deducted_qty" }
        ];

        const rows = filteredDeductions.map(item => ({
            inv_date: item.inv_date ? (App.formatDate ? App.formatDate(item.inv_date) : item.inv_date) : 'N/A',
            inv_no: item.inv_no,
            invoice_qty: `${parseFloat(item.invoice_qty || 0).toFixed(2)} ${item.invoice_uom || 'NOS'}`,
            wound_code: item.wound_code || 'N/A',
            rm_code: item.rm_code || 'N/A',
            m_description: item.m_description || 'N/A',
            material_type: item.material_type || 'N/A',
            deducted_qty: item.rm_code ? `-${parseFloat(item.deducted_qty || 0).toFixed(2)} ${item.material_unit || 'NOS'}` : '0.00'
        }));

        doc.autoTable({
            columns: columns,
            body: rows,
            startY: 37,
            theme: 'striped',
            headStyles: {
                fillColor: [255, 159, 0], // Sahara Amber
                textColor: [0, 0, 0],
                fontStyle: 'bold'
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245]
            },
            columnStyles: {
                invoice_qty: { halign: 'right' },
                deducted_qty: { halign: 'right', textColor: [239, 68, 68] }
            }
        });

        doc.save(`Material_Deductions_${startVal}_to_${endVal}.pdf`);
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
