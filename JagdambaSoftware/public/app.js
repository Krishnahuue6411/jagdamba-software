// ===== MODULE CONFIGURATION =====
const moduleConfig = {
  RM: {
    label: 'Raw Material Master',
    section: 'MASTER',
    table: 'RM',
    fields: ['RM_CODE', 'RM_DESCRIPTION', 'HEAD', 'BAL_QTY', 'UNIT'],
    buttons: ['New', 'Save', 'Add', 'Search', 'Exit']
  },
  TRANSPORT_MASTER: {
    label: 'Transporter Master',
    section: 'MASTER',
    table: 'TRANSPORT_MASTER',
    fields: ['CODE', 'NAME', 'ADDRESS', 'PHONE', 'GST_NO'],
    buttons: ['New', 'Save', 'Add', 'Search', 'Exit']
  },
  BOM: {
    label: 'Bill of Material',
    section: 'MASTER',
    table: 'BOM',
    fields: ['MACHINE_NO', 'CAM_CODE', 'CAM_DESCRIPTION', 'ITEM_QTY', 'MATERIAL_CODE', 'REMARKS'],
    buttons: ['New', 'Save', 'Add', 'Search', 'Clear']
  },
  PO_MASTER: {
    label: 'PO Master',
    section: 'MASTER',
    table: 'PO_MASTER',
    fields: ['PO_NO', 'NAME', 'ADDRESS', 'PHONE', 'FAX', 'EMAIL', 'WEBSITE', 'PO_DATE'],
    buttons: ['New', 'Save', 'Add', 'Search', 'Exit']
  },
  PO_NO: {
    label: 'PO No',
    section: 'MASTER',
    table: 'PO_NO',
    fields: ['PO_NO', 'PO_DATE', 'DESCRIPTION'],
    buttons: ['New', 'Save', 'Add', 'Search', 'Exit']
  },
  UNIT_MASTER: {
    label: 'Unit Master',
    section: 'MASTER',
    table: 'UNIT_MASTER',
    fields: ['UNIT_CODE', 'UNIT_DESC'],
    buttons: ['New', 'Save', 'Add', 'Search', 'Exit']
  },
  INWARD: {
    label: 'Inward Raw Material',
    section: 'TRANSACTION',
    table: 'INWARD',
    fields: ['CHALLAN_TYPE', 'CHALLAN_NO', 'CHALLAN_DATE', 'RM_CODE', 'RM_TYPE', 'QTY', 'UNIT', 'MACHINE_NO', 'REMARK'],
    buttons: ['New', 'Save', 'Add', 'Search', 'Stock', 'Exit', 'Close']
  },
  TAX_INVOICE: {
    label: 'Tax Invoice',
    section: 'TRANSACTION',
    table: 'TAX_INVOICE',
    fields: ['CUSTOMER_NAME', 'ADDRESS', 'GST_NO', 'PAN_NO', 'STATE', 'STATE_CODE', 'INVOICE_NO', 'INVOICE_DATE', 'VENDOR_CODE', 'PO_NO', 'PO_DATE', 'ASN_NO', 'VEHICLE_NO', 'HSN_CODE', 'QTY'],
    buttons: ['New', 'Save', 'Add', 'Edit', 'Tax Calculation', 'Exit']
  },
  NFP: {
    label: 'NFP / Return',
    section: 'TRANSACTION',
    table: 'NFP',
    fields: ['CUSTOMER_NAME', 'ADDRESS', 'GST_NO', 'PAN_NO', 'STATE', 'STATE_CODE', 'INVOICE_NO', 'INVOICE_DATE', 'PO_NO', 'PO_DATE', 'ITEM_CODE', 'MATERIAL_DESC', 'DRAWING_NO', 'VEHICLE_NO', 'HSN_CODE', 'QTY', 'UCM', 'RATE', 'AMOUNT'],
    buttons: ['Save', 'Add', 'Edit', 'Print', 'Exit']
  },
  WIP: {
    label: 'WIP Entry',
    section: 'TRANSACTION',
    table: 'WIP',
    fields: ['SR_NO', 'MACHINE_NO', 'CONST', 'START_DATE', 'COILS', 'STMP', 'KITS', 'WDG_READY', 'REMARK', 'WDG_DONE'],
    buttons: ['New', 'Save', 'Print', 'Search', 'Close']
  },
  RM_STOCK: {
    label: 'Stock Update',
    section: 'TRANSACTION',
    table: 'RM_STOCK',
    fields: ['RM_CODE', 'RM_DESCRIPTION', 'BAL_QTY', 'UNIT'],
    buttons: ['New', 'Save', 'Add', 'Search', 'Exit']
  },
  STOCK: {
    label: 'Raw Material Stock',
    section: 'REPORT',
    table: 'RM',
    isStock: true,
    fields: ['RM_CODE', 'RM_DESCRIPTION', 'HEAD', 'BAL_QTY', 'UNIT']
  },
  INWARD_REPORT: {
    label: 'Inward Report',
    section: 'REPORT',
    table: 'INWARD',
    isReport: true,
    dateField: 'CH_DATE',
    fields: ['CH_DATE', 'CH_NO', 'RM_CODE', 'RM_TYPE', 'QTY']
  },
  NFP_REPORT: {
    label: 'Dispatch Report',
    section: 'REPORT',
    table: 'NFP',
    isReport: true,
    dateField: 'INV_DATE',
    fields: ['INV_DATE', 'CUSTOMER_NAME', 'INVOICE_NO', 'QTY']
  },
  TAX_INVOICE_REPORT: {
    label: 'GST Report',
    section: 'REPORT',
    table: 'TAX_INVOICE',
    isReport: true,
    dateField: 'INV_DATE',
    fields: ['INV_DATE', 'CUSTOMER_NAME', 'INVOICE_NO', 'GST_NO', 'AMOUNT']
  }
};

// ===== STATE =====
const state = {
  currentModule: 'RM',
  columns: [],
  rows: [],
  filteredRows: [],
  selectedOriginal: null,
  editing: false
};

// ===== DOM REFS =====
const $ = sel => document.querySelector(sel);
const $$ = sel => document.querySelectorAll(sel);

// ===== API =====
async function api(action, payload = {}) {
  try {
    const response = await fetch(`/api/${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed');
    return data;
  } catch (err) {
    throw new Error(err.message || 'Network error');
  }
}

// ===== TOAST =====
function toast(msg) {
  const el = $('#toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => el.classList.remove('show'), 3000);
}

// ===== HELPERS =====
function formatLabel(name) {
  return name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function isDateField(name) {
  return ['DATE', 'CH_DATE', 'INV_DATE', 'PO_DATE', 'START_DATE', 'WDG_DONE'].some(h => name.toUpperCase().includes(h));
}

function isNumericField(name) {
  return ['QTY', 'BAL_QTY', 'RATE', 'AMOUNT', 'SUB_TOTAL', 'CGST', 'SGST', 'NET_TOTAL', 'IN_QTY', 'OUT_QTY'].some(h => name.toUpperCase().includes(h));
}

function normalizeDate(val) {
  if (!val) return '';
  const str = String(val);
  const m = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[1]}-${m[2]}-${m[3]}` : str;
}

function escapeHtml(str) {
  return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

function setDefaultDates() {
  const today = new Date();
  const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
  $('#fromDate').value = monthStart.toISOString().slice(0, 10);
  $('#toDate').value = today.toISOString().slice(0, 10);
}

// ===== NAVIGATION =====
function renderNav() {
  const config = moduleConfig[state.currentModule];
  $$('.nav-btn[data-module]').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.module === state.currentModule);
  });
  $('#breadcrumb').textContent = config.section;
  $('#pageTitle').textContent = config.label;
}

// ===== FORM =====
function renderForm(row = {}) {
  const config = moduleConfig[state.currentModule];
  const form = $('#entryForm');
  form.innerHTML = '';
  
  // Stock screen
  if (config.isStock) {
    $('#formSection').style.display = 'none';
    $('#stockScreen').style.display = 'block';
    $('#statsBar').style.display = 'none';
    renderStockSelector();
    return;
  }
  
  $('#formSection').style.display = 'block';
  $('#stockScreen').style.display = 'none';
  $('#statsBar').style.display = 'grid';
  
  // Build form fields
  const fields = config.fields || [];
  fields.forEach(col => {
    const div = document.createElement('div');
    div.className = 'field';
    if (col.length > 20) div.classList.add('wide');
    
    const label = document.createElement('label');
    label.htmlFor = `f-${col}`;
    label.textContent = formatLabel(col);
    
    const input = document.createElement('input');
    input.id = `f-${col}`;
    input.name = col;
    input.value = row[col] ?? '';
    input.autocomplete = 'off';
    
    if (isDateField(col)) input.type = 'date';
    else if (isNumericField(col)) input.type = 'number';
    else input.type = 'text';
    
    div.append(label, input);
    form.appendChild(div);
  });
  
  // Update hints
  $('#formTitle').textContent = config.label + ' Entry';
  $('#formHint').textContent = state.editing ? 'Editing selected row' : 'Select a row to edit or press New';
  
  // Render buttons
  renderButtons(config.buttons || []);
}

function renderButtons(buttons) {
  const container = $('#formActions');
  container.innerHTML = '';
  
  const btnMap = {
    'New': { cls: 'btn-primary', action: clearForm },
    'Save': { cls: 'btn-success', action: saveRecord },
    'Add': { cls: 'btn-primary', action: () => toast('Add new item') },
    'Edit': { cls: 'btn-secondary', action: () => toast('Edit mode') },
    'Delete': { cls: 'btn-danger', action: deleteRecord },
    'Search': { cls: 'btn-secondary', action: showParamDialog },
    'Print': { cls: 'btn-secondary', action: () => window.print() },
    'Tax Calculation': { cls: 'btn-secondary', action: () => toast('Tax Calculation') },
    'Stock': { cls: 'btn-secondary', action: () => selectModule('STOCK') },
    'Clear': { cls: 'btn-secondary', action: clearForm },
    'Exit': { cls: 'btn-danger', action: () => toast('Exiting...') },
    'Close': { cls: 'btn-secondary', action: () => toast('Closed') }
  };
  
  buttons.forEach(label => {
    const btnInfo = btnMap[label];
    if (btnInfo) {
      const btn = document.createElement('button');
      btn.className = btnInfo.cls;
      btn.textContent = label;
      btn.addEventListener('click', btnInfo.action);
      container.appendChild(btn);
    }
  });
}

function collectForm() {
  const data = {};
  new FormData($('#entryForm')).forEach((v, k) => { data[k] = v; });
  return data;
}

function clearForm() {
  state.selectedOriginal = null;
  state.editing = false;
  renderForm({});
  $$('tbody tr.selected').forEach(el => el.classList.remove('selected'));
  $('#formHint').textContent = 'New record mode';
}

// ===== STOCK =====
function renderStockSelector() {
  const select = $('#stockCode');
  const current = select.value;
  select.innerHTML = '<option value="">All Codes</option>';
  
  const codes = new Set();
  state.rows.forEach(row => {
    if (row.RM_CODE) codes.add(row.RM_CODE);
  });
  
  codes.forEach(code => {
    const opt = document.createElement('option');
    opt.value = code;
    const desc = state.rows.find(r => r.RM_CODE === code)?.RM_DESCRIPTION || '';
    opt.textContent = `${code} - ${desc}`;
    select.appendChild(opt);
  });
  
  if (current) select.value = current;
}

// ===== TABLE =====
function renderTable() {
  const thead = $('#dataTable thead');
  const tbody = $('#dataTable tbody');
  const config = moduleConfig[state.currentModule];
  
  const cols = state.columns.length ? state.columns : (config.fields || []);
  if (cols.length === 0) {
    thead.innerHTML = '<tr><th>No data</th></tr>';
    tbody.innerHTML = '<tr><td>No columns found</td></tr>';
    return;
  }
  
  thead.innerHTML = `<tr>${cols.map(c => `<th>${formatLabel(c)}</th>`).join('')}</tr>`;
  
  tbody.innerHTML = '';
  state.filteredRows.forEach((row, i) => {
    const tr = document.createElement('tr');
    tr.dataset.index = i;
    tr.innerHTML = cols.map(c => `<td>${escapeHtml(row[c] ?? '')}</td>`).join('');
    tr.addEventListener('click', () => selectRow(row, tr));
    tbody.appendChild(tr);
  });
  
  $('#rowCount').textContent = `${state.filteredRows.length} rows`;
  $('#totalRecords').textContent = state.rows.length;
  $('#showingRecords').textContent = state.filteredRows.length;
}

function selectRow(row, tr) {
  $$('tbody tr.selected').forEach(el => el.classList.remove('selected'));
  tr.classList.add('selected');
  state.selectedOriginal = structuredClone(row);
  state.editing = true;
  renderForm(row);
}

// ===== CRUD =====
async function saveRecord() {
  const row = collectForm();
  const config = moduleConfig[state.currentModule];
  
  const hasData = Object.values(row).some(v => v && v.trim && v.trim() !== '');
  if (!hasData) {
    toast('Please enter some data');
    return;
  }
  
  try {
    if (state.editing && state.selectedOriginal) {
      await api('update', { table: config.table, row, original: state.selectedOriginal });
      toast('Record updated');
    } else {
      await api('insert', { table: config.table, row });
      toast('Record saved');
    }
    await loadData();
  } catch (err) {
    toast('Error: ' + err.message);
  }
}

async function deleteRecord() {
  if (!state.selectedOriginal) { toast('Select a row first'); return; }
  if (!confirm('Delete selected record?')) return;
  
  const config = moduleConfig[state.currentModule];
  try {
    await api('delete', { table: config.table, original: state.selectedOriginal });
    toast('Record deleted');
    state.selectedOriginal = null;
    state.editing = false;
    await loadData();
  } catch (err) {
    toast('Error: ' + err.message);
  }
}

// ===== LOAD DATA =====
async function loadData() {
  const config = moduleConfig[state.currentModule];
  try {
    const result = await api('list', { table: config.table, limit: 2000 });
    state.columns = result.columns || [];
    state.rows = result.rows || [];
    state.filteredRows = [...state.rows];
    renderTable();
    renderForm();
    renderStats();
    $('#dbStatus').textContent = 'Connected ✓';
  } catch (err) {
    toast('Error loading data: ' + err.message);
    $('#dbStatus').textContent = 'Error';
  }
}

function renderStats() {
  const total = state.filteredRows.reduce((sum, row) => {
    const amt = Number(row.AMOUNT || row.NET_TOTAL || 0);
    return sum + (isNaN(amt) ? 0 : amt);
  }, 0);
  $('#totalAmount').textContent = total > 0 ? `₹${total.toLocaleString('en-IN', { maximumFractionDigits: 2 })}` : '₹0';
}

// ===== MODULE SWITCH =====
async function selectModule(moduleKey) {
  state.currentModule = moduleKey;
  state.editing = false;
  state.selectedOriginal = null;
  renderNav();
  
  const config = moduleConfig[moduleKey];
  
  $('#reportFilter').style.display = config.isReport ? 'flex' : 'none';
  if (config.isReport) setDefaultDates();
  
  if (config.isStock) {
    $('#formSection').style.display = 'none';
    $('#stockScreen').style.display = 'block';
    $('#statsBar').style.display = 'none';
    await loadData();
    renderStockSelector();
    return;
  }
  
  $('#formSection').style.display = 'block';
  $('#stockScreen').style.display = 'none';
  $('#statsBar').style.display = 'grid';
  await loadData();
}

// ===== FILTERS =====
function applyFilters() {
  const query = $('#globalSearch').value.trim().toLowerCase();
  const stockCode = $('#stockCode')?.value || '';
  const config = moduleConfig[state.currentModule];
  
  state.filteredRows = state.rows.filter(row => {
    if (config.isStock && stockCode && row.RM_CODE !== stockCode) return false;
    
    if (query) {
      const match = state.columns.some(col => 
        String(row[col] ?? '').toLowerCase().includes(query)
      );
      if (!match) return false;
    }
    
    if (config.isReport && config.dateField) {
      const from = $('#fromDate').value;
      const to = $('#toDate').value;
      const val = normalizeDate(row[config.dateField]);
      if (!val) return false;
      if (from && val < from) return false;
      if (to && val > to) return false;
    }
    
    return true;
  });
  
  renderTable();
  renderStats();
}

// ===== EXPORT =====
function exportCSV() {
  const cols = state.columns;
  if (cols.length === 0) { toast('No data to export'); return; }
  
  const lines = [cols.join(',')];
  state.filteredRows.forEach(row => {
    lines.push(cols.map(c => {
      let val = String(row[c] ?? '');
      if (val.includes(',') || val.includes('"') || val.includes('\n')) {
        val = `"${val.replace(/"/g, '""')}"`;
      }
      return val;
    }).join(','));
  });
  
  const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${state.currentModule}_${new Date().toISOString().slice(0,10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
  toast('CSV exported');
}

// ===== PARAMETER DIALOG =====
function showParamDialog() {
  const modal = $('#paramModal');
  modal.style.display = 'grid';
  $('#paramFromDate').value = $('#fromDate').value || '';
  $('#paramToDate').value = $('#toDate').value || '';
}

function hideParamDialog() {
  $('#paramModal').style.display = 'none';
}

// ===== INIT =====
function init() {
  $$('.nav-btn[data-module]').forEach(btn => {
    btn.addEventListener('click', () => selectModule(btn.dataset.module));
  });
  
  $('#stockCode').addEventListener('change', applyFilters);
  $('#stockRegisterBtn').addEventListener('click', () => window.print());
  $('#stockExitBtn').addEventListener('click', () => selectModule('RM'));
  
  $('#globalSearch').addEventListener('input', applyFilters);
  $('#applyFilterBtn').addEventListener('click', applyFilters);
  $('#exportBtn').addEventListener('click', exportCSV);
  
  $('#refreshBtn').addEventListener('click', loadData);
  $('#printBtn').addEventListener('click', () => window.print());
  
  $('#paramOkBtn').addEventListener('click', () => {
    const from = $('#paramFromDate').value;
    const to = $('#paramToDate').value;
    if (from) $('#fromDate').value = from;
    if (to) $('#toDate').value = to;
    hideParamDialog();
    applyFilters();
  });
  $('#paramCancelBtn').addEventListener('click', hideParamDialog);
  $('#paramModal').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) hideParamDialog();
  });
  
  $('#closeBtn').addEventListener('click', () => {
    if (confirm('Close application?')) {
      window.close();
    }
  });
  
  selectModule('RM');
}

// ===== START =====
document.addEventListener('DOMContentLoaded', init);