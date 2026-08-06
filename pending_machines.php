<?php
// Jagdamba Electrical - Pending Machines Tracker (pending_machines.php)
require_once 'sidebar.php';
renderHeader("Pending & Available Machines", "pending_machines");
?>

<!-- Statistics counters -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Pending Machines</span>
            <span class="stat-value" id="stats-total-mc">0</span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-microchip"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Active Inward Challans</span>
            <span class="stat-value" id="stats-total-ch">0</span>
        </div>
        <div class="stat-icon-box blue">
            <i class="fa-solid fa-file-invoice"></i>
        </div>
    </div>

    <div class="stat-card" style="grid-column: span 2;">
        <div class="stat-info">
            <span class="stat-label">Availability Status</span>
            <span class="stat-value" style="color: var(--success); font-size: 1.5rem;"><i class="fa-solid fa-circle-check"></i> Ready for Invoicing</span>
        </div>
        <div class="stat-icon-box green">
            <i class="fa-solid fa-clipboard-check"></i>
        </div>
    </div>
</div>

<!-- Search Controls -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-body" style="padding: 16px 24px;">
        <div style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 250px;">
                <label class="form-label" for="machine-search" style="margin-bottom: 6px; display: block; font-size: 0.85rem; color: var(--text-muted);">Search Machine / Challan</label>
                <div class="topbar-search" style="display: flex; width: 100%;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px; top: 50%; transform: translateY(-50%);"></i>
                    <input type="text" id="machine-search" placeholder="Search by Machine Serial Number or Challan Reference..." style="width: 100%; padding-left: 40px; height: 42px;" oninput="applyFilters()">
                </div>
            </div>

            <div style="width: 200px;">
                <label class="form-label" for="filter-status" style="margin-bottom: 6px; display: block; font-size: 0.85rem; color: var(--text-muted);">Availability Status</label>
                <select id="filter-status" class="form-control" onchange="applyFilters()" style="height: 42px; width: 100%; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 8px; padding: 0 12px; font-family: var(--font-body); font-weight: 500;">
                    <option value="pending">Pending (Available)</option>
                    <option value="invoiced">Invoiced</option>
                    <option value="completed">Completed</option>
                    <option value="all">All Machines</option>
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

<!-- Split Grid View -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">

    <!-- Grouped Challans View (Left) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-folder-open" style="color: var(--secondary);"></i> Grouped by Inward Challan</span>
            <span class="panel-subtitle" id="grouped-count-label">0 Groups</span>
        </div>
        <div class="panel-body" id="grouped-container" style="display: flex; flex-direction: column; gap: 16px; max-height: 550px; overflow-y: auto;">
            <!-- Populated via JS -->
        </div>
    </div>

    <!-- Detailed Serial List View (Right) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Available Serial Registry</span>
            <span class="panel-subtitle" id="detailed-count-label">0 Machines</span>
        </div>
        <div class="panel-body">
            <div class="table-container" style="max-height: 500px;">
                <table class="custom-table" id="machines-detail-table">
                    <thead>
                        <tr>
                            <th>Machine Number</th>
                            <th>Inward Challan</th>
                            <th>Challan Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="detailed-grid-body">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let machinesCache = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        await loadParties();
        loadPendingMachinesData();
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

    // Query data from remaining_machines
    async function loadPendingMachinesData() {
        App.showLoading('Retrieving machine records...');
        try {
            // Fetch all records from remaining_machines table
            const res = await App.api('query', {
                sql: "SELECT * FROM remaining_machines ORDER BY ch_date ASC, ch_no ASC, machine_no ASC"
            });
            machinesCache = res.rows || [];
            applyFilters();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Apply filters
    function applyFilters() {
        const query = document.getElementById('machine-search').value.toLowerCase().trim();
        const statusFilter = document.getElementById('filter-status').value;
        const partyVal = document.getElementById('filter-party').value;

        // 1. Filter detailed serials
        const filteredDetailed = machinesCache.filter(item => {
            // Filter by Status
            if (statusFilter !== 'all' && item.status !== statusFilter) {
                return false;
            }
            // Filter by Party
            if (partyVal && item.party_id != partyVal) {
                return false;
            }
            // Filter by Search Query
            return (
                !query ||
                (item.machine_no && item.machine_no.toLowerCase().includes(query)) ||
                (item.ch_no && item.ch_no.toLowerCase().includes(query))
            );
        });
        populateDetailedGrid(filteredDetailed);

        // 2. Generate Grouped challans dynamically from filtered list
        const grouped = {};
        filteredDetailed.forEach(item => {
            const key = item.ch_no;
            if (!grouped[key]) {
                grouped[key] = {
                    ch_no: item.ch_no,
                    ch_date: item.ch_date,
                    machines: []
                };
            }
            grouped[key].machines.push(item.machine_no);
        });

        const groupedData = Object.values(grouped).map(g => ({
            ch_no: g.ch_no,
            ch_date: g.ch_date,
            machine_count: g.machines.length,
            machines_list: g.machines.join(', ')
        }));

        populateGroupedView(groupedData);

        // Update statistics counters based on currently selected status
        const totalSelectedStatus = machinesCache.filter(item => statusFilter === 'all' || item.status === statusFilter).length;
        const totalUniqueChallans = new Set(machinesCache.filter(item => statusFilter === 'all' || item.status === statusFilter).map(item => item.ch_no)).size;

        document.getElementById('stats-total-mc').innerText = totalSelectedStatus;
        document.getElementById('stats-total-ch').innerText = totalUniqueChallans;

        // Update stats card label dynamically based on selected status
        const statsLabel = document.querySelector('.stat-card:nth-child(1) .stat-label');
        if (statsLabel) {
            if (statusFilter === 'pending') {
                statsLabel.innerText = "Total Pending Machines";
            } else if (statusFilter === 'invoiced') {
                statsLabel.innerText = "Total Invoiced Machines";
            } else if (statusFilter === 'completed') {
                statsLabel.innerText = "Total Completed Machines";
            } else {
                statsLabel.innerText = "Total Registered Machines";
            }
        }
    }

    // Render left panel (Challan cards)
    function populateGroupedView(data) {
        const container = document.getElementById('grouped-container');
        container.innerHTML = '';

        document.getElementById('grouped-count-label').innerText = `${data.length} Groups`;

        if (data.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 32px;">No grouped challan profiles found.</div>';
            return;
        }

        data.forEach(item => {
            const card = document.createElement('div');
            // Glassmorphic small inner panel
            card.style.background = 'rgba(255,255,255,0.01)';
            card.style.border = '1px solid var(--border-color)';
            card.style.borderRadius = '8px';
            card.style.padding = '14px 18px';
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.gap = '8px';

            let countLabel = '';
            const statusFilter = document.getElementById('filter-status').value;
            if (statusFilter === 'pending') {
                countLabel = `<span class="badge" style="font-weight:700; background-color:rgba(16, 185, 129, 0.15); color:var(--success); padding:2px 8px; border-radius:12px; font-size:0.75rem;">${item.machine_count} Available</span>`;
            } else if (statusFilter === 'invoiced') {
                countLabel = `<span class="badge" style="font-weight:700; background-color:rgba(245, 158, 11, 0.15); color:var(--warning); padding:2px 8px; border-radius:12px; font-size:0.75rem;">${item.machine_count} Invoiced</span>`;
            } else if (statusFilter === 'completed') {
                countLabel = `<span class="badge" style="font-weight:700; background-color:rgba(139, 92, 246, 0.15); color:var(--pending); padding:2px 8px; border-radius:12px; font-size:0.75rem;">${item.machine_count} Completed</span>`;
            } else {
                countLabel = `<span class="badge" style="font-weight:700; background-color:rgba(255, 255, 255, 0.08); color:var(--text-muted); padding:2px 8px; border-radius:12px; font-size:0.75rem;">${item.machine_count} Total</span>`;
            }

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:700; color:var(--text-highlight);"><i class="fa-solid fa-receipt" style="color:var(--secondary); margin-right:6px;"></i> Challan: ${escapeHtml(item.ch_no)}</span>
                    ${countLabel}
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted);">Receipt Date: ${App.formatDate(item.ch_date)}</div>
                <div style="font-family:monospace; font-size:0.8rem; background:rgba(0,0,0,0.15); padding:8px; border-radius:4px; line-height:1.4; color:#fff; word-break:break-all;">
                    ${escapeHtml(item.machines_list)}
                </div>
            `;
            container.appendChild(card);
        });
    }

    // Render right panel (Detailed Table)
    function populateDetailedGrid(data) {
        const body = document.getElementById('detailed-grid-body');
        body.innerHTML = '';

        document.getElementById('detailed-count-label').innerText = `${data.length} Machines`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:32px;">No matching machine serials found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');

            let statusBadge = '';
            if (item.status === 'pending') {
                statusBadge = '<span class="badge" style="font-weight:700; background-color:rgba(16, 185, 129, 0.15); color:var(--success); padding:4px 8px; border-radius:4px; font-size:0.75rem;">Available</span>';
            } else if (item.status === 'invoiced') {
                statusBadge = '<span class="badge" style="font-weight:700; background-color:rgba(245, 158, 11, 0.15); color:var(--warning); padding:4px 8px; border-radius:4px; font-size:0.75rem;">Invoiced</span>';
            } else if (item.status === 'completed') {
                statusBadge = '<span class="badge" style="font-weight:700; background-color:rgba(139, 92, 246, 0.15); color:var(--pending); padding:4px 8px; border-radius:4px; font-size:0.75rem;">Completed</span>';
            }

            tr.innerHTML = `
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.machine_no)}</strong></td>
                <td><span style="font-family: monospace; font-size:0.85rem;">${escapeHtml(item.ch_no)}</span></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(item.ch_date)}</td>
                <td>${statusBadge}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Reset Filters
    function resetFilters() {
        document.getElementById('machine-search').value = '';
        document.getElementById('filter-status').value = 'pending';
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
</script>

<?php
renderFooter();
?>
