<?php
// Sahara Electrical - Tax Invoice Module (tax_invoice.php)
require_once 'sidebar.php';
renderHeader("Create Tax Invoice", "tax_invoice");
?>

<div class="module-container" id="tax-invoice-container" style="grid-template-columns: 1fr;">
    <!-- Form Panel (Left) -->
    <div class="panel" id="form-panel">
        <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span class="panel-title"><i class="fa-solid fa-file-invoice-dollar" style="color: var(--primary);"></i> Invoice Builder</span>
                <span class="panel-subtitle" style="display: block;">Staging Area for Multi-Item Invoice Creation</span>
            </div>
            <button type="button" class="btn btn-primary" id="btn-toggle-history" onclick="toggleHistoryPanel()" title="Toggle Previous Invoices History Table" style="padding: 6px 14px; font-size: 0.8rem; height: 34px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; white-space: nowrap;">
                <i class="fa-solid fa-eye" id="toggle-history-icon"></i>
                <span id="toggle-history-text">Show Previous Invoices</span>
            </button>
        </div>
        <div class="panel-body" style="padding: 24px;">
            <form id="invoice-form" onsubmit="handleInvoiceSubmit(event)">
                <div class="form-grid">
                    <!-- Section 1: Invoice Header Details -->
                    <h3 style="font-size: 0.95rem; color: var(--primary); margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;"><i class="fa-solid fa-file-invoice"></i> Invoice Header Details</h3>
                    <div class="form-grid two-col" style="margin-bottom: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="inv_no">Invoice Number <span style="color:var(--error);">*</span></label>
                            <input type="text" id="inv_no" class="form-control" placeholder="e.g. INV-1002" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="inv_date">Invoice Date <span style="color:var(--error);">*</span></label>
                            <input type="date" id="inv_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-grid two-col" style="margin-bottom: 16px;">
                        <input type="hidden" id="part_no" value="">
                        <div class="form-group">
                            <label class="form-label" for="invoice_party">Select Party <span style="color:var(--error);">*</span></label>
                            <select id="invoice_party" class="form-control" required style="background-color: var(--bg-main);" onchange="handlePartyChange()">
                                <option value="">[Select Party]</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="vehicle_no">Vehicle Number <span style="color:var(--error);">*</span></label>
                            <select id="vehicle_no" class="form-control" required>
                                <option value="">[Select Vehicle]</option>
                                <!-- Loaded via JS -->
                            </select>
                        </div>
                    </div>

                    <div class="form-grid two-col" style="margin-bottom: 24px;">
                        <input type="hidden" id="asn_no" value="">
                        <div class="form-group">
                            <label class="form-label" for="hsn_code">HSN Code</label>
                            <input type="text" id="hsn_code" class="form-control" value="9988">
                        </div>
                        <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end;">
                            <label class="form-label" for="include_sign" style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px; user-select: none;">
                                <input type="checkbox" id="include_sign" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary);">
                                <span style="font-weight: 600;">Include Digital Signature</span>
                            </label>
                        </div>
                    </div>

                    <!-- Section 2: Itemized Entry Details -->
                    <h3 style="font-size: 0.95rem; color: var(--secondary); margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fa-solid fa-cart-plus"></i> Add Item Details</span>
                        <span id="items-count-badge" style="font-size: 0.75rem; background: rgba(255,255,255,0.08); padding: 2px 8px; border-radius: 12px; color: var(--text-highlight);">0/6 Items Added</span>
                    </h3>

                    <div class="form-grid two-col" style="margin-bottom: 12px;">
                        <div class="form-group autocomplete-wrapper">
                            <label class="form-label" for="wound_code">Wound Code <span style="color:var(--error);">*</span></label>
                            <input type="text" id="wound_code" class="form-control" placeholder="PO Wound Code..." autocomplete="off" oninput="handlePOSearch(this.value); validateInvoiceInputs(); syncWoundCodeToDrawingNumber()" onblur="validateInvoiceInputs(); syncWoundCodeToDrawingNumber()">
                            <div id="wound_code-suggestions" class="autocomplete-dropdown" style="display: none;"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="item_sr_no">PO Sr No / Item No</label>
                            <input type="text" id="item_sr_no" class="form-control" placeholder="e.g. 10">
                        </div>
                    </div>

                    <div class="form-grid two-col" style="margin-bottom: 12px;">
                        <div class="form-group">
                            <label class="form-label" for="drg_no">Drawing Number</label>
                            <input type="text" id="drg_no" class="form-control" >
                        </div>
                        <div class="form-group" style="padding-top: 0;">
                            <fieldset style="border: 1px solid var(--border-color); border-radius: 8px; padding: 6px 12px; background: rgba(255,255,255,0.01);">
                                <legend style="font-size: 0.7rem; color: var(--primary); font-weight: 700; padding: 0 6px; text-transform: uppercase;">PO References</legend>
                                <div style="display: flex; gap: 16px; font-size: 0.8rem; margin-top: 2px;">
                                    <div>No: <strong id="txt-po-no" style="color: var(--text-highlight);">-</strong></div>
                                    <div>Date: <strong id="txt-po-date" style="color: var(--text-highlight);">-</strong></div>
                                </div>
                                <input type="hidden" id="po_no">
                                <input type="hidden" id="po_date">
                            </fieldset>
                        </div>
                    </div>

                    <!-- Machine Selection Grid -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label class="form-label" style="margin-bottom: 0;">Select Machines to Dispatch <span id="selected-mc-counter" style="color: var(--secondary); font-weight: bold;">(0 Checked)</span></label>
                            <div style="position: relative; display: inline-block;">
                                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.75rem; color: var(--text-muted);"></i>
                                <input type="text" id="machine-filter" placeholder="Search Machine/Challan..." oninput="filterPendingMachines()" onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 5px var(--primary-glow)';" onblur="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';" style="width: 170px; height: 28px; padding-left: 28px; padding-right: 8px; font-size: 0.8rem; border-radius: 6px; border: 1px solid var(--border-color); background-color: rgba(255,255,255,0.03); color: var(--text-main); outline: none; transition: var(--transition);">
                            </div>
                        </div>
                        <div class="machine-select-grid" id="pending-machines-grid" style="max-height: 120px; overflow-y: auto;">
                            <!-- Populated with checkboxes -->
                            <div style="grid-column: span 3; text-align: center; color: var(--text-muted); font-size: 0.8rem; padding: 12px;">Please select a party first to view pending machines.</div>
                        </div>
                    </div>

                    <div class="form-grid three-col" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="qty">Qty <span style="color:var(--error);">*</span></label>
                            <input type="number" id="qty" class="form-control" step="0.01" oninput="calculateInvoiceValues(); validateInvoiceInputs(); handleMachineSelectionChange()">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="uom">UOM</label>
                            <input type="text" id="uom" class="form-control" value="NOS">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="rate">Rate (₹)</label>
                            <input type="number" id="rate" class="form-control" step="0.01" min="0" value="0.00" oninput="calculateInvoiceValues()">
                        </div>
                    </div>

                    <div style="display: flex; gap: 16px; align-items: flex-start; margin-bottom: 24px;">
                        <fieldset style="border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; flex: 1; background: rgba(0,0,0,0.15);">
                            <legend style="font-size: 0.7rem; color: var(--success); font-weight: 700; padding: 0 8px; text-transform: uppercase;">Current Item Calculations</legend>
                            <div class="form-grid two-col" style="gap: 8px; font-size: 0.8rem;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Sub Total:</span>
                                    <strong>₹<span id="txt-subtotal">0.00</span></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>CGST (9%):</span>
                                    <strong style="color: var(--text-muted);">₹<span id="txt-cgst">0.00</span></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>SGST (9%):</span>
                                    <strong style="color: var(--text-muted);">₹<span id="txt-sgst">0.00</span></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; border-top: 1px dashed var(--border-color); padding-top: 4px; color: var(--primary);">
                                    <span>Net:</span>
                                    <strong>₹<span id="txt-nettotal">0.00</span></strong>
                                </div>
                            </div>
                        </fieldset>

                        <div style="display: flex; flex-direction: column; gap: 8px; align-self: center;">
                            <button type="button" class="btn btn-secondary" id="btn-add-item" onclick="addItemToList()" style="height: 44px; padding: 0 20px; font-weight: 700; background: linear-gradient(135deg, var(--secondary), #8b5cf6); color: white; border: none; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.25); cursor: pointer;"><i class="fa-solid fa-plus"></i> Add Item</button>
                        </div>
                    </div>

                    <!-- Staging Items List Section -->
                    <div id="staging-list-container" style="display: none; margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: rgba(255,255,255,0.01);">
                        <div style="padding: 10px 14px; background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color); font-weight: bold; font-size: 0.85rem; color: var(--text-highlight);">Invoice Items List</div>
                        <div class="table-container" style="max-height: 200px; overflow-y: auto;">
                            <table class="custom-table" style="margin: 0; border: none;">
                                <thead>
                                    <tr>
                                        <th>Sr</th>
                                        <th>Wound Code</th>
                                        <th>PO Info</th>
                                        <th>Dwg / HSN</th>
                                        <th style="text-align: right;">Qty</th>
                                        <th style="text-align: right;">Rate</th>
                                        <th style="text-align: right;">Amount</th>
                                        <th style="text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="staging-grid-body">
                                    <!-- Populated in JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Invoice Grand Totals Section -->
                    <fieldset id="grand-totals-fieldset" style="display: none; border: 1.5px solid var(--primary); border-radius: 8px; padding: 14px; margin-bottom: 15px; background: rgba(255, 159, 0, 0.03);">
                        <legend style="font-size: 0.75rem; color: var(--primary); font-weight: 800; padding: 0 8px; text-transform: uppercase;">Invoice Grand Totals</legend>
                        <div class="form-grid two-col" style="gap: 16px; font-size: 0.9rem;">
                            <div style="display: flex; justify-content: space-between;">
                                <span>Grand Sub Total:</span>
                                <strong>₹<span id="grand-subtotal">0.00</span></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Grand CGST (9%):</span>
                                <strong style="color: var(--text-muted);">₹<span id="grand-cgst">0.00</span></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Grand SGST (9%):</span>
                                <strong style="color: var(--text-muted);">₹<span id="grand-sgst">0.00</span></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; border-top: 1.5px solid var(--border-color); padding-top: 8px; font-size: 1.1rem; color: var(--success);">
                                <span>Grand Net Total:</span>
                                <strong>₹<span id="grand-nettotal">0.00</span></strong>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <!-- Validation Errors Alert Box -->
                <div id="validation-errors" style="display: none; background-color: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 8px; padding: 14px 18px; margin-top: 15px; color: var(--error); font-size: 0.85rem; line-height: 1.45;">
                </div>

                <div class="btn-group" style="margin-top: 24px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-print"></i> Save & Print Invoice</button>
                    <button type="button" id="btn-cancel-invoice" class="btn btn-danger" style="display: none;" onclick="handleCancelInvoice()"><i class="fa-solid fa-trash-can"></i> Delete Invoice</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fa-solid fa-arrow-rotate-left"></i> Reset Form</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Panel: Past Invoices Grid -->
    <div class="panel" id="history-panel" style="display: none;">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-receipt" style="color: var(--secondary);"></i> Generated Invoices</span>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="panel-subtitle" id="record-count-label" style="font-size:0.85rem; font-weight:500; margin:0;">0 Records</span>
                <div class="topbar-search" style="display: inline-flex; width: 180px;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="grid-search" placeholder="Search Invoices..." oninput="filterGrid()" style="height: 30px; font-size: 0.8rem; padding-left: 32px;">
                </div>
            </div>
        </div>
        <div class="panel-body">
            <div class="table-container" style="max-height: 650px;">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Date</th>
                            <th>PO Ref</th>
                            <th>Wound Code(s)</th>
                            <th style="text-align: right;">Total Qty</th>
                            <th style="text-align: right;">Net Amount</th>
                            <th>Vehicle No</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                        <!-- Loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let invoicesList = [];
    let vehiclesList = [];
    let poCache = [];
    let pendingMachines = [];
    let addedItems = [];
    let partiesList = [];

    let isEditMode = false;
    let originalInvoiceNo = '';
    let linkedMachines = [];
    let originalInvoiceItems = [];

    let isWoundCodeValid = true;
    let isStockValid = true;

    let validWoundCodesForPart = new Set();

    async function loadWoundCodesForPart() {
        const partNo = document.getElementById('part_no').value;
        if (!partNo) {
            validWoundCodesForPart.clear();
            return;
        }
        try {
            const res = await App.api('query', {
                sql: "SELECT DISTINCT `wound_code` FROM `bom` WHERE `mac_no` = :part_no",
                params: { ':part_no': partNo }
            });
            validWoundCodesForPart = new Set((res.rows || []).map(r => r.wound_code));
        } catch (err) {
            console.error("Error loading wound codes for part:", err);
        }
    }

    async function fetchNextInvoiceNo() {
        try {
            const res = await App.api('get_next_invoice_no');
            if (res.ok && res.next_no) {
                document.getElementById('inv_no').value = res.next_no;
            }
        } catch (err) {
            console.error("Error fetching next invoice number:", err);
        }
    }

    async function handlePartNoChange() {
        clearItemInputFields();
        document.getElementById('wound_code').value = '';

        App.showLoading('Loading part details...');
        await loadWoundCodesForPart();
        await loadPendingMachines();
        App.hideLoading();
    }

    document.addEventListener('DOMContentLoaded', async () => {
        // Set default invoice date to today
        document.getElementById('inv_date').value = App.formatDate(new Date());

        // Restore saved history panel visibility preference (defaults to hidden/true)
        const savedHistoryPref = localStorage.getItem('hide_tax_invoice_history');
        if (savedHistoryPref === 'false') {
            toggleHistoryPanel(false);
        } else {
            toggleHistoryPanel(true);
        }

        await loadParties();
        await loadVehicles();
        loadInvoices();
        loadPOCache();

        // Check for edit mode parameter
        const urlParams = new URLSearchParams(window.location.search);
        const editInvNo = urlParams.get('edit');
        if (editInvNo) {
            isEditMode = true;
            originalInvoiceNo = editInvNo;
            await loadInvoiceForEditing(editInvNo);
        } else {
            loadPendingMachines();
            fetchNextInvoiceNo();
        }

        // Apply M314 only styles dynamically if configured
        if (<?php echo (defined('M314_ONLY') && M314_ONLY) ? 'true' : 'false'; ?>) {
            document.getElementById('part_no').value = 'M314';
            await loadWoundCodesForPart();
            await loadPendingMachines();
        }

        // Manual wound code blur exact matching listener
        document.getElementById('wound_code').addEventListener('blur', () => {
            setTimeout(() => {
                const woundCode = document.getElementById('wound_code').value.trim();
                if (woundCode) {
                    const partyId = parseInt(document.getElementById('invoice_party').value);
                    const partNo = document.getElementById('part_no').value;
                    if (!isNaN(partyId)) {
                        const match = poCache.find(item =>
                            item.party_id == partyId &&
                            (!partNo || !item.part_no || item.part_no == partNo) &&
                            item.wound_code.toLowerCase() === woundCode.toLowerCase()
                        );
                        if (match) {
                            document.getElementById('po_no').value = match.pono || '';
                            document.getElementById('po_date').value = match.po_date || '';
                            document.getElementById('txt-po-no').innerText = match.pono || '-';
                            document.getElementById('txt-po-date').innerText = match.po_date ? App.formatDate(match.po_date) : '-';
                            document.getElementById('item_sr_no').value = match.item_no || '';
                            document.getElementById('drg_no').value = match.drg_no || '9988';
                            document.getElementById('uom').value = match.unit || 'NOS';
                            document.getElementById('rate').value = match.rate;
                            calculateInvoiceValues();
                            validateInvoiceInputs();
                            syncWoundCodeToDrawingNumber();
                        }
                    }
                }
            }, 250);
        });

        // Autoclose suggestions dropdown
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.autocomplete-wrapper')) {
                document.getElementById('wound_code-suggestions').style.display = 'none';
            }
        });
    });

    function toggleHistoryPanel(forceState = null) {
        const historyPanel = document.getElementById('history-panel');
        const moduleContainer = document.getElementById('tax-invoice-container');
        const toggleBtn = document.getElementById('btn-toggle-history');
        const toggleIcon = document.getElementById('toggle-history-icon');
        const toggleText = document.getElementById('toggle-history-text');

        if (!historyPanel || !moduleContainer) return;

        let isCurrentlyHidden = historyPanel.style.display === 'none';
        let shouldHide = forceState !== null ? forceState : !isCurrentlyHidden;

        if (shouldHide) {
            historyPanel.style.display = 'none';
            moduleContainer.style.gridTemplateColumns = '1fr';
            if (toggleBtn) {
                toggleBtn.classList.remove('btn-secondary');
                toggleBtn.classList.add('btn-primary');
            }
            if (toggleIcon) toggleIcon.className = 'fa-solid fa-eye';
            if (toggleText) toggleText.innerText = 'Show Previous Invoices';
            localStorage.setItem('hide_tax_invoice_history', 'true');
        } else {
            historyPanel.style.display = 'block';
            moduleContainer.style.gridTemplateColumns = '540px 1fr';
            if (toggleBtn) {
                toggleBtn.classList.remove('btn-primary');
                toggleBtn.classList.add('btn-secondary');
            }
            if (toggleIcon) toggleIcon.className = 'fa-solid fa-eye-slash';
            if (toggleText) toggleText.innerText = 'Hide Previous Invoices';
            localStorage.setItem('hide_tax_invoice_history', 'false');
        }
    }

    function filterPendingMachines() {
        const query = document.getElementById('machine-filter').value.toLowerCase().trim();
        const container = document.getElementById('pending-machines-grid');
        const labels = container.querySelectorAll('.machine-checkbox-label');

        labels.forEach(label => {
            const span = label.querySelector('span');
            const title = label.getAttribute('title') || '';
            if (span) {
                const text = span.textContent.toLowerCase();
                const titleText = title.toLowerCase();
                if (text.includes(query) || titleText.includes(query)) {
                    label.style.display = 'flex';
                } else {
                    label.style.display = 'none';
                }
            }
        });
    }

    async function loadInvoiceForEditing(invNo) {
        App.showLoading('Loading invoice details for editing...');
        try {
            // 1. Fetch invoice rows
            const res = await App.api('query', {
                sql: "SELECT * FROM `tax_invoice` WHERE `inv_no` = :inv_no ORDER BY `id` ASC",
                params: { ':inv_no': invNo }
            });
            const rows = res.rows || [];
            if (rows.length === 0) {
                App.showToast('Invoice not found.', 'error');
                return;
            }

            const header = rows[0];

            // Store original rows for stock validation adjustment
            originalInvoiceItems = rows.map(row => ({
                wound_code: row.wound_code,
                qty: parseFloat(row.qty)
            }));

            // Populate invoice header
            document.getElementById('inv_no').value = header.inv_no;
            document.getElementById('inv_no').disabled = true; // Disable inv_no edit
            document.getElementById('inv_date').value = header.inv_date;
            document.getElementById('vehicle_no').value = header.vehicle_no;
            document.getElementById('asn_no').value = header.asn_no;
            document.getElementById('hsn_code').value = header.hsn_code;
            document.getElementById('invoice_party').value = header.party_id || '';
            document.getElementById('part_no').value = header.part_no || '';
            document.getElementById('include_sign').checked = (header.include_sign !== 0 && header.include_sign !== '0');

            // Load BOM wound codes matching the part
            await loadWoundCodesForPart();

            // Show cancel/delete button in edit mode
            document.getElementById('btn-cancel-invoice').style.display = 'block';

            // Update title badge to show editing mode
            const headerTitle = document.querySelector('#invoice-form h3');
            if (headerTitle) {
                headerTitle.innerHTML = `<i class="fa-solid fa-pen-to-square"></i> Editing Invoice: <span style="color:var(--warning); font-family:monospace;">${invNo}</span>`;
            }

            // 2. Fetch linked machines
            const resMc = await App.api('query', {
                sql: "SELECT `machine_no` FROM `invoice_machines` WHERE `inv_no` = :inv_no",
                params: { ':inv_no': invNo }
            });
            linkedMachines = (resMc.rows || []).map(row => row.machine_no);

            // 3. Populate addedItems staging array and distribute linked machines sequentially
            let machineIndex = 0;
            addedItems = rows.map(row => {
                const itemQty = Math.ceil(parseFloat(row.qty));
                const itemMachines = [];
                for (let i = 0; i < itemQty; i++) {
                    if (machineIndex < linkedMachines.length) {
                        itemMachines.push(linkedMachines[machineIndex]);
                        machineIndex++;
                    }
                }
                return {
                    wound_code: row.wound_code,
                    qty: parseFloat(row.qty),
                    rate: parseFloat(row.rate || 0),
                    uom: row.uom || 'NOS',
                    po_no: row.po_no || '',
                    po_date: row.po_date || '',
                    item_sr_no: row.item_sr_no || '',
                    drg_no: row.drg_no || '9988',
                    machines: itemMachines,
                    amount: parseFloat(row.qty) * parseFloat(row.rate || 0)
                };
            });

            // Refresh staging list view
            renderStagingGrid();
            calculateGrandTotals();

            // Refresh machine checklist
            await loadPendingMachines();

            App.showToast(`Loaded invoice ${invNo} successfully.`, 'success');
        } catch (err) {
            console.error("Load invoice error:", err);
            App.showToast('Failed to load invoice for editing.', 'error');
        } finally {
            App.hideLoading();
        }
    }

    async function loadParties() {
        try {
            const res = await App.api('list', { table: 'parties' });
            partiesList = res.rows || [];

            const select = document.getElementById('invoice_party');
            select.innerHTML = '<option value="">[Select Party]</option>';

            partiesList.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.innerText = p.party_name;
                select.appendChild(opt);
            });

            if (partiesList.length === 1) {
                select.value = partiesList[0].id;
                handlePartyChange();
            }
        } catch (err) {
            console.error("Parties load error:", err);
        }
    }

    function handlePartyChange() {
        // Clear PO search & detail inputs when changing party
        clearPOFields();
        document.getElementById('wound_code').value = '';
        filterVehiclesByParty();
        syncWoundCodeToDrawingNumber();
    }

    function syncWoundCodeToDrawingNumber() {
        const partyId = document.getElementById('invoice_party').value;
        const selectedPartyObj = partiesList.find(p => p.id == partyId);
        const isM314Party = selectedPartyObj && selectedPartyObj.party_name.trim().toUpperCase() === 'M314';
        if (isM314Party) {
            const woundCode = document.getElementById('wound_code').value;
            document.getElementById('drg_no').value = woundCode;
        }
    }

    // Fetch Invoices List and Group items by invoice number
    async function loadInvoices() {
        App.showLoading('Loading invoice records...');
        try {
            const res = await App.api('list', { table: 'tax_invoice' });
            const rawInvoices = res.rows || [];

            // Group by inv_no
            const groupedMap = {};
            rawInvoices.forEach(row => {
                const invNo = row.inv_no;
                if (!groupedMap[invNo]) {
                    groupedMap[invNo] = {
                        inv_no: invNo,
                        inv_date: row.inv_date,
                        po_no: row.po_no,
                        wound_code: row.wound_code,
                        qty: 0,
                        uom: row.uom,
                        net_total: 0,
                        vehicle_no: row.vehicle_no,
                        item_count: 0
                    };
                }
                groupedMap[invNo].qty += parseFloat(row.qty || 0);
                groupedMap[invNo].net_total += parseFloat(row.net_total || 0);
                groupedMap[invNo].item_count += 1;

                // If there are multiple wound codes, append them
                if (groupedMap[invNo].wound_code !== row.wound_code) {
                    if (!groupedMap[invNo].wound_codes_list) {
                        groupedMap[invNo].wound_codes_list = [groupedMap[invNo].wound_code];
                    }
                    if (!groupedMap[invNo].wound_codes_list.includes(row.wound_code)) {
                        groupedMap[invNo].wound_codes_list.push(row.wound_code);
                    }
                }
            });

            invoicesList = Object.values(groupedMap).reverse();
            populateGrid(invoicesList);
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Load vehicles for select options
    async function loadVehicles() {
        try {
            const res = await App.api('list', { table: 'transport_master' });
            vehiclesList = res.rows || [];
            filterVehiclesByParty();
        } catch (err) {
            console.error("Vehicles load error:", err);
        }
    }

    function filterVehiclesByParty() {
        const partyId = document.getElementById('invoice_party').value;
        const select = document.getElementById('vehicle_no');
        const activeVal = select.value;
        select.innerHTML = '<option value="">[Select Vehicle]</option>';

        const filtered = vehiclesList.filter(v => !partyId || !v.party_id || v.party_id == partyId);

        filtered.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.vech_no;
            opt.innerText = `${v.vech_no} (${v.transport_name})`;
            select.appendChild(opt);
        });

        if (filtered.length === 1) {
            select.value = filtered[0].vech_no;
        } else if (filtered.some(v => v.vech_no === activeVal)) {
            select.value = activeVal;
        }
    }

    // Load active POs into cache for autocomplete
    async function loadPOCache() {
        try {
            const res = await App.api('list', { table: 'po_master' });
            poCache = res.rows || [];
        } catch (err) {
            console.error("PO Cache load error:", err);
        }
    }

    async function loadPendingMachines() {
        const selectedPart = document.getElementById('part_no').value;
        try {
            let res;
            if (isEditMode) {
                if (selectedPart) {
                    res = await App.api('query', {
                        sql: "SELECT * FROM `remaining_machines` WHERE (`status` = 'pending' AND `part_no` = :part_no) OR `machine_no` IN (SELECT `machine_no` FROM `invoice_machines` WHERE `inv_no` = :inv_no) ORDER BY `ch_no` ASC, `machine_no` ASC",
                        params: { ':inv_no': originalInvoiceNo, ':part_no': selectedPart }
                    });
                } else {
                    res = await App.api('query', {
                        sql: "SELECT * FROM `remaining_machines` WHERE (`status` = 'pending') OR `machine_no` IN (SELECT `machine_no` FROM `invoice_machines` WHERE `inv_no` = :inv_no) ORDER BY `ch_no` ASC, `machine_no` ASC",
                        params: { ':inv_no': originalInvoiceNo }
                    });
                }
            } else {
                if (selectedPart) {
                    res = await App.api('query', {
                        sql: "SELECT * FROM `remaining_machines` WHERE `status` = 'pending' AND `part_no` = :part_no ORDER BY `ch_no` ASC, `machine_no` ASC",
                        params: { ':part_no': selectedPart }
                    });
                } else {
                    res = await App.api('query', {
                        sql: "SELECT * FROM `remaining_machines` WHERE `status` = 'pending' ORDER BY `ch_no` ASC, `machine_no` ASC"
                    });
                }
            }
            pendingMachines = res.rows || [];
            renderPendingMachinesCheckboxGrid();
        } catch (err) {
            console.error("Pending machines load error:", err);
        }
    }

    // Render checkbox list of pending machines
    function renderPendingMachinesCheckboxGrid() {
        const container = document.getElementById('pending-machines-grid');
        container.innerHTML = '';

        const selectedPartyId = document.getElementById('invoice_party').value;
        const selectedPart = document.getElementById('part_no').value;

        if (!selectedPartyId) {
            container.innerHTML = '<div style="grid-column: span 3; text-align: center; color: var(--text-muted); font-size: 0.8rem; padding: 12px;">Please select a party first to view pending machines.</div>';
            document.getElementById('selected-mc-counter').innerText = '(0 Checked)';
            return;
        }

        // Exclude machines that are already staged in addedItems
        const stagedMachines = new Set();
        addedItems.forEach(item => {
            if (item.machines) {
                item.machines.forEach(mc => stagedMachines.add(mc));
            }
        });

        const availableMachines = pendingMachines.filter(mc => {
            const isNotStaged = !stagedMachines.has(mc.machine_no);
            const matchesParty = mc.party_id == selectedPartyId || (isEditMode && linkedMachines.includes(mc.machine_no));
            const matchesPart = !selectedPart || mc.part_no == selectedPart;
            return isNotStaged && matchesParty && matchesPart;
        });

        if (availableMachines.length === 0) {
            container.innerHTML = '<div style="grid-column: span 3; text-align: center; color: var(--text-muted); font-size: 0.8rem; padding: 12px;">No pending machines mapped to the selected party.</div>';
            document.getElementById('selected-mc-counter').innerText = '(0 Checked)';
            return;
        }

        // Group standard machines by base name (removing trailing dash and digits)
        const stdGroups = {};
        const varMachines = [];

        availableMachines.forEach(mc => {
            if (mc.mode === 'standard') {
                const baseName = mc.machine_no.replace(/-\d+$/, '');
                if (!stdGroups[baseName]) {
                    stdGroups[baseName] = {
                        base_name: baseName,
                        ch_no: mc.ch_no,
                        ch_date: mc.ch_date,
                        machines: []
                    };
                }
                stdGroups[baseName].machines.push(mc);
            } else {
                varMachines.push(mc);
            }
        });

        // Render variable machines
        varMachines.forEach(mc => {
            const label = document.createElement('label');
            label.className = 'machine-checkbox-label';
            label.title = `Challan: ${mc.ch_no} (${mc.ch_date})`;

            const isChecked = isEditMode && linkedMachines.includes(mc.machine_no);

            label.innerHTML = `
                <input type="checkbox" name="machines[]" value="${escapeHtml(mc.machine_no)}" ${isChecked ? 'checked' : ''} onchange="handleMachineSelectionChange()">
                <span>${escapeHtml(mc.machine_no)}</span>
            `;
            container.appendChild(label);
        });

        // Render standard groups grouped by base_name
        Object.values(stdGroups).forEach(group => {
            const baseName = group.base_name;
            const count = group.machines.length;
            const rep = group.machines[0];

            const label = document.createElement('label');
            label.className = 'machine-checkbox-label';
            const allMachineNos = group.machines.map(m => m.machine_no).join(', ');
            label.title = `Challan: ${rep.ch_no} (${rep.ch_date}) - Standard Entry (${count} Available) - Machines: ${allMachineNos}`;
            label.style.border = '1px dashed rgba(255, 159, 0, 0.3)';
            label.style.padding = '5px 8px';
            label.style.borderRadius = '6px';

            label.innerHTML = `
                <input type="checkbox" name="standard_groups[]" data-base-name="${escapeHtml(baseName)}" data-available="${count}" value="${escapeHtml(baseName)}" onchange="handleMachineSelectionChange()">
                <span style="color: var(--primary); font-weight: 500;">${escapeHtml(baseName)} <small style="opacity:0.75; font-size:0.75rem;">(Std: ${count})</small></span>
            `;
            container.appendChild(label);
        });

        // Compute initially checked count & apply filter
        handleMachineSelectionChange();
        filterPendingMachines();
    }

    function refreshMachineChecklist() {
        renderPendingMachinesCheckboxGrid();
    }

    // Autocomplete searching for PO records
    function handlePOSearch(val) {
        const dropdown = document.getElementById('wound_code-suggestions');

        if (!val.trim()) {
            dropdown.style.display = 'none';
            clearPOFields();
            return;
        }

        const partyId = parseInt(document.getElementById('invoice_party').value);
        if (isNaN(partyId)) {
            App.showToast('Please select a party first.', 'warning');
            dropdown.style.display = 'none';
            document.getElementById('wound_code').value = '';
            return;
        }

        const partNo = document.getElementById('part_no').value;

        const query = val.toLowerCase().trim();
        const matches = poCache.filter(item =>
            item.party_id == partyId &&
            (!partNo || !item.part_no || item.part_no == partNo) && (
                (item.wound_code && item.wound_code.toLowerCase().includes(query)) ||
                (item.pono && item.pono.toLowerCase().includes(query))
            )
        ).slice(0, 10);

        if (matches.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        dropdown.innerHTML = '';
        matches.forEach(item => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';

            let displayCode = item.wound_code || '';
            const regex = new RegExp(`(${query})`, 'gi');
            displayCode = displayCode.replace(regex, `<span class="match">$1</span>`);

            div.innerHTML = `
                <div class="autocomplete-item-title">${displayCode}</div>
                <div class="autocomplete-item-desc">PO: ${escapeHtml(item.pono)} | Rate: ₹${App.formatDecimal(item.rate)} | Dwg: ${escapeHtml(item.drg_no || '')}</div>
            `;

            div.onclick = () => {
                // Populate invoice fields
                document.getElementById('wound_code').value = item.wound_code || '';
                document.getElementById('po_no').value = item.pono || '';
                document.getElementById('po_date').value = item.po_date || '';

                document.getElementById('txt-po-no').innerText = item.pono || '-';
                document.getElementById('txt-po-date').innerText = item.po_date ? App.formatDate(item.po_date) : '-';

                document.getElementById('item_sr_no').value = item.item_no || '';
                document.getElementById('drg_no').value = item.drg_no || '9988';
                document.getElementById('uom').value = item.unit || 'NOS';
                document.getElementById('rate').value = item.rate;

                dropdown.style.display = 'none';
                syncWoundCodeToDrawingNumber();

                calculateInvoiceValues();
                validateInvoiceInputs();
                document.getElementById('qty').focus();
            };
            dropdown.appendChild(div);
        });

        dropdown.style.display = 'block';
    }

    function clearPOFields() {
        document.getElementById('po_no').value = '';
        document.getElementById('po_date').value = '';
        document.getElementById('txt-po-no').innerText = '-';
        document.getElementById('txt-po-date').innerText = '-';
        document.getElementById('item_sr_no').value = '';
        document.getElementById('drg_no').value = '9988';
        document.getElementById('uom').value = 'NOS';
        document.getElementById('rate').value = '0.00';
        calculateInvoiceValues();
    }

    // Checkbox selector triggers quantity counter
    function handleMachineSelectionChange() {
        const checkedBoxes = document.querySelectorAll('input[name="machines[]"]:checked');
        const checkedStdGroups = document.querySelectorAll('input[name="standard_groups[]"]:checked');

        let totalChecked = checkedBoxes.length;
        checkedStdGroups.forEach(groupInput => {
            const qty = Math.ceil(parseFloat(document.getElementById('qty').value || 0));
            const available = parseInt(groupInput.dataset.available);
            totalChecked += Math.min(qty, available);
        });

        document.getElementById('selected-mc-counter').innerText = `(${totalChecked} Checked)`;
    }

    // Compute subtotal, CGST, SGST, Net Total for the CURRENT ITEM
    function calculateInvoiceValues() {
        const qty = parseFloat(document.getElementById('qty').value || 0);
        const rate = parseFloat(document.getElementById('rate').value || 0);

        const subtotal = qty * rate;
        const cgst = subtotal * 0.09;
        const sgst = subtotal * 0.09;
        const nettotal = subtotal + cgst + sgst;

        document.getElementById('txt-subtotal').innerText = App.formatDecimal(subtotal);
        document.getElementById('txt-cgst').innerText = App.formatDecimal(cgst);
        document.getElementById('txt-sgst').innerText = App.formatDecimal(sgst);
        document.getElementById('txt-nettotal').innerText = App.formatDecimal(nettotal);
    }

    // Populate past invoices table
    function populateGrid(data) {
        const body = document.getElementById('grid-body');
        body.innerHTML = '';

        document.getElementById('record-count-label').innerText = `${data.length} Invoices`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:32px;">No invoice receipts generated.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.title = "Click to View/Print Invoice";
            tr.onclick = () => {
                window.open(`print.php?inv_no=${encodeURIComponent(item.inv_no)}`, '_blank');
            };

            const woundDisplay = item.wound_codes_list ?
                item.wound_codes_list.map(w => `<span class="badge" style="background:rgba(255,255,255,0.05); border:1px solid var(--border-color); font-family:monospace; padding:1px 4px; font-size:0.75rem; margin-right:4px; display:inline-block;">${escapeHtml(w)}</span>`).join('') :
                `<span style="font-family: monospace; font-size:0.85rem; background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px; border:1px solid var(--border-color);">${escapeHtml(item.wound_code || '')}</span>`;

            tr.innerHTML = `
                <td><strong style="color:var(--success);">${escapeHtml(item.inv_no)}</strong></td>
                <td style="font-size:0.85rem; color:var(--text-muted);">${App.formatDate(item.inv_date)}</td>
                <td>${escapeHtml(item.po_no || '-')}</td>
                <td>${woundDisplay}</td>
                <td style="text-align: right; font-weight:700;">${App.formatDecimal(item.qty)} ${escapeHtml(item.uom || 'NOS')}</td>
                <td style="text-align: right; font-weight:700; color:var(--text-highlight);">₹${App.formatDecimal(item.net_total)}</td>
                <td><span class="badge badge-pending">${escapeHtml(item.vehicle_no)}</span></td>
                <td>
                    <button class="btn" style="background:var(--primary); color:white; border:none; padding:3px 6px; border-radius:4px; font-size:0.7rem; font-weight:bold; cursor:pointer;" onclick="event.stopPropagation(); window.location.href='tax_invoice.php?edit=${encodeURIComponent(item.inv_no)}'" title="Edit">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button class="btn" style="background:var(--error); color:white; border:none; padding:3px 6px; border-radius:4px; font-size:0.7rem; font-weight:bold; cursor:pointer; margin-left: 4px;" onclick="event.stopPropagation(); deleteInvoiceRecord('${escapeHtml(item.inv_no)}')" title="Delete">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });
    }

    async function deleteInvoiceRecord(invNo) {
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

    function handleCancelInvoice() {
        if (isEditMode && originalInvoiceNo) {
            deleteInvoiceRecord(originalInvoiceNo).then(() => {
                window.location.href = 'tax_invoice.php';
            });
        }
    }

    // Filter past invoices table
    function filterGrid() {
        const query = document.getElementById('grid-search').value.toLowerCase().trim();
        if (!query) {
            populateGrid(invoicesList);
            return;
        }

        const filtered = invoicesList.filter(item => {
            return (
                (item.inv_no && item.inv_no.toLowerCase().includes(query)) ||
                (item.po_no && item.po_no.toLowerCase().includes(query)) ||
                (item.wound_code && item.wound_code.toLowerCase().includes(query)) ||
                (item.vehicle_no && item.vehicle_no.toLowerCase().includes(query))
            );
        });

        populateGrid(filtered);
    }

    // Add Item to Staging List
    async function addItemToList() {
        const woundCode = document.getElementById('wound_code').value.trim();
        const qty = parseFloat(document.getElementById('qty').value || 0);
        const rate = parseFloat(document.getElementById('rate').value || 0);
        const uom = document.getElementById('uom').value.trim() || 'NOS';
        const po_no = document.getElementById('po_no').value.trim() || '';
        const po_date = document.getElementById('po_date').value || '';
        const item_sr_no = document.getElementById('item_sr_no').value.trim() || '';
        const drg_no = document.getElementById('drg_no').value.trim() || '9988';

        if (addedItems.length >= 6) {
            App.showToast("Maximum of 6 items allowed per invoice.", "error");
            return;
        }

        if (!woundCode || qty <= 0) {
            App.showToast("Please enter a valid Wound Code and a positive Quantity.", "error");
            return;
        }

        App.showLoading("Validating item stock...");
        await validateInvoiceInputs();
        App.hideLoading();

        if (!isWoundCodeValid || !isStockValid) {
            App.showToast("Cannot add item. Please resolve validation errors.", "error");
            return;
        }

        // Get checked machines for this item
        const checkedBoxes = document.querySelectorAll('input[name="machines[]"]:checked');
        const selectedMachines = Array.from(checkedBoxes).map(box => box.value);

        // Auto-select machines for checked standard groups based on qty
        const checkedStdGroups = document.querySelectorAll('input[name="standard_groups[]"]:checked');
        checkedStdGroups.forEach(groupInput => {
            const baseName = groupInput.dataset.baseName;

            // Find all pending machines in standard mode that match this base name
            const groupMachines = pendingMachines.filter(mc => mc.mode === 'standard' && mc.machine_no.replace(/-\d+$/, '') === baseName);

            // Exclude already staged machines
            const stagedMachines = new Set();
            addedItems.forEach(item => {
                if (item.machines) {
                    item.machines.forEach(m => stagedMachines.add(m));
                }
            });
            const availableGroupMachines = groupMachines.filter(mc => !stagedMachines.has(mc.machine_no));

            // Sort availableGroupMachines numerically to consume them in sequential order (suffix number)
            availableGroupMachines.sort((a, b) => {
                const numA = parseInt(a.machine_no.match(/-(\d+)$/)?.[1] || 0);
                const numB = parseInt(b.machine_no.match(/-(\d+)$/)?.[1] || 0);
                return numA - numB;
            });

            // Auto select up to 'qty' machines
            const qtyToSelect = Math.min(Math.ceil(qty), availableGroupMachines.length);
            for (let i = 0; i < qtyToSelect; i++) {
                selectedMachines.push(availableGroupMachines[i].machine_no);
            }
        });

        // Add to staging array
        const itemObj = {
            wound_code: woundCode,
            qty: qty,
            rate: rate,
            uom: uom,
            po_no: po_no,
            po_date: po_date,
            item_sr_no: item_sr_no,
            drg_no: drg_no,
            machines: selectedMachines,
            amount: qty * rate
        };

        addedItems.push(itemObj);

        // Reset item input fields
        clearItemInputFields();

        // Refresh grid
        renderStagingGrid();

        // Update grand totals
        calculateGrandTotals();

        // Refresh machine checklist
        refreshMachineChecklist();

        App.showToast("Item added to invoice staging.", "success");
    }

    function clearItemInputFields() {
        document.getElementById('wound_code').value = '';
        document.getElementById('po_no').value = '';
        document.getElementById('po_date').value = '';
        document.getElementById('txt-po-no').innerText = '-';
        document.getElementById('txt-po-date').innerText = '-';
        document.getElementById('item_sr_no').value = '';
        document.getElementById('drg_no').value = '9988';
        document.getElementById('qty').value = '0';
        document.getElementById('uom').value = 'NOS';
        document.getElementById('rate').value = '0.00';
        calculateInvoiceValues();
    }

    // autoFillDrawingNumber has been removed as Drawing Number now loads directly from selected PO record

    // Render Staging Items List
    function renderStagingGrid() {
        const container = document.getElementById('staging-list-container');
        const tbody = document.getElementById('staging-grid-body');
        const countBadge = document.getElementById('items-count-badge');
        const btnAdd = document.getElementById('btn-add-item');

        tbody.innerHTML = '';
        countBadge.innerText = `${addedItems.length}/6 Items Added`;

        if (addedItems.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';

        addedItems.forEach((item, index) => {
            const tr = document.createElement('tr');

            const poDisplay = item.po_no ?
                `<strong>${escapeHtml(item.po_no)}</strong>${item.po_date ? '<br><span style="font-size:0.75rem; color:var(--text-muted);">' + App.formatDate(item.po_date) + '</span>' : ''}` :
                '<span style="color:var(--text-muted); font-style:italic;">No PO Ref</span>';

            const machinesDisplay = item.machines.length > 0 ?
                `<br><span style="font-family:monospace; font-size:0.75rem; color:var(--success);">Dispatched: ${item.machines.join(', ')}</span>` :
                '';

            tr.innerHTML = `
                <td style="text-align: center; font-weight: bold; color: var(--text-highlight);">${index + 1}</td>
                <td>
                    <strong style="color:var(--secondary);">${escapeHtml(item.wound_code)}</strong>
                    ${machinesDisplay}
                </td>
                <td>${poDisplay}</td>
                <td style="font-size:0.8rem;">Dwg: ${escapeHtml(item.drg_no)}</td>
                <td style="text-align: right; font-weight: bold;">${App.formatDecimal(item.qty)} ${escapeHtml(item.uom)}</td>
                <td style="text-align: right;">₹${App.formatDecimal(item.rate)}</td>
                <td style="text-align: right; font-weight: bold; color:var(--primary);">₹${App.formatDecimal(item.amount)}</td>
                <td style="text-align: center;">
                    <button type="button" class="btn btn-secondary" onclick="removeItemFromList(${index})" style="padding: 4px 8px; font-size:0.75rem; background: rgba(239, 68, 68, 0.15); color: var(--error); border: 1px solid rgba(239, 68, 68, 0.2); cursor: pointer;"><i class="fa-solid fa-trash-can"></i> Remove</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Limit to 6 items maximum
        if (addedItems.length >= 6) {
            btnAdd.disabled = true;
            btnAdd.style.opacity = '0.5';
            btnAdd.innerText = 'Max 6 Items';
        } else {
            btnAdd.disabled = false;
            btnAdd.style.opacity = '1';
            btnAdd.innerText = 'Add Item';
        }
    }

    function removeItemFromList(index) {
        addedItems.splice(index, 1);
        renderStagingGrid();
        calculateGrandTotals();
        refreshMachineChecklist();
        validateInvoiceInputs();
    }

    function calculateGrandTotals() {
        let subtotal = 0;
        addedItems.forEach(item => {
            subtotal += item.amount;
        });

        const cgst = subtotal * 0.09;
        const sgst = subtotal * 0.09;
        const nettotal = subtotal + cgst + sgst;

        const container = document.getElementById('grand-totals-fieldset');
        if (addedItems.length > 0) {
            container.style.display = 'block';
            document.getElementById('grand-subtotal').innerText = App.formatDecimal(subtotal);
            document.getElementById('grand-cgst').innerText = App.formatDecimal(cgst);
            document.getElementById('grand-sgst').innerText = App.formatDecimal(sgst);
            document.getElementById('grand-nettotal').innerText = App.formatDecimal(nettotal);
        } else {
            container.style.display = 'none';
        }
    }

    // Reset Form
    function resetForm() {
        document.getElementById('invoice-form').reset();
        document.getElementById('inv_date').value = App.formatDate(new Date());

        // Re-enable invoice number
        document.getElementById('inv_no').disabled = false;
        isEditMode = false;
        originalInvoiceNo = '';
        linkedMachines = [];
        originalInvoiceItems = [];

        // Restore header title
        const headerTitle = document.querySelector('#invoice-form h3');
        if (headerTitle) {
            headerTitle.innerHTML = `<i class="fa-solid fa-file-invoice"></i> Invoice Header Details`;
        }

        // Clear staging list
        addedItems = [];
        renderStagingGrid();
        calculateGrandTotals();

        document.getElementById('wound_code-suggestions').style.display = 'none';

        // Hide validation box
        const errorBox = document.getElementById('validation-errors');
        if (errorBox) {
            errorBox.style.display = 'none';
            errorBox.innerHTML = '';
        }
        isWoundCodeValid = true;
        isStockValid = true;

        calculateInvoiceValues();
        loadPendingMachines(); // reload checkboxes
    }

    // Submit invoice
    async function handleInvoiceSubmit(e) {
        e.preventDefault();

        // 1. If staging is empty, but they filled out the current inputs, try to add it automatically
        const currentWoundCode = document.getElementById('wound_code').value.trim();
        const currentQty = parseFloat(document.getElementById('qty').value || 0);

        if (addedItems.length === 0 && currentWoundCode && currentQty > 0) {
            await addItemToList();
        }

        if (addedItems.length === 0) {
            App.showToast('Please add at least one item to the invoice.', 'error');
            return;
        }

        // Basic inputs
        const inv_no = document.getElementById('inv_no').value.trim();
        const inv_date = document.getElementById('inv_date').value;
        const vehicle_no = document.getElementById('vehicle_no').value;
        const asn_no = document.getElementById('asn_no').value.trim() || '9988';
        const hsn_code = document.getElementById('hsn_code').value.trim() || '9988';

        if (!inv_no || !inv_date || !vehicle_no) {
            App.showToast('Please fill all required invoice header fields (No, Date, and Vehicle).', 'error');
            return;
        }

        const party_id = parseInt(document.getElementById('invoice_party').value) || null;
        const part_no = document.getElementById('part_no').value || null;

        const include_sign = document.getElementById('include_sign').checked ? 1 : 0;

        const payload = {
            invoiceHeader: {
                inv_no: inv_no,
                original_inv_no: originalInvoiceNo || inv_no,
                inv_date: inv_date,
                vehicle_no: vehicle_no,
                asn_no: asn_no,
                hsn_code: hsn_code,
                party_id: party_id,
                part_no: part_no,
                include_sign: include_sign
            },
            items: addedItems,
            is_edit: isEditMode
        };

        App.showLoading('Saving tax invoice & updating inventory stock (FIFO)...');
        try {
            await App.api('save_tax_invoice', payload);

            App.showToast('Invoice generated successfully! Loading print preview...', 'success');

            resetForm();
            await loadInvoices();

            // Redirect to print invoice
            setTimeout(() => {
                window.open(`print.php?inv_no=${encodeURIComponent(inv_no)}`, '_blank');
            }, 1000);

        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // Validate Wound Code existence and material stock availability
    async function validateInvoiceInputs() {
        const woundCode = document.getElementById('wound_code').value.trim();
        const qty = parseFloat(document.getElementById('qty').value || 0);
        const errorBox = document.getElementById('validation-errors');

        // Hide by default
        errorBox.style.display = 'none';
        errorBox.innerHTML = '';

        const partNo = document.getElementById('part_no').value || null;

        if (!woundCode) {
            isWoundCodeValid = true;
            isStockValid = true;
            return;
        }

        // 1. Check if Wound Code exists in BOM
        try {
            let resWound;
            if (partNo) {
                resWound = await App.api('query', {
                    sql: "SELECT COUNT(*) as cnt FROM `bom` WHERE `wound_code` = :wound_code AND `mac_no` = :part_no",
                    params: { ':wound_code': woundCode, ':part_no': partNo }
                });
            } else {
                resWound = await App.api('query', {
                    sql: "SELECT COUNT(*) as cnt FROM `bom` WHERE `wound_code` = :wound_code",
                    params: { ':wound_code': woundCode }
                });
            }
            const cnt = parseInt(resWound.rows[0].cnt || 0);
            if (cnt === 0) {
                isWoundCodeValid = false;
                showValidationError(`❌ The entered Wound Code "<strong>${escapeHtml(woundCode)}</strong>" does not exist in the Bill of Materials (BOM) registry.`);
                return;
            } else {
                isWoundCodeValid = true;
            }
        } catch (err) {
            console.error("Wound code validation error:", err);
            return;
        }

        // 2. Aggregate requirements across addedItems AND current item inputs
        const requirementsMap = {};

        // Add existing items' requirements
        for (const item of addedItems) {
            try {
                let sqlB = "SELECT `rm_code`, `req_qty` FROM `bom` WHERE `wound_code` = :wound_code";
                let paramsB = { ':wound_code': item.wound_code };
                if (partNo) {
                    sqlB += " AND `mac_no` = :part_no";
                    paramsB[':part_no'] = partNo;
                }
                const resB = await App.api('query', {
                    sql: sqlB,
                    params: paramsB
                });
                const bomRows = resB.rows || [];
                bomRows.forEach(b => {
                    const totalReq = parseFloat(b.req_qty) * parseFloat(item.qty);
                    requirementsMap[b.rm_code] = (requirementsMap[b.rm_code] || 0) + totalReq;
                });
            } catch (err) {
                console.error(err);
            }
        }

        // Add current item input requirements
        if (qty > 0) {
            try {
                let sqlB = "SELECT `rm_code`, `req_qty` FROM `bom` WHERE `wound_code` = :wound_code";
                let paramsB = { ':wound_code': woundCode };
                if (partNo) {
                    sqlB += " AND `mac_no` = :part_no";
                    paramsB[':part_no'] = partNo;
                }
                const resB = await App.api('query', {
                    sql: sqlB,
                    params: paramsB
                });
                const bomRows = resB.rows || [];
                bomRows.forEach(b => {
                    const totalReq = parseFloat(b.req_qty) * qty;
                    requirementsMap[b.rm_code] = (requirementsMap[b.rm_code] || 0) + totalReq;
                });
            } catch (err) {
                console.error(err);
            }
        }

        // 3. If in edit mode, calculate original invoice requirements to adjust available stock
        const originalReqMap = {};
        if (isEditMode && originalInvoiceItems.length > 0) {
            for (const item of originalInvoiceItems) {
                try {
                    let sqlB = "SELECT `rm_code`, `req_qty` FROM `bom` WHERE `wound_code` = :wound_code";
                    let paramsB = { ':wound_code': item.wound_code };
                    if (partNo) {
                        sqlB += " AND `mac_no` = :part_no";
                        paramsB[':part_no'] = partNo;
                    }
                    const resB = await App.api('query', {
                        sql: sqlB,
                        params: paramsB
                    });
                    const bomRows = resB.rows || [];
                    bomRows.forEach(b => {
                        const totalReq = parseFloat(b.req_qty) * parseFloat(item.qty);
                        originalReqMap[b.rm_code] = (originalReqMap[b.rm_code] || 0) + totalReq;
                    });
                } catch (err) {
                    console.error(err);
                }
            }
        }
        const rmCodes = Object.keys(requirementsMap);
        if (rmCodes.length > 0) {
            try {
                const deficits = [];
                for (const rmCode of rmCodes) {
                    const totalRequired = requirementsMap[rmCode];
                    let sqlStock = "SELECT COALESCE(SUM(bal_qty), 0) as available_bal FROM inward_transaction WHERE m_code = :m_code AND bal_qty > 0";
                    let paramsStock = { ':m_code': rmCode };
                    if (partNo) {
                        sqlStock = "SELECT COALESCE(SUM(bal_qty), 0) as available_bal FROM inward_transaction WHERE m_code = :m_code AND part_no = :part_no AND bal_qty > 0";
                        paramsStock[':part_no'] = partNo;
                    }
                    const resStock = await App.api('query', {
                        sql: sqlStock,
                        params: paramsStock
                    });
                    let available = parseFloat(resStock.rows[0].available_bal || 0);

                    // Adjust available stock for edit mode by adding back original requirements (rollback simulation)
                    if (isEditMode && originalReqMap[rmCode]) {
                        available += originalReqMap[rmCode];
                    }

                    if (totalRequired > available) {
                        const deficit = totalRequired - available;
                        deficits.push(`<li><strong>${escapeHtml(rmCode)}</strong>: Total Required: ${totalRequired.toFixed(2)}, Available: ${available.toFixed(2)} (Deficit: ${deficit.toFixed(2)})</li>`);
                    }
                }

                if (deficits.length > 0) {
                    isStockValid = false;
                    showValidationError(`❌ <strong>Insufficient inventory stock (including already queued items):</strong><br><ul style="margin-left: 20px; margin-top: 5px; line-height:1.4;">${deficits.join('')}</ul>`);
                } else {
                    isStockValid = true;
                }
            } catch (err) {
                console.error("Stock validation error:", err);
            }
        } else {
            isStockValid = true;
        }
    }

    function showValidationError(message) {
        const errorBox = document.getElementById('validation-errors');
        errorBox.innerHTML = message;
        errorBox.style.display = 'block';
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
