<?php
// Jagdamba Electrical - WIP Material Allocation Report (wip_report.php)
require_once 'sidebar.php';
renderHeader("WIP Allocation Report", "wip_report");
?>

<!-- Statistics counters -->
<div class="stats-grid" style="margin-bottom: 24px; grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Machines</span>
            <span class="stat-value" id="stats-total-mc" style="color: var(--primary);">0</span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-microchip"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">WIP Machines</span>
            <span class="stat-value" id="stats-wip-mc" style="color: var(--success);">0</span>
        </div>
        <div class="stat-icon-box green">
            <i class="fa-solid fa-industry"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Invoiced / Completed</span>
            <span class="stat-value" id="stats-invoiced-mc" style="color: var(--secondary);">0</span>
        </div>
        <div class="stat-icon-box blue">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Allocated Qty (Total)</span>
            <span class="stat-value" id="stats-allocated-qty">0.00</span>
        </div>
        <div class="stat-icon-box purple">
            <i class="fa-solid fa-weight-hanging"></i>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-body" style="padding: 16px 24px;">
        <div style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 250px;">
                <label class="form-label" for="wip-search" style="margin-bottom: 6px; display: block; font-size: 0.85rem; color: var(--text-muted);">Search Machine / Challan</label>
                <div class="topbar-search" style="display: flex; width: 100%;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px; top: 50%; transform: translateY(-50%);"></i>
                    <input type="text" id="wip-search" placeholder="Search by Machine Serial Number, Challan, or Part Number..." style="width: 100%; padding-left: 40px; height: 42px;" oninput="applyFilters()">
                </div>
            </div>

            <div style="width: 200px;">
                <label class="form-label" for="filter-status" style="margin-bottom: 6px; display: block; font-size: 0.85rem; color: var(--text-muted);">Status Filter</label>
                <select id="filter-status" class="form-control" onchange="applyFilters()" style="height: 42px; width: 100%; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 8px; padding: 0 12px; font-family: var(--font-body); font-weight: 500;">
                    <option value="pending">Pending (WIP) only</option>
                    <option value="invoiced">Invoiced</option>
                    <option value="completed">Completed</option>
                    <option value="all">All Statuses</option>
                </select>
            </div>

            <div style="width: 200px;">
                <label class="form-label" for="filter-party" style="margin-bottom: 6px; display: block; font-size: 0.85rem; color: var(--text-muted);">Filter by Party</label>
                <select id="filter-party" class="form-control" onchange="applyFilters()" style="height: 42px; width: 100%; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 8px; padding: 0 12px; font-family: var(--font-body); font-weight: 500;">
                    <option value="">-- All Parties --</option>
                </select>
            </div>

            <div>
                <button class="btn btn-secondary" onclick="resetFilters()" style="height: 42px;"><i class="fa-solid fa-rotate-left"></i> Clear Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Main Table Panel -->
<div class="panel">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <span class="panel-title"><i class="fa-solid fa-table-list" style="color: var(--primary);"></i> Machine Material Allocation Registry</span>
            <span class="panel-subtitle" id="record-count-label">0 Records</span>
        </div>
        <div>
            <button class="btn btn-secondary" onclick="exportToExcel()" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600;"><i class="fa-solid fa-file-excel" style="color: #16a34a;"></i> Export to Excel</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-container">
            <table class="custom-table" id="wip-table">
                <thead>
                    <tr id="table-headers">
                        <!-- Dynamic Columns -->
                    </tr>
                </thead>
                <tbody id="wip-grid-body">
                    <!-- Populated via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SheetJS Excel Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
    let reportData = [];
    let materialTypes = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        await loadParties();
        await loadWipReport();
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

    async function loadWipReport() {
        App.showLoading('Aggregating material allocations in memory...');
        try {
            const res = await App.api('wip_report');
            reportData = res.allocations || [];
            materialTypes = res.material_types || [];

            generateHeaders();
            applyFilters();
        } catch (err) {
            console.error(err);
            App.showToast(err.message || 'Failed to load WIP report.', 'error');
        } finally {
            App.hideLoading();
        }
    }

    const standardMaterialTypes = ['COIL', 'STAMPING', 'KIT', 'C CLASS', 'LEAD WIRE'];
    let extraMaterialTypes = [];
    let displayMaterialTypes = [];

    function generateHeaders() {
        extraMaterialTypes = materialTypes.filter(t => !standardMaterialTypes.includes(t));
        displayMaterialTypes = [...standardMaterialTypes, ...extraMaterialTypes];

        const headersTr = document.getElementById('table-headers');
        headersTr.innerHTML = `
            <th>Machine No</th>
            <th>Party</th>
            <th>Challan No</th>
            <th style="text-align: right;">Coil Qty</th>
            <th style="text-align: right;">Stamping Qty</th>
            <th style="text-align: right;">Kit Qty</th>
            <th style="text-align: right;">C Class Qty</th>
            <th style="text-align: right;">Lead Wire Qty</th>
            ${extraMaterialTypes.map(type => `<th style="text-align: right;">${escapeHtml(type)} Qty</th>`).join('')}
            <th style="text-align: center;">Status</th>
        `;
    }

    function applyFilters() {
        const query = document.getElementById('wip-search').value.toLowerCase().trim();
        const statusFilter = document.getElementById('filter-status').value;
        const partyVal = document.getElementById('filter-party').value;

        const filtered = reportData.filter(item => {
            // 1. Status Filter
            if (statusFilter !== 'all' && item.status !== statusFilter) {
                return false;
            }
            // 2. Party Filter
            if (partyVal && item.party_id != partyVal) {
                return false;
            }
            // 3. Search Query
            if (query) {
                return (
                    (item.machine_no && item.machine_no.toLowerCase().includes(query)) ||
                    (item.ch_no && item.ch_no.toLowerCase().includes(query)) ||
                    (item.part_no && item.part_no.toLowerCase().includes(query)) ||
                    (item.party_name && item.party_name.toLowerCase().includes(query))
                );
            }
            return true;
        });

        populateGrid(filtered);
        calculateStats(filtered);
    }

    function populateGrid(data) {
        const body = document.getElementById('wip-grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Machines`;

        if (data.length === 0) {
            body.innerHTML = `<tr><td colspan="${4 + displayMaterialTypes.length}" style="text-align:center; color:var(--text-muted); padding:32px;">No machine records match the selected filters.</td></tr>`;
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');

            let statusBadge = '';
            if (item.status === 'pending') {
                statusBadge = '<span class="badge" style="font-weight:700; background-color:rgba(16, 185, 129, 0.15); color:var(--success); padding:4px 8px; border-radius:4px; font-size:0.75rem;">WIP</span>';
            } else if (item.status === 'invoiced') {
                statusBadge = '<span class="badge" style="font-weight:700; background-color:rgba(245, 158, 11, 0.15); color:var(--warning); padding:4px 8px; border-radius:4px; font-size:0.75rem;">Invoiced</span>';
            } else if (item.status === 'completed') {
                statusBadge = '<span class="badge" style="font-weight:700; background-color:rgba(139, 92, 246, 0.15); color:var(--pending); padding:4px 8px; border-radius:4px; font-size:0.75rem;">Completed</span>';
            }

            const materialQtyCells = displayMaterialTypes.map(type => {
                const qty = item.materials[type] || 0;
                return `<td style="text-align: right; font-weight: 700; color: ${qty > 0 ? 'var(--primary)' : 'var(--text-muted)'};">${qty > 0 ? App.formatDecimal(qty) : '-'}</td>`;
            }).join('');

            tr.innerHTML = `
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.machine_no)}</strong></td>
                <td><span class="badge" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: var(--text-highlight);">${escapeHtml(item.party_name)}</span></td>
                <td><span style="font-family: monospace; font-size: 0.85rem;">${escapeHtml(item.ch_no)}</span></td>
                ${materialQtyCells}
                <td style="text-align: center;">${statusBadge}</td>
            `;
            body.appendChild(tr);
        });
    }

    function calculateStats(data) {
        let totalQty = 0;
        let wipCount = 0;
        let invoiceCount = 0;

        data.forEach(item => {
            if (item.status === 'pending') {
                wipCount++;
            } else {
                invoiceCount++;
            }

            Object.values(item.materials).forEach(qty => {
                totalQty += parseFloat(qty) || 0;
            });
        });

        document.getElementById('stats-total-mc').innerText = data.length;
        document.getElementById('stats-wip-mc').innerText = wipCount;
        document.getElementById('stats-invoiced-mc').innerText = invoiceCount;
        document.getElementById('stats-allocated-qty').innerText = App.formatDecimal(totalQty);
    }

    function resetFilters() {
        document.getElementById('wip-search').value = '';
        document.getElementById('filter-status').value = 'pending';
        document.getElementById('filter-party').value = '';
        applyFilters();
    }

    function exportToExcel() {
        const query = document.getElementById('wip-search').value.toLowerCase().trim();
        const statusFilter = document.getElementById('filter-status').value;
        const partyVal = document.getElementById('filter-party').value;

        const dataToExport = reportData.filter(item => {
            if (statusFilter !== 'all' && item.status !== statusFilter) return false;
            if (partyVal && item.party_id != partyVal) return false;
            if (query) {
                return (
                    (item.machine_no && item.machine_no.toLowerCase().includes(query)) ||
                    (item.ch_no && item.ch_no.toLowerCase().includes(query)) ||
                    (item.part_no && item.part_no.toLowerCase().includes(query)) ||
                    (item.party_name && item.party_name.toLowerCase().includes(query))
                );
            }
            return true;
        }).map(item => {
            const row = {
                'Machine No': item.machine_no,
                'Party': item.party_name,
                'Challan No': item.ch_no,
                'Coil Qty': parseFloat(item.materials['COIL'] || 0),
                'Stamping Qty': parseFloat(item.materials['STAMPING'] || 0),
                'Kit Qty': parseFloat(item.materials['KIT'] || 0),
                'C Class Qty': parseFloat(item.materials['C CLASS'] || 0),
                'Lead Wire Qty': parseFloat(item.materials['LEAD WIRE'] || 0),
            };

            extraMaterialTypes.forEach(type => {
                row[type + ' Qty'] = parseFloat(item.materials[type] || 0);
            });

            row['Status'] = item.status;
            return row;
        });

        if (dataToExport.length === 0) {
            App.showToast('No records to export.', 'warning');
            return;
        }

        const worksheet = XLSX.utils.json_to_sheet(dataToExport);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "WIP Allocation");

        // Autofit Columns
        const max_len = dataToExport.reduce((prev, toex) => {
            Object.keys(toex).forEach((k, i) => {
                const val = String(toex[k]);
                prev[i] = Math.max(prev[i] || 0, val.length, k.length);
            });
            return prev;
        }, []);
        worksheet['!cols'] = max_len.map(w => ({ wch: w + 2 }));

        XLSX.writeFile(workbook, 'WIP_Allocation_Report.xlsx');
        App.showToast("WIP allocation report exported successfully.");
    }

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
