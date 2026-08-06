<?php
// Jagdamba Electrical - Remaining Stock Report (remaining_stock.php)
require_once 'sidebar.php';
renderHeader("Remaining Stock Tracker", "remaining_stock");
?>

<!-- Tab Selector -->
<div class="modal-tabs" style="margin-bottom: 24px;">
    <button class="modal-tab-btn active" id="tab-master" onclick="switchStockTab('master')"><i class="fa-solid fa-boxes-stacked"></i> Master Stock Balances</button>
    <button class="modal-tab-btn" id="tab-fifo" onclick="switchStockTab('fifo')"><i class="fa-solid fa-list-ol"></i> FIFO Inward Receipt Breakdown</button>
</div>

<!-- Search Controls -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-body" style="padding: 16px 24px;">
        <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
            <div class="topbar-search" style="flex: 2; min-width: 250px;">
                <i class="fa-solid fa-magnifying-glass" style="left: 14px; top: 50%; transform: translateY(-50%);"></i>
                <input type="text" id="stock-search" placeholder="Filter by Material Code, Description, Category, or Challan..." style="width: 100%; padding-left: 40px; height: 42px;" oninput="applyFilters()">
            </div>

            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                <select id="filter-party" class="form-control" onchange="applyFilters()" style="height: 42px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 8px; padding: 0 12px; font-family: var(--font-body); font-weight: 500;">
                    <option value="">-- All Parties --</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <button class="btn btn-secondary" onclick="resetFilters()" style="height: 42px; display: inline-flex; align-items: center; gap: 6px;"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                <button class="btn btn-success" onclick="exportToExcel()" style="height: 42px; background-color: var(--success); color: white; border: none; font-weight: 600; padding: 0 16px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;"><i class="fa-solid fa-file-excel"></i> Export Excel</button>
                <button class="btn btn-danger" onclick="exportToPDF()" style="height: 42px; background-color: var(--error); color: white; border: none; font-weight: 600; padding: 0 16px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;"><i class="fa-solid fa-file-pdf"></i> Export PDF</button>
            </div>
        </div>
    </div>
</div>

<!-- Master Stock Balances Grid -->
<div class="panel" id="panel-master-view">
    <div class="panel-header">
        <span class="panel-title"><i class="fa-solid fa-warehouse" style="color: var(--secondary);"></i> Material Master Inventory Totals</span>
        <span class="panel-subtitle" id="master-count-label">0 Materials</span>
    </div>
    <div class="panel-body">
        <div class="table-container">
            <table class="custom-table" id="master-table">
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Description</th>
                        <th>Category / Head</th>
                        <th style="text-align: right;">Current Stock Balance</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody id="master-grid-body">
                    <!-- Populated dynamically -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- FIFO Breakdown Grid -->
<div class="panel" id="panel-fifo-view" style="display: none;">
    <div class="panel-header">
        <span class="panel-title"><i class="fa-solid fa-hourglass-half" style="color: var(--primary);"></i> Depleting Inward Challans (FIFO Balance)</span>
        <span class="panel-subtitle" id="fifo-count-label">0 Active Receipts</span>
    </div>
    <div class="panel-body">
        <div class="table-container">
            <table class="custom-table" id="fifo-table">
                <thead>
                    <tr>
                        <th>Challan No</th>
                        <th>Challan Date</th>
                        <th>Material Code</th>
                        <th>Material Type</th>
                        <th style="text-align: right;">Original Inward</th>
                        <th style="text-align: right; color: var(--primary);">Current Available</th>
                        <th>Unit</th>
                        <th style="text-align: right;">Depletion %</th>
                        <th style="text-align: right;">Age (Days)</th>
                    </tr>
                </thead>
                <tbody id="fifo-grid-body">
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
    let activeStockTab = 'master'; // 'master' or 'fifo'
    let masterStockData = [];
    let fifoStockData = [];
    let filteredMasterData = [];
    let filteredFifoData = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        await loadParties();
        loadStockData();
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

    // Switch tabs
    function switchStockTab(tab) {
        activeStockTab = tab;
        document.getElementById('tab-master').classList.toggle('active', tab === 'master');
        document.getElementById('tab-fifo').classList.toggle('active', tab === 'fifo');

        document.getElementById('panel-master-view').style.display = tab === 'master' ? 'block' : 'none';
        document.getElementById('panel-fifo-view').style.display = tab === 'fifo' ? 'block' : 'none';

        document.getElementById('stock-search').value = '';
        applyFilters();
    }

    // Load both datasets from database
    async function loadStockData() {
        App.showLoading('Querying stock levels...');
        try {
            // 1. Fetch Master Inventory
            const resMaster = await App.api('list', { table: 'raw_material' });
            masterStockData = resMaster.rows || [];

            // 2. Fetch FIFO records where balance is active
            const resFifo = await App.api('query', {
                sql: "SELECT * FROM inward_transaction WHERE bal_qty > 0 ORDER BY ch_date ASC, id ASC"
            });
            fifoStockData = resFifo.rows || [];

            applyFilters();
        } catch (err) {
            console.error("Stock fetch error:", err);
        } finally {
            App.hideLoading();
        }
    }

    // Apply filter locally
    function applyFilters() {
        const query = document.getElementById('stock-search').value.toLowerCase().trim();
        const partyVal = document.getElementById('filter-party').value;

        if (activeStockTab === 'master') {
            const partyM_Codes = new Set(fifoStockData.filter(x => !partyVal || x.party_id == partyVal).map(x => x.m_code));

            const filtered = masterStockData.filter(item => {
                if (partyVal && !partyM_Codes.has(item.m_code)) return false;
                return (
                    !query ||
                    (item.m_code && item.m_code.toLowerCase().includes(query)) ||
                    (item.m_description && item.m_description.toLowerCase().includes(query)) ||
                    (item.head && item.head.toLowerCase().includes(query))
                );
            });
            filteredMasterData = filtered;
            populateMasterGrid(filtered);
        } else {
            const filtered = fifoStockData.filter(item => {
                if (partyVal && item.party_id != partyVal) return false;
                return (
                    !query ||
                    (item.ch_no && item.ch_no.toLowerCase().includes(query)) ||
                    (item.m_code && item.m_code.toLowerCase().includes(query)) ||
                    (item.m_description && item.m_description.toLowerCase().includes(query)) ||
                    (item.material_type && item.material_type.toLowerCase().includes(query))
                );
            });
            filteredFifoData = filtered;
            populateFifoGrid(filtered);
        }
    }

    // Populate Master Grid
    function populateMasterGrid(data) {
        const body = document.getElementById('master-grid-body');
        body.innerHTML = '';

        document.getElementById('master-count-label').innerText = `${data.length} Materials`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:32px;">No material stock levels found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');

            // Highlight low stock (under 10 units as demo)
            const lowStockStyle = parseFloat(item.bal_qty) < 10 ? 'style="color: var(--error);"' : '';

            tr.innerHTML = `
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.m_code)}</strong></td>
                <td style="max-width: 300px; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(item.m_description || '')}">${escapeHtml(item.m_description || '-')}</td>
                <td><span class="badge badge-pending">${escapeHtml(item.head || '-')}</span></td>
                <td style="text-align: right; font-weight:700;" ${lowStockStyle}>${App.formatDecimal(item.bal_qty)}</td>
                <td style="color:var(--text-muted);">${escapeHtml(item.unit || '')}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Populate FIFO Breakdown Grid
    function populateFifoGrid(data) {
        const body = document.getElementById('fifo-grid-body');
        body.innerHTML = '';

        document.getElementById('fifo-count-label').innerText = `${data.length} Active Receipts`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:32px;">No active FIFO inward balances available.</td></tr>';
            return;
        }

        const today = new Date();

        data.forEach(item => {
            const inQty = parseFloat(item.in_qty || 0);
            const balQty = parseFloat(item.bal_qty || 0);
            const depletionPercent = inQty > 0 ? Math.round(((inQty - balQty) / inQty) * 100) : 0;

            // Calculate Age in Days
            const challanDate = new Date(item.ch_date);
            const diffTime = Math.abs(today - challanDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.ch_no)}</strong></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(item.ch_date)}</td>
                <td><span style="font-family: monospace; font-size:0.85rem; background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px; border:1px solid var(--border-color);">${escapeHtml(item.m_code)}</span></td>
                <td><span class="badge badge-pending">${escapeHtml(item.material_type || '-')}</span></td>
                <td style="text-align: right;">${App.formatDecimal(inQty)}</td>
                <td style="text-align: right; font-weight:700; color: var(--primary);">${App.formatDecimal(balQty)}</td>
                <td style="color:var(--text-muted);">${escapeHtml(item.unit || '')}</td>
                <td style="text-align: right; font-weight:600;">
                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:8px;">
                        <span>${depletionPercent}%</span>
                        <div style="width:50px; height:6px; background:rgba(255,255,255,0.1); border-radius:3px; overflow:hidden;">
                            <div style="width:${depletionPercent}%; height:100%; background:var(--primary);"></div>
                        </div>
                    </div>
                </td>
                <td style="text-align: right; color:var(--text-muted);">${diffDays} Days</td>
            `;
            body.appendChild(tr);
        });
    }

    // Reset Filters
    function resetFilters() {
        document.getElementById('stock-search').value = '';
        document.getElementById('filter-party').value = '';
        applyFilters();
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

    // Export current filtered rows to Excel
    function exportToExcel() {
        let dataToExport = [];
        let filename = "";

        if (activeStockTab === 'master') {
            if (!filteredMasterData || filteredMasterData.length === 0) {
                alert("No records found to export.");
                return;
            }

            // Format rows for Excel
            dataToExport = filteredMasterData.map(item => ({
                "Material Code": item.m_code,
                "Description": item.m_description || '-',
                "Category / Head": item.head || '-',
                "Current Stock Balance": parseFloat(item.bal_qty || 0),
                "Unit": item.unit || '-'
            }));
            filename = "Master_Stock_Balances.xlsx";
        } else {
            if (!filteredFifoData || filteredFifoData.length === 0) {
                alert("No records found to export.");
                return;
            }

            // Format rows for Excel
            dataToExport = filteredFifoData.map(item => ({
                "Challan No": item.ch_no,
                "Challan Date": App.formatDate ? App.formatDate(item.ch_date) : item.ch_date,
                "Material Code": item.m_code,
                "Material Type": item.material_type || '-',
                "Original Inward": parseFloat(item.in_qty || 0),
                "Current Available": parseFloat(item.bal_qty || 0),
                "Unit": item.unit || '-',
                "Depletion %": Math.round(((parseFloat(item.in_qty || 0) - parseFloat(item.bal_qty || 0)) / parseFloat(item.in_qty || 1)) * 100) + "%"
            }));
            filename = "FIFO_Stock_Breakdown.xlsx";
        }

        // Export using SheetJS (XLSX)
        const worksheet = XLSX.utils.json_to_sheet(dataToExport);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Stock Data");

        // Set column widths auto-fit
        const max_width = dataToExport.reduce((w, r) => Math.max(w, Object.keys(r).reduce((max, key) => Math.max(max, String(r[key]).length), 10)), 15);
        worksheet["!cols"] = Object.keys(dataToExport[0]).map(() => ({ wch: max_width }));

        XLSX.writeFile(workbook, filename);
    }

    // Export current filtered rows to PDF
    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');

        // Header Title
        doc.setFont("Helvetica", "bold");
        doc.setFontSize(16);
        doc.text("JAGDAMBA ELECTRICAL", 14, 20);

        doc.setFont("Helvetica", "normal");
        doc.setFontSize(10);

        let title = "";
        let columns = [];
        let rows = [];
        let filename = "";

        const partySelect = document.getElementById('filter-party');
        const partyText = partySelect.options[partySelect.selectedIndex].text;
        const partyFilterInfo = partySelect.value ? ` | Party: ${partyText}` : "";

        if (activeStockTab === 'master') {
            if (!filteredMasterData || filteredMasterData.length === 0) {
                alert("No records found to export.");
                return;
            }

            title = "Master Stock Balances Report" + partyFilterInfo;
            filename = "Master_Stock_Balances.pdf";

            columns = [
                { header: "Material Code", dataKey: "m_code" },
                { header: "Description", dataKey: "m_description" },
                { header: "Category / Head", dataKey: "head" },
                { header: "Current Balance", dataKey: "bal_qty" },
                { header: "Unit", dataKey: "unit" }
            ];

            rows = filteredMasterData.map(item => ({
                m_code: item.m_code,
                m_description: item.m_description || '-',
                head: item.head || '-',
                bal_qty: parseFloat(item.bal_qty || 0).toFixed(2),
                unit: item.unit || '-'
            }));
        } else {
            if (!filteredFifoData || filteredFifoData.length === 0) {
                alert("No records found to export.");
                return;
            }

            title = "FIFO Stock Breakdown Report" + partyFilterInfo;
            filename = "FIFO_Stock_Breakdown.pdf";

            columns = [
                { header: "Challan No", dataKey: "ch_no" },
                { header: "Date", dataKey: "ch_date" },
                { header: "Material Code", dataKey: "m_code" },
                { header: "Type", dataKey: "material_type" },
                { header: "Original", dataKey: "in_qty" },
                { header: "Available", dataKey: "bal_qty" },
                { header: "Unit", dataKey: "unit" }
            ];

            rows = filteredFifoData.map(item => ({
                ch_no: item.ch_no,
                ch_date: App.formatDate ? App.formatDate(item.ch_date) : item.ch_date,
                m_code: item.m_code,
                material_type: item.material_type || '-',
                in_qty: parseFloat(item.in_qty || 0).toFixed(2),
                bal_qty: parseFloat(item.bal_qty || 0).toFixed(2),
                unit: item.unit || '-'
            }));
        }

        doc.text(title, 14, 26);
        doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 31);

        doc.autoTable({
            columns: columns,
            body: rows,
            startY: 36,
            theme: 'grid',
            styles: { fontSize: 8.5 },
            headStyles: { fillColor: [37, 99, 235], textColor: [255, 255, 255] }
        });

        doc.save(filename);
    }
</script>

<?php
renderFooter();
?>
