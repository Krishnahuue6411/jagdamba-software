<?php
// Sahara Electrical - Invoices List Report (invoices_list.php)
require_once 'sidebar.php';
renderHeader("All Previous Invoices", "invoices_list");
?>
<?php if (isset($_SESSION['signature_msg'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            App.showToast("<?php echo $_SESSION['signature_msg']['text']; ?>", "<?php echo $_SESSION['signature_msg']['type']; ?>");
        });
    </script>
    <?php unset($_SESSION['signature_msg']); ?>
<?php endif; ?>

<!-- Digital Signature Upload Manager -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header" style="background-color: rgba(255, 255, 255, 0.01);">
        <span class="panel-title"><i class="fa-solid fa-file-signature" style="color: var(--primary);"></i> Authorized Digital Signature</span>
        <span class="panel-subtitle">Upload signatory signature image (JPG/PNG) to print automatically on invoices</span>
    </div>
    <div class="panel-body">
        <form action="upload_signature.php" method="POST" enctype="multipart/form-data" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; margin: 0; min-width: 250px;">
                <input type="file" name="signature_img" class="form-control" accept="image/png, image/jpeg, image/jpg" required style="padding: 6px; background-color: var(--bg-main);">
            </div>
            <button type="submit" class="btn btn-primary" style="height: 38px; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Signature</button>
            <?php if (file_exists(__DIR__ . '/signature.png')): ?>
                <div style="display: flex; align-items: center; gap: 8px; margin-left: 16px;">
                    <span style="font-size: 0.8rem; color: var(--success); font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Signature Active</span>
                    <img src="signature.png?t=<?php echo time(); ?>" alt="Active Signature" style="height: 38px; background: white; padding: 2px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>


<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header" style="background-color: rgba(255, 255, 255, 0.01);">
        <span class="panel-title"><i class="fa-solid fa-filter" style="color: var(--primary);"></i> Filter Dispatched Invoices</span>
        <span class="panel-subtitle">Filter by Date Range and Keyword</span>
    </div>
    <div class="panel-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
            <div class="form-group">
                <label class="form-label" for="filter-start-date">Start Date</label>
                <input type="date" id="filter-start-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-end-date">End Date</label>
                <input type="date" id="filter-end-date" class="form-control" onchange="applyFilters()">
            </div>

            <div class="form-group">
                <label class="form-label" for="filter-party">Filter by Party</label>
                <select id="filter-party" class="form-control" onchange="applyFilters()" style="background-color: var(--bg-main);">
                    <option value="">-- All Parties --</option>
                </select>
            </div>

            <div class="form-group" style="flex: 2;">
                <label class="form-label" for="filter-search">Quick Search</label>
                <div class="topbar-search" style="display: flex; width: 100%;">
                    <i class="fa-solid fa-magnifying-glass" style="left: 14px;"></i>
                    <input type="text" id="filter-search" placeholder="Invoice No, PO No, Wound Code, Vehicle..." style="width: 100%; padding-left: 40px;" oninput="applyFilters()">
                </div>
            </div>

            <div>
                <button class="btn btn-secondary" onclick="resetFilters()" style="width: 100%;"><i class="fa-solid fa-xmark"></i> Reset Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Stats Bar Summary -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Invoices Issued</span>
            <span class="stat-value" id="stats-total-count">0</span>
        </div>
        <div class="stat-icon-box blue">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Dispatch Quantity</span>
            <span class="stat-value" id="stats-total-qty">0.00</span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-dolly"></i>
        </div>
    </div>

    <div class="stat-card" style="grid-column: span 2;">
        <div class="stat-info">
            <span class="stat-label">Total Net Dispatched Value</span>
            <span class="stat-value" style="color: var(--success); text-shadow: 0 0 10px rgba(16,185,129,0.2);">₹<span id="stats-total-value">0.00</span></span>
        </div>
        <div class="stat-icon-box green">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
    </div>
</div>

<!-- Grid List Panel -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="fa-solid fa-table-list" style="color: var(--secondary);"></i> Generated Invoices Registry</span>
        <span class="panel-subtitle" id="record-count-label">0 Records</span>
    </div>
    <div class="panel-body">
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 12px;">
            💡 Click on any invoice row below to open its print-preview document template.
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="custom-table" id="invoices-list-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Invoice Date</th>
                        <th>PO Number</th>
                        <th>Wound Code</th>
                        <th>HSN Code</th>
                        <th style="text-align: right;">Quantity</th>
                        <th>UOM</th>
                        <th style="text-align: right;">Rate (₹)</th>
                        <th style="text-align: right;">Net Total (₹)</th>
                        <th>Vehicle No</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <!-- Populated dynamically -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let invoicesCache = [];
    let partiesList = [];

    document.addEventListener('DOMContentLoaded', async () => {
        await loadParties();
        loadInvoices();
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

    // Fetch Invoices
    async function loadInvoices() {
        App.showLoading('Loading invoice database...');
        try {
            const res = await App.api('list', { table: 'tax_invoice' });
            invoicesCache = res.rows || [];
            applyFilters();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Apply multiple filters locally (real-time responsiveness)
    function applyFilters() {
        const startVal = document.getElementById('filter-start-date').value;
        const endVal = document.getElementById('filter-end-date').value;
        const partyVal = document.getElementById('filter-party').value;
        const searchVal = document.getElementById('filter-search').value.toLowerCase().trim();

        const startDate = startVal ? new Date(startVal) : null;
        const endDate = endVal ? new Date(endVal) : null;

        // Set timezone offsets to midnight for strict date comparison
        if (startDate) startDate.setHours(0,0,0,0);
        if (endDate) endDate.setHours(23,59,59,999);

        const filtered = invoicesCache.filter(item => {
            // 1. Party filter
            if (partyVal && item.party_id != partyVal) return false;

            // 2. Date Range filter
            const invDate = new Date(item.inv_date);
            invDate.setHours(0,0,0,0);

            if (startDate && invDate < startDate) return false;
            if (endDate && invDate > endDate) return false;

            // 3. Text Search filter
            if (searchVal) {
                const matchInv = item.inv_no && item.inv_no.toLowerCase().includes(searchVal);
                const matchPO = item.po_no && item.po_no.toLowerCase().includes(searchVal);
                const matchWound = item.wound_code && item.wound_code.toLowerCase().includes(searchVal);
                const matchVeh = item.vehicle_no && item.vehicle_no.toLowerCase().includes(searchVal);
                const matchDwg = item.drg_no && item.drg_no.toLowerCase().includes(searchVal);
                return matchInv || matchPO || matchWound || matchVeh || matchDwg;
            }

            return true;
        });

        populateGrid(filtered);
        calculateStats(filtered);
    }

    // Populate Table
    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Records`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="11" style="text-align:center; color:var(--text-muted); padding:32px;">No matching invoices found.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.title = "Click to View/Print Invoice Details";
            tr.onclick = () => {
                window.open(`print.php?inv_no=${encodeURIComponent(item.inv_no)}`, '_blank');
            };

            tr.innerHTML = `
                <td><strong style="color:var(--success);">${escapeHtml(item.inv_no)}</strong></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(item.inv_date)}</td>
                <td>${escapeHtml(item.po_no || '-')}</td>
                <td><span style="font-family: monospace; font-size:0.85rem; background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px; border:1px solid var(--border-color);">${escapeHtml(item.wound_code)}</span></td>
                <td>${escapeHtml(item.hsn_code || '9988')}</td>
                <td style="text-align: right; font-weight:700;">${App.formatDecimal(item.qty)}</td>
                <td style="color:var(--text-muted); font-size:0.85rem;">${escapeHtml(item.uom || 'NOS')}</td>
                <td style="text-align: right;">${App.formatDecimal(item.rate)}</td>
                <td style="text-align: right; font-weight:700; color:var(--text-highlight);">₹${App.formatDecimal(item.net_total)}</td>
                <td><span class="badge badge-pending">${escapeHtml(item.vehicle_no)}</span></td>
                <td>
                    <button class="btn" style="background:var(--primary); color:white; border:none; padding:4px 10px; border-radius:4px; font-size:0.75rem; font-weight:bold; cursor:pointer;" onclick="event.stopPropagation(); window.location.href='tax_invoice.php?edit=${encodeURIComponent(item.inv_no)}'">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                    <button class="btn" style="background:var(--error); color:white; border:none; padding:4px 10px; border-radius:4px; font-size:0.75rem; font-weight:bold; cursor:pointer; margin-left: 6px;" onclick="event.stopPropagation(); deleteInvoiceRecord('${escapeHtml(item.inv_no)}')">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });
    }

    // Calculate sum statistics
    function calculateStats(data) {
        const totalCount = data.length;
        let totalQty = 0;
        let totalValue = 0;

        data.forEach(item => {
            totalQty += parseFloat(item.qty || 0);
            totalValue += parseFloat(item.net_total || 0);
        });

        document.getElementById('stats-total-count').innerText = totalCount;
        document.getElementById('stats-total-qty').innerText = App.formatDecimal(totalQty);
        document.getElementById('stats-total-value').innerText = App.formatDecimal(totalValue);
    }

    // Reset all filters
    function resetFilters() {
        document.getElementById('filter-start-date').value = '';
        document.getElementById('filter-end-date').value = '';
        document.getElementById('filter-party').value = '';
        document.getElementById('filter-search').value = '';
        applyFilters();
    }

    async function deleteInvoiceRecord(invNo) {
        // Double confirmation for safety
        const c1 = confirm(`⚠️ Are you sure you want to delete Invoice: ${invNo}? This will revert all stock deductions and restore raw material balances.`);
        if (!c1) return;

        const c2 = confirm(`🚨 FINAL CONFIRMATION: Deleting Invoice: ${invNo} is irreversible. Press OK to permanently delete.`);
        if (!c2) return;

        App.showLoading(`Deleting invoice ${invNo}...`);
        try {
            const res = await App.api('delete_invoice', { inv_no: invNo });
            if (res.ok) {
                App.showToast(`Invoice ${invNo} successfully deleted.`);
                await loadInvoices();
            } else {
                App.showToast(res.error || 'Failed to delete invoice.', 'error');
            }
        } catch (err) {
            console.error("Delete invoice error:", err);
            App.showToast(err.message || 'Failed to delete invoice.', 'error');
        } finally {
            App.hideLoading();
        }
    }

    // HTML escape helper
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
