<?php
// Jagdamba Electrical - MO Jobs Registry (mo.php)
require_once 'sidebar.php';
renderHeader("MO (Jobs)", "mo");
?>

<style>
    .btn-delete-row {
        background: none;
        border: none;
        color: var(--error);
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: var(--transition);
    }
    .btn-delete-row:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #ff5f5f;
    }
    .working-process-text {
        max-width: 300px;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.85rem;
        color: var(--text-muted);
    }
</style>

<div class="module-container" style="grid-template-columns: 420px 1fr;">
    <!-- Form Panel (Left) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-square-plus" style="color: var(--primary);"></i> Create MO Job Card</span>
            <span class="panel-subtitle">Associate machine serials with a working process</span>
        </div>
        <div class="panel-body">
            <form id="mo-form" onsubmit="handleFormSubmit(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="mc_no">Machine Number(s) <span style="color:var(--error);">*</span></label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="mc_no" class="form-control" placeholder="Generate machine serials..." readonly required>
                            <button type="button" class="btn btn-secondary" onclick="openMachineModal()" title="Generate Serial Numbers" style="height: 42px; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-solid fa-gears"></i></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="working_process">Working Process <span style="color:var(--error);">*</span></label>
                        <textarea id="working_process" class="form-control" placeholder="Type here how to make job..." style="height: 150px; resize: vertical;" required></textarea>
                    </div>
                </div>

                <div class="btn-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-check"></i> Save MO Job</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fa-solid fa-arrow-rotate-right"></i> Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid List Panel (Right) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-industry" style="color: var(--secondary);"></i> MO Jobs Directory</span>
            <span class="panel-subtitle" id="record-count-label">0 Jobs</span>
        </div>
        <div class="panel-body">
            <!-- Search field -->
            <div class="search-box-wrapper" style="margin-bottom: 16px;">
                <div class="topbar-search" style="flex: 1; display: flex;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px;"></i>
                    <input type="text" id="grid-search" placeholder="Search by Machine No..." style="width: 100%; padding-left: 40px;" oninput="handleSearch()">
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="custom-table" id="mo-jobs-table">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Machine Number</th>
                            <th>Working Process (Job Card Instructions)</th>
                            <th style="width: 160px;">Date Created</th>
                            <th style="width: 80px; text-align: center;">Actions</th>
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

<!-- VIEW JOB DETAIL POPUP -->
<div class="modal-backdrop" id="view-job-modal">
    <div class="modal-content" style="width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-file-lines" style="color: var(--primary); margin-right: 8px;"></i> Job Card Details</h3>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 20px;">
            <div>
                <label class="form-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Machine Number</label>
                <div id="view-machine-no" style="font-family: monospace; font-size: 1.3rem; font-weight: 700; color: var(--primary); background: var(--primary-glow); border: 1px solid rgba(37,99,235,0.3); border-radius: 8px; padding: 10px 16px; margin-top: 6px; letter-spacing: 1px;"></div>
            </div>
            <div>
                <label class="form-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Working Process / Job Instructions</label>
                <div id="view-working-process" style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; margin-top: 6px; white-space: pre-wrap; word-break: break-word; font-size: 0.95rem; line-height: 1.7; min-height: 80px; color: var(--text-main);"></div>
            </div>
            <div id="view-created-at" style="font-size: 0.78rem; color: var(--text-muted); text-align: right;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
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
                <button type="button" class="modal-tab-btn active" id="modal-tab-var" onclick="switchModalTab('variable')">Variable Mode (Range)</button>
                <button type="button" class="modal-tab-btn" id="modal-tab-std" onclick="switchModalTab('standard')">Standard Mode (Count)</button>
            </div>

            <form id="serial-gen-form">
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

                <!-- Info description -->
                <div style="margin-top: 20px; font-size: 0.8rem; color: var(--text-muted);">
                    Generate a range of serial numbers to be associated with this job card.
                </div>

                <div class="btn-group" style="margin-top: 24px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeMachineModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="executeSerialGeneration()"><i class="fa-solid fa-plus"></i> Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let moJobsList = [];
    let modalMode = 'variable';
    let generatedMachinesTemp = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadMoJobs();
    });

    async function loadMoJobs() {
        App.showLoading('Loading MO job registry...');
        try {
            const res = await App.api('get_mo_jobs');
            moJobsList = res.rows || [];
            populateGrid(moJobsList);
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Jobs`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:32px;">No MO jobs stored in the system.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.title = 'Click to view job details';
            tr.innerHTML = `
                <td><strong style="color:var(--primary); font-family:monospace; font-size:0.95rem;">${escapeHtml(item.machine_no)}</strong></td>
                <td><div class="working-process-text">${escapeHtml(item.working_process || '-')}</div></td>
                <td>${App.formatDate(item.created_at)} <span style="font-size:0.75rem; color:var(--text-muted);">${new Date(item.created_at).toLocaleTimeString('en-IN', {hour: '2-digit', minute:'2-digit'})}</span></td>
                <td style="text-align: center;">
                    <button class="btn-delete-row" onclick="event.stopPropagation(); handleDelete(${item.id}, '${escapeHtml(item.machine_no)}')" title="Delete MO Job">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;
            tr.addEventListener('click', () => openViewModal(item));
            body.appendChild(tr);
        });
    }

    function openViewModal(item) {
        document.getElementById('view-machine-no').textContent = item.machine_no;
        document.getElementById('view-working-process').textContent = item.working_process || 'No instructions provided.';
        document.getElementById('view-created-at').textContent = 'Created: ' + App.formatDate(item.created_at);
        document.getElementById('view-job-modal').classList.add('visible');
    }

    function closeViewModal() {
        document.getElementById('view-job-modal').classList.remove('visible');
    }

    async function handleSearch() {
        const query = document.getElementById('grid-search').value.trim();
        try {
            const res = await App.api('get_mo_jobs', { search: query });
            populateGrid(res.rows || []);
        } catch (err) {
            console.error(err);
        }
    }

    function openMachineModal() {
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

    function generateMachinesLocally(payload) {
        const machines = [];
        const mode = payload.mode;

        if (mode === 'variable') {
            const start = payload.start_no;
            const end = payload.end_no;
            const pattern = /^([a-zA-Z\-_]*?)(\d+)([a-zA-Z\-_]*)$/;
            const matchStart = start.match(pattern);
            const matchEnd = end.match(pattern);

            if (matchStart && matchEnd) {
                const prefix = matchStart[1];
                const suffix = matchStart[3];
                const startNum = parseInt(matchStart[2], 10);
                const endNum = parseInt(matchEnd[2], 10);
                const digitLen = matchStart[2].length;

                if (matchStart[1] !== matchEnd[1] || matchStart[3] !== matchEnd[3]) {
                    throw new Error('Prefix or suffix mismatch between start and end machine number.');
                }
                if (startNum > endNum) {
                    throw new Error('Start number cannot be greater than end number.');
                }
                if ((endNum - startNum) > 1000) {
                    throw new Error('Range is too large. Maximum 1000 machines allowed.');
                }

                for (let i = startNum; i <= endNum; i++) {
                    const paddedNum = String(i).padStart(digitLen, '0');
                    machines.push(prefix + paddedNum + suffix);
                }
            } else {
                throw new Error('Could not parse numeric range from machine serial numbers. Ensure they contain digits.');
            }
        } else if (mode === 'standard') {
            const base = payload.base_no;
            const count = parseInt(payload.count, 10);

            if (!base || count <= 0) {
                throw new Error('Base machine number and count greater than 0 are required.');
            }
            if (count > 1000) {
                throw new Error('Count is too large. Maximum 1000 machines allowed.');
            }

            const pattern = /^([a-zA-Z\-_]*?)(\d+)([a-zA-Z\-_]*)$/;
            const match = base.match(pattern);
            if (match) {
                const prefix = match[1];
                const suffix = match[3];
                const startNum = parseInt(match[2], 10);
                const digitLen = match[2].length;

                for (let i = 0; i < count; i++) {
                    const paddedNum = String(startNum + i).padStart(digitLen, '0');
                    machines.push(prefix + paddedNum + suffix);
                }
            } else {
                for (let i = 1; i <= count; i++) {
                    machines.push(base + '-' + i);
                }
            }
        }
        return machines;
    }

    function executeSerialGeneration() {
        const payload = { mode: modalMode };

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

        try {
            generatedMachinesTemp = generateMachinesLocally(payload);

            if (generatedMachinesTemp.length > 0) {
                let summary = '';
                if (modalMode === 'variable') {
                    summary = `${payload.start_no} - ${payload.end_no} (${generatedMachinesTemp.length} machines)`;
                } else {
                    summary = `${payload.base_no} [x${payload.count}] (${generatedMachinesTemp.length} machines)`;
                }

                document.getElementById('mc_no').value = summary;
                App.showToast(`Locally generated ${generatedMachinesTemp.length} machines serials.`, 'success');
            }
            closeMachineModal();
        } catch (err) {
            App.showToast(err.message, 'error');
        }
    }

    async function handleFormSubmit(e) {
        e.preventDefault();

        const working_process = document.getElementById('working_process').value.trim();

        if (generatedMachinesTemp.length === 0) {
            App.showToast('Please generate machine numbers first.', 'error');
            return;
        }
        if (!working_process) {
            App.showToast('Working Process description is required.', 'error');
            return;
        }

        App.showLoading('Saving MO jobs...');
        try {
            await App.api('save_mo_job', {
                machines: generatedMachinesTemp,
                working_process: working_process
            });

            App.showToast(`MO Job(s) saved successfully for ${generatedMachinesTemp.length} machines.`, 'success');
            resetForm();
            loadMoJobs();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    function resetForm() {
        document.getElementById('mo-form').reset();
        document.getElementById('mc_no').value = '';
        generatedMachinesTemp = [];
    }

    async function handleDelete(id, machine_no) {
        if (!confirm(`Are you sure you want to delete MO job for machine "${machine_no}"?`)) {
            return;
        }

        App.showLoading('Deleting MO job...');
        try {
            await App.api('delete_mo_job', { id: id });
            App.showToast(`Successfully deleted MO job for machine "${machine_no}".`, 'success');
            loadMoJobs();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
</script>

<?php renderFooter(); ?>
