<?php
// Sahara Electrical - System Aging Alerts (alerts.php)
require_once 'sidebar.php';
renderHeader("Aged Stock & Inventory Alerts", "alerts");
?>

<!-- Statistics counters -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Aged Stock Batches (>30 Days)</span>
            <span class="stat-value" id="stats-total-batches" style="color: var(--warning); text-shadow: 0 0 10px rgba(245,158,11,0.15);">0</span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Critical Aged Batches (>60 Days)</span>
            <span class="stat-value" id="stats-critical-batches" style="color: var(--error); text-shadow: 0 0 10px rgba(239,68,68,0.15);">0</span>
        </div>
        <div class="stat-icon-box red">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>
    </div>

    <div class="stat-card" style="grid-column: span 2;">
        <div class="stat-info">
            <span class="stat-label">Total Aged Unused Quantity</span>
            <span class="stat-value" id="stats-total-qty" style="color: var(--text-highlight);">0.00</span>
        </div>
        <div class="stat-icon-box blue">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
    </div>
</div>

<!-- Search & Filtering Panel -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header" style="background-color: rgba(255, 255, 255, 0.01); border-bottom: 1px solid var(--border-color);">
        <span class="panel-title"><i class="fa-solid fa-filter" style="color: var(--primary);"></i> Filter Inventory Alerts</span>
        <span class="panel-subtitle">Narrow down alerts by dates, keyword, or age severity</span>
    </div>
    <div class="panel-body" style="padding: 24px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: flex-end;">
            <div class="form-group">
                <label class="form-label" for="filter-start-date">Inward Date From</label>
                <input type="date" id="filter-start-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-end-date">Inward Date To</label>
                <input type="date" id="filter-end-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-age-group">Age Bracket</label>
                <select id="filter-age-group" class="form-control" onchange="applyFilters()" style="height: 42px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 8px; padding: 0 12px; font-family: var(--font-body); font-weight: 500;">
                    <option value="all">All Aged (>30 Days)</option>
                    <option value="aged">Warning (31-60 Days)</option>
                    <option value="critical">Critical (>60 Days)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-party">Filter by Party</label>
                <select id="filter-party" class="form-control" onchange="applyFilters()" style="background-color: var(--bg-main); height: 42px;">
                    <option value="">-- All Parties --</option>
                </select>
            </div>

            <div class="form-group" style="flex: 2; min-width: 200px;">
                <label class="form-label" for="filter-search">Search Keyword</label>
                <div class="topbar-search" style="display: flex; width: 100%;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px; top: 50%; transform: translateY(-50%);"></i>
                    <input type="text" id="filter-search" placeholder="Search by Material Code, Challan, Type..." style="width: 100%; padding-left: 40px; height: 42px;" oninput="applyFilters()">
                </div>
            </div>

            <div>
                <button class="btn btn-secondary" onclick="resetFilters()" style="width: 100%; height: 42px;"><i class="fa-solid fa-rotate-left"></i> Clear Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Alerts Table Panel -->
<div class="panel">
    <div class="panel-header" style="flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color);">
        <span class="panel-title" style="color: var(--error);"><i class="fa-solid fa-triangle-exclamation" style="color: var(--error);"></i> Aged Stock Alerts Register</span>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span class="panel-subtitle" id="record-count-label" style="font-size: 0.85rem; font-weight: 500; margin: 0; color: var(--text-muted);">0 Batches</span>
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
            <table class="custom-table" id="alerts-ledger-table">
                <thead>
                    <tr>
                        <th>Inward Date</th>
                        <th style="text-align: right;">Age (Days)</th>
                        <th>Challan Number</th>
                        <th>Material Code</th>
                        <th>Material Description</th>
                        <th>Material Type</th>
                        <th style="text-align: right;">Original Qty</th>
                        <th style="text-align: right; color: var(--warning);">Remaining Bal</th>
                        <th>Status</th>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

<script>
    let alertsCache = [];
    let filteredAlerts = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        await loadParties();
        loadAlertsData();
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

    // Query aged stocks from database
    async function loadAlertsData() {
        App.showLoading('Retrieving aged inventory records...');
        try {
            const res = await App.api('query', {
                sql: `
                    SELECT
                        id,
                        ch_no,
                        ch_date,
                        m_code,
                        m_description,
                        material_type,
                        in_qty,
                        bal_qty,
                        unit,
                        party_id,
                        DATEDIFF(CURDATE(), ch_date) as age_days
                    FROM inward_transaction
                    WHERE bal_qty > 0 AND ch_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    ORDER BY age_days DESC
                `
            });
            alertsCache = res.rows || [];
            applyFilters();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Apply Filter criteria
    function applyFilters() {
        const startVal = document.getElementById('filter-start-date').value;
        const endVal = document.getElementById('filter-end-date').value;
        const ageGroup = document.getElementById('filter-age-group').value;
        const partyVal = document.getElementById('filter-party').value;
        const searchVal = document.getElementById('filter-search').value.toLowerCase().trim();

        const startDate = startVal ? new Date(startVal) : null;
        const endDate = endVal ? new Date(endVal) : null;

        if (startDate) startDate.setHours(0,0,0,0);
        if (endDate) endDate.setHours(23,59,59,999);

        filteredAlerts = alertsCache.filter(item => {
            // 1. Party filter
            if (partyVal && item.party_id != partyVal) return false;

            // 2. Date filter
            if (item.ch_date) {
                const rowDate = new Date(item.ch_date);
                rowDate.setHours(0,0,0,0);
                if (startDate && rowDate < startDate) return false;
                if (endDate && rowDate > endDate) return false;
            }

            // 2. Age Bracket filter
            const age = parseInt(item.age_days || 0);
            if (ageGroup === 'aged' && (age <= 30 || age > 60)) return false;
            if (ageGroup === 'critical' && age <= 60) return false;

            // 3. Keyword filter
            if (searchVal) {
                const ch = (item.ch_no || '').toLowerCase();
                const mCode = (item.m_code || '').toLowerCase();
                const desc = (item.m_description || '').toLowerCase();
                const type = (item.material_type || '').toLowerCase();

                return ch.includes(searchVal) || mCode.includes(searchVal) || desc.includes(searchVal) || type.includes(searchVal);
            }

            return true;
        });

        populateGrid(filteredAlerts);
        updateStatistics(filteredAlerts);
    }

    // Populate table rows
    function populateGrid(data) {
        const body = document.getElementById('detailed-grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Batches`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:32px;">No aged material batches found matching the filters.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');

            const age = parseInt(item.age_days || 0);
            let statusBadge = '';
            let ageColor = '';

            if (age > 60) {
                statusBadge = '<span class="badge" style="font-weight:700; background-color:rgba(239, 68, 68, 0.15); color:var(--error); padding:4px 8px; border-radius:4px; font-size:0.75rem;">Critical (>60d)</span>';
                ageColor = 'color: var(--error); font-weight: 700; text-shadow: 0 0 8px rgba(239,68,68,0.15);';
            } else {
                statusBadge = '<span class="badge" style="font-weight:700; background-color:rgba(245, 158, 11, 0.15); color:var(--warning); padding:4px 8px; border-radius:4px; font-size:0.75rem;">Warning (31-60d)</span>';
                ageColor = 'color: var(--warning); font-weight: 700;';
            }

            const mCode = item.m_code ?
                `<span style="font-family: monospace; font-size:0.85rem; background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px; border:1px solid var(--border-color); color:var(--text-highlight);">${escapeHtml(item.m_code)}</span>` :
                `<span style="color:var(--text-muted); font-style:italic;">N/A</span>`;
            const mDesc = item.m_description || `<span style="color:var(--text-muted); font-style:italic;">N/A</span>`;
            const mType = item.material_type ?
                `<span class="badge" style="background-color:rgba(255,255,255,0.08); color:var(--text-main); padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;">${escapeHtml(item.material_type)}</span>` :
                `<span style="color:var(--text-muted); font-style:italic;">N/A</span>`;

            tr.innerHTML = `
                <td style="font-size:0.85rem; color:var(--text-muted);">${item.ch_date ? App.formatDate(item.ch_date) : 'N/A'}</td>
                <td style="text-align: right; ${ageColor}">${age} Days</td>
                <td><span style="font-family: monospace; font-size:0.85rem; color:var(--secondary); font-weight:600;">${escapeHtml(item.ch_no)}</span></td>
                <td>${mCode}</td>
                <td style="white-space:normal; max-width:200px; font-size:0.85rem;">${mDesc}</td>
                <td>${mType}</td>
                <td style="text-align: right; font-weight:500;">${App.formatDecimal(item.in_qty)} ${escapeHtml(item.unit || 'NOS')}</td>
                <td style="text-align: right; font-weight:700; color:var(--warning);">${App.formatDecimal(item.bal_qty)} ${escapeHtml(item.unit || 'NOS')}</td>
                <td>${statusBadge}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Update stats cards
    function updateStatistics(data) {
        document.getElementById('stats-total-batches').innerText = data.length;

        // Critical batches count (age > 60)
        const criticalCount = data.filter(item => parseInt(item.age_days || 0) > 60).length;
        document.getElementById('stats-critical-batches').innerText = criticalCount;

        // Sum remaining balance qty
        let totalQty = 0;
        data.forEach(item => {
            totalQty += parseFloat(item.bal_qty || 0);
        });
        document.getElementById('stats-total-qty').innerText = App.formatDecimal(totalQty);
    }

    // Reset controls
    function resetFilters() {
        document.getElementById('filter-start-date').value = '';
        document.getElementById('filter-end-date').value = '';
        document.getElementById('filter-age-group').value = 'all';
        document.getElementById('filter-party').value = '';
        document.getElementById('filter-search').value = '';
        applyFilters();
    }

    // Export current filtered rows to Excel
    function exportToExcel() {
        if (filteredAlerts.length === 0) {
            alert("No records found to export.");
            return;
        }

        const data = filteredAlerts.map(item => ({
            "Inward Date": item.ch_date || 'N/A',
            "Stock Age (Days)": parseInt(item.age_days || 0),
            "Challan Number": item.ch_no,
            "Material Code": item.m_code || 'N/A',
            "Material Description": item.m_description || 'N/A',
            "Material Type": item.material_type || 'N/A',
            "Original Inward Qty": parseFloat(item.in_qty || 0),
            "Remaining Balance Qty": parseFloat(item.bal_qty || 0),
            "Unit": item.unit || 'NOS',
            "Severity Status": parseInt(item.age_days || 0) > 60 ? "Critical" : "Warning"
        }));

        const worksheet = XLSX.utils.json_to_sheet(data);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Aged Stock Alerts");

        worksheet['!cols'] = [
            { wch: 15 }, // Inward Date
            { wch: 15 }, // Age (Days)
            { wch: 18 }, // Challan No
            { wch: 18 }, // Material Code
            { wch: 25 }, // Description
            { wch: 15 }, // Type
            { wch: 18 }, // Inward Qty
            { wch: 18 }, // Remaining Qty
            { wch: 10 }, // Unit
            { wch: 15 }  // Status
        ];

        const startVal = document.getElementById('filter-start-date').value || 'all';
        const endVal = document.getElementById('filter-end-date').value || 'all';
        XLSX.writeFile(workbook, `Aged_Stock_Alerts_${startVal}_to_${endVal}.xlsx`);
    }

    // Export current filtered rows to PDF
    function exportToPDF() {
        if (filteredAlerts.length === 0) {
            alert("No records found to export.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4');

        doc.setFont("Helvetica", "bold");
        doc.setFontSize(16);
        doc.text("SAHARA ELECTRICAL", 14, 20);

        doc.setFont("Helvetica", "normal");
        doc.setFontSize(10);
        doc.text("Aged Stock Alerts Registry (FIFO Balance Older than 30 Days)", 14, 26);

        const startVal = document.getElementById('filter-start-date').value || 'Beginning';
        const endVal = document.getElementById('filter-end-date').value || 'Present';
        doc.text(`Period: ${startVal} to ${endVal}`, 14, 31);

        const columns = [
            { header: "Inward Date", dataKey: "ch_date" },
            { header: "Age (Days)", dataKey: "age_days" },
            { header: "Challan No", dataKey: "ch_no" },
            { header: "Material Code", dataKey: "m_code" },
            { header: "Description", dataKey: "m_description" },
            { header: "Material Type", dataKey: "material_type" },
            { header: "Original Qty", dataKey: "in_qty" },
            { header: "Remaining Bal", dataKey: "bal_qty" },
            { header: "Severity", dataKey: "severity" }
        ];

        const rows = filteredAlerts.map(item => ({
            ch_date: item.ch_date ? (App.formatDate ? App.formatDate(item.ch_date) : item.ch_date) : 'N/A',
            age_days: `${parseInt(item.age_days || 0)} Days`,
            ch_no: item.ch_no,
            m_code: item.m_code || 'N/A',
            m_description: item.m_description || 'N/A',
            material_type: item.material_type || 'N/A',
            in_qty: `${parseFloat(item.in_qty || 0).toFixed(2)} ${item.unit || 'NOS'}`,
            bal_qty: `${parseFloat(item.bal_qty || 0).toFixed(2)} ${item.unit || 'NOS'}`,
            severity: parseInt(item.age_days || 0) > 60 ? "Critical" : "Warning"
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
                age_days: { halign: 'right' },
                in_qty: { halign: 'right' },
                bal_qty: { halign: 'right', fontStyle: 'bold', textColor: [245, 158, 11] }
            },
            didParseCell: function (data) {
                // Style critical rows in red text
                if (data.column.key === 'severity') {
                    if (data.cell.raw === "Critical") {
                        data.cell.styles.textColor = [239, 68, 68];
                        data.cell.styles.fontStyle = 'bold';
                    } else {
                        data.cell.styles.textColor = [245, 158, 11];
                    }
                }
            }
        });

        doc.save(`Aged_Stock_Alerts_${startVal}_to_${endVal}.pdf`);
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
