<?php
session_start();
if (!isset($_SESSION['user_id'])) { die("Access Denied"); }

require_once '../db/db.php';

$user_id = $_SESSION['user_id'];

// 1. Fetch User Details
$user_query = "SELECT username, email FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$username = htmlspecialchars($user['username']);
$email = htmlspecialchars($user['email']);

// 2. Get Filters
$from_date = $_POST['from_date'] ?? date('Y-m-01');
$to_date = $_POST['to_date'] ?? date('Y-m-d');
$include_stamp = isset($_POST['include_stamp']); 

// 3. Fetch Transactions
$query = "SELECT t.*, c.name as cat_name 
          FROM transactions t 
          LEFT JOIN categories c ON t.category_id = c.id 
          WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ? 
          ORDER BY t.transaction_date ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $user_id, $from_date, $to_date);
$stmt->execute();
$result = $stmt->get_result();

// 4. Calculations
$total_in = 0;
$total_out = 0;
$rows = [];
while($row = $result->fetch_assoc()) {
    if ($row['type'] == 'income') $total_in += $row['amount'];
    else $total_out += $row['amount'];
    $rows[] = $row;
}
$net_balance = $total_in - $total_out;

// Format Dates for Display
$period_start = date('d M, Y', strtotime($from_date));
$period_end = date('d M, Y', strtotime($to_date));
$req_date = date('d F Y, h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement_<?php echo $username; ?>_<?php echo date('Ymd'); ?></title>
    <style>
        /* --- PRINT SETTINGS --- */
        @page { margin: 0; size: A4; }
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            color: #1f2937; 
            background: #fff; 
            margin: 0;
            padding: 40px 50px; 
            font-size: 12px; 
            line-height: 1.4; 
            -webkit-print-color-adjust: exact; 
        }

        /* --- WATERMARK --- */
        .watermark {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80px; color: rgba(0, 102, 255, 0.04); font-weight: 900; text-transform: uppercase;
            z-index: -1; pointer-events: none; white-space: nowrap; border: 10px solid rgba(0, 102, 255, 0.04);
            padding: 20px 50px; border-radius: 20px;
        }

        /* --- HEADER --- */
        .header-container {
            display: flex; justify-content: space-between; align-items: flex-start;
            border-bottom: 3px solid #0066FF; padding-bottom: 20px; margin-bottom: 30px;
        }
        .brand-section svg { height: 50px; width: auto; }
        .company-details { margin-top: 10px; font-size: 10px; color: #6b7280; }

        /* --- DETAILS GRID --- */
        .details-grid {
            display: flex; justify-content: space-between; margin-bottom: 40px;
            background: #f9fafb; padding: 25px; border-radius: 8px; border: 1px solid #e5e7eb;
            position: relative; /* Anchor for stamp */
        }
        .info-column { width: 40%; z-index: 2; position: relative; }
        .info-column h4 { font-size: 10px; text-transform: uppercase; color: #6b7280; margin: 0 0 5px 0; letter-spacing: 0.5px; }
        .info-column p { font-size: 13px; font-weight: 600; color: #111827; margin: 0 0 15px 0; }

        /* --- BIG REALISTIC BLUE STAMP --- */
        .official-stamp {
            position: absolute;
            top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-5deg);
            width: 220px; height: 140px;
            border: 5px double #0052cc; /* Deep Blue */
            border-radius: 12px;
            color: #0052cc;
            display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;
            opacity: 0.85; 
            background: transparent;
            z-index: 1; pointer-events: none;
            mix-blend-mode: multiply;
            box-shadow: inset 0 0 20px rgba(0, 82, 204, 0.1);
        }
        .stamp-head { font-size: 18px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; border-bottom: 2px solid #0052cc; padding-bottom: 4px; width: 90%; margin-bottom: 6px; }
        .stamp-body { font-size: 11px; font-weight: 800; text-transform: uppercase; margin-bottom: 2px; letter-spacing: 1px; }
        .stamp-period { font-size: 9px; font-weight: bold; text-transform: uppercase; margin-top: 4px; background: rgba(255,255,255,0.7); padding: 2px 5px; border-radius: 4px; }
        .stamp-date { font-family: 'Courier New', Courier, monospace; font-size: 10px; margin-top: 4px; }

        /* --- KPI --- */
        .summary-row { display: flex; gap: 20px; margin-bottom: 30px; }
        .kpi-box { flex: 1; background: #fff; border: 1px solid #e5e7eb; padding: 15px; border-radius: 6px; text-align: center; }
        .kpi-title { font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: bold; margin-bottom: 5px; }
        .kpi-val { font-size: 18px; font-weight: 800; }
        .text-green { color: #059669; } .text-red { color: #dc2626; } .text-blue { color: #0066FF; }

        /* --- TABLE --- */
        table { width: 100%; border-collapse: collapse; margin-bottom: 50px; page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th { background-color: #0066FF; color: white; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 15px; text-align: left; font-weight: 600; }
        td { padding: 12px 15px; border-bottom: 1px solid #e5e7eb; font-size: 12px; color: #374151; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .text-right { text-align: right; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }

        /* --- FOOTER --- */
        .footer-fixed {
            position: fixed; bottom: 0; left: 0; right: 0; height: 30px;
            background: #fff; border-top: 1px solid #0066FF; padding: 10px 50px;
            font-size: 9px; color: #6b7280;
            display: flex; justify-content: space-between; align-items: center;
        }
        /* Page Numbering Logic */
        .page-counter:after { content: "Page " counter(page); }

        @media print {
            .no-print { display: none !important; }
            .footer-fixed { position: fixed; bottom: 0; }
        }
    </style>
</head>
<body>

    <!-- Print Button -->
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" style="background: #0066FF; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 12px rgba(0,102,255,0.3); display: flex; align-items: center; gap: 8px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Statement
        </button>
    </div>

    <div class="watermark">OFFICIAL RECORD</div>

    <!-- LETTERHEAD -->
    <div class="header-container">
        <div class="brand-section">
            <!-- SVG LOGO -->
            <svg viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g transform="translate(0, 5)">
                    <rect x="0" y="24" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.4"/>
                    <rect x="0" y="14" width="28" height="7" rx="2" fill="#0066FF" fill-opacity="0.7"/>
                    <rect x="0" y="4" width="28" height="7" rx="2" fill="#0066FF"/>
                    <path d="M34 28C34 28 38 28 40 20C42 12 46 6 46 6" stroke="#00CC88" stroke-width="3" stroke-linecap="round"/>
                </g>
                <g transform="translate(56, 0)">
                    <text x="0" y="22" font-family="Arial, sans-serif" font-weight="900" font-size="24" fill="#1e293b">FlowStack</text>
                    <text x="0" y="42" font-family="Arial, sans-serif" font-weight="500" font-size="14" fill="#0066FF" letter-spacing="2">LEDGER</text>
                </g>
            </svg>
            <div class="company-details">
                FlowStack Financial Systems Inc.<br>
                123 Fintech Plaza, Tech City<br>
                support@flowstack.com | +1 (800) 123-4567
            </div>
        </div>
        <div style="text-align: right;">
            <h1 style="font-size: 24px; color: #0066FF; margin: 0; text-transform: uppercase; letter-spacing: 1px;">Statement</h1>
            <p style="color: #6b7280; margin: 5px 0 0 0;">#STMT-<?php echo date('Ymd'); ?>-<?php echo $user_id; ?></p>
        </div>
    </div>

    <!-- DETAILS GRID -->
    <div class="details-grid">
        <div class="info-column">
            <h4>Customer Name</h4>
            <p><?php echo $username; ?></p>
            <h4>Email Address</h4>
            <p><?php echo $email; ?></p>
        </div>

        <div class="info-column" style="text-align: right;">
            <h4>Statement Period</h4>
            <p><?php echo $period_start; ?> — <?php echo $period_end; ?></p>
            <h4>Request Date</h4>
            <p><?php echo $req_date; ?></p>
        </div>
        
        <!-- REALISTIC BLUE STAMP (Centered) -->
        <?php if ($include_stamp): ?>
        <div class="official-stamp">
            <div class="stamp-head">FLOWSTACK</div>
            <div class="stamp-body">OFFICIAL STATEMENT</div>
            <div class="stamp-period"><?php echo $period_start; ?> — <?php echo $period_end; ?></div>
            <div class="stamp-date">VERIFIED: <?php echo date('d M Y'); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- KPI SUMMARY -->
    <div class="summary-row">
        <div class="kpi-box">
            <div class="kpi-title">Total Income</div>
            <div class="kpi-val text-green">+<?php echo number_format($total_in, 2); ?></div>
        </div>
        <div class="kpi-box">
            <div class="kpi-title">Total Expenses</div>
            <div class="kpi-val text-red">-<?php echo number_format($total_out, 2); ?></div>
        </div>
        <div class="kpi-box">
            <div class="kpi-title">Net Balance</div>
            <div class="kpi-val text-blue"><?php echo number_format($net_balance, 2); ?></div>
        </div>
    </div>

    <!-- TRANSACTIONS TABLE -->
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Category</th>
                <th>Type</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($rows) > 0): ?>
                <?php foreach($rows as $row): ?>
                <tr>
                    <td style="width: 15%; color: #6b7280;"><?php echo date('d M Y', strtotime($row['transaction_date'])); ?></td>
                    <td style="width: 40%;"><strong><?php echo htmlspecialchars($row['description']); ?></strong></td>
                    <td style="width: 20%;"><?php echo htmlspecialchars($row['cat_name'] ?? 'General'); ?></td>
                    <td style="width: 10%;">
                        <?php if($row['type']=='income'): ?>
                            <span style="color:#059669; font-weight:bold; font-size:10px; text-transform:uppercase;">CR</span>
                        <?php else: ?>
                            <span style="color:#dc2626; font-weight:bold; font-size:10px; text-transform:uppercase;">DR</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right font-mono" style="width: 15%; font-weight:bold;">
                        <?php 
                            if($row['type']=='expense') echo '<span class="text-red">- ' . number_format($row['amount'], 2) . '</span>';
                            else echo '<span class="text-green">' . number_format($row['amount'], 2) . '</span>';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center; padding:30px; color:#9ca3af; font-style:italic;">No transactions found for this period.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- DISCLAIMER -->
    <div style="margin-top: 20px; font-size: 10px; color: #9ca3af; text-align: justify;">
        <strong>Disclaimer:</strong> This statement is system-generated and is valid without a physical signature. The data presented reflects the transactions recorded in the FlowStack Ledger system for the specified period.
    </div>

    <!-- FOOTER -->
    <div class="footer-fixed">
        <div class="page-counter"></div>
        <div>FlowStack Ledger Systems © <?php echo date('Y'); ?></div>
        <div>www.flowstack.com</div>
    </div>

</body>
</html>