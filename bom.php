<?php
// Jagdamba Electrical - Bill of Materials (bom.php)
require_once 'sidebar.php';
renderHeader("Bill of Materials (BOM)", "bom");
?>

<div class="module-container">
    <!-- Form Panel (Left) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-folder-tree" style="color: var(--primary);"></i> <span id="form-action-title">Create BOM Entry</span></span>
            <span class="panel-subtitle">Ctrl+Enter: Save | Ctrl+Shift+Enter: Add Line</span>
        </div>
        <div class="panel-body">
            <form id="bom-form" onsubmit="return false;">
                <!-- Hidden Edit ID -->
                <input type="hidden" id="bom-id" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="wound_code">Wound Code <span style="color:var(--error);">*</span></label>
                        <input type="text" id="wound_code" class="form-control" placeholder="e.g. WND-50HP" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="mac_no">MAC Number <span style="color:var(--error);">*</span></label>
                        <input type="text" id="mac_no" class="form-control" placeholder="e.g. MC-8899" required>
                    </div>

                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label" for="m_code">Material Code <span style="color:var(--error);">*</span></label>
                        <input type="text" id="m_code" class="form-control" placeholder="Type to search material..." autocomplete="off" required oninput="handleMaterialSearch(this.value)">
                        <div id="m_code-suggestions" class="autocomplete-dropdown" style="display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="m_description">Material Description</label>
                        <textarea id="m_description" class="form-control" placeholder="Auto-filled from inventory master..." readonly></textarea>
                    </div>

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label" for="req_qty">Required Qty <span style="color:var(--error);">*</span></label>
                            <input type="number" id="req_qty" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="material_type">Material Type <span style="color:var(--error);">*</span></label>
                            <select id="material_type" class="form-control" required>
                                <option value="COIL">COIL</option>
                                <option value="KIT">KIT</option>
                                <option value="STAMPING">STAMPING</option>
                                <option value="LEAD WIRE">LEAD WIRE</option>
                                <option value="C CLASS">C CLASS</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label" for="bom_tier">BOM Tier / Set <span style="color:var(--error);">*</span></label>
                        <select id="bom_tier" class="form-control" required>
                            <option value="PRIMARY">1st Winding Set (Primary)</option>
                            <option value="SECONDARY">2nd Winding Set (Secondary)</option>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top: 24px;">
                    <!-- Save and Clear All -->
                    <button type="button" class="btn btn-primary" onclick="submitForm(true)" style="flex: 1;"><i class="fa-solid fa-check"></i> Save & Exit</button>
                    <!-- Add and Keep Wound/MAC -->
                    <button type="button" class="btn btn-success" onclick="submitForm(false)" style="flex: 1; color:#000;"><i class="fa-solid fa-plus"></i> Add Line</button>
                </div>
                <div class="btn-group" style="margin-top: 10px;">
                    <button type="button" id="btn-delete" class="btn btn-danger" onclick="handleDelete()" style="display: none; flex: 1;"><i class="fa-solid fa-trash-can"></i> Delete Line</button>
                    <button type="button" class="btn btn-warning" onclick="handleDeleteAllForWound()" style="flex: 1; color:#000;" title="Clear existing BOM entries for this Wound Code before saving new set"><i class="fa-solid fa-rotate"></i> Clear Old BOM</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm(true)" style="flex: 1;"><i class="fa-solid fa-xmark"></i> Clear Form</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid List Panel (Right) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-diagram-project" style="color: var(--secondary);"></i> Bill of Material Records</span>
            <span class="panel-subtitle" id="record-count-label">0 Records</span>
        </div>
        <div class="panel-body">
            <!-- Search Inputs -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
                <div class="form-group">
                    <input type="text" id="search-wound" class="form-control" placeholder="Search Wound Code..." oninput="filterBOMGrid()">
                </div>
                <div class="form-group">
                    <input type="text" id="search-mac" class="form-control" placeholder="Search MAC No..." oninput="filterBOMGrid()">
                </div>
                <div class="form-group">
                    <input type="text" id="search-mcode" class="form-control" placeholder="Search Material Code..." oninput="filterBOMGrid()">
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="custom-table" id="bom-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Wound Code</th>
                            <th>MAC No</th>
                            <th>M Code</th>
                            <th>Material Description</th>
                            <th style="text-align: right;">Req Qty</th>
                            <th>Type</th>
                            <th>Tier / Set</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let bomList = [];
    let materialsCache = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadBOM();
        loadMaterialsCache();

        // Keyboard Shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'Enter') {
                e.preventDefault();
                submitForm(false); // Add line and retain headers
            } else if (e.ctrlKey && !e.shiftKey && e.key === 'Enter') {
                e.preventDefault();
                submitForm(true); // Save and clear
            }
        });

        // Close autocomplete suggestion box on clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.autocomplete-wrapper')) {
                document.getElementById('m_code-suggestions').style.display = 'none';
            }
        });
    });

    // Fetch BOM Records
    async function loadBOM() {
        App.showLoading('Loading BOM list...');
        try {
            const res = await App.api('list', { table: 'bom' });
            bomList = res.rows || [];
            populateGrid(bomList);
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Cache materials to make autocomplete search extremely fast
    async function loadMaterialsCache() {
        try {
            const res = await App.api('list', { table: 'raw_material' });
            materialsCache = res.rows || [];
        } catch (err) {
            console.error("Cache load failed: ", err);
        }
    }

    // Material Auto-complete search logic
    function handleMaterialSearch(val) {
        const dropdown = document.getElementById('m_code-suggestions');
        const descInput = document.getElementById('m_description');

        if (!val.trim()) {
            dropdown.style.display = 'none';
            descInput.value = '';
            return;
        }

        const query = val.toLowerCase().trim();
        const matches = materialsCache.filter(item =>
            item.m_code.toLowerCase().includes(query) ||
            (item.m_description && item.m_description.toLowerCase().includes(query))
        ).slice(0, 10); // cap suggestions at 10

        if (matches.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        dropdown.innerHTML = '';
        matches.forEach(item => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';

            // Highlight matching text in title
            let displayCode = item.m_code;
            const regex = new RegExp(`(${query})`, 'gi');
            displayCode = displayCode.replace(regex, `<span class="match">$1</span>`);

            div.innerHTML = `
                <div class="autocomplete-item-title">${displayCode}</div>
                <div class="autocomplete-item-desc">${escapeHtml(item.m_description || 'No description')} (${escapeHtml(item.unit || '')})</div>
            `;
            div.onclick = () => {
                document.getElementById('m_code').value = item.m_code;
                descInput.value = item.m_description || '';
                dropdown.style.display = 'none';
                document.getElementById('req_qty').focus(); // shift focus
            };
            dropdown.appendChild(div);
        });

        dropdown.style.display = 'block';
    }

    // Populate Table
    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Records`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:32px;">No Bill of Materials found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.dataset.id = item.id;
            tr.onclick = () => selectRow(tr, item);

            // Highlight selected edit row
            const activeId = document.getElementById('bom-id').value;
            if (activeId && parseInt(activeId) === item.id) {
                tr.classList.add('selected');
            }

            const tierBadge = item.bom_tier === 'SECONDARY'
                ? '<span class="badge badge-warning" style="color:#000;">Set 2 (Secondary)</span>'
                : '<span class="badge badge-success">Set 1 (Primary)</span>';

            tr.innerHTML = `
                <td>${item.id}</td>
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.wound_code)}</strong></td>
                <td><span class="badge badge-pending">${escapeHtml(item.mac_no)}</span></td>
                <td><span style="font-family: monospace; font-size:0.85rem;">${escapeHtml(item.rm_code)}</span></td>
                <td style="max-width: 250px; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(item.m_description || '')}">${escapeHtml(item.m_description || '-')}</td>
                <td style="text-align: right; font-weight:700;">${App.formatDecimal(item.req_qty)}</td>
                <td><span class="badge badge-success">${escapeHtml(item.material_type)}</span></td>
                <td>${tierBadge}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Multi-column filter
    function filterBOMGrid() {
        const wQ = document.getElementById('search-wound').value.toLowerCase().trim();
        const mQ = document.getElementById('search-mac').value.toLowerCase().trim();
        const cQ = document.getElementById('search-mcode').value.toLowerCase().trim();

        const filtered = bomList.filter(item => {
            const matchW = !wQ || (item.wound_code && item.wound_code.toLowerCase().includes(wQ));
            const matchM = !mQ || (item.mac_no && item.mac_no.toLowerCase().includes(mQ));
            const matchC = !cQ || (item.rm_code && item.rm_code.toLowerCase().includes(cQ));
            return matchW && matchM && matchC;
        });

        populateGrid(filtered);
    }

    // Select row for Edit
    function selectRow(trElement, item) {
        document.querySelectorAll('#bom-table tbody tr').forEach(r => r.classList.remove('selected'));
        trElement.classList.add('selected');

        document.getElementById('bom-id').value = item.id;
        document.getElementById('wound_code').value = item.wound_code;
        document.getElementById('mac_no').value = item.mac_no;
        document.getElementById('m_code').value = item.rm_code;
        document.getElementById('m_description').value = item.m_description || '';
        document.getElementById('req_qty').value = item.req_qty;
        document.getElementById('material_type').value = item.material_type;
        document.getElementById('bom_tier').value = item.bom_tier || 'PRIMARY';

        document.getElementById('form-action-title').innerText = "Edit BOM Record";
        document.getElementById('btn-delete').style.display = "block";
    }

    // Form Reset
    function resetForm(clearAll = true) {
        if (clearAll) {
            document.getElementById('wound_code').value = '';
            document.getElementById('mac_no').value = '';
            document.getElementById('form-action-title').innerText = "Create BOM Entry";
            document.getElementById('btn-delete').style.display = "none";
        }
        document.getElementById('bom-id').value = '';
        document.getElementById('m_code').value = '';
        document.getElementById('m_description').value = '';
        document.getElementById('req_qty').value = '';
        document.getElementById('material_type').selectedIndex = 0;
        document.getElementById('bom_tier').value = 'PRIMARY';

        document.getElementById('m_code-suggestions').style.display = 'none';
        document.querySelectorAll('#bom-table tbody tr').forEach(r => r.classList.remove('selected'));
    }

    // Form submission
    async function submitForm(exitAfterSave) {
        const form = document.getElementById('bom-form');

        const id = document.getElementById('bom-id').value;
        const wound_code = document.getElementById('wound_code').value.trim();
        const mac_no = document.getElementById('mac_no').value.trim();
        const rm_code = document.getElementById('m_code').value.trim();
        const m_description = document.getElementById('m_description').value.trim();
        const req_qty = parseFloat(document.getElementById('req_qty').value || 0);
        const material_type = document.getElementById('material_type').value;
        const bom_tier = document.getElementById('bom_tier').value || 'PRIMARY';

        if (!wound_code || !mac_no || !rm_code || req_qty <= 0) {
            App.showToast('Please fill all required fields correctly.', 'error');
            return;
        }

        const data = {
            wound_code,
            mac_no,
            rm_code,
            m_description,
            req_qty,
            material_type,
            bom_tier
        };

        App.showLoading('Saving BOM record...');
        try {
            if (id) {
                // Update
                await App.api('update', {
                    table: 'bom',
                    id: parseInt(id),
                    data: data
                });
                App.showToast('BOM updated.');
            } else {
                // Insert
                await App.api('insert', {
                    table: 'bom',
                    data: data
                });
                App.showToast('BOM line entry added.');
            }

            resetForm(exitAfterSave); // if exitAfterSave is false, keeps Wound and MAC No
            await loadBOM();

            // set focus back to material code for rapid data entry if keeping layout
            if (!exitAfterSave) {
                document.getElementById('m_code').focus();
            }
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Delete Record
    async function handleDelete() {
        const id = document.getElementById('bom-id').value;
        if (!id) return;

        if (confirm('Delete this BOM line entry?')) {
            App.showLoading('Deleting BOM entry...');
            try {
                await App.api('delete', {
                    table: 'bom',
                    id: parseInt(id)
                });
                App.showToast('BOM line entry deleted.');
                resetForm(true);
                await loadBOM();
            } catch (err) {
                console.error(err);
            } finally {
                App.hideLoading();
            }
        }
    }

    // Delete All BOM Entries for Wound Code (+ MAC No)
    async function handleDeleteAllForWound() {
        const wound_code = document.getElementById('wound_code').value.trim();
        const mac_no = document.getElementById('mac_no').value.trim();
        if (!wound_code) {
            App.showToast('Please enter or select a Wound Code first.', 'warning');
            return;
        }
        const msg = mac_no ?
            `Delete all existing BOM lines for Wound Code "${wound_code}" and MAC No "${mac_no}"?` :
            `Delete all existing BOM lines for Wound Code "${wound_code}"?`;

        if (confirm(msg)) {
            App.showLoading('Clearing existing BOM lines...');
            try {
                await App.api('delete_bom_for_wound', {
                    wound_code: wound_code,
                    mac_no: mac_no
                });
                App.showToast('BOM lines cleared successfully.');
                resetForm(false);
                await loadBOM();
            } catch (err) {
                console.error(err);
            } finally {
                App.hideLoading();
            }
        }
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
</script>

<?php
renderFooter();
?>
