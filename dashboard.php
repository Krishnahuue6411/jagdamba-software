<?php
// Jagdamba Electrical - Dashboard (dashboard.php)
require_once 'sidebar.php';

$pdo = getSidebarConnection();

$stats = [
    'raw_materials' => 0,
    'inwards' => 0,
    'bom' => 0,
    'po' => 0,
    'transporters' => 0,
    'invoices' => 0,
    'pending_machines' => 0
];

$recentInwards = [];
$recentInvoices = [];

$dbError = null;

if ($pdo) {
    try {
        $stats['raw_materials'] = $pdo->query("SELECT COUNT(*) FROM `raw_material`")->fetchColumn();
        $stats['inwards'] = $pdo->query("SELECT COUNT(*) FROM `inward_transaction`")->fetchColumn();
        $stats['bom'] = $pdo->query("SELECT COUNT(*) FROM `bom`")->fetchColumn();
        $stats['po'] = $pdo->query("SELECT COUNT(*) FROM `po_master`")->fetchColumn();
        $stats['transporters'] = $pdo->query("SELECT COUNT(*) FROM `transport_master`")->fetchColumn();
        $stats['invoices'] = $pdo->query("SELECT COUNT(*) FROM `tax_invoice`")->fetchColumn();
        $stats['pending_machines'] = $pdo->query("SELECT COUNT(*) FROM `remaining_machines` WHERE `status` = 'pending'")->fetchColumn();

        // Fetch recent inwards
        $stmt = $pdo->query("SELECT * FROM `inward_transaction` ORDER BY id DESC LIMIT 5");
        $recentInwards = $stmt->fetchAll();

        // Fetch recent invoices
        $stmt = $pdo->query("SELECT * FROM `tax_invoice` ORDER BY id DESC LIMIT 5");
        $recentInvoices = $stmt->fetchAll();
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
} else {
    $dbError = "Could not establish database connection. Verify MySQL is running and setup schema.sql.";
}

renderHeader("System Dashboard", "dashboard");
?>

<?php if ($dbError): ?>
    <div style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 24px; color: var(--error);">
        <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>
        <strong>Database Status:</strong> <?php echo htmlspecialchars($dbError); ?>
    </div>
<?php endif; ?>

<!-- Quick Actions Panel -->
<div class="panel" style="margin-bottom: 24px;">
    <div class="panel-header" style="padding: 14px 24px; background: rgba(255, 255, 255, 0.01);">
        <span class="panel-title"><i class="fa-solid fa-wand-magic-sparkles" style="color: var(--primary);"></i> Quick Operations Control</span>
    </div>
    <div class="panel-body" style="padding: 16px 24px;">
        <div style="display: flex; flex-wrap: wrap; gap: 14px;">
            <a href="inward.php" class="btn btn-primary"><i class="fa-solid fa-arrow-down-long"></i> Record New Inward</a>
            <a href="tax_invoice.php" class="btn btn-success"><i class="fa-solid fa-file-invoice-dollar"></i> Generate Tax Invoice</a>
            <a href="po_master.php" class="btn btn-secondary"><i class="fa-solid fa-file-excel"></i> Import PO Excel</a>
            <a href="bom.php" class="btn btn-secondary"><i class="fa-solid fa-diagram-project"></i> Define BOM</a>
            <a href="raw_material_master.php" class="btn btn-secondary"><i class="fa-solid fa-boxes-stacked"></i> Add Raw Material</a>
            <a href="transport_master.php" class="btn btn-secondary"><i class="fa-solid fa-truck-pickup"></i> Setup Transporter</a>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid" style="margin-bottom: 32px;">
    <!-- Raw Materials -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Raw Materials</span>
            <span class="stat-value"><?php echo $stats['raw_materials']; ?></span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-cubes"></i>
        </div>
    </div>

    <!-- Inwards -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Inward Invoices</span>
            <span class="stat-value"><?php echo $stats['inwards']; ?></span>
        </div>
        <div class="stat-icon-box blue">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
        </div>
    </div>

    <!-- BOM -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">BOM Specs</span>
            <span class="stat-value"><?php echo $stats['bom']; ?></span>
        </div>
        <div class="stat-icon-box green">
            <i class="fa-solid fa-network-wired"></i>
        </div>
    </div>

    <!-- PO Master -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Purchase Orders</span>
            <span class="stat-value"><?php echo $stats['po']; ?></span>
        </div>
        <div class="stat-icon-box purple">
            <i class="fa-solid fa-file-signature"></i>
        </div>
    </div>

    <!-- Transporters -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Transporters</span>
            <span class="stat-value"><?php echo $stats['transporters']; ?></span>
        </div>
        <div class="stat-icon-box orange">
            <i class="fa-solid fa-truck"></i>
        </div>
    </div>

    <!-- Tax Invoices -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Tax Invoices</span>
            <span class="stat-value"><?php echo $stats['invoices']; ?></span>
        </div>
        <div class="stat-icon-box green">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>

    <!-- Pending Machines -->
    <div class="stat-card" style="grid-column: span 2;">
        <div class="stat-info">
            <span class="stat-label">Machines Pending Invoice</span>
            <span class="stat-value"><?php echo $stats['pending_machines']; ?></span>
        </div>
        <div class="stat-icon-box purple">
            <i class="fa-solid fa-gears"></i>
        </div>
    </div>
</div>

<!-- Main Split Panel Layout -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">

    <!-- Recent Inwards -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-cart-flatbed" style="color: var(--secondary);"></i> Recent Inward Materials</span>
            <span class="panel-subtitle">Latest 5 Transactions</span>
        </div>
        <div class="panel-body" style="padding: 0;">
            <div class="table-container" style="border: none; max-height: none;">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Challan No</th>
                            <th>Date</th>
                            <th>Material Code</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentInwards)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No inward records recorded.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentInwards as $in): ?>
                                <tr>
                                    <td><strong style="color: var(--text-highlight);"><?php echo htmlspecialchars($in['ch_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($in['ch_date']); ?></td>
                                    <td><span style="font-family: monospace; font-size: 0.85rem; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border-color);"><?php echo htmlspecialchars($in['m_code']); ?></span></td>
                                    <td><?php echo number_format($in['in_qty'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($in['unit']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Invoices -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-file-invoice" style="color: var(--success);"></i> Recent Dispatched Invoices</span>
            <span class="panel-subtitle">Latest 5 Outwards</span>
        </div>
        <div class="panel-body" style="padding: 0;">
            <div class="table-container" style="border: none; max-height: none;">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Date</th>
                            <th>Wound Code</th>
                            <th>Qty</th>
                            <th>Net Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentInvoices)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No tax invoices generated yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentInvoices as $inv): ?>
                                <tr>
                                    <td><strong style="color: var(--success);"><?php echo htmlspecialchars($inv['inv_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($inv['inv_date']); ?></td>
                                    <td><span style="font-family: monospace; font-size: 0.85rem; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border-color);"><?php echo htmlspecialchars($inv['wound_code']); ?></span></td>
                                    <td><?php echo number_format($inv['qty'], 2); ?> <?php echo htmlspecialchars($inv['uom']); ?></td>
                                    <td><strong style="color: var(--text-highlight);">₹<?php echo number_format($inv['net_total'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
renderFooter();
?>
