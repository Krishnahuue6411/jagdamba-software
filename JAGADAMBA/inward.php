<?php
// Sahara Electrical - Inward Transaction Entry (inward.php)
require_once 'sidebar.php';
renderHeader("Inward Transaction Entry", "inward");
?>
<!-- SheetJS Excel Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div class="module-container" style="grid-template-columns: 420px 1fr;">
    <!-- Form Panel (Left) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-file-import" style="color: var(--primary);"></i> <span id="form-action-title">Inward Record Form</span></span>
            <span class="panel-subtitle">Ctrl+Enter: Save | Ctrl+Shift+Enter: Clear</span>
        </div>
        <div class="panel-body">
            <form id="inward-form" onsubmit="handleFormSubmit(event)">
                <!-- Hidden Edit ID -->
                <input type="hidden" id="inward-id" value="">

                <div class="form-grid">
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label" for="ch_no">Challan No <span style="color:var(--error);">*</span></label>
                            <input type="text" id="ch_no" class="form-control" placeholder="e.g. CH-4455" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="ch_date">Challan Date <span style="color:var(--error);">*</span></label>
                            <input type="date" id="ch_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="inward_party">Select Party</label>
                        <select id="inward_party" class="form-control" style="background-color: var(--bg-main);">
                            <option value="">-- Choose Party --</option>
                        </select>
                    </div>

                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label" for="m_code">Material Code <span style="color:var(--error);">*</span></label>
                        <input type="text" id="m_code" class="form-control" placeholder="Search code from master..." autocomplete="off" required oninput="handleMaterialSearch(this.value)">
                        <div id="m_code-suggestions" class="autocomplete-dropdown" style="display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="m_description">Material Description</label>
                        <textarea id="m_description" class="form-control" placeholder="Auto-filled..." readonly></textarea>
                    </div>

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label" for="head">Head / Category</label>
                            <input type="text" id="head" class="form-control" placeholder="Auto-filled..." readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="unit">Unit</label>
                            <input type="text" id="unit" class="form-control" placeholder="Auto-filled..." readonly>
                        </div>
                    </div>

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label" for="in_qty">Inward Qty <span style="color:var(--error);">*</span></label>
                            <input type="number" id="in_qty" class="form-control" step="0.01" min="0.01" placeholder="e.g. 50" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="material_type">Material Type</label>
                            <select id="material_type" class="form-control">
                                <option value="">[Select Type]</option>
                                <option value="COIL">COIL</option>
                                <option value="KIT">KIT</option>
                                <option value="STAMPING">STAMPING</option>
                                <option value="LEAD WIRE">LEAD WIRE</option>
                                <option value="C CLASS">C CLASS</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label" for="challan_type">Challan Type</label>
                            <input type="text" id="challan_type" class="form-control" placeholder="e.g. Non-Returnable">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="remark">Remark</label>
                            <input type="text" id="remark" class="form-control" placeholder="e.g. Received OK">
                        </div>
                    </div>

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label" for="wip_count">WIP Count (Optional Divisor)</label>
                            <input type="number" id="wip_count" class="form-control" min="0" placeholder="e.g. 10">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="mc_no">Machine Number(s)</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="mc_no" class="form-control" placeholder="Generated machine list..." readonly>
                                <button type="button" class="btn btn-secondary" onclick="openMachineModal()" title="Generate Serial Numbers"><i class="fa-solid fa-gears"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="btn-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-circle-check"></i> Save Inward</button>
                    <button type="button" id="btn-delete" class="btn btn-danger" onclick="handleDelete()" style="display: none;"><i class="fa-solid fa-trash-can"></i> Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fa-solid fa-arrow-rotate-right"></i> Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid Panel (Right) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-list" style="color: var(--secondary);"></i> Inward Receipts Journal</span>
            <span class="panel-subtitle" id="record-count-label">0 Records</span>
        </div>
        <div class="panel-body">
            <!-- Search & Filters -->
            <div class="search-box-wrapper" style="display: flex; gap: 16px; margin-bottom: 16px; align-items: center; flex-wrap: wrap;">
                <div class="topbar-search" style="flex: 2; display: flex; margin: 0; min-width: 250px;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px;"></i>
                    <input type="text" id="grid-search" placeholder="Search by Challan, Material, Date, or Serial..." style="width: 100%; padding-left: 40px;" oninput="filterGrid()">
                </div>

                <div class="form-group" style="flex: 1; margin: 0; min-width: 150px;">
                    <input type="text" id="filter_mc_no" class="form-control" placeholder="Filter by Machine No..." oninput="filterGrid()">
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" onclick="exportToExcel()" style="height: 38px; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;"><i class="fa-solid fa-file-excel" style="color: #16a34a;"></i> Export Excel</button>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Challan No</th>
                            <th>Challan Date</th>

                            <th>Party</th>
                            <th>Material Code</th>
                            <th>Description</th>
                            <th style="text-align: right;">In Qty</th>
                            <th style="text-align: right;">Bal Qty</th>
                            <th>Unit</th>
                            <th>Machine(s)</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MACHINE NUMBER GENERATION POPUP -->
<div class="modal-backdrop" id="machine-modal">
    <div class="modal-content" style="width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-gears" style="color: var(--primary); margin-right: 8px;"></i> Generate Machine Serials</h3>
            <button class="modal-close" onclick="closeMachineModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Tab buttons inside modal -->
            <div class="modal-tabs">
                <button class="modal-tab-btn active" id="modal-tab-var" onclick="switchModalTab('variable')">Variable Mode (Range)</button>
                <button class="modal-tab-btn" id="modal-tab-std" onclick="switchModalTab('standard')">Standard Mode (Count)</button>
            </div>

            <form id="serial-gen-form" onsubmit="executeSerialGeneration(event)">
                <!-- Variable Mode Fields -->
                <div id="modal-fields-var" class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="var_start">Starting Serial <span style="color:var(--error);">*</span></label>
                        <input type="text" id="var_start" class="form-control" placeholder="e.g. NADN14023AV">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="var_end">Ending Serial <span style="color:var(--error);">*</span></label>
                        <input type="text" id="var_end" class="form-control" placeholder="e.g. NADN14026AV">
                    </div>
                </div>

                <!-- Standard Mode Fields -->
                <div id="modal-fields-std" class="form-grid" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="std_base">Base Serial Code <span style="color:var(--error);">*</span></label>
                        <input type="text" id="std_base" class="form-control" placeholder="e.g. MT123SH">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="std_count">Total Quantity <span style="color:var(--error);">*</span></label>
                        <input type="number" id="std_count" class="form-control" min="1" max="1000" placeholder="e.g. 5">
                    </div>
                </div>

                <!-- Live preview area -->
                <div style="margin-top: 20px; font-size: 0.8rem; color: var(--text-muted);">
                    Generated serials will be stored as <strong style="color: var(--primary);">pending</strong> in the invoice checklist.
                </div>

                <div class="btn-group" style="margin-top: 24px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeMachineModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Generate & Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let inwardsList = [];
    let materialsCache = [];
    let modalMode = 'variable'; // 'variable' or 'standard'
    let generatedMachinesTemp = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        // Set default Challan Date to today
        document.getElementById('ch_date').value = App.formatDate(new Date());



        await loadParties();
        loadInwards();
        loadLastChallan();
        loadMaterialsCache();

        // Keyboard Shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'Enter') {
                e.preventDefault();
                resetForm();
                App.showToast('Form cleared.');
            } else if (e.ctrlKey && !e.shiftKey && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('inward-form').dispatchEvent(new Event('submit'));
            }
        });

        // Close suggestions on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.autocomplete-wrapper')) {
                document.getElementById('m_code-suggestions').style.display = 'none';
            }
        });
    });

    async function loadParties() {
        try {
            const res = await App.api('list', { table: 'parties' });
            partiesList = res.rows || [];

            const select = document.getElementById('inward_party');
            select.innerHTML = '<option value="">-- Choose Party --</option>';

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

    // Fetch Inwards
    async function loadInwards() {
        App.showLoading('Loading inwards log...');
        try {
            const res = await App.api('list', { table: 'inward_transaction' });
            inwardsList = res.rows || [];
            populateGrid(inwardsList);
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Load Last Challan Number on page load
    async function loadLastChallan() {
        try {
            const res = await App.api('query', {
                sql: "SELECT ch_no FROM inward_transaction ORDER BY id DESC LIMIT 1"
            });
            if (res.rows && res.rows.length > 0) {
                document.getElementById('ch_no').value = res.rows[0].ch_no;
            }
        } catch (err) {
            console.warn("Failed to retrieve last challan:", err);
        }
    }

    // Cache materials for autocomplete
    async function loadMaterialsCache() {
        try {
            const res = await App.api('list', { table: 'raw_material' });
            materialsCache = res.rows || [];
        } catch (err) {
            console.error(err);
        }
    }

    // Material Auto-complete search logic
    function handleMaterialSearch(val) {
        const dropdown = document.getElementById('m_code-suggestions');
        const descInput = document.getElementById('m_description');
        const headInput = document.getElementById('head');
        const unitInput = document.getElementById('unit');

        if (!val.trim()) {
            dropdown.style.display = 'none';
            descInput.value = '';
            headInput.value = '';
            unitInput.value = '';
            return;
        }

        const query = val.toLowerCase().trim();
        const matches = materialsCache.filter(item =>
            item.m_code.toLowerCase().includes(query) ||
            (item.m_description && item.m_description.toLowerCase().includes(query))
        ).slice(0, 10);

        if (matches.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        dropdown.innerHTML = '';
        matches.forEach(item => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';

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
                headInput.value = item.head || '';
                unitInput.value = item.unit || '';
                dropdown.style.display = 'none';
                document.getElementById('in_qty').focus();
            };
            dropdown.appendChild(div);
        });

        dropdown.style.display = 'block';
    }

    // Populate Table Grid
    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Records`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:32px;">No inward records recorded.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.dataset.id = item.id;
            tr.style.cursor = 'pointer';
            tr.onclick = () => selectRow(tr, item);

            // Highlight active edit row
            const activeId = document.getElementById('inward-id').value;
            if (activeId && parseInt(activeId) === item.id) {
                tr.classList.add('selected');
            }

            const partyObj = partiesList.find(p => p.id == item.party_id);
            const partyName = partyObj ? partyObj.party_name : '-';

            tr.innerHTML = `
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.ch_no)}</strong></td>
                <td style="font-size: 0.85rem; color: var(--text-muted);">${App.formatDate(item.ch_date)}</td>

                <td><span class="badge" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: var(--text-highlight);">${escapeHtml(partyName)}</span></td>
                <td><span style="font-family: monospace; font-size: 0.85rem;">${escapeHtml(item.m_code)}</span></td>
                <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(item.m_description || '')}">${escapeHtml(item.m_description || '-')}</td>
                <td style="text-align: right; font-weight: 700;">${App.formatDecimal(item.in_qty)}</td>
                <td style="text-align: right; font-weight: 700; color: var(--primary);">${App.formatDecimal(item.bal_qty)}</td>
                <td style="color:var(--text-muted);">${escapeHtml(item.unit || '')}</td>
                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(item.mc_no || '')}">${escapeHtml(item.mc_no || '-')}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Search Filter & Machine Number Filtering
    function filterGrid() {
        const query = document.getElementById('grid-search').value.toLowerCase().trim();
        const mcQuery = document.getElementById('filter_mc_no').value.toLowerCase().trim();

        const filtered = inwardsList.filter(item => {
            // 1. Machine Number Filter
            if (mcQuery) {
                if (!item.mc_no || !item.mc_no.toLowerCase().includes(mcQuery)) {
                    return false;
                }
            }

            // 2. Global Text Search
            if (query) {
                return (
                    (item.ch_no && item.ch_no.toLowerCase().includes(query)) ||
                    (item.m_code && item.m_code.toLowerCase().includes(query)) ||
                    (item.m_description && item.m_description.toLowerCase().includes(query)) ||
                    (item.mc_no && item.mc_no.toLowerCase().includes(query)) ||
                    (item.ch_date && item.ch_date.toLowerCase().includes(query))
                );
            }

            return true;
        });

        populateGrid(filtered);
    }

    // Reset Inward Form
    function resetForm() {
        const savedCh = document.getElementById('ch_no').value;
        const savedDate = document.getElementById('ch_date').value;

        document.getElementById('inward-form').reset();
        document.getElementById('inward-id').value = '';
        document.getElementById('inward_party').value = '';


        document.getElementById('form-action-title').innerText = "Inward Record Form";
        const btnDelete = document.getElementById('btn-delete');
        if (btnDelete) btnDelete.style.display = "none";

        // Remove row selection highlight
        document.querySelectorAll('.custom-table tbody tr').forEach(r => r.classList.remove('selected'));

        document.getElementById('ch_no').value = savedCh;
        document.getElementById('ch_date').value = savedDate;

        generatedMachinesTemp = [];
        document.getElementById('m_code-suggestions').style.display = 'none';
    }

    // Load selected row into Form for Editing
    function selectRow(trElement, item) {
        // Highlight active row
        document.querySelectorAll('.custom-table tbody tr').forEach(r => r.classList.remove('selected'));
        trElement.classList.add('selected');

        // Fill form fields
        document.getElementById('inward-id').value = item.id;
        document.getElementById('ch_no').value = item.ch_no;
        document.getElementById('ch_date').value = item.ch_date;

        document.getElementById('inward_party').value = item.party_id || '';
        document.getElementById('m_code').value = item.m_code;
        document.getElementById('m_description').value = item.m_description || '';
        document.getElementById('head').value = item.head || '';
        document.getElementById('unit').value = item.unit || '';
        document.getElementById('in_qty').value = item.in_qty;
        document.getElementById('material_type').value = item.material_type || '';
        document.getElementById('challan_type').value = item.challan_type || '';
        document.getElementById('remark').value = item.remark || '';
        document.getElementById('wip_count').value = item.wip_count || '';

        // Setup generated machines array from record
        generatedMachinesTemp = item.mc_no ? item.mc_no.split(',') : [];

        // Format MC No textbox summary
        if (generatedMachinesTemp.length > 0) {
            document.getElementById('mc_no').value = `${generatedMachinesTemp[0]} ... (${generatedMachinesTemp.length} machines)`;
        } else {
            document.getElementById('mc_no').value = '';
        }

        // Update UI headers & buttons
        document.getElementById('form-action-title').innerText = "Edit Inward Record";
        const btnDelete = document.getElementById('btn-delete');
        if (btnDelete) btnDelete.style.display = "block";
    }

    // Delete inward transaction with 3-time confirmation checks
    async function handleDelete() {
        const id = document.getElementById('inward-id').value;
        if (!id) return;

        // 1st Confirmation
        const c1 = confirm("⚠️ Are you sure you want to delete this inward transaction? This will revert the raw material stock balance.");
        if (!c1) return;

        // 2nd Confirmation
        const c2 = confirm("🚨 WARNING: Any generated machines for this challan that are currently pending will also be permanently deleted. Are you absolutely sure?");
        if (!c2) return;

        // 3rd Confirmation
        const c3 = confirm("🔥 FINAL CONFIRMATION: This action is irreversible. Press OK to permanently delete the inward record and adjust the inventory.");
        if (!c3) return;

        App.showLoading('Deleting inward transaction...');
        try {
            await App.api('delete_inward_transaction', { id: parseInt(id) });
            App.showToast('Inward transaction deleted successfully.');
            resetForm();
            await loadMaterialsCache();
            await loadInwards();
        } catch (err) {
            console.error(err);
            App.showToast(err.message || 'Failed to delete record.', 'error');
        } finally {
            App.hideLoading();
        }
    }

    // ==========================================
    // MACHINE RANGE GENERATOR POPUP
    // ==========================================
    function openMachineModal() {
        const ch_no = document.getElementById('ch_no').value.trim();
        if (!ch_no) {
            App.showToast('Please enter Challan Number before generating machines.', 'error');
            return;
        }
        document.getElementById('machine-modal').classList.add('visible');
    }

    function closeMachineModal() {
        document.getElementById('machine-modal').classList.remove('visible');
        document.getElementById('serial-gen-form').reset();
    }

    function switchModalTab(mode) {
        modalMode = mode;
        document.getElementById('modal-tab-var').classList.toggle('active', mode === 'variable');
        document.getElementById('modal-tab-std').classList.toggle('active', mode === 'standard');

        document.getElementById('modal-fields-var').style.display = mode === 'variable' ? 'grid' : 'none';
        document.getElementById('modal-fields-std').style.display = mode === 'standard' ? 'grid' : 'none';
    }

    // Execute serial range generation (Call API)
    async function executeSerialGeneration(e) {
        e.preventDefault();

        const ch_no = document.getElementById('ch_no').value.trim();
        const ch_date = document.getElementById('ch_date').value;

        if (!ch_no || !ch_date) {
            App.showToast('Challan number and date are required.', 'error');
            return;
        }

        const payload = {
            ch_no,
            ch_date,
            mode: modalMode,
            party_id: parseInt(document.getElementById('inward_party').value) || null
        };

        if (modalMode === 'variable') {
            payload.start_no = document.getElementById('var_start').value.trim();
            payload.end_no = document.getElementById('var_end').value.trim();
            if (!payload.start_no || !payload.end_no) {
                App.showToast('Starting and Ending serials are required.', 'error');
                return;
            }
        } else {
            payload.base_no = document.getElementById('std_base').value.trim();
            payload.count = parseInt(document.getElementById('std_count').value);
            if (!payload.base_no || isNaN(payload.count) || payload.count <= 0) {
                App.showToast('Base serial and positive count are required.', 'error');
                return;
            }
        }

        App.showLoading('Generating serial numbers...');
        try {
            const res = await App.api('generate_machines', payload);

            generatedMachinesTemp = res.machines || [];

            // Format MC No textbox summary
            if (generatedMachinesTemp.length > 0) {
                let summary = '';
                if (modalMode === 'variable') {
                    summary = `${payload.start_no} - ${payload.end_no} (${generatedMachinesTemp.length} machines)`;
                } else {
                    summary = `${payload.base_no} [x${payload.count}] (${generatedMachinesTemp.length} machines)`;
                }

                document.getElementById('mc_no').value = summary;
                App.showToast(`Successfully linked ${generatedMachinesTemp.length} machines.`);
            }
            closeMachineModal();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Form Save (Submits Inward and Updates Raw Material Balance)
    async function handleFormSubmit(e) {
        e.preventDefault();

        const m_code = document.getElementById('m_code').value.trim();
        const in_qty = parseFloat(document.getElementById('in_qty').value || 0);

        if (!m_code || in_qty <= 0) {
            App.showToast('Material Code and positive quantity are required.', 'error');
            return;
        }

        // Check if material code exists in cache to ensure consistency
        const materialMasterItem = materialsCache.find(item => item.m_code === m_code);
        if (!materialMasterItem) {
            App.showToast('Material Code does not exist in master registry.', 'error');
            return;
        }

        const id = document.getElementById('inward-id').value;
        const data = {
            ch_no: document.getElementById('ch_no').value.trim(),
            ch_date: document.getElementById('ch_date').value,
            party_id: parseInt(document.getElementById('inward_party').value) || null,
            m_code: m_code,
            m_description: document.getElementById('m_description').value.trim(),
            head: document.getElementById('head').value.trim(),
            in_qty: in_qty,
            unit: document.getElementById('unit').value.trim(),
            material_type: document.getElementById('material_type').value,
            remark: document.getElementById('remark').value.trim(),
            challan_type: document.getElementById('challan_type').value.trim(),
            wip_count: parseInt(document.getElementById('wip_count').value) || 0,
            mc_no: generatedMachinesTemp.join(',') // comma separated list
        };

        App.showLoading('Recording inward transaction...');
        try {
            await App.api('save_inward_transaction', {
                id: id ? parseInt(id) : null,
                data: data
            });

            App.showToast(id ? 'Inward transaction updated successfully.' : 'Inward transaction logged successfully.');

            // Reload caches and logs
            await loadMaterialsCache();
            resetForm();
            await loadInwards();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Export Inward Entries to Excel Sheet using SheetJS
    function exportToExcel() {
        // We export the currently filtered inwards list to represent what user sees
        const query = document.getElementById('grid-search').value.toLowerCase().trim();
        const mcQuery = document.getElementById('filter_mc_no').value.toLowerCase().trim();

        const dataToExport = inwardsList.filter(item => {
            if (mcQuery && (!item.mc_no || !item.mc_no.toLowerCase().includes(mcQuery))) return false;
            if (query) {
                return (
                    (item.ch_no && item.ch_no.toLowerCase().includes(query)) ||
                    (item.m_code && item.m_code.toLowerCase().includes(query)) ||
                    (item.m_description && item.m_description.toLowerCase().includes(query)) ||
                    (item.mc_no && item.mc_no.toLowerCase().includes(query)) ||
                    (item.ch_date && item.ch_date.toLowerCase().includes(query))
                );
            }
            return true;
        }).map(item => {
            const partyObj = partiesList.find(p => p.id == item.party_id);
            const partyName = partyObj ? partyObj.party_name : '-';
            return {
                'Challan No': item.ch_no,
                'Challan Date': App.formatDate(item.ch_date),
                'Party Name': partyName,
                'Material Code': item.m_code,
                'Description': item.m_description || '-',
                'Head': item.head || '-',
                'In Qty': parseFloat(item.in_qty),
                'Bal Qty': parseFloat(item.bal_qty),
                'Unit': item.unit || '-',
                'Material Type': item.material_type || '-',
                'Challan Type': item.challan_type || '-',
                'WIP Count': parseInt(item.wip_count) || 0,
                'Machines': item.mc_no || '-',
                'Remarks': item.remark || '-'
            };
        });

        if (dataToExport.length === 0) {
            App.showToast('No matching records to export.', 'warning');
            return;
        }

        const worksheet = XLSX.utils.json_to_sheet(dataToExport);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Inward Records");

        // Autofit Columns
        const max_len = dataToExport.reduce((prev, toex) => {
            Object.keys(toex).forEach((k, i) => {
                const val = String(toex[k]);
                prev[i] = Math.max(prev[i] || 0, val.length, k.length);
            });
            return prev;
        }, []);
        worksheet['!cols'] = max_len.map(w => ({ wch: w + 2 }));

        let filename = 'Inward_Receipts_Journal';
        XLSX.writeFile(workbook, filename + '.xlsx');
        App.showToast("Inward journal exported to Excel successfully.");
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
