<?php
// Jagdamba Electrical - NFP Outward Challan Print Template (nfp_print.php)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/db_config.php';

$challan_no = $_GET['challan_no'] ?? null;
$challanRows = [];
$challan = null;
$partyName = '';
$error = null;

if (!$challan_no) {
    $error = "Challan Number parameter is missing.";
} else {
    try {
        $pdo = getDatabaseConnection();


        // Fetch challan items
        $stmt = $pdo->prepare("SELECT * FROM `nfp_outward` WHERE `challan_no` = :challan_no ORDER BY sr_no ASC");
        $stmt->execute([':challan_no' => $challan_no]);
        $challanRows = $stmt->fetchAll();

        if (empty($challanRows)) {
            $error = "Challan not found: " . htmlspecialchars($challan_no);
        } else {
            $challan = $challanRows[0];

            // Fetch party name
            if ($challan['party_id']) {
                $stmtP = $pdo->prepare("SELECT `party_name` FROM `parties` WHERE `id` = :id LIMIT 1");
                $stmtP->execute([':id' => $challan['party_id']]);
                $pRow = $stmtP->fetch();
                $partyName = $pRow ? $pRow['party_name'] : '-';
            }
            if ($partyName === 'M314' || $partyName === 'M311') {
                $partyName = 'CG POWER AND INDUSTRIAL SOLUTION LTD';
            }

            // Fetch transporter name from vehicle number
            $transporter = 'By Road';
            if ($challan['vehicle_no']) {
                $stmtTr = $pdo->prepare("SELECT `transport_name` FROM `transport_master` WHERE `vech_no` = :vn LIMIT 1");
                $stmtTr->execute([':vn' => $challan['vehicle_no']]);
                $trRow = $stmtTr->fetch();
                if ($trRow) $transporter = $trRow['transport_name'];
            }
        }
    } catch (Exception $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Number to Words converter helper
function convertNumberToWords($number) {
    if (floatval($number) == 0) {
        return 'Zero Rupees Only';
    }
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two',
        3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
        7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
        13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
        70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    $paise = ($decimal > 0) ? " and " . ($words[floor($decimal / 10) * 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
    return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . ' Only';
}

function formatDateDisplay($dateStr) {
    if (!$dateStr) return '-';
    return date("d-m-Y", strtotime($dateStr));
}

// Calculate totals
$total_qty = 0;
$total_sub_total = 0;
if (!empty($challanRows)) {
    foreach ($challanRows as $row) {
        $total_qty += floatval($row['qty']);
        $total_sub_total += floatval($row['amount']);
    }
}
$total_cgst = round($total_sub_total * 0.09, 2);
$total_sgst = round($total_sub_total * 0.09, 2);
$total_net_total = $total_sub_total + $total_cgst + $total_sgst;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NFP Outward Challan - <?php echo htmlspecialchars($challan_no ?? 'Error'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            color: #000;
            background-color: #fff;
            margin: 0;
            padding: 10px;
            font-size: 12px;
            line-height: 1.35;
        }

        .print-actions {
            margin-bottom: 15px;
            padding: 8px;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            display: flex;
            gap: 10px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn {
            padding: 6px 12px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 4px;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background-color: #2563eb;
            color: white;
            border-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #e2e8f0;
            color: #334155;
            border-color: #cbd5e1;
        }

        .invoice-wrapper {
            max-width: 800px;
            margin: auto;
            border: 1.5px solid #000;
            background-color: #fff;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            min-height: 268mm;
        }

        .invoice-header {
            position: relative;
            text-align: center;
            padding: 12px 15px;
            border-bottom: 1.5px solid #000;
        }

        .logo-box {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 80px;
        }

        .jagdamba-logo-graphics {
            position: relative;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-s-curve {
            font-family: 'Arial Black', Impact, sans-serif;
            font-size: 38px;
            font-weight: 900;
            color: #1e293b;
            line-height: 1;
            user-select: none;
            position: relative;
            transform: scaleX(1.1);
        }

        .logo-bolt-overlay {
            position: absolute;
            font-size: 20px;
            color: #fbbf24;
            transform: rotate(-10deg);
            text-shadow:
                -1.5px -1.5px 0 #fff,
                 1.5px -1.5px 0 #fff,
                -1.5px  1.5px 0 #fff,
                 1.5px  1.5px 0 #fff;
            z-index: 2;
        }

        .logo-text-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 2px;
            width: 100%;
        }

        .logo-text-jagdamba {
            font-family: 'Arial Black', sans-serif;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #000;
            line-height: 1;
            text-transform: uppercase;
        }

        .logo-text-elec {
            font-family: Arial, sans-serif;
            font-size: 4.5px;
            font-weight: bold;
            letter-spacing: 0.2px;
            color: #d97706;
            text-transform: uppercase;
            line-height: 1;
            margin-top: 1px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .logo-text-elec::before, .logo-text-elec::after {
            content: "";
            flex-grow: 1;
            height: 0.5px;
            background: #d97706;
            margin: 0 2px;
        }

        .header-text {
            width: 100%;
        }

        .invoice-title {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }

        .company-name {
            font-size: 26px;
            font-weight: bold;
            font-family: 'Arial Black', sans-serif;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .company-address, .company-tax {
            font-size: 12px;
            margin-bottom: 2px;
        }

        .invoice-meta-row {
            display: flex;
            border-bottom: 1.5px solid #000;
        }

        .meta-col-left {
            width: 55%;
            border-right: 1px solid #000;
            padding: 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-sizing: border-box;
        }

        .party-box {
            line-height: 1.4;
        }

        .box-label {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 12px;
        }

        .party-name {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .party-address {
            margin-bottom: 4px;
            font-size: 12px;
        }

        .party-details {
            font-size: 12px;
        }

        .meta-col-right {
            width: 45%;
            display: flex;
            flex-direction: column;
        }

        .meta-data-table {
            width: 100%;
            border-collapse: collapse;
            flex-grow: 1;
        }

        .meta-data-table td {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 6px 8px;
            vertical-align: middle;
            font-size: 12px;
            height: 30px;
            box-sizing: border-box;
        }

        .meta-data-table td:last-child {
            border-right: none;
        }

        .meta-data-table tr:last-child td {
            border-bottom: none;
        }

        .items-grid-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #000;
        }

        .items-grid-table th {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 5px;
            background-color: #f2f2f2;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            height: 36px;
            box-sizing: border-box;
        }

        .items-grid-table th:last-child {
            border-right: none;
        }

        .items-grid-table td {
            border-right: 1px solid #000;
            padding: 5px 6px;
            font-size: 12px;
            box-sizing: border-box;
            vertical-align: top;
        }

        .items-grid-table td:last-child {
            border-right: none;
        }

        /* Compact height for regular body rows */
        .items-grid-table tbody tr {
            height: 38px;
        }

        /* Let the last spacer row stretch to absorb the table's remaining height */
        .items-grid-table tbody tr.empty-spacer-row:nth-last-child(2) {
            height: auto;
        }

        .empty-spacer-row td {
            border-top: none;
            border-bottom: none;
        }

        .totals-section {
            display: flex;
            border-bottom: 1.5px solid #000;
        }

        .words-box {
            width: 55%;
            border-right: 1px solid #000;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            box-sizing: border-box;
        }

        .words-text {
            text-transform: uppercase;
            font-weight: bold;
            font-size: 11px;
            margin-top: 4px;
        }

        .totals-box {
            width: 45%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 8px;
            font-size: 11px;
            border-bottom: 1px solid #000;
            box-sizing: border-box;
        }

        .totals-table tr:last-child td {
            border-bottom: none;
        }

        .totals-table td.lbl {
            border-right: 1px solid #000;
            font-weight: bold;
            width: 50%;
        }

        .totals-table td.val {
            text-align: right;
            font-weight: bold;
            width: 50%;
        }

        .net-payable-row td {
            background-color: #f2f2f2;
            font-size: 12px !important;
        }

        .footer-grid-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-grid-table td {
            border: 1px solid #000;
            padding: 10px;
            vertical-align: top;
            font-size: 10.5px;
            line-height: 1.35;
            box-sizing: border-box;
            height: 90px;
        }

        .footer-grid-table tr:first-child td {
            border-top: none;
        }

        .footer-grid-table tr:last-child td {
            border-bottom: none;
        }

        .footer-grid-table td:first-child {
            border-left: none;
        }

        .footer-grid-table td:last-child {
            border-right: none;
        }

        @media print {
            .print-actions {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            .invoice-wrapper {
                border: 1.5px solid #000 !important;
                max-width: 100%;
                width: 100%;
                min-height: 272mm;
            }
        }
    </style>
</head>
<body onload="window.print()">

    <!-- Print Control Buttons -->
    <div class="print-actions">
        <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Challan</button>
        <?php if ($challan): ?>
            <button class="btn btn-secondary" style="background-color: #f59e0b; color: white; border-color: #d97706;" onclick="window.location.href = 'nfp_outward.php'"><i class="fa-solid fa-pen-to-square"></i> Edit Challan</button>
        <?php endif; ?>
        <button class="btn btn-secondary" onclick="window.close();">Close Window</button>
        <?php if ($challan): ?>
            <button class="btn btn-secondary" onclick="window.location.href = 'nfp_outward.php'">Back to NFP Outward</button>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div style="max-width: 800px; margin: auto; padding: 20px; border: 1px solid red; background-color: #fef2f2; color: red; border-radius: 6px;">
            <h3>Error Loading Challan</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php else: ?>

        <div class="invoice-wrapper">
            <!-- Header section -->
            <div class="invoice-header">
                <div class="logo-box">
                    <div class="jagdamba-logo-graphics">
                        <div class="logo-s-curve">J</div>
                        <div class="logo-bolt-overlay"><i class="fa-solid fa-bolt"></i></div>
                    </div>
                    <div class="logo-text-group">
                        <div class="logo-text-jagdamba">JAGDAMBA</div>
                        <div class="logo-text-elec">ELECTRICAL</div>
                    </div>
                </div>
                <div class="header-text">
                    <div class="company-name">JAGDAMBA ELECTRICAL</div>
                    <div class="company-address">M-38, MIDC, AHILYANAGAR - 414 111,, MAHARASHTRA , CODE -27</div>
                    <div class="company-tax">GSTIN NO : 27AQHPA2227N1ZV, PAN NO : AQHPA2227N</div>
                </div>
            </div>

            <!-- Buyer Details & Meta Data Grid -->
            <div class="invoice-meta-row">
                <div class="meta-col-left">
                    <div class="party-box">
                        <div class="box-label">Billed To,</div>
                        <div class="party-name">CG POWER AND INDUSTRIAL SOLUTION LTD</div>
                        <div class="party-address">
                            A- 6/2, MIDC Area, Ahilyanagar- 414 111
                        </div>
                        <div class="party-details">
                            <strong>GSTIN No :</strong> 22AAACC3840K1ZP<br>
                            <strong>PAN No :</strong> AAACC3840K<br>
                            <strong>State -</strong> Maharashtra &nbsp;&nbsp;&nbsp;&nbsp; <strong>Code :</strong> 27
                        </div>
                    </div>
                </div>
                <div class="meta-col-right">
                    <table class="meta-data-table">
                        <tr>
                            <td style="width: 50%;"><strong>Challan No</strong></td>
                            <td style="width: 50%;">: <?php echo htmlspecialchars($challan['challan_no']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Challan Date</strong></td>
                            <td>: <?php echo formatDateDisplay($challan['challan_date']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>PO. No</strong></td>
                            <td>: NFP</td>
                        </tr>
                        <tr>
                            <td><strong>Vehicle No</strong></td>
                            <td>: <?php echo htmlspecialchars($challan['vehicle_no'] ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Vendor Code</strong></td>
                            <td>: 7900758</td>
                        </tr>
                        <tr>
                            <td><strong>PLANT NAME</strong></td>
                            <td>: <?php echo htmlspecialchars($partyName ?: '-'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Itemized grid table (Fixed 6 rows limit) -->
            <div style="flex-grow: 1; display: flex; flex-direction: column;">
                <table class="items-grid-table" style="flex-grow: 1; height: 100%;">
                <thead>
                    <tr>
                        <th style="width: 5%;">Sr. No</th>
                        <th style="width: 37%;">Description</th>
                        <th style="width: 20%;">Material Code</th>
                        <th style="width: 10%;">HSN /SAC<br>Code</th>
                        <th style="width: 6%;">Qty.</th>
                        <th style="width: 6%;">Uom</th>
                        <th style="width: 7%;">Rate</th>
                        <th style="width: 9%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    for ($i = 0; $i < 6; $i++) {
                        if (isset($challanRows[$i])) {
                            $row = $challanRows[$i];
                            ?>
                            <tr>
                                <td style="text-align: center; vertical-align: top; border-bottom: 1px solid #000;"><?php echo intval($row['sr_no']); ?></td>
                                <td style="vertical-align: top; border-bottom: 1px solid #000;">
                                    <strong><?php echo htmlspecialchars($row['wound_code']); ?></strong>
                                    <?php if ($row['description']): ?>
                                        <br><span style="color: #333; font-size: 8.0px;"><?php echo htmlspecialchars($row['description']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; vertical-align: top; border-bottom: 1px solid #000;"><?php echo htmlspecialchars($row['m_code'] ?: '-'); ?></td>
                                <td style="text-align: center; vertical-align: top; border-bottom: 1px solid #000;"><?php echo htmlspecialchars($row['hsn_code'] ?: '-'); ?></td>
                                <td style="text-align: center; vertical-align: top; font-weight: bold; border-bottom: 1px solid #000;"><?php echo (floatval($row['qty']) == intval($row['qty'])) ? intval($row['qty']) : number_format($row['qty'], 2); ?></td>
                                <td style="text-align: center; vertical-align: top; border-bottom: 1px solid #000;"><?php echo htmlspecialchars($row['uom'] ?: 'NOS'); ?></td>
                                <td style="text-align: center; vertical-align: top; border-bottom: 1px solid #000;"><?php echo (floatval($row['rate']) == 0) ? '0.0' : number_format($row['rate'], 2); ?></td>
                                <td style="text-align: right; vertical-align: top; font-weight: bold; border-bottom: 1px solid #000;"><?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                            <?php
                        } else {
                            ?>
                            <tr class="empty-spacer-row">
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                    <!-- Total Row -->
                    <tr style="height: 28px; border-top: 1.5px solid #000; font-weight: bold; background-color: #f2f2f2;">
                        <td colspan="4" style="text-align: right; font-weight: bold; border-right: 1px solid #000; vertical-align: middle; padding: 6px 8px;">Total :</td>
                        <td style="text-align: center; font-weight: bold; border-right: 1px solid #000; vertical-align: middle; padding: 6px 8px;"><?php echo (floatval($total_qty) == intval($total_qty)) ? intval($total_qty) : number_format($total_qty, 2); ?></td>
                        <td style="border-right: 1px solid #000; vertical-align: middle;">&nbsp;</td>
                        <td style="border-right: 1px solid #000; vertical-align: middle;">&nbsp;</td>
                        <td style="text-align: right; font-weight: bold; vertical-align: middle; padding: 6px 8px;"><?php echo number_format($total_sub_total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Net Total & Info -->
        <div class="totals-section">
                <div class="words-box">
                    <strong>Amt. in word :</strong>
                    <div class="words-text"><?php echo convertNumberToWords($total_net_total); ?></div>
                </div>
                <div class="totals-box">
                    <table class="totals-table">
                        <tr>
                            <td class="lbl">Sub Total :</td>
                            <td class="val"><?php echo number_format($total_sub_total, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">CGST 9%</td>
                            <td class="val"><?php echo number_format($total_cgst, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">SGST 9%</td>
                            <td class="val"><?php echo number_format($total_sgst, 2); ?></td>
                        </tr>
                        <tr class="net-payable-row">
                            <td class="lbl">Net Payable</td>
                            <td class="val"><?php echo number_format($total_net_total, 2); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Custom Signatures Grid -->
            <table class="footer-grid-table">
                <tr>
                    <td style="width: 25%;">
                        <strong>Security Entry No. & Date</strong>
                        <div style="height: 50px;"></div>
                    </td>
                    <td style="width: 25%;">
                        <strong>D&R Checked By & Date</strong>
                        <div style="height: 30px;"></div>
                        <strong>Inspection By & Date</strong>
                    </td>
                    <td style="width: 25%;">
                        <strong>Remark :</strong>
                        <div style="height: 50px;"></div>
                    </td>
                    <td style="width: 25%; text-align: center;">
                        <strong>For JAGDAMBA ELECTRICAL</strong>
                        <div style="height: 30px;"></div>
                        <div style="font-weight: bold; font-size: 8px;">Authorize Signatory</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Received the Material subject to quantity & quality check</strong>
                        <div style="height: 35px;"></div>
                    </td>
                    <td>
                        <strong>Received by & Date</strong>
                        <div style="height: 45px;"></div>
                    </td>
                    <td>
                        <strong>C.C.I No.</strong>
                        <div style="height: 45px;"></div>
                    </td>
                    <td style="text-align: center; vertical-align: bottom;">
                        <strong>Store Incharge</strong>
                    </td>
                </tr>
            </table>
        </div>

    <?php endif; ?>

</body>
</html>
