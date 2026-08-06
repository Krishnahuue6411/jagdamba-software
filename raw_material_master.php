<?php
// Jagdamba Electrical - Raw Material Master (raw_material_master.php)
require_once 'sidebar.php';
renderHeader("Raw Material Master", "raw_materials");
?>

<div class="module-container">
    <!-- Form Panel (Left) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-square-plus" style="color: var(--primary);"></i> <span id="form-action-title">Add Raw Material</span></span>
            <span class="panel-subtitle">Ctrl+S to Save</span>
        </div>
        <div class="panel-body">
            <form id="material-form" onsubmit="handleFormSubmit(event)">
                <!-- Hidden fields to track edit state -->
                <input type="hidden" id="item-id" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="m_code">Material Code <span style="color:var(--error);">*</span></label>
                        <input type="text" id="m_code" class="form-control" placeholder="e.g. COP-WIRE-22" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="m_description">Description</label>
                        <textarea id="m_description" class="form-control" placeholder="Detailed material specification..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="head">Head / Category</label>
                        <input type="text" id="head" class="form-control" placeholder="e.g. COPPER WIRE, KIT, STAMPING">
                    </div>

                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label" for="unit">Unit</label>
                        <input type="text" id="unit" class="form-control" placeholder="e.g. NOS, PCS" value="NOS">
                    </div>

                    <div class="form-group" style="margin-top: 12px;">
                        <label class="form-label" for="part_no">Part Classification</label>
                        <select id="part_no" class="form-control" style="background-color: var(--bg-main);">
                            <option value="">All / Generic</option>
                            <option value="M311">M311 Only</option>
                            <option value="M314">M314 Only</option>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                    <button type="button" id="btn-delete" class="btn btn-danger" onclick="handleDelete()" style="display: none;"><i class="fa-solid fa-trash-can"></i> Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fa-solid fa-xmark"></i> Clear</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid List Panel (Right) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-list-check" style="color: var(--secondary);"></i> Material Inventory Directory</span>
            <span class="panel-subtitle" id="record-count-label">0 Records</span>
        </div>
        <div class="panel-body">
            <!-- Search & Filters -->
            <div class="search-box-wrapper" style="display: flex; gap: 12px; margin-bottom: 16px;">
                <div class="topbar-search" style="flex: 1; display: flex;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px;"></i>
                    <input type="text" id="grid-search" placeholder="Search by Code, Description, or Head..." style="width: 100%;" oninput="filterGrid()">
                </div>
                <div style="width: 180px;">
                    <select id="grid-part-filter" class="form-control" onchange="filterGrid()" style="background-color: var(--bg-card); border: 1px solid var(--border-color); padding: 8px 12px; height: 100%;">
                        <option value="ALL">Show All Parts</option>
                        <option value="M311">M311 Only</option>
                        <option value="M314">M314 Only</option>
                        <option value="GENERIC">Generic Only</option>
                    </select>
                </div>
            </div>

            <!-- Stats Bar -->
            <div style="display: flex; gap: 24px; padding: 10px 16px; background-color: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 16px; font-size: 0.8rem; color: var(--text-muted);">
                <div>Filtered Rows: <strong id="filtered-rows-count" style="color: var(--secondary);">0</strong></div>
                <div style="display: none;">Total Stock Balance: <strong id="total-stock-weight">0.00</strong></div>
            </div>




            <!-- Table -->
            <div class="table-container">
                <table class="custom-table" id="material-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>M Code</th>
                            <th>Description</th>
                            <th>Head</th>
                            <th>Part</th>
                            <th>Unit</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                        <!-- Data will be populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let materialsList = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadMaterials();

        // Ctrl+S Keyboard Shortcut
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.getElementById('material-form').dispatchEvent(new Event('submit'));
            }
        });
    });

    // Fetch materials from API
    async function loadMaterials() {
        App.showLoading('Fetching raw materials...');
        try {
            const res = await App.api('list', { table: 'raw_material' });
            materialsList = res.rows || [];
            populateGrid(materialsList);
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Populate materials list into Table
    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Records`;
        document.getElementById('filtered-rows-count').innerText = data.length;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:32px;">No raw material records found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.dataset.id = item.id;
            tr.onclick = () => selectRow(tr, item);

            // Check if selected
            const activeId = document.getElementById('item-id').value;
            if (activeId && parseInt(activeId) === item.id) {
                tr.classList.add('selected');
            }

            tr.innerHTML = `
                <td>${item.id}</td>
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.m_code)}</strong></td>
                <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(item.m_description || '')}">${escapeHtml(item.m_description || '-')}</td>
                <td><span class="badge badge-pending">${escapeHtml(item.head || '-')}</span></td>
                <td><span style="color:var(--primary); font-weight:600;">${escapeHtml(item.part_no || 'Generic')}</span></td>
                <td style="color:var(--text-muted);">${escapeHtml(item.unit || '')}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Filter table rows locally
    function filterGrid() {
        const query = document.getElementById('grid-search').value.toLowerCase().trim();
        const partFilter = document.getElementById('grid-part-filter').value;

        const filtered = materialsList.filter(item => {
            // Part filter mapping
            let matchesPart = true;
            if (partFilter === 'M311') {
                matchesPart = (item.part_no === 'M311');
            } else if (partFilter === 'M314') {
                matchesPart = (item.part_no === 'M314');
            } else if (partFilter === 'GENERIC') {
                matchesPart = (!item.part_no || item.part_no === '');
            }

            // Text search mapping
            let matchesText = true;
            if (query) {
                matchesText = (
                    (item.m_code && item.m_code.toLowerCase().includes(query)) ||
                    (item.m_description && item.m_description.toLowerCase().includes(query)) ||
                    (item.head && item.head.toLowerCase().includes(query))
                );
            }

            return matchesPart && matchesText;
        });

        populateGrid(filtered);
    }

    // Load row data into form for Editing
    function selectRow(trElement, item) {
        // Highlight active row
        document.querySelectorAll('#material-table tbody tr').forEach(r => r.classList.remove('selected'));
        trElement.classList.add('selected');

        // Fill form
        document.getElementById('item-id').value = item.id;
        document.getElementById('m_code').value = item.m_code;
        document.getElementById('m_description').value = item.m_description || '';
        document.getElementById('head').value = item.head || '';
        document.getElementById('unit').value = item.unit || '';
        document.getElementById('part_no').value = item.part_no || '';

        // UI modifications
        document.getElementById('form-action-title').innerText = "Edit Raw Material";
        document.getElementById('btn-delete').style.display = "block";
    }

    // Reset Form to Add Mode
    function resetForm() {
        document.getElementById('material-form').reset();
        document.getElementById('item-id').value = "";
        document.getElementById('form-action-title').innerText = "Add Raw Material";
        document.getElementById('btn-delete').style.display = "none";
        document.querySelectorAll('#material-table tbody tr').forEach(r => r.classList.remove('selected'));
    }

    // Handle Form submission for Insert / Update
    async function handleFormSubmit(e) {
        e.preventDefault();

        const id = document.getElementById('item-id').value;
        const data = {
            m_code: document.getElementById('m_code').value.trim(),
            m_description: document.getElementById('m_description').value.trim(),
            head: document.getElementById('head').value.trim(),
            unit: document.getElementById('unit').value.trim(),
            part_no: document.getElementById('part_no').value || null
        };

        if (!data.m_code) {
            App.showToast('Material Code is required.', 'error');
            return;
        }

        App.showLoading('Saving raw material...');
        try {
            if (id) {
                // Update operation
                await App.api('update', {
                    table: 'raw_material',
                    id: parseInt(id),
                    data: data
                });
                App.showToast('Material updated successfully.');
            } else {
                // Insert operation
                await App.api('insert', {
                    table: 'raw_material',
                    data: data
                });
                App.showToast('Material saved successfully.');
            }

            resetForm();
            await loadMaterials();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Handle Delete
    async function handleDelete() {
        const id = document.getElementById('item-id').value;
        if (!id) return;

        if (confirm('Are you sure you want to delete this material? This action cannot be undone.')) {
            App.showLoading('Deleting raw material...');
            try {
                await App.api('delete', {
                    table: 'raw_material',
                    id: parseInt(id)
                });
                App.showToast('Material deleted successfully.');
                resetForm();
                await loadMaterials();
            } catch (err) {
                console.error(err);
            } finally {
                App.hideLoading();
            }
        }
    }

    // Helper to escape HTML characters
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
