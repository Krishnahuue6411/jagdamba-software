<?php
// Sahara Electrical - GST Records Report (gst_report.php)
require_once 'sidebar.php';
renderHeader("GST Records & Tax Ledger", "gst_report");
?>

<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header" style="background-color: rgba(255, 255, 255, 0.01);">
        <span class="panel-title"><i class="fa-solid fa-calendar-days" style="color: var(--primary);"></i> Select Tax Filing Period</span>
        <span class="panel-subtitle">Filter invoices by custom date range</span>
    </div>
    <div class="panel-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
            <div class="form-group">
                <label class="form-label" for="filter-start-date">Filing Period Start</label>
                <input type="date" id="filter-start-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-end-date">Filing Period End</label>
                <input type="date" id="filter-end-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group" style="flex: 2;">
                <label class="form-label" for="filter-search">Search Invoice</label>
                <div class="topbar-search" style="display: flex; width: 100%;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px;"></i>
                    <input type="text" id="filter-search" placeholder="Search by Invoice Number..." style="width: 100%; padding-left: 40px;" oninput="applyFilters()">
                </div>
            </div>

            <div>
                <button class="btn btn-secondary" onclick="resetFilters()" style="width: 100%;"><i class="fa-solid fa-rotate-left"></i> Clear Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Consolidated Tax Summaries -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Taxable Subtotal (Before GST)</span>
            <span class="stat-value">₹<span id="stats-taxable">0.00</span></span>
        </div>
        <div class="stat-icon-box blue">
            <i class="fa-solid fa-calculator"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Consolidated CGST (9%)</span>
            <span class="stat-value" style="color: var(--text-muted);">₹<span id="stats-cgst">0.00</span></span>
        </div>
        <div class="stat-icon-box purple">
            <i class="fa-solid fa-percent"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Consolidated SGST (9%)</span>
            <span class="stat-value" style="color: var(--text-muted);">₹<span id="stats-sgst">0.00</span></span>
        </div>
        <div class="stat-icon-box purple">
            <i class="fa-solid fa-percent"></i>
        </div>
    </div>

    <div class="stat-card" style="grid-column: span 2;">
        <div class="stat-info">
            <span class="stat-label">Total GST Tax Collected</span>
            <span class="stat-value" style="color: var(--warning); text-shadow: 0 0 10px rgba(245,158,11,0.2);">₹<span id="stats-total-tax">0.00</span></span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-coins"></i>
        </div>
    </div>

    <div class="stat-card" style="grid-column: span 2;">
        <div class="stat-info">
            <span class="stat-label">Net Revenue (After GST)</span>
            <span class="stat-value" style="color: var(--success); text-shadow: 0 0 10px rgba(16,185,129,0.2);">₹<span id="stats-net">0.00</span></span>
        </div>
        <div class="stat-icon-box green">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
    </div>
</div>

<!-- GST Ledger Panel -->
<div class="panel">
    <div class="panel-header" style="flex-wrap: wrap; gap: 12px;">
        <span class="panel-title"><i class="fa-solid fa-percent" style="color: var(--primary);"></i> GST Inward/Outward Tax Registry</span>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span class="panel-subtitle" id="record-count-label" style="font-size: 0.85rem; font-weight: 500; margin: 0;">0 Invoices</span>
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
    <div class="panel-body">
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 12px;">
            💡 Click on any invoice row to view print layout details.
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="custom-table" id="gst-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Invoice Date</th>
                        <th style="text-align: right;">Subtotal (₹)</th>
                        <th style="text-align: right;">CGST (9%) (₹)</th>
                        <th style="text-align: right;">SGST (9%) (₹)</th>
                        <th style="text-align: right; color: var(--warning);">Total Tax (₹)</th>
                        <th style="text-align: right; color: var(--success);">Net Total (₹)</th>
                    </tr>
                </thead>
                <tbody id="grid-body">
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
    let invoicesCache = [];
    let filteredInvoices = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadInvoices();
    });

    // Group tax_invoice line items by inv_no so each invoice number displays exactly once
    function groupInvoicesByInvNo(rows) {
        const map = new Map();
        (rows || []).forEach(item => {
            const invNo = (item.inv_no || '').trim();
            if (!invNo) return;

            if (!map.has(invNo)) {
                map.set(invNo, {
                    inv_no: invNo,
                    inv_date: item.inv_date,
                    party_id: item.party_id,
                    sub_total: 0,
                    cgst: 0,
                    sgst: 0,
                    net_total: 0
                });
            }

            const group = map.get(invNo);
            group.sub_total += parseFloat(item.sub_total || 0);
            group.cgst += parseFloat(item.cgst || 0);
            group.sgst += parseFloat(item.sgst || 0);
            group.net_total += parseFloat(item.net_total || 0);
            if (item.inv_date) group.inv_date = item.inv_date;
        });
        return Array.from(map.values());
    }

    // Fetch Invoice data
    async function loadInvoices() {
        App.showLoading('Loading tax records...');
        try {
            const res = await App.api('list', { table: 'tax_invoice' });
            invoicesCache = groupInvoicesByInvNo(res.rows || []);
            applyFilters();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Filter local cache
    function applyFilters() {
        const startVal = document.getElementById('filter-start-date').value;
        const endVal = document.getElementById('filter-end-date').value;
        const searchVal = document.getElementById('filter-search').value.toLowerCase().trim();

        const startDate = startVal ? new Date(startVal) : null;
        const endDate = endVal ? new Date(endVal) : null;

        if (startDate) startDate.setHours(0,0,0,0);
        if (endDate) endDate.setHours(23,59,59,999);

        const filtered = invoicesCache.filter(item => {
            // 1. Date Range
            const invDate = new Date(item.inv_date);
            invDate.setHours(0,0,0,0);

            if (startDate && invDate < startDate) return false;
            if (endDate && invDate > endDate) return false;

            // 2. Keyword
            if (searchVal) {
                return item.inv_no && item.inv_no.toLowerCase().includes(searchVal);
            }

            return true;
        });

        filteredInvoices = filtered;
        populateGrid(filtered);
        calculateTaxStats(filtered);
    }

    // Populate Table
    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Invoices`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:32px;">No invoice tax records found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const subtotal = parseFloat(item.sub_total || 0);
            const cgst = parseFloat(item.cgst || 0);
            const sgst = parseFloat(item.sgst || 0);
            const totalTax = cgst + sgst;
            const netTotal = parseFloat(item.net_total || 0);

            const tr = document.createElement('tr');
            tr.title = "View Print Preview";
            tr.onclick = () => {
                window.open(`print.php?inv_no=${encodeURIComponent(item.inv_no)}`, '_blank');
            };

            tr.innerHTML = `
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.inv_no)}</strong></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(item.inv_date)}</td>
                <td style="text-align: right;">${App.formatDecimal(subtotal)}</td>
                <td style="text-align: right; color: var(--text-muted);">${App.formatDecimal(cgst)}</td>
                <td style="text-align: right; color: var(--text-muted);">${App.formatDecimal(sgst)}</td>
                <td style="text-align: right; font-weight:700; color:var(--warning);">₹${App.formatDecimal(totalTax)}</td>
                <td style="text-align: right; font-weight:700; color:var(--success);">₹${App.formatDecimal(netTotal)}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Consolidated tax math
    function calculateTaxStats(data) {
        let sumTaxable = 0;
        let sumCgst = 0;
        let sumSgst = 0;
        let sumNet = 0;

        data.forEach(item => {
            sumTaxable += parseFloat(item.sub_total || 0);
            sumCgst += parseFloat(item.cgst || 0);
            sumSgst += parseFloat(item.sgst || 0);
            sumNet += parseFloat(item.net_total || 0);
        });

        const sumTax = sumCgst + sumSgst;

        document.getElementById('stats-taxable').innerText = App.formatDecimal(sumTaxable);
        document.getElementById('stats-cgst').innerText = App.formatDecimal(sumCgst);
        document.getElementById('stats-sgst').innerText = App.formatDecimal(sumSgst);
        document.getElementById('stats-total-tax').innerText = App.formatDecimal(sumTax);
        document.getElementById('stats-net').innerText = App.formatDecimal(sumNet);
    }

    // Reset Filters
    function resetFilters() {
        document.getElementById('filter-start-date').value = '';
        document.getElementById('filter-end-date').value = '';
        document.getElementById('filter-search').value = '';
        applyFilters();
    }

    // Escaper
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

    // Export current filtered rows to Excel
    function exportToExcel() {
        if (!filteredInvoices || filteredInvoices.length === 0) {
            alert("No records found to export.");
            return;
        }

        const data = filteredInvoices.map(item => {
            const subtotal = parseFloat(item.sub_total || 0);
            const cgst = parseFloat(item.cgst || 0);
            const sgst = parseFloat(item.sgst || 0);
            const totalTax = cgst + sgst;
            const netTotal = parseFloat(item.net_total || 0);

            return {
                "Invoice No": item.inv_no,
                "Invoice Date": item.inv_date,
                "Subtotal (INR)": subtotal,
                "CGST 9% (INR)": cgst,
                "SGST 9% (INR)": sgst,
                "Total Tax (INR)": totalTax,
                "Net Total (INR)": netTotal
            };
        });

        // Calculate totals for Excel summary row
        let totalSubtotal = 0;
        let totalCgst = 0;
        let totalSgst = 0;
        let totalTax = 0;
        let totalNet = 0;

        filteredInvoices.forEach(item => {
            const sub = parseFloat(item.sub_total || 0);
            const cg = parseFloat(item.cgst || 0);
            const sg = parseFloat(item.sgst || 0);
            totalSubtotal += sub;
            totalCgst += cg;
            totalSgst += sg;
            totalTax += (cg + sg);
            totalNet += parseFloat(item.net_total || 0);
        });

        data.push({
            "Invoice No": "TOTAL",
            "Invoice Date": "",
            "Subtotal (INR)": totalSubtotal,
            "CGST 9% (INR)": totalCgst,
            "SGST 9% (INR)": totalSgst,
            "Total Tax (INR)": totalTax,
            "Net Total (INR)": totalNet
        });

        const worksheet = XLSX.utils.json_to_sheet(data);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "GST Report");

        // Format column widths
        const wscols = [
            { wch: 15 }, // Invoice No
            { wch: 15 }, // Invoice Date
            { wch: 18 }, // Subtotal
            { wch: 15 }, // CGST
            { wch: 15 }, // SGST
            { wch: 18 }, // Total Tax
            { wch: 18 }  // Net Total
        ];
        worksheet['!cols'] = wscols;

        const startVal = document.getElementById('filter-start-date').value || 'all';
        const endVal = document.getElementById('filter-end-date').value || 'all';
        const filename = `GST_Report_${startVal}_to_${endVal}.xlsx`;
        XLSX.writeFile(workbook, filename);
    }

    // Export current filtered rows to PDF
    function exportToPDF() {
        if (!filteredInvoices || filteredInvoices.length === 0) {
            alert("No records found to export.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');

        // Header Title
        doc.setFont("Helvetica", "bold");
        doc.setFontSize(16);
        doc.text("SAHARA ELECTRICAL", 14, 20);

        doc.setFont("Helvetica", "normal");
        doc.setFontSize(10);
        doc.text("GST Records & Tax Ledger", 14, 26);

        const startVal = document.getElementById('filter-start-date').value || 'Beginning';
        const endVal = document.getElementById('filter-end-date').value || 'Present';
        doc.text(`Filing Period: ${startVal} to ${endVal}`, 14, 31);

        const columns = [
            { header: "Invoice No", dataKey: "inv_no" },
            { header: "Invoice Date", dataKey: "inv_date" },
            { header: "Subtotal (INR)", dataKey: "sub_total" },
            { header: "CGST 9% (INR)", dataKey: "cgst" },
            { header: "SGST 9% (INR)", dataKey: "sgst" },
            { header: "Total Tax (INR)", dataKey: "total_tax" },
            { header: "Net Total (INR)", dataKey: "net_total" }
        ];

        const rows = filteredInvoices.map(item => {
            const subtotal = parseFloat(item.sub_total || 0).toFixed(2);
            const cgst = parseFloat(item.cgst || 0).toFixed(2);
            const sgst = parseFloat(item.sgst || 0).toFixed(2);
            const totalTax = (parseFloat(item.cgst || 0) + parseFloat(item.sgst || 0)).toFixed(2);
            const netTotal = parseFloat(item.net_total || 0).toFixed(2);

            return {
                inv_no: item.inv_no,
                inv_date: App.formatDate ? App.formatDate(item.inv_date) : item.inv_date,
                sub_total: subtotal,
                cgst: cgst,
                sgst: sgst,
                total_tax: totalTax,
                net_total: netTotal
            };
        });

        // Add TOTAL row at the bottom
        let totalSubtotal = 0;
        let totalCgst = 0;
        let totalSgst = 0;
        let totalTax = 0;
        let totalNet = 0;

        filteredInvoices.forEach(item => {
            const sub = parseFloat(item.sub_total || 0);
            const cg = parseFloat(item.cgst || 0);
            const sg = parseFloat(item.sgst || 0);
            totalSubtotal += sub;
            totalCgst += cg;
            totalSgst += sg;
            totalTax += (cg + sg);
            totalNet += parseFloat(item.net_total || 0);
        });

        rows.push({
            inv_no: "TOTAL",
            inv_date: "",
            sub_total: totalSubtotal.toFixed(2),
            cgst: totalCgst.toFixed(2),
            sgst: totalSgst.toFixed(2),
            total_tax: totalTax.toFixed(2),
            net_total: totalNet.toFixed(2)
        });

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
                sub_total: { halign: 'right' },
                cgst: { halign: 'right' },
                sgst: { halign: 'right' },
                total_tax: { halign: 'right' },
                net_total: { halign: 'right' }
            },
            didParseCell: function (data) {
                // Formatting for the TOTAL summary row
                if (data.row.index === rows.length - 1) {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.textColor = [0, 0, 0];
                    if (data.cell.raw === "TOTAL") {
                        data.cell.styles.halign = 'left';
                    }
                }
            }
        });

        const filename = `GST_Report_${startVal}_to_${endVal}.pdf`;
        doc.save(filename);
    }
</script>

<?php
renderFooter();
?>
