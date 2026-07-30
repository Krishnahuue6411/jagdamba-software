<?php
// Sahara Electrical - Transporter Master (transport_master.php)
require_once 'sidebar.php';
renderHeader("Transporter Master", "transporters");
?>

<div class="module-container">
    <!-- Form Panel (Left) -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-truck" style="color: var(--primary);"></i> <span id="form-action-title">Add Transporter</span></span>
            <span class="panel-subtitle">Manage Vehicles</span>
        </div>
        <div class="panel-body">
            <form id="transporter-form" onsubmit="handleFormSubmit(event)">
                <!-- Hidden ID for Editing -->
                <input type="hidden" id="transporter-id" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="transport_name">Transporter Name <span style="color:var(--error);">*</span></label>
                        <input type="text" id="transport_name" class="form-control" placeholder="e.g. Blue Dart Logistics" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="vech_no">Vehicle Number <span style="color:var(--error);">*</span></label>
                        <input type="text" id="vech_no" class="form-control" placeholder="e.g. MH-12-PQ-5678" required>
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
            <span class="panel-title"><i class="fa-solid fa-road" style="color: var(--secondary);"></i> Transporter & Vehicle Registry</span>
            <span class="panel-subtitle" id="record-count-label">0 Records</span>
        </div>
        <div class="panel-body">
            <!-- Search -->
            <div class="search-box-wrapper">
                <div class="topbar-search" style="flex: 1; display: flex;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px;"></i>
                    <input type="text" id="grid-search" placeholder="Search by Transporter Name or Vehicle Number..." style="width: 100%;" oninput="filterGrid()">
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="custom-table" id="transporter-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Transporter Name</th>
                            <th>Vehicle Number</th>
                            <th>Created At</th>
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
    let transportersList = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadTransporters();
    });

    // Fetch Transporters
    async function loadTransporters() {
        App.showLoading('Loading transporters...');
        try {
            const res = await App.api('list', { table: 'transport_master' });
            transportersList = res.rows || [];
            populateGrid(transportersList);
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Populate Table
    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Records`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:32px;">No transporters found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.dataset.id = item.id;
            tr.onclick = () => selectRow(tr, item);

            // Highlight active edit
            const activeId = document.getElementById('transporter-id').value;
            if (activeId && parseInt(activeId) === item.id) {
                tr.classList.add('selected');
            }

            tr.innerHTML = `
                <td>${item.id}</td>
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.transport_name)}</strong></td>
                <td><span class="badge badge-success">${escapeHtml(item.vech_no)}</span></td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">${App.formatDate(item.created_at)}</td>
            `;
            body.appendChild(tr);
        });
    }

    // Client-side text filter
    function filterGrid() {
        const query = document.getElementById('grid-search').value.toLowerCase().trim();
        if (!query) {
            populateGrid(transportersList);
            return;
        }

        const filtered = transportersList.filter(item => {
            return (
                (item.transport_name && item.transport_name.toLowerCase().includes(query)) ||
                (item.vech_no && item.vech_no.toLowerCase().includes(query))
            );
        });

        populateGrid(filtered);
    }

    // Load selected row into Form
    function selectRow(trElement, item) {
        document.querySelectorAll('#transporter-table tbody tr').forEach(r => r.classList.remove('selected'));
        trElement.classList.add('selected');

        document.getElementById('transporter-id').value = item.id;
        document.getElementById('transport_name').value = item.transport_name;
        document.getElementById('vech_no').value = item.vech_no;

        document.getElementById('form-action-title').innerText = "Edit Transporter";
        document.getElementById('btn-delete').style.display = "block";
    }

    // Reset Form
    function resetForm() {
        document.getElementById('transporter-form').reset();
        document.getElementById('transporter-id').value = "";
        document.getElementById('form-action-title').innerText = "Add Transporter";
        document.getElementById('btn-delete').style.display = "none";
        document.querySelectorAll('#transporter-table tbody tr').forEach(r => r.classList.remove('selected'));
    }

    // Save (Insert or Update)
    async function handleFormSubmit(e) {
        e.preventDefault();

        const id = document.getElementById('transporter-id').value;
        const data = {
            transport_name: document.getElementById('transport_name').value.trim(),
            vech_no: document.getElementById('vech_no').value.trim().toUpperCase()
        };

        if (!data.transport_name || !data.vech_no) {
            App.showToast('Transporter Name and Vehicle Number are required.', 'error');
            return;
        }

        App.showLoading('Saving transporter...');
        try {
            if (id) {
                await App.api('update', {
                    table: 'transport_master',
                    id: parseInt(id),
                    data: data
                });
                App.showToast('Transporter details updated.');
            } else {
                await App.api('insert', {
                    table: 'transport_master',
                    data: data
                });
                App.showToast('Transporter added successfully.');
            }

            resetForm();
            await loadTransporters();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Delete
    async function handleDelete() {
        const id = document.getElementById('transporter-id').value;
        if (!id) return;

        if (confirm('Delete this transporter profile? This may affect linked invoices.')) {
            App.showLoading('Deleting transporter...');
            try {
                await App.api('delete', {
                    table: 'transport_master',
                    id: parseInt(id)
                });
                App.showToast('Transporter removed successfully.');
                resetForm();
                await loadTransporters();
            } catch (err) {
                console.error(err);
            } finally {
                App.hideLoading();
            }
        }
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
