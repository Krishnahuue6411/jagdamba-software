<?php
// Jagdamba Electrical - Invoiced Machines Registry (invoiced_machines.php)
require_once 'sidebar.php';
renderHeader("Invoiced Machines Registry", "invoiced_machines");
?>

<!-- Statistics counters -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Invoiced Machines</span>
            <span class="stat-value" id="stats-total-mc">0</span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-barcode"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Unique Invoices Linked</span>
            <span class="stat-value" id="stats-total-inv">0</span>
        </div>
        <div class="stat-icon-box blue">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
    </div>

    <div class="stat-card" style="grid-column: span 2;">
        <div class="stat-info">
            <span class="stat-label">Lifecycle Status</span>
            <span class="stat-value" style="color: var(--warning); font-size: 1.5rem;"><i class="fa-solid fa-circle-check"></i> Dispatched / Invoiced</span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-truck-ramp-box"></i>
        </div>
    </div>
</div>

<!-- Search & Date Filter Panel -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header" style="background-color: rgba(255, 255, 255, 0.01); border-bottom: 1px solid var(--border-color);">
        <span class="panel-title"><i class="fa-solid fa-filter" style="color: var(--primary);"></i> Filter Dispatched Serial Mapping</span>
        <span class="panel-subtitle">Narrow down mapped machines by keyword or invoice date range</span>
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
                    <input type="text" id="filter-search" placeholder="Search by Machine Serial, Invoice No, Customer Name..." style="width: 100%; padding-left: 40px; height: 42px;" oninput="applyFilters()">
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
        <span class="panel-title"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Invoiced Machine Mapping Registry</span>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span class="panel-subtitle" id="record-count-label" style="font-size: 0.85rem; font-weight: 500; margin: 0;">0 Machines</span>
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
            <table class="custom-table" id="invoiced-machines-table">
                <thead>
                    <tr>
                        <th>Machine Number</th>
                        <th>Invoice Number</th>
                        <th>Invoice Date</th>
                        <th>Billed To (Customer Name)</th>
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
    let registryCache = [];
    let filteredRegistry = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        await loadParties();
        loadInvoicedMachinesData();
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

    // Query mapping from database
    async function loadInvoicedMachinesData() {
        App.showLoading('Retrieving invoiced machine mappings...');
        try {
            const res = await App.api('query', {
                sql: `
                    SELECT
                        im.machine_no,
                        im.inv_no,
                        ti.inv_date,
                        ti.party_id,
                        p.party_name
                    FROM invoice_machines im
                    LEFT JOIN (
                        SELECT
                            TRIM(inv_no) AS inv_no,
                            MIN(inv_date) AS inv_date,
                            MIN(party_id) AS party_id
                        FROM tax_invoice
                        GROUP BY TRIM(inv_no)
                    ) ti ON TRIM(im.inv_no) = ti.inv_no
                    LEFT JOIN parties p ON ti.party_id = p.id
                    ORDER BY ti.inv_date DESC, im.machine_no ASC
                `
            });
            registryCache = (res.rows || []).map(row => {
                row.party_name = row.party_name || 'CG Power and Industrial Solution Ltd';
                return row;
            });
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

        filteredRegistry = registryCache.filter(item => {
            // 1. Party filter
            if (partyVal && item.party_id != partyVal) return false;

            // 2. Date filter
            if (item.inv_date) {
                const invDate = new Date(item.inv_date);
                invDate.setHours(0,0,0,0);
                if (startDate && invDate < startDate) return false;
                if (endDate && invDate > endDate) return false;
            } else if (startDate || endDate) {
                // If date criteria are set but record has no invoice date, exclude
                return false;
            }

            // 3. Keyword filter
            if (searchVal) {
                const mNo = (item.machine_no || '').toLowerCase();
                const iNo = (item.inv_no || '').toLowerCase();
                const party = (item.party_name || '').toLowerCase();
                return mNo.includes(searchVal) || iNo.includes(searchVal) || party.includes(searchVal);
            }

            return true;
        });

        populateGrid(filteredRegistry);
        updateStatistics(filteredRegistry);
    }

    // Populate Detailed Grid rows
    function populateGrid(data) {
        const body = document.getElementById('detailed-grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Machines`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:32px;">No invoiced machines match the search criteria.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.title = "View Invoice PDF Preview";
            tr.style.cursor = 'pointer';
            tr.onclick = () => {
                window.open(`print.php?inv_no=${encodeURIComponent(item.inv_no)}`, '_blank');
            };

            tr.innerHTML = `
                <td><strong style="color:var(--text-highlight);"><i class="fa-solid fa-barcode" style="color:var(--warning); margin-right:6px;"></i> ${escapeHtml(item.machine_no)}</strong></td>
                <td><span style="font-family: monospace; font-size:0.85rem; color:var(--secondary); font-weight:700;">${escapeHtml(item.inv_no)}</span></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${item.inv_date ? App.formatDate(item.inv_date) : 'N/A'}</td>
                <td style="font-size:0.9rem; font-weight:500;">${escapeHtml(item.party_name || 'N/A')}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Update Top Stat cards
    function updateStatistics(data) {
        document.getElementById('stats-total-mc').innerText = data.length;

        // Count unique invoice numbers
        const uniqueInvoices = new Set(data.map(item => item.inv_no).filter(Boolean));
        document.getElementById('stats-total-inv').innerText = uniqueInvoices.size;
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
        if (filteredRegistry.length === 0) {
            alert("No records found to export.");
            return;
        }

        const data = filteredRegistry.map(item => ({
            "Machine Number": item.machine_no,
            "Invoice Number": item.inv_no,
            "Invoice Date": item.inv_date || 'N/A',
            "Customer Name": item.party_name || 'N/A'
        }));

        const worksheet = XLSX.utils.json_to_sheet(data);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Invoiced Machines");

        // Set column widths
        worksheet['!cols'] = [
            { wch: 20 }, // Machine
            { wch: 18 }, // Invoice
            { wch: 15 }, // Date
            { wch: 30 }  // Customer
        ];

        const startVal = document.getElementById('filter-start-date').value || 'all';
        const endVal = document.getElementById('filter-end-date').value || 'all';
        XLSX.writeFile(workbook, `Invoiced_Machines_Report_${startVal}_to_${endVal}.xlsx`);
    }

    // Export current filtered rows to PDF
    function exportToPDF() {
        if (filteredRegistry.length === 0) {
            alert("No records found to export.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');

        doc.setFont("Helvetica", "bold");
        doc.setFontSize(16);
        doc.text("JAGDAMBA ELECTRICAL", 14, 20);

        doc.setFont("Helvetica", "normal");
        doc.setFontSize(10);
        doc.text("Invoiced Machine Mapping Registry", 14, 26);

        const startVal = document.getElementById('filter-start-date').value || 'Beginning';
        const endVal = document.getElementById('filter-end-date').value || 'Present';
        doc.text(`Period: ${startVal} to ${endVal}`, 14, 31);

        const columns = [
            { header: "Machine Number", dataKey: "machine_no" },
            { header: "Invoice Number", dataKey: "inv_no" },
            { header: "Invoice Date", dataKey: "inv_date" },
            { header: "Customer Name", dataKey: "party_name" }
        ];

        const rows = filteredRegistry.map(item => ({
            machine_no: item.machine_no,
            inv_no: item.inv_no,
            inv_date: item.inv_date ? (App.formatDate ? App.formatDate(item.inv_date) : item.inv_date) : 'N/A',
            party_name: item.party_name || 'N/A'
        }));

        doc.autoTable({
            columns: columns,
            body: rows,
            startY: 37,
            theme: 'striped',
            headStyles: {
                fillColor: [37, 99, 235], // Sapphire Blue
                textColor: [255, 255, 255],
                fontStyle: 'bold'
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245]
            }
        });

        doc.save(`Invoiced_Machines_Report_${startVal}_to_${endVal}.pdf`);
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
