<?php
// Sahara Electrical - NFP Outward Challan Entry (nfp_outward.php)
require_once 'sidebar.php';

// Auto-create table if not exists
$pdo = getSidebarConnection();
if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `nfp_outward` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `challan_no` VARCHAR(50) NOT NULL,
            `challan_date` DATE NOT NULL,
            `party_id` INT NULL,
            `vehicle_no` VARCHAR(50),
            `sr_no` INT,
            `wound_code` VARCHAR(50),
            `description` TEXT,
            `m_code` VARCHAR(50),
            `hsn_code` VARCHAR(50) DEFAULT '9988',
            `qty` DECIMAL(10,2) NOT NULL,
            `uom` VARCHAR(20) DEFAULT 'NOS',
            `rate` DECIMAL(10,2) DEFAULT 0.00,
            `amount` DECIMAL(10,2) DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`challan_no`),
            INDEX (`m_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) { /* Table may already exist */ }
}

renderHeader("NFP Outward Challan", "nfp_outward");
?>

<div class="module-container" style="grid-template-columns: 320px 1fr; gap: 16px;">
    <!-- Left: Convertible Inward Challans -->
    <div class="panel" style="max-height: 85vh; overflow-y: auto;">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-file-invoice" style="color: var(--secondary);"></i> Convertible Inwards</span>
            <span class="panel-subtitle">Select an inward challan to convert</span>
        </div>
        <div class="panel-body" style="padding: 12px;">
            <div class="topbar-search" style="width: 100%; margin-bottom: 12px; display: flex;">
                <i class="fa-solid fa-magnifying-glass" style="left: 10px;"></i>
                <input type="text" id="inward-search" placeholder="Search Inward..." oninput="filterInwards()" style="font-size: 0.8rem; padding-left: 32px; width: 100%;">
            </div>
            <div id="inward-convertible-list" style="display: flex; flex-direction: column; gap: 10px;">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Right Container -->
    <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0; width: 100%;">
        <!-- Top: Challan Header Info -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="fa-solid fa-file-arrow-up" style="color: var(--primary);"></i> NFP Outward Challan</span>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="button" class="btn btn-secondary btn-icon" onclick="resetAll()" title="New Challan">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="panel-body">
                <!-- Challan Header Form -->
                <div class="form-grid" style="grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="challan_no">Challan No <span style="color:var(--error);">*</span></label>
                        <input type="text" id="challan_no" class="form-control" placeholder="e.g. NFP-001" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="challan_date">Date <span style="color:var(--error);">*</span></label>
                        <input type="date" id="challan_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="party_id">Plant (Party) <span style="color:var(--error);">*</span></label>
                        <select id="party_id" class="form-control" style="background-color: var(--bg-main);">
                            <option value="">-- Select Party --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="vehicle_no">Vehicle No</label>
                        <input type="text" id="vehicle_no" class="form-control" placeholder="e.g. MH-17-AB-1234">
                    </div>
                </div>

                <!-- Line Items Entry -->
                <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; margin-bottom: 16px;">
                    <div style="background: rgba(255,255,255,0.04); padding: 10px 14px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-primary);"><i class="fa-solid fa-list-ol" style="color: var(--secondary); margin-right: 6px;"></i> Line Items</span>
                        <button type="button" class="btn btn-primary" onclick="addItemRow()" style="font-size:0.75rem; padding: 4px 12px;">
                            <i class="fa-solid fa-plus"></i> Add Item
                        </button>
                    </div>
                    <table class="data-table" style="width: 100%; margin: 0;">
                        <thead>
                            <tr>
                                <th style="width:5%;">Sr No</th>
                                <th style="width:15%;">Wound Code</th>
                                <th style="width:25%;">Description</th>
                                <th style="width:12%;">Material Code</th>
                                <th style="width:10%;">HSN Code</th>
                                <th style="width:7%;">Qty</th>
                                <th style="width:7%;">UOM</th>
                                <th style="width:7%;">Rate</th>
                                <th style="width:8%;">Amount</th>
                                <th style="width:4%;">Del</th>
                            </tr>
                        </thead>
                        <tbody id="items-body">
                            <!-- Rows added dynamically -->
                        </tbody>
                    </table>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-primary" onclick="saveChallan()" style="flex: 1;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Challan
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetAll()" style="flex: 0.4;">
                        <i class="fa-solid fa-xmark"></i> Clear All
                    </button>
                </div>
            </div>
        </div>

        <!-- Bottom: Saved Challans List -->
        <div class="panel" style="margin-top: 16px;">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="fa-solid fa-table-list" style="color: var(--secondary);"></i>
                    Saved NFP Challans
                    <span id="challan-count" style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400; margin-left: 8px;"></span>
                </span>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <div class="topbar-search" style="width: 220px;">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="filter-search" placeholder="Search challans..." oninput="applyFilter()">
                    </div>
                </div>
            </div>
            <div class="panel-body" style="padding: 0; overflow: auto; max-height: 50vh;">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Challan No</th>
                            <th>Date</th>
                            <th>Party</th>
                            <th>Vehicle No</th>
                            <th style="text-align:center;">Items</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="challan-list-body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let partiesList = [];
    let allRecords = [];
    let itemRowCounter = 0;
    let convertibleInwards = [];

    document.addEventListener('DOMContentLoaded', async () => {
        document.getElementById('challan_date').value = App.formatDate(new Date());
        await loadParties();
        addItemRow(); // Start with one empty row
        loadSavedChallans();
        loadConvertibleInwards();
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

    // --- Item Rows ---
    function addItemRow() {
        itemRowCounter++;
        const body = document.getElementById('items-body');
        const tr = document.createElement('tr');
        tr.id = `item-row-${itemRowCounter}`;
        tr.innerHTML = `
            <td style="text-align:center; font-weight:bold; color:var(--secondary);">${body.rows.length + 1}</td>
            <td><input type="text" class="form-control item-wound-code" placeholder="Wound code" style="font-size:0.8rem; padding:4px 6px;"></td>
            <td><input type="text" class="form-control item-description" placeholder="Description" style="font-size:0.8rem; padding:4px 6px;"></td>
            <td><input type="text" class="form-control item-m-code" placeholder="Material code" style="font-size:0.8rem; padding:4px 6px;"></td>
            <td><input type="text" class="form-control item-hsn" value="9988" style="font-size:0.8rem; padding:4px 6px; text-align:center;"></td>
            <td><input type="number" class="form-control item-qty" value="0" min="0" step="0.01" style="font-size:0.8rem; padding:4px 6px; text-align:right;"></td>
            <td><input type="text" class="form-control item-uom" value="NOS" style="font-size:0.8rem; padding:4px 6px; text-align:center;"></td>
            <td><input type="number" class="form-control item-rate" value="0.00" min="0" step="0.01" style="font-size:0.8rem; padding:4px 6px; text-align:right;"></td>
            <td style="text-align:right; font-weight:bold; font-size:0.8rem;">0.00</td>
            <td style="text-align:center;">
                <button type="button" class="btn" style="background:var(--error); color:white; border:none; padding:3px 6px; border-radius:4px; font-size:0.65rem; cursor:pointer;" onclick="removeItemRow('item-row-${itemRowCounter}')" title="Remove">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        body.appendChild(tr);
        recalcSrNos();
    }

    function removeItemRow(rowId) {
        const row = document.getElementById(rowId);
        if (row) row.remove();
        recalcSrNos();
        renderConvertibleInwards();
    }

    function recalcSrNos() {
        const rows = document.getElementById('items-body').rows;
        for (let i = 0; i < rows.length; i++) {
            rows[i].cells[0].innerText = i + 1;
        }
    }

    function getLineItems() {
        const rows = document.getElementById('items-body').rows;
        const items = [];
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const qty = parseFloat(row.querySelector('.item-qty')?.value || 0);
            const rate = parseFloat(row.querySelector('.item-rate')?.value || 0);
            const inwardChallanId = row.dataset.inwardChallanId ? parseInt(row.dataset.inwardChallanId) : null;
            items.push({
                sr_no: i + 1,
                wound_code: row.querySelector('.item-wound-code')?.value?.trim() || '',
                description: row.querySelector('.item-description')?.value?.trim() || '',
                m_code: row.querySelector('.item-m-code')?.value?.trim() || '',
                hsn_code: row.querySelector('.item-hsn')?.value?.trim() || '9988',
                qty: qty,
                uom: row.querySelector('.item-uom')?.value?.trim() || 'NOS',
                rate: rate,
                amount: parseFloat((qty * rate).toFixed(2)),
                inward_challan_id: inwardChallanId
            });
        }
        return items;
    }

    // --- Save ---
    async function saveChallan() {
        const challan_no = document.getElementById('challan_no').value.trim();
        const challan_date = document.getElementById('challan_date').value;
        const party_id = parseInt(document.getElementById('party_id').value) || null;
        const vehicle_no = document.getElementById('vehicle_no').value.trim();

        if (!challan_no || !challan_date) {
            App.showToast('Please fill Challan No and Date.', 'error');
            return;
        }

        const items = getLineItems();
        if (items.length === 0 || !items[0].wound_code) {
            App.showToast('Please add at least one item with a wound code.', 'error');
            return;
        }

        App.showLoading('Saving NFP Outward Challan...');
        try {
            const res = await App.api('save_nfp_challan', {
                challan_no,
                challan_date,
                party_id,
                vehicle_no,
                rows: items
            });

            if (res.ok) {
                App.showToast('NFP Outward Challan saved!', 'success');
                resetAll();
                loadSavedChallans();
                loadConvertibleInwards();
            } else {
                App.showToast(res.error || 'Failed to save challan.', 'error');
            }
        } catch (err) {
            console.error("Save error:", err);
            App.showToast(err.message || 'Error saving challan.', 'error');
        } finally {
            App.hideLoading();
        }
    }

    // --- Load saved challans (grouped by challan_no) ---
    async function loadSavedChallans() {
        try {
            const res = await App.api('list', { table: 'nfp_outward' });
            allRecords = res.rows || [];
            applyFilter();
        } catch (err) {
            console.error("Load error:", err);
        }
    }

    function applyFilter() {
        const searchVal = document.getElementById('filter-search').value.toLowerCase().trim();

        // Group records by challan_no
        const grouped = {};
        allRecords.forEach(r => {
            if (!grouped[r.challan_no]) {
                grouped[r.challan_no] = {
                    challan_no: r.challan_no,
                    challan_date: r.challan_date,
                    party_id: r.party_id,
                    vehicle_no: r.vehicle_no,
                    items: []
                };
            }
            grouped[r.challan_no].items.push(r);
        });

        let challans = Object.values(grouped);

        if (searchVal) {
            challans = challans.filter(ch => {
                return ch.challan_no.toLowerCase().includes(searchVal) ||
                       getPartyName(ch.party_id).toLowerCase().includes(searchVal) ||
                       (ch.vehicle_no || '').toLowerCase().includes(searchVal);
            });
        }

        renderChallanList(challans);
    }

    function renderChallanList(challans) {
        const body = document.getElementById('challan-list-body');
        body.innerHTML = '';

        document.getElementById('challan-count').innerText = `(${challans.length} challans)`;

        if (challans.length === 0) {
            body.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:32px;">No NFP outward challans found.</td></tr>';
            return;
        }

        challans.forEach(ch => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong style="color:var(--secondary);">${escapeHtml(ch.challan_no)}</strong></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(ch.challan_date)}</td>
                <td style="font-size:0.85rem;">${escapeHtml(getPartyName(ch.party_id))}</td>
                <td>${escapeHtml(ch.vehicle_no || '-')}</td>
                <td style="text-align:center;"><span style="background:var(--primary); color:white; padding:2px 8px; border-radius:10px; font-size:0.75rem; font-weight:bold;">${ch.items.length}</span></td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn" style="background:var(--primary); color:white; border:none; padding:4px 8px; border-radius:4px; font-size:0.7rem; font-weight:bold; cursor:pointer;" onclick="editChallan('${escapeHtml(ch.challan_no)}')" title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn" style="background:#16a34a; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:0.7rem; font-weight:bold; cursor:pointer;" onclick="printChallan('${escapeHtml(ch.challan_no)}')" title="Print">
                            <i class="fa-solid fa-print"></i>
                        </button>
                        <button class="btn" style="background:var(--error); color:white; border:none; padding:4px 8px; border-radius:4px; font-size:0.7rem; font-weight:bold; cursor:pointer;" onclick="deleteChallan('${escapeHtml(ch.challan_no)}')" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            body.appendChild(tr);
        });
    }

    // --- Edit (load back into form) ---
    function editChallan(challanNo) {
        const items = allRecords.filter(r => r.challan_no === challanNo);
        if (items.length === 0) return;

        const first = items[0];
        document.getElementById('challan_no').value = first.challan_no;
        document.getElementById('challan_date').value = first.challan_date;
        document.getElementById('party_id').value = first.party_id || '';
        document.getElementById('vehicle_no').value = first.vehicle_no || '';

        // Clear existing items and populate
        const body = document.getElementById('items-body');
        body.innerHTML = '';
        itemRowCounter = 0;

        items.forEach(item => {
            itemRowCounter++;
            const tr = document.createElement('tr');
            tr.id = `item-row-${itemRowCounter}`;
            tr.dataset.inwardChallanId = item.inward_challan_id || '';
            tr.innerHTML = `
                <td style="text-align:center; font-weight:bold; color:var(--secondary);">${item.sr_no}</td>
                <td><input type="text" class="form-control item-wound-code" value="${escapeHtml(item.wound_code || '')}" style="font-size:0.8rem; padding:4px 6px;"></td>
                <td><input type="text" class="form-control item-description" value="${escapeHtml(item.description || '')}" style="font-size:0.8rem; padding:4px 6px;"></td>
                <td><input type="text" class="form-control item-m-code" value="${escapeHtml(item.m_code || '')}" style="font-size:0.8rem; padding:4px 6px;"></td>
                <td><input type="text" class="form-control item-hsn" value="${escapeHtml(item.hsn_code || '9988')}" style="font-size:0.8rem; padding:4px 6px; text-align:center;"></td>
                <td><input type="number" class="form-control item-qty" value="${parseFloat(item.qty || 0)}" min="0" step="0.01" style="font-size:0.8rem; padding:4px 6px; text-align:right;"></td>
                <td><input type="text" class="form-control item-uom" value="${escapeHtml(item.uom || 'NOS')}" style="font-size:0.8rem; padding:4px 6px; text-align:center;"></td>
                <td><input type="number" class="form-control item-rate" value="${parseFloat(item.rate || 0).toFixed(2)}" min="0" step="0.01" style="font-size:0.8rem; padding:4px 6px; text-align:right;"></td>
                <td style="text-align:right; font-weight:bold; font-size:0.8rem;">${parseFloat(item.amount || 0).toFixed(2)}</td>
                <td style="text-align:center;">
                    <button type="button" class="btn" style="background:var(--error); color:white; border:none; padding:3px 6px; border-radius:4px; font-size:0.65rem; cursor:pointer;" onclick="removeItemRow('item-row-${itemRowCounter}')" title="Remove">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });
        renderConvertibleInwards();

        window.scrollTo({ top: 0, behavior: 'smooth' });
        App.showToast(`Loaded challan ${challanNo} for editing.`, 'success');
    }

    // --- Print ---
    function printChallan(challanNo) {
        window.open('nfp_print.php?challan_no=' + encodeURIComponent(challanNo), '_blank');
    }

    // --- Delete ---
    async function deleteChallan(challanNo) {
        if (!confirm(`Delete NFP challan "${challanNo}" and all its items?`)) return;

        App.showLoading('Deleting challan...');
        try {
            const res = await App.api('delete_nfp_challan', { challan_no: challanNo });
            if (res.ok) {
                App.showToast('Challan deleted.', 'success');
                loadSavedChallans();
                loadConvertibleInwards();
            } else {
                App.showToast(res.error || 'Failed to delete challan.', 'error');
            }
        } catch (err) {
            console.error("Delete error:", err);
            App.showToast(err.message || 'Error deleting challan.', 'error');
        } finally {
            App.hideLoading();
        }
    }

    // --- Reset ---
    function resetAll() {
        document.getElementById('challan_no').value = '';
        document.getElementById('challan_date').value = App.formatDate(new Date());
        document.getElementById('party_id').value = '';
        document.getElementById('vehicle_no').value = '';
        document.getElementById('items-body').innerHTML = '';
        itemRowCounter = 0;
        addItemRow();
        loadConvertibleInwards();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // --- Convertible Inward Methods ---
    async function loadConvertibleInwards() {
        try {
            const res = await App.api('query', {
                sql: "SELECT * FROM `challan_inward` WHERE `bal_qty` > 0 ORDER BY `ch_date` DESC"
            });
            convertibleInwards = res.rows || [];
            renderConvertibleInwards();
        } catch (err) {
            console.error("Error loading convertible inwards:", err);
        }
    }

    function renderConvertibleInwards() {
        const listDiv = document.getElementById('inward-convertible-list');
        listDiv.innerHTML = '';

        const searchVal = document.getElementById('inward-search').value.toLowerCase().trim();
        const filtered = convertibleInwards.filter(item => {
            if (!searchVal) return true;
            return (item.ch_no || '').toLowerCase().includes(searchVal) ||
                   (item.m_code || '').toLowerCase().includes(searchVal) ||
                   (item.m_description || '').toLowerCase().includes(searchVal);
        });

        if (filtered.length === 0) {
            listDiv.innerHTML = '<div style="text-align: center; color: var(--text-muted); font-size: 0.8rem; padding: 16px;">No convertible inwards found.</div>';
            return;
        }

        const addedIds = Array.from(document.getElementById('items-body').rows)
            .map(row => row.dataset.inwardChallanId)
            .filter(Boolean);

        filtered.forEach(item => {
            const isAlreadyAdded = addedIds.includes(String(item.id));
            const card = document.createElement('div');
            card.style.border = '1px solid var(--border-color)';
            card.style.borderRadius = '6px';
            card.style.padding = '10px';
            card.style.transition = 'all 0.2s';

            if (isAlreadyAdded) {
                card.style.background = 'rgba(255,255,255,0.02)';
                card.style.opacity = '0.4';
                card.style.cursor = 'not-allowed';
                card.style.pointerEvents = 'none';
            } else {
                card.style.background = 'rgba(255,255,255,0.01)';
                card.style.cursor = 'pointer';
                card.onclick = () => convertInwardToOutward(item);

                card.onmouseover = () => {
                    card.style.borderColor = 'var(--primary)';
                    card.style.background = 'rgba(255,255,255,0.03)';
                };
                card.onmouseout = () => {
                    card.style.borderColor = 'var(--border-color)';
                    card.style.background = 'rgba(255,255,255,0.01)';
                };
            }

            card.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <strong style="color: var(--secondary); font-size: 0.85rem;">${escapeHtml(item.ch_no)}</strong>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">${App.formatDate(item.ch_date)}</span>
                </div>
                <div style="font-size: 0.8rem; margin-bottom: 4px;">
                    Code: <strong style="color: var(--text-highlight);">${escapeHtml(item.m_code)}</strong>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(item.m_description || '')}">
                    ${escapeHtml(item.m_description || '-')}
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px; border-top: 1px dashed var(--border-color); padding-top: 4px;">
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Bal Qty:</span>
                    <strong style="color: var(--success); font-size: 0.85rem;">${parseFloat(item.bal_qty).toFixed(2)}</strong>
                </div>
            `;
            listDiv.appendChild(card);
        });
    }

    function filterInwards() {
        renderConvertibleInwards();
    }

    function convertInwardToOutward(item) {
        const body = document.getElementById('items-body');

        // Guard against duplicate item addition
        const existingRow = Array.from(body.rows).find(row => row.dataset.inwardChallanId == item.id);
        if (existingRow) {
            App.showToast('This item has already been added to the challan.', 'error');
            return;
        }

        if (item.party_id) {
            document.getElementById('party_id').value = item.party_id;
        }
        if (item.vehicle_no) {
            document.getElementById('vehicle_no').value = item.vehicle_no;
        }

        // Remove default empty row if it's the only one
        if (body.rows.length === 1) {
            const firstRow = body.rows[0];
            const woundCode = firstRow.querySelector('.item-wound-code')?.value || '';
            const desc = firstRow.querySelector('.item-description')?.value || '';
            const mCode = firstRow.querySelector('.item-m-code')?.value || '';
            if (!woundCode.trim() && !desc.trim() && !mCode.trim()) {
                body.innerHTML = '';
            }
        }

        itemRowCounter++;
        const tr = document.createElement('tr');
        tr.id = `item-row-${itemRowCounter}`;
        tr.dataset.inwardChallanId = item.id;

        tr.innerHTML = `
            <td style="text-align:center; font-weight:bold; color:var(--secondary);">${body.rows.length + 1}</td>
            <td><input type="text" class="form-control item-wound-code" value="${escapeHtml(item.m_code)}" placeholder="Wound code" style="font-size:0.8rem; padding:4px 6px;"></td>
            <td><input type="text" class="form-control item-description" value="${escapeHtml(item.m_description || '')}" placeholder="Description" style="font-size:0.8rem; padding:4px 6px;"></td>
            <td><input type="text" class="form-control item-m-code" value="${escapeHtml(item.m_code)}" placeholder="Material code" style="font-size:0.8rem; padding:4px 6px;"></td>
            <td><input type="text" class="form-control item-hsn" value="9988" style="font-size:0.8rem; padding:4px 6px; text-align:center;"></td>
            <td><input type="number" class="form-control item-qty" value="${parseFloat(item.bal_qty).toFixed(2)}" min="0.01" max="${parseFloat(item.bal_qty)}" step="0.01" style="font-size:0.8rem; padding:4px 6px; text-align:right;"></td>
            <td><input type="text" class="form-control item-uom" value="NOS" style="font-size:0.8rem; padding:4px 6px; text-align:center;"></td>
            <td><input type="number" class="form-control item-rate" value="0.00" min="0" step="0.01" style="font-size:0.8rem; padding:4px 6px; text-align:right;"></td>
            <td style="text-align:right; font-weight:bold; font-size:0.8rem;">0.00</td>
            <td style="text-align:center;">
                <button type="button" class="btn" style="background:var(--error); color:white; border:none; padding:3px 6px; border-radius:4px; font-size:0.65rem; cursor:pointer;" onclick="removeItemRow('item-row-${itemRowCounter}')" title="Remove">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        body.appendChild(tr);
        recalcSrNos();
        renderConvertibleInwards();
        App.showToast(`Converted Inward Challan ${item.ch_no} to line item.`, 'success');
    }
</script>

<?php
renderFooter();
?>
