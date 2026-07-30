<?php
// Sahara Electrical - PO Master (po_master.php)
require_once 'sidebar.php';
renderHeader("Purchase Order Master", "po_master");
?>

<!-- Excel CDN Loader -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div class="module-container" style="grid-template-columns: 360px 1fr;">
    <!-- Form Panel (Left) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-file-pen" style="color: var(--primary);"></i> <span id="form-action-title">Create PO Line</span></span>
            <span class="panel-subtitle">Manage PO details</span>
        </div>
        <div class="panel-body">
            <form id="po-form" onsubmit="handleFormSubmit(event)">
                <!-- Hidden Edit ID -->
                <input type="hidden" id="po-id" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="pono">PO Number <span style="color:var(--error);">*</span></label>
                        <input type="text" id="pono" class="form-control" placeholder="e.g. PO-77889" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="po_party">Associate Party</label>
                        <select id="po_party" class="form-control" style="background-color: var(--bg-main);">
                            <option value="">-- Choose Party --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="part_no">Part Number</label>
                        <select id="part_no" class="form-control" style="background-color: var(--bg-main);">
                            <option value="">-- Choose Part --</option>
                            <option value="M311">M311</option>
                            <option value="M314">M314</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="po_date">PO Date</label>
                        <input type="date" id="po_date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="item_no">Item Number</label>
                        <input type="text" id="item_no" class="form-control" placeholder="e.g. 10, 20">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="wound_code">Wound Code</label>
                        <input type="text" id="wound_code" class="form-control" placeholder="e.g. WND-50HP">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Material Description</label>
                        <textarea id="description" class="form-control" placeholder="Description of the item..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="drg_no">Drawing Number</label>
                        <input type="text" id="drg_no" class="form-control" placeholder="e.g. DRG-123">
                    </div>

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label" for="rate">Rate</label>
                            <input type="number" id="rate" class="form-control" step="0.01" min="0" value="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="unit">Unit</label>
                            <input type="text" id="unit" class="form-control" placeholder="e.g. NOS, SET" value="NOS">
                        </div>
                    </div>
                </div>

                <!-- Fast insertion mode: Retain header metadata -->
                <div style="margin-top: 14px; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="retain-meta" style="accent-color: var(--primary); cursor: pointer;" checked>
                    <label for="retain-meta" style="font-size: 0.8rem; color: var(--text-muted); cursor: pointer;">Retain PO No & Date on Save</label>
                </div>

                <div class="btn-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-floppy-disk"></i> Save Line</button>
                    <button type="button" id="btn-delete" class="btn btn-danger" onclick="handleDelete()" style="display: none;"><i class="fa-solid fa-trash-can"></i> Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm(true)"><i class="fa-solid fa-xmark"></i> Clear</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid List Panel (Right) -->
    <div class="panel">
        <!-- Panel Tabs -->
        <div class="modal-tabs" style="margin-bottom: 0;">
            <button class="modal-tab-btn active" id="tab-active" onclick="switchTab('active')"><i class="fa-solid fa-folder-open"></i> Active PO Master</button>
            <button class="modal-tab-btn" id="tab-archive" onclick="switchTab('archive')"><i class="fa-solid fa-box-archive"></i> Archived PO Copy</button>
        </div>

        <div class="panel-header" style="border-top: none;">
            <span class="panel-title"><i class="fa-solid fa-table-list" style="color: var(--secondary);"></i> Purchase Order Records</span>
            <span class="panel-subtitle" id="record-count-label">0 Records</span>
        </div>
        <div class="panel-body">
            <!-- Action Controls -->
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px;">
                <div class="topbar-search" style="flex: 1; min-width: 200px;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="grid-search" placeholder="Search PO, Item, Wound Code..." style="width: 100%;" oninput="filterGrid()">
                </div>

                <div class="btn-group" id="active-action-group" style="margin: 0;">
                    <button class="btn btn-success" style="color:#000;" onclick="openImportModal()"><i class="fa-solid fa-file-excel"></i> Import Excel</button>
                    <button class="btn btn-secondary" onclick="exportPOsToExcel()"><i class="fa-solid fa-download"></i> Export Excel</button>
                    <button class="btn btn-secondary" onclick="archiveAllPOs()"><i class="fa-solid fa-box-archive"></i> Move to Copy</button>
                    <button class="btn btn-danger" onclick="deleteAllPOs()"><i class="fa-solid fa-trash-arrow-up"></i> Delete All</button>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="custom-table" id="po-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>PO No</th>
                            <th>PO Date</th>
                            <th>Part No</th>
                            <th>Party</th>
                            <th>Item</th>
                            <th>Wound Code</th>
                            <th>Description</th>
                            <th>Drawing</th>
                            <th style="text-align: right;">Rate</th>
                            <th>Unit</th>
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

<!-- EXCEL IMPORT MODAL -->
<div class="modal-backdrop" id="import-modal">
    <div class="modal-content" style="width: 700px; max-width: 95%;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-file-excel" style="color: var(--success); margin-right: 8px;"></i> Import Excel Data</h3>
            <button class="modal-close" onclick="closeImportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="excel-file-input">Select Excel File (.xlsx, .xls) <span style="color:var(--error);">*</span></label>
                <input type="file" id="excel-file-input" class="form-control" accept=".xlsx, .xls" onchange="handleExcelFile(event)">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="import-excel-party">Associate with Party</label>
                <select id="import-excel-party" class="form-control" style="background-color: var(--bg-main);">
                    <option value="">-- Choose Party --</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="import-excel-part">Part Number (M311 / M314)</label>
                <select id="import-excel-part" class="form-control" style="background-color: var(--bg-main);">
                    <option value="">-- Choose Part --</option>
                    <option value="M311">M311</option>
                    <option value="M314">M314</option>
                </select>
            </div>

            <!-- Header Mapping Panel -->
            <div id="mapping-panel" style="display: none; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; background-color: rgba(255,255,255,0.01); margin-bottom: 20px;">
                <h4 style="font-family: var(--font-header); margin-bottom: 12px; color: var(--text-highlight);">Header Mapping Configuration</h4>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 12px;">Map DB fields to Excel columns. We auto-detected matches where possible.</div>

                <table class="excel-mapping-table">
                    <thead>
                        <tr style="text-align: left; font-size: 0.8rem; color: var(--text-highlight);">
                            <th>Database Column</th>
                            <th>Excel Header Source</th>
                        </tr>
                    </thead>
                    <tbody id="mapping-tbody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>

            <!-- Excel Row Preview -->
            <div id="preview-panel" style="display: none;">
                <h4 style="font-family: var(--font-header); margin-bottom: 8px; color: var(--text-highlight);">Import Preview (First 5 Rows)</h4>
                <div class="table-container" style="max-height: 180px;">
                    <table class="custom-table" style="font-size: 0.8rem;">
                        <thead>
                            <tr id="preview-thead-tr">
                                <!-- Excel headers -->
                            </tr>
                        </thead>
                        <tbody id="preview-tbody">
                            <!-- Rows preview -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
            <button class="btn btn-primary" id="btn-import-execute" disabled onclick="executeBulkImport()"><i class="fa-solid fa-cloud-arrow-up"></i> Execute Upload</button>
        </div>
    </div>
</div>

<script>
    let currentTab = 'active'; // 'active' or 'archive'
    let poList = [];

    // Excel upload temporary variables
    let excelHeaders = [];
    let excelRows = [];

    // Core database columns mapped to possible Excel headers
    const dbFields = [
        { key: 'pono', label: 'PO Number (pono) *', aliases: ['purchasing document', 'po no', 'pono', 'po number', 'purchase order'] },
        { key: 'po_date', label: 'PO Date (po_date)', aliases: ['document date', 'po date', 'podate', 'date'] },
        { key: 'part_no', label: 'Part Number (part_no)', aliases: ['part no', 'part_no', 'part number', 'machine', 'part'] },
        { key: 'item_no', label: 'Item Number (item_no)', aliases: ['item', 'item no', 'item_no', 'item number', 'line item'] },
        { key: 'wound_code', label: 'Wound Code (wound_code)', aliases: ['material', 'wound code', 'wound_code', 'material code'] },
        { key: 'description', label: 'Description (description)', aliases: ['short text', 'description', 'desc', 'material description'] },
        { key: 'drg_no', label: 'Drawing No (drg_no)', aliases: ['drawing no', 'drg_no', 'drawing number'] },
        { key: 'rate', label: 'Rate (rate)', aliases: ['net price', 'rate', 'rate inr', 'price'] },
        { key: 'unit', label: 'Unit (unit)', aliases: ['unit', 'unit of measure', 'uom', 'order unit'] }
    ];

    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        await loadParties();
        loadPOs();
    });

    async function loadParties() {
        try {
            const res = await App.api('list', { table: 'parties' });
            partiesList = res.rows || [];

            const selectForm = document.getElementById('po_party');
            const selectImport = document.getElementById('import-excel-party');

            let opts = '<option value="">-- Choose Party --</option>';
            partiesList.forEach(p => {
                opts += `<option value="${p.id}">${escapeHtml(p.party_name)}</option>`;
            });

            if (selectForm) selectForm.innerHTML = opts;
            if (selectImport) selectImport.innerHTML = opts;
        } catch (err) {
            console.error("Parties load error:", err);
        }
    }

    // Switch between Active PO Master and Copy Archive
    function switchTab(tab) {
        currentTab = tab;
        document.getElementById('tab-active').classList.toggle('active', tab === 'active');
        document.getElementById('tab-archive').classList.toggle('active', tab === 'archive');

        // Hide/Show Active Actions
        document.getElementById('active-action-group').style.display = tab === 'active' ? 'flex' : 'none';

        // Disable Form editing if in Archive view
        const formFields = document.querySelectorAll('#po-form input, #po-form textarea, #po-form button, #po-form select');
        formFields.forEach(el => {
            if (tab === 'archive') {
                el.setAttribute('disabled', 'true');
            } else {
                el.removeAttribute('disabled');
            }
        });

        resetForm(true);
        loadPOs();
    }

    // Load PO data
    async function loadPOs() {
        const table = currentTab === 'active' ? 'po_master' : 'po_master_copy';
        App.showLoading('Loading PO records...');
        try {
            const res = await App.api('list', { table });
            poList = res.rows || [];
            populateGrid(poList);
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Populate Grid
    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Records`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="11" style="text-align:center; color:var(--text-muted); padding:32px;">No PO records found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.dataset.id = item.id;

            // Edit only allowed in Active mode
            if (currentTab === 'active') {
                tr.onclick = () => selectRow(tr, item);
            }

            const activeId = document.getElementById('po-id').value;
            if (activeId && parseInt(activeId) === item.id) {
                tr.classList.add('selected');
            }

            const partyObj = partiesList.find(p => p.id == item.party_id);
            const partyName = partyObj ? partyObj.party_name : '-';

            tr.innerHTML = `
                <td>${item.id}</td>
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.pono)}</strong></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(item.po_date)}</td>
                <td><span class="badge" style="background: rgba(255,255,255,0.05); color: var(--text-highlight); border:1px solid var(--border-color);">${escapeHtml(item.part_no || '-')}</span></td>
                <td><span class="badge" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: var(--text-highlight);">${escapeHtml(partyName)}</span></td>
                <td><span class="badge badge-pending">${escapeHtml(item.item_no || '-')}</span></td>
                <td><span style="font-family: monospace; font-size:0.85rem;">${escapeHtml(item.wound_code || '-')}</span></td>
                <td style="max-width: 250px; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(item.description || '')}">${escapeHtml(item.description || '-')}</td>
                <td>${escapeHtml(item.drg_no || '-')}</td>
                <td style="text-align: right; font-weight:700;">${App.formatDecimal(item.rate)}</td>
                <td style="color:var(--text-muted);">${escapeHtml(item.unit || 'NOS')}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Client-side text filter
    function filterGrid() {
        const query = document.getElementById('grid-search').value.toLowerCase().trim();
        if (!query) {
            populateGrid(poList);
            return;
        }

        const filtered = poList.filter(item => {
            return (
                (item.pono && item.pono.toLowerCase().includes(query)) ||
                (item.wound_code && item.wound_code.toLowerCase().includes(query)) ||
                (item.item_no && item.item_no.toLowerCase().includes(query)) ||
                (item.description && item.description.toLowerCase().includes(query))
            );
        });

        populateGrid(filtered);
    }

    // Load row for Edit
    function selectRow(trElement, item) {
        document.querySelectorAll('#po-table tbody tr').forEach(r => r.classList.remove('selected'));
        trElement.classList.add('selected');

        document.getElementById('po-id').value = item.id;
        document.getElementById('pono').value = item.pono;
        document.getElementById('po_party').value = item.party_id || '';
        document.getElementById('part_no').value = item.part_no || '';
        document.getElementById('po_date').value = App.formatDate(item.po_date);
        document.getElementById('item_no').value = item.item_no || '';
        document.getElementById('wound_code').value = item.wound_code || '';
        document.getElementById('description').value = item.description || '';
        document.getElementById('drg_no').value = item.drg_no || '';
        document.getElementById('rate').value = item.rate;
        document.getElementById('unit').value = item.unit || 'NOS';

        document.getElementById('form-action-title').innerText = "Edit PO Record";
        document.getElementById('btn-delete').style.display = "block";
    }

    // Reset Form
    function resetForm(clearAll = true) {
        const retain = document.getElementById('retain-meta').checked;
        const savedPono = document.getElementById('pono').value;
        const savedDate = document.getElementById('po_date').value;

        document.getElementById('po-form').reset();
        document.getElementById('po-id').value = "";
        document.getElementById('po_party').value = "";
        document.getElementById('part_no').value = "";

        if (retain && !clearAll) {
            document.getElementById('pono').value = savedPono;
            document.getElementById('po_date').value = savedDate;
        }

        document.getElementById('form-action-title').innerText = "Create PO Line";
        document.getElementById('btn-delete').style.display = "none";
        document.querySelectorAll('#po-table tbody tr').forEach(r => r.classList.remove('selected'));
    }

    // Form Save
    async function handleFormSubmit(e) {
        e.preventDefault();

        const id = document.getElementById('po-id').value;
        const data = {
            pono: document.getElementById('pono').value.trim(),
            party_id: parseInt(document.getElementById('po_party').value) || null,
            part_no: document.getElementById('part_no').value || null,
            po_date: document.getElementById('po_date').value || null,
            item_no: document.getElementById('item_no').value.trim(),
            wound_code: document.getElementById('wound_code').value.trim(),
            description: document.getElementById('description').value.trim(),
            drg_no: document.getElementById('drg_no').value.trim(),
            rate: parseFloat(document.getElementById('rate').value || 0),
            unit: document.getElementById('unit').value.trim()
        };

        if (!data.pono) {
            App.showToast('PO Number is required.', 'error');
            return;
        }

        App.showLoading('Saving PO record...');
        try {
            if (id) {
                await App.api('update', {
                    table: 'po_master',
                    id: parseInt(id),
                    data: data
                });
                App.showToast('PO line updated successfully.');
            } else {
                await App.api('insert', {
                    table: 'po_master',
                    data: data
                });
                App.showToast('PO line added successfully.');
            }

            resetForm(false); // don't clear PO No / Date if "retain" checked
            await loadPOs();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Form Delete
    async function handleDelete() {
        const id = document.getElementById('po-id').value;
        if (!id) return;

        if (confirm('Delete this PO line?')) {
            App.showLoading('Deleting PO entry...');
            try {
                await App.api('delete', {
                    table: 'po_master',
                    id: parseInt(id)
                });
                App.showToast('PO entry deleted.');
                resetForm(true);
                await loadPOs();
            } catch (err) {
                console.error(err);
            } finally {
                App.hideLoading();
            }
        }
    }

    // Archive Active to Copy Copy
    async function archiveAllPOs() {
        if (confirm('Are you sure you want to ARCHIVE all active POs to the copy archive? This will move all records and truncate the active master.')) {
            App.showLoading('Archiving POs...');
            try {
                await App.api('archive_po');
                App.showToast('All active POs archived to Copy Master successfully.');
                loadPOs();
            } catch (err) {
                console.error(err);
            } finally {
                App.hideLoading();
            }
        }
    }

    // Truncate Table
    async function deleteAllPOs() {
        if (confirm('Are you sure you want to DELETE ALL active PO master records? This action is permanent!')) {
            App.showLoading('Truncating PO master...');
            try {
                await App.api('truncate', { table: 'po_master' });
                App.showToast('Active PO master cleared.');
                loadPOs();
            } catch (err) {
                console.error(err);
            } finally {
                App.hideLoading();
            }
        }
    }

    // ==========================================
    // EXCEL IMPORT WORKFLOW
    // ==========================================
    function openImportModal() {
        document.getElementById('import-modal').classList.add('visible');
    }

    function closeImportModal() {
        document.getElementById('import-modal').classList.remove('visible');
        // Clear variables
        document.getElementById('excel-file-input').value = '';
        document.getElementById('mapping-panel').style.display = 'none';
        document.getElementById('preview-panel').style.display = 'none';
        document.getElementById('btn-import-execute').setAttribute('disabled', 'true');
        excelHeaders = [];
        excelRows = [];
    }

    function handleExcelFile(e) {
        const file = e.target.files[0];
        if (!file) return;

        App.showLoading('Reading Excel spreadsheet...');

        const reader = new FileReader();
        reader.onload = function(evt) {
            try {
                const data = new Uint8Array(evt.target.result);
                const workbook = XLSX.read(data, { type: 'array', cellDates: true });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];

                // Get raw sheets matrix (header + row matrices)
                const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });

                if (rows.length === 0) {
                    throw new Error('Spreadsheet appears to be empty.');
                }

                excelHeaders = rows[0].map(h => String(h || '').trim());
                excelRows = rows.slice(1);

                setupHeaderMapping();
                renderPreviewTable();

                document.getElementById('mapping-panel').style.display = 'block';
                document.getElementById('preview-panel').style.display = 'block';
                document.getElementById('btn-import-execute').removeAttribute('disabled');
            } catch (err) {
                App.showToast(err.message || 'Error parsing Excel sheet', 'error');
            } finally {
                App.hideLoading();
            }
        };
        reader.readAsArrayBuffer(file);
    }

    // Dynamically render selector mapper
    function setupHeaderMapping() {
        const tbody = document.getElementById('mapping-tbody');
        tbody.innerHTML = '';

        dbFields.forEach(dbField => {
            const tr = document.createElement('tr');

            // Build dropdown options from Excel columns
            let optionsHtml = `<option value="">[Ignore field]</option>`;

            let matchedIndex = -1;

            excelHeaders.forEach((exHeader, index) => {
                const cleanEx = exHeader.toLowerCase().trim();
                const isMatch = dbField.aliases.some(alias => cleanEx === alias || cleanEx.includes(alias));

                if (isMatch && matchedIndex === -1) {
                    matchedIndex = index;
                }

                optionsHtml += `<option value="${index}">${escapeHtml(exHeader)} (Col ${index + 1})</option>`;
            });

            tr.innerHTML = `
                <td style="font-weight:600; color:var(--text-highlight);">${dbField.label}</td>
                <td>
                    <select id="map-${dbField.key}" class="form-control" style="padding: 6px 12px; font-size: 0.8rem;">
                        ${optionsHtml}
                    </select>
                </td>
            `;
            tbody.appendChild(tr);

            // Select matching header
            if (matchedIndex !== -1) {
                document.getElementById(`map-${dbField.key}`).value = matchedIndex;
            }
        });
    }

    // Render Preview
    function renderPreviewTable() {
        const thead = document.getElementById('preview-thead-tr');
        const tbody = document.getElementById('preview-tbody');
        thead.innerHTML = '';
        tbody.innerHTML = '';

        // Headers preview
        excelHeaders.forEach(h => {
            const th = document.createElement('th');
            th.innerText = h;
            thead.appendChild(th);
        });

        // Rows preview (up to 5)
        const previewLimit = Math.min(excelRows.length, 5);
        for (let i = 0; i < previewLimit; i++) {
            const tr = document.createElement('tr');
            excelRows[i].forEach(cell => {
                const td = document.createElement('td');
                td.innerText = formatExcelCell(cell);
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        }
    }

    function formatExcelCell(cell) {
        if (cell === null || cell === undefined) return '';
        if (cell instanceof Date) {
            return App.formatDate(cell);
        }
        return String(cell);
    }

    // Parse Excel dates safely
    function parseExcelDate(val) {
        if (!val) return null;
        if (val instanceof Date) {
            const yyyy = val.getFullYear();
            const mm = String(val.getMonth() + 1).padStart(2, '0');
            const dd = String(val.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }
        // If it's serial number
        if (typeof val === 'number') {
            const d = new Date((val - 25569) * 86400 * 1000);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        // try direct conversion
        const parsed = new Date(val);
        if (!isNaN(parsed.getTime())) {
            const yyyy = parsed.getFullYear();
            const mm = String(parsed.getMonth() + 1).padStart(2, '0');
            const dd = String(parsed.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }
        return String(val);
    }

    // Perform Bulk Insertion
    async function executeBulkImport() {
        // Collect mapping indexes
        const mappings = {};
        dbFields.forEach(dbField => {
            const selectVal = document.getElementById(`map-${dbField.key}`).value;
            mappings[dbField.key] = selectVal !== "" ? parseInt(selectVal) : null;
        });

        // Validation
        if (mappings['pono'] === null) {
            App.showToast('You must map the PO Number field to continue.', 'error');
            return;
        }

        const totalToImport = excelRows.length;
        if (totalToImport === 0) {
            App.showToast('No rows to import.', 'error');
            return;
        }

        const partyId = parseInt(document.getElementById('import-excel-party').value) || null;
        const partNo = document.getElementById('import-excel-part').value || null;

        App.showLoading(`Importing PO records...`, true);

        const rowsToInsert = [];

        try {
            // Bulk import mapping loop
            for (let i = 0; i < totalToImport; i++) {
                const row = excelRows[i];
                if (!row || !Array.isArray(row)) {
                    continue;
                }

                // Map values
                const data = {};

                dbFields.forEach(dbField => {
                    const idx = mappings[dbField.key];
                    if (idx !== null && idx < row.length) {
                        const rawVal = row[idx];
                        if (dbField.key === 'po_date') {
                            data[dbField.key] = parseExcelDate(rawVal);
                        } else if (dbField.key === 'rate') {
                            const valFloat = parseFloat(rawVal);
                            data[dbField.key] = isNaN(valFloat) ? 0 : valFloat;
                        } else {
                            data[dbField.key] = rawVal !== undefined ? String(rawVal).trim() : '';
                        }
                    } else {
                        data[dbField.key] = dbField.key === 'rate' ? 0 : (dbField.key === 'po_date' ? null : '');
                    }
                });

                // If empty PO number, skip
                if (!data.pono) {
                    continue;
                }

                data.party_id = partyId;
                if (!data.part_no) {
                    data.part_no = partNo;
                }
                rowsToInsert.push(data);
            }

            if (rowsToInsert.length === 0) {
                App.hideLoading();
                App.showToast('No valid rows mapped from the file.', 'error');
                return;
            }

            // Call the batch insertion endpoint
            const res = await App.api('insert_batch', {
                table: 'po_master',
                rows: rowsToInsert
            });

            closeImportModal();
            App.showToast(`Import completed. Successfully loaded ${res.count || rowsToInsert.length} records.`);
        } catch (err) {
            console.error('Import failed:', err);
            App.showToast('Import failed: ' + err.message, 'error');
        } finally {
            App.hideLoading();
            loadPOs();
        }
    }

    function exportPOsToExcel() {
        const query = document.getElementById('grid-search').value.toLowerCase().trim();
        const filtered = poList.filter(item => {
            if (!query) return true;
            return (
                (item.pono && item.pono.toLowerCase().includes(query)) ||
                (item.wound_code && item.wound_code.toLowerCase().includes(query)) ||
                (item.item_no && item.item_no.toLowerCase().includes(query)) ||
                (item.description && item.description.toLowerCase().includes(query))
            );
        }).map(item => {
            const partyObj = partiesList.find(p => p.id == item.party_id);
            const partyName = partyObj ? partyObj.party_name : '-';
            return {
                'ID': item.id,
                'PO Number': item.pono,
                'PO Date': App.formatDate(item.po_date),
                'Part No': item.part_no || '-',
                'Party Name': partyName,
                'Item Number': item.item_no || '-',
                'Wound Code': item.wound_code || '-',
                'Description': item.description || '-',
                'Drawing Number': item.drg_no || '-',
                'Rate': parseFloat(item.rate),
                'Unit': item.unit || '-'
            };
        });

        if (filtered.length === 0) {
            App.showToast('No records to export.', 'warning');
            return;
        }

        const worksheet = XLSX.utils.json_to_sheet(filtered);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, currentTab === 'active' ? "Active POs" : "Archived POs");

        // Autofit columns
        const max_len = filtered.reduce((prev, row) => {
            Object.keys(row).forEach((k, i) => {
                const val = String(row[k]);
                prev[i] = Math.max(prev[i] || 0, val.length, k.length);
            });
            return prev;
        }, []);
        worksheet['!cols'] = max_len.map(w => ({ wch: w + 2 }));

        XLSX.writeFile(workbook, currentTab === 'active' ? "Active_PO_List.xlsx" : "Archived_PO_List.xlsx");
        App.showToast("PO list exported successfully.");
    }

    // HTML escape utility
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
