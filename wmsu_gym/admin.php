<?php
session_start();
include 'db_connect.php';
include 'email_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Auto-update reservation statuses
$conn->query("UPDATE reservations SET status = 'Completed' WHERE status = 'Approved' AND date < CURDATE()");

$hasActive = $conn->query("SELECT 1 FROM reservations WHERE status = 'Approved' AND date >= CURDATE() LIMIT 1");
$conn->query("UPDATE gym_info SET status='" . ($hasActive->num_rows > 0 ? 'Reserved' : 'Available') . "' WHERE id=1");

// Handle DELETE actions
if (isset($_GET['delete_type'], $_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $delete_type = $_GET['delete_type'];
    
    if ($delete_type === 'user') {
        $check = $conn->query("SELECT role FROM users WHERE id=$delete_id")->fetch_assoc();
        if ($check && $check['role'] !== 'admin') {
            $conn->query("DELETE FROM password_changes WHERE user_id=$delete_id");
            $conn->query("DELETE FROM reservations WHERE user_id=$delete_id");
            $conn->query("DELETE FROM usage_history WHERE user_id=$delete_id");
            $conn->query("DELETE FROM users WHERE id=$delete_id");
        }
    } elseif ($delete_type === 'reservation') {
        $conn->query("DELETE FROM reservations WHERE id=$delete_id");
    } elseif ($delete_type === 'account_request') {
        $conn->query("DELETE FROM account_requests WHERE id=$delete_id");
    }
    
    header("Location: admin.php");
    exit();
}

// Handle reservation actions
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        $conn->query("UPDATE reservations SET status='Approved' WHERE id=$id");
    } elseif ($action === 'decline') {
        $conn->query("UPDATE reservations SET status='Declined' WHERE id=$id");
    } elseif ($action === 'cancel') {
        $conn->query("UPDATE reservations SET status='Cancelled' WHERE id=$id");
    }

    header("Location: admin.php");
    exit();
}

// Handle account request approval/decline
if (isset($_GET['req_action'], $_GET['req_id'])) {
    $req_id = (int)$_GET['req_id'];
    $req_action = $_GET['req_action'];

    if ($req_action === 'approve') {
        $req = $conn->query("SELECT * FROM account_requests WHERE id=$req_id AND status='Pending'")->fetch_assoc();

        if ($req) {
            $temporaryPassword = "wmsu123";
            $hashed = password_hash($temporaryPassword, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, department) VALUES (?, ?, ?, 'user', ?)");
            $stmt->bind_param("ssss", $req['name'], $req['email'], $hashed, $req['department']);
            $stmt->execute();

            $conn->query("UPDATE account_requests SET status='Approved' WHERE id=$req_id");

            $emailBody = getAccountApprovalEmailTemplate($req['name'], $req['email'], $temporaryPassword);
            sendEmail($req['email'], $req['name'], "Your WMSU Gym Reservation System Account Has Been Approved", $emailBody);
        }
    } elseif ($req_action === 'decline') {
        $conn->query("UPDATE account_requests SET status='Declined' WHERE id=$req_id");
    }

    header("Location: admin.php");
    exit();
}

// Handle new admin account creation
$admin_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $admin_name = trim($_POST['admin_name']);
    $admin_email = trim($_POST['admin_email']);
    $admin_password = $_POST['admin_password'];
    $admin_department = trim($_POST['admin_department']);

    // Validation
    if (strlen($admin_password) < 6) {
        $admin_message = "<div class='alert alert-warning'><span style='font-size:24px;'>⚠️</span><div><strong>Error</strong><p style='margin:5px 0 0 0;'>Password must be at least 6 characters long.</p></div></div>";
    } else {
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM users WHERE email=?");
        $check_email->bind_param("s", $admin_email);
        $check_email->execute();
        
        if ($check_email->get_result()->num_rows > 0) {
            $admin_message = "<div class='alert alert-warning'><span style='font-size:24px;'>⚠️</span><div><strong>Error</strong><p style='margin:5px 0 0 0;'>Email already exists in the system.</p></div></div>";
        } else {
            // Create admin account
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, department) VALUES (?, ?, ?, 'admin', ?)");
            $stmt->bind_param("ssss", $admin_name, $admin_email, $hashed_password, $admin_department);
            
            if ($stmt->execute()) {
                $admin_message = "<div class='alert alert-success'><span style='font-size:24px;'>✅</span><div><strong>Success!</strong><p style='margin:5px 0 0 0;'>Admin account created successfully!</p></div></div>";
            } else {
                $admin_message = "<div class='alert alert-warning'><span style='font-size:24px;'>❌</span><div><strong>Error</strong><p style='margin:5px 0 0 0;'>Failed to create admin account. Please try again.</p></div></div>";
            }
        }
    }
}

// Fetch data
$gym = $conn->query("SELECT * FROM gym_info WHERE id=1")->fetch_assoc();
$current = $conn->query("SELECT COALESCE(SUM(num_people), 0) AS total FROM reservations WHERE status='Approved' AND date = CURDATE() AND CURTIME() BETWEEN start_time AND end_time");
$current_people = $current->fetch_assoc()['total'];

$total_users = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user'")->fetch_assoc()['c'];
$total_reservations = $conn->query("SELECT COUNT(*) AS c FROM reservations")->fetch_assoc()['c'];
$total_approved = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='Approved'")->fetch_assoc()['c'];
$total_declined = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='Declined'")->fetch_assoc()['c'];
$total_pending = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='Pending'")->fetch_assoc()['c'];
$total_completed = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='Completed'")->fetch_assoc()['c'];
$total_cancelled = $conn->query("SELECT COUNT(*) AS c FROM reservations WHERE status='Cancelled'")->fetch_assoc()['c'];

$res = $conn->query("SELECT r.*, u.name, u.email FROM reservations r JOIN users u ON r.user_id = u.id ORDER BY date DESC");

$show_all_requests = isset($_GET['show_all']) && $_GET['show_all'] === '1';
if ($show_all_requests) {
    $requests = $conn->query("SELECT * FROM account_requests ORDER BY created_at DESC");
} else {
    $requests = $conn->query("SELECT * FROM account_requests WHERE status='Pending' ORDER BY created_at DESC");
}
$pending_requests_count = $conn->query("SELECT COUNT(*) AS c FROM account_requests WHERE status='Pending'")->fetch_assoc()['c'];

$password_logs = $conn->query("SELECT p.*, u.name, u.email FROM password_changes p JOIN users u ON p.user_id = u.id ORDER BY p.changed_at DESC");
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | WMSU Gym Reservation System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%); min-height: 100vh; }
        .header { background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 20px 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .logo-section { display: flex; align-items: center; gap: 15px; }
        .logo-section img { width: 60px; height: 60px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .header-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.3s; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: white; color: #8B0000; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .btn-secondary { background: rgba(255,255,255,0.2); color: white; border: 2px solid white; }
        .btn-secondary:hover { background: rgba(255,255,255,0.3); }
        .btn-logout { background: #f5f5f5; color: #555; border: 2px solid #e0e0e0; }
        .btn-logout:hover { background: #e8e8e8; }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 4px solid #8B0000; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 36px; margin-bottom: 10px; }
        .stat-value { font-size: 32px; font-weight: 700; color: #8B0000; margin: 8px 0; }
        .stat-label { font-size: 13px; color: #666; font-weight: 500; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 25px; }
        .card-header { background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .card-header h2 { font-size: 22px; font-weight: 600; margin: 0; }
        .card-header p { font-size: 14px; opacity: 0.9; margin: 5px 0 0 0; }
        .card-body { padding: 25px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: start; gap: 12px; }
        .alert-success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert-info { background: #e3f2fd; border-left: 4px solid #2196F3; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        thead tr { background: #8B0000; color: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { font-weight: 600; font-size: 14px; }
        td { font-size: 14px; }
        tbody tr:hover { background: #f9f9f9; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #e3f2fd; color: #0c5460; }
        .status-declined { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e0e0e0; color: #555; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .btn-action { padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s; border: none; cursor: pointer; }
        .btn-approve { background: #28a745; color: white; }
        .btn-approve:hover { background: #218838; }
        .btn-decline { background: #6c757d; color: white; }
        .btn-decline:hover { background: #5a6268; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }
        .badge { background: #ff9800; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; font-weight: 600; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state-icon { font-size: 48px; margin-bottom: 15px; }
        footer { background: #2c2c2c; color: white; text-align: center; padding: 25px 20px; margin-top: 60px; }
        @media print { .no-print { display: none !important; } }
        @media (max-width: 768px) { .header-content { flex-direction: column; text-align: center; } .stats-grid { grid-template-columns: 1fr; } table { font-size: 12px; } th, td { padding: 8px; } }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <img src="assets/images/wmsu_logo.png" alt="WMSU Logo" style="width: 80px; height: 55px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                <div>
                    <h1 style="font-size: 24px; font-weight: 700; margin-bottom: 5px;">🛡️ Admin Dashboard</h1>
                    <p style="font-size: 14px; opacity: 0.9;">Welcome, <?= htmlspecialchars($_SESSION['name']); ?></p>
                </div>
            </div>
            <div class="header-buttons">
                <button onclick="window.print()" class="btn btn-primary no-print">🖨️ Print Report</button>
                <a href="dashboard.php" class="btn btn-secondary no-print">← User View</a>
                <a href="logout.php" class="btn btn-logout no-print">🚪 Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (isset($_SESSION['email_status']) && isset($_SESSION['email_message'])): ?>
        <div class="alert alert-<?= $_SESSION['email_status']; ?> no-print">
            <span style="font-size: 24px;"><?= $_SESSION['email_status'] === 'success' ? '✅' : '⚠️'; ?></span>
            <div><?= htmlspecialchars($_SESSION['email_message']); ?></div>
        </div>
        <?php unset($_SESSION['email_status']); unset($_SESSION['email_message']); endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value"><?= $total_users; ?></div><div class="stat-label">Registered Users</div></div>
            <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-value"><?= $total_reservations; ?></div><div class="stat-label">Total Reservations</div></div>
            <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-value"><?= $total_pending; ?></div><div class="stat-label">Pending</div></div>
            <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value"><?= $total_approved; ?></div><div class="stat-label">Approved</div></div>
            <div class="stat-card"><div class="stat-icon">🏆</div><div class="stat-value"><?= $total_completed; ?></div><div class="stat-label">Completed</div></div>
            <div class="stat-card"><div class="stat-icon">❌</div><div class="stat-value"><?= $total_declined; ?></div><div class="stat-label">Declined</div></div>
        </div>

        <div class="card no-print">
            <div class="card-header">
                <div>
                    <h2>🖨️ Export & Reports</h2>
                    <p>Generate comprehensive reports for administrative use</p>
                </div>
            </div>
            <div class="card-body">
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button onclick="window.print()" class="btn btn-primary">🖶 Print Full Report</button>
                    <button onclick="exportReservationsCSV()" class="btn btn-secondary" style="background: #555; color: white;">⬇️ Export Reservations</button>
                    <button onclick="exportUsersCSV()" class="btn btn-secondary" style="background: #555; color: white;">⬇️ Export Users</button>
                    <button onclick="generateComprehensivePDF()" class="btn btn-secondary" style="background: #8B0000; color: white;">📄 Generate Complete Report PDF</button>
                </div>
            </div>
        </div>

        <!-- Printable Report Section (Hidden, only visible when printing) -->
        <div class="print-only" style="display: none;">
            <div style="text-align: center; padding: 30px; border-bottom: 3px solid #8B0000; page-break-after: always;">
                <img src="assets/images/wmsu_logo.png" alt="WMSU Logo" style="width: 100px; margin-bottom: 15px;">
                <h1 style="color: #8B0000; margin: 0;">WMSU Gym Reservation System</h1>
                <h2 style="margin: 10px 0;">Administrative Report</h2>
                <p style="margin: 5px 0;">Generated: <?= date('F d, Y h:i A'); ?></p>
                <p style="margin: 5px 0;">Report by: <?= htmlspecialchars($_SESSION['name']); ?> (Administrator)</p>
            </div>

            <!-- Executive Summary -->
            <div style="padding: 30px; page-break-after: always;">
                <h2 style="color: #8B0000; margin-bottom: 20px;">📊 Executive Summary</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="border: 2px solid #8B0000; padding: 15px; border-radius: 8px;">
                        <h3 style="color: #8B0000; margin-top: 0;">User Statistics</h3>
                        <p><strong>Total Registered Users:</strong> <?= $total_users; ?></p>
                        <p><strong>Active Users Today:</strong> <?= $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM reservations WHERE date = CURDATE() AND status='Approved'")->fetch_assoc()['c']; ?></p>
                    </div>
                    <div style="border: 2px solid #8B0000; padding: 15px; border-radius: 8px;">
                        <h3 style="color: #8B0000; margin-top: 0;">Reservation Statistics</h3>
                        <p><strong>Total Reservations:</strong> <?= $total_reservations; ?></p>
                        <p><strong>Pending:</strong> <?= $total_pending; ?></p>
                        <p><strong>Approved:</strong> <?= $total_approved; ?></p>
                        <p><strong>Completed:</strong> <?= $total_completed; ?></p>
                        <p><strong>Declined:</strong> <?= $total_declined; ?></p>
                        <p><strong>Cancelled:</strong> <?= $total_cancelled; ?></p>
                    </div>
                </div>
            </div>

            <!-- User List with Reservations -->
            <div style="padding: 30px;">
                <h2 style="color: #8B0000; margin-bottom: 20px;">👥 Complete User Directory & Reservation History</h2>
                <?php 
                $users_report = $conn->query("SELECT * FROM users WHERE role='user' ORDER BY name ASC");
                $user_counter = 1;
                while($user_data = $users_report->fetch_assoc()): 
                    $user_reservations = $conn->query("
                        SELECT * FROM reservations 
                        WHERE user_id = {$user_data['id']} 
                        ORDER BY date DESC
                    ");
                    $total_user_reservations = $user_reservations->num_rows;
                    $approved_user = $conn->query("SELECT COUNT(*) as c FROM reservations WHERE user_id={$user_data['id']} AND status='Approved'")->fetch_assoc()['c'];
                    $completed_user = $conn->query("SELECT COUNT(*) as c FROM reservations WHERE user_id={$user_data['id']} AND status='Completed'")->fetch_assoc()['c'];
                    $pending_user = $conn->query("SELECT COUNT(*) as c FROM reservations WHERE user_id={$user_data['id']} AND status='Pending'")->fetch_assoc()['c'];
                ?>
                <div style="border: 2px solid #e0e0e0; padding: 20px; margin-bottom: 20px; border-radius: 8px; page-break-inside: avoid;">
                    <h3 style="color: #8B0000; margin-top: 0; border-bottom: 2px solid #8B0000; padding-bottom: 10px;">
                        <?= $user_counter++; ?>. <?= htmlspecialchars($user_data['name']); ?>
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div>
                            <p style="margin: 5px 0;"><strong>Email:</strong> <?= htmlspecialchars($user_data['email']); ?></p>
                            <p style="margin: 5px 0;"><strong>Department:</strong> <?= htmlspecialchars($user_data['department']); ?></p>
                        </div>
                        <div>
                            <p style="margin: 5px 0;"><strong>Member Since:</strong> <?= date('M d, Y', strtotime($user_data['created_at'])); ?></p>
                            <p style="margin: 5px 0;"><strong>Total Reservations:</strong> <?= $total_user_reservations; ?></p>
                        </div>
                    </div>
                    
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                        <strong>Reservation Summary:</strong>
                        <span style="color: #28a745; margin-left: 10px;">✅ Approved: <?= $approved_user; ?></span>
                        <span style="color: #2196F3; margin-left: 10px;">🏆 Completed: <?= $completed_user; ?></span>
                        <span style="color: #ffc107; margin-left: 10px;">⏳ Pending: <?= $pending_user; ?></span>
                    </div>

                    <?php if ($user_reservations->num_rows > 0): ?>
                    <h4 style="color: #555; margin: 15px 0 10px 0;">Recent Reservations:</h4>
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                        <thead>
                            <tr style="background: #8B0000; color: white;">
                                <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Date</th>
                                <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Time</th>
                                <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">People</th>
                                <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 0;
                            $user_reservations->data_seek(0);
                            while($res_data = $user_reservations->fetch_assoc()): 
                                if($count >= 10) break; // Limit to 10 most recent
                                $count++;
                            ?>
                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd;"><?= date('M d, Y', strtotime($res_data['date'])); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <?= date('h:i A', strtotime($res_data['start_time'])); ?> - 
                                    <?= date('h:i A', strtotime($res_data['end_time'])); ?>
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;"><?= $res_data['num_people']; ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <strong style="color: <?= 
                                        $res_data['status'] == 'Approved' ? '#28a745' : 
                                        ($res_data['status'] == 'Pending' ? '#ffc107' : 
                                        ($res_data['status'] == 'Completed' ? '#2196F3' : 
                                        ($res_data['status'] == 'Declined' ? '#dc3545' : '#6c757d'))) 
                                    ?>;">
                                        <?= $res_data['status']; ?>
                                    </strong>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php if($total_user_reservations > 10): ?>
                    <p style="margin: 10px 0 0 0; font-size: 11px; color: #666; font-style: italic;">
                        Showing 10 of <?= $total_user_reservations; ?> reservations
                    </p>
                    <?php endif; ?>
                    <?php else: ?>
                    <p style="color: #999; font-style: italic;">No reservations made yet.</p>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <style>
            @media print {
                .print-only {
                    display: block !important;
                }
                body {
                    background: white !important;
                }
            }
        </style>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2>📨 Account Requests</h2>
                    <p>Review and approve new account requests</p>
                </div>
                <div class="no-print">
                    <?php if ($show_all_requests): ?>
                    <a href="admin.php" class="btn btn-primary">👁️ Show Pending Only</a>
                    <?php else: ?>
                    <a href="admin.php?show_all=1" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); border: 2px solid white;">📋 Show All</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($pending_requests_count > 0): ?>
                <div class="alert alert-warning no-print">
                    <span style="font-size: 24px;">⚠️</span>
                    <div><strong>Action Required!</strong><p style="margin: 5px 0 0 0;"><?= $pending_requests_count; ?> pending account request(s) awaiting review.</p></div>
                </div>
                <?php endif; ?>

                <?php if ($requests->num_rows > 0): ?>
                <table id="requestsTable">
                    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Department</th><th>Status</th><th class="no-print">Actions</th></tr></thead>
                    <tbody>
                    <?php while($r = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['name']); ?></td>
                        <td><?= htmlspecialchars($r['email']); ?></td>
                        <td><?= htmlspecialchars($r['phone']); ?></td>
                        <td><?= htmlspecialchars($r['department']); ?></td>
                        <td><span class="status-badge status-<?= strtolower($r['status']); ?>"><?= htmlspecialchars($r['status']); ?></span></td>
                        <td class="no-print">
                            <div class="action-buttons">
                                <?php if($r['status'] == 'Pending'): ?>
                                <a href="?req_action=approve&req_id=<?= $r['id']; ?>" class="btn-action btn-approve">✅ Approve</a>
                                <a href="?req_action=decline&req_id=<?= $r['id']; ?>" class="btn-action btn-decline">❌ Decline</a>
                                <?php endif; ?>
                                <button onclick="confirmDelete('account_request', <?= $r['id']; ?>, '<?= addslashes($r['name']); ?>')" class="btn-action btn-delete">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state"><div class="empty-state-icon">🎉</div><p><?= $show_all_requests ? 'No account requests found.' : 'No pending account requests!'; ?></p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div><h2>📋 All Reservations</h2><p>Manage gym reservations and bookings</p></div>
            </div>
            <div class="card-body">
                <table id="reservationsTable">
                    <thead><tr><th>User</th><th>Email</th><th>Date</th><th>Start</th><th>End</th><th>People</th><th>Status</th><th class="no-print">Actions</th></tr></thead>
                    <tbody>
                    <?php while($row = $res->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']); ?></td>
                        <td><?= htmlspecialchars($row['email']); ?></td>
                        <td><?= date('M d, Y', strtotime($row['date'])); ?></td>
                        <td><?= date('h:i A', strtotime($row['start_time'])); ?></td>
                        <td><?= date('h:i A', strtotime($row['end_time'])); ?></td>
                        <td><?= htmlspecialchars($row['num_people']); ?></td>
                        <td><span class="status-badge status-<?= strtolower($row['status']); ?>"><?= htmlspecialchars($row['status']); ?></span></td>
                        <td class="no-print">
                            <div class="action-buttons">
                                <?php if($row['status'] == 'Pending'): ?>
                                <a href="?action=approve&id=<?= $row['id']; ?>" class="btn-action btn-approve">Approve</a>
                                <a href="?action=decline&id=<?= $row['id']; ?>" class="btn-action btn-decline">Decline</a>
                                <?php elseif($row['status'] == 'Approved'): ?>
                                <a href="?action=cancel&id=<?= $row['id']; ?>" class="btn-action btn-decline">Cancel</a>
                                <?php endif; ?>
                                <button onclick="confirmDelete('reservation', <?= $row['id']; ?>, '<?= addslashes($row['name']); ?>')" class="btn-action btn-delete">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h2>👥 User Management</h2><p>View and manage registered users</p></div></div>
            <div class="card-body">
                <table id="usersTable">
                    <thead><tr><th>Name</th><th>Email</th><th>Department</th><th>Role</th><th>Created</th><th class="no-print">Actions</th></tr></thead>
                    <tbody>
                    <?php $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['name']); ?></td>
                        <td><?= htmlspecialchars($user['email']); ?></td>
                        <td><?= htmlspecialchars($user['department']); ?></td>
                        <td><span style="color: <?= $user['role'] === 'admin' ? '#8B0000' : '#555'; ?>; font-weight: bold;"><?= ucfirst($user['role']); ?></span></td>
                        <td><?= date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td class="no-print">
                            <?php if($user['role'] !== 'admin'): ?>
                            <button onclick="confirmDelete('user', <?= $user['id']; ?>, '<?= addslashes($user['name']); ?>')" class="btn-action btn-delete">🗑️ Delete</button>
                            <?php else: ?>
                            <span style="color: #999; font-style: italic;">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card no-print">
            <div class="card-header"><div><h2>🛡️ Create New Admin Account</h2><p>Add a new administrator to the system</p></div></div>
            <div class="card-body">
                <?= $admin_message; ?>
                
                <div class="alert alert-info">
                    <span style="font-size: 24px;">ℹ️</span>
                    <div>
                        <strong>Admin Privileges</strong>
                        <p style="margin: 5px 0 0 0;">Admin accounts have full access to manage reservations, users, and system settings.</p>
                    </div>
                </div>

                <form method="POST" style="display: grid; gap: 20px; max-width: 600px;">
                    <input type="hidden" name="create_admin" value="1">
                    
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-weight: 600; color: #333; font-size: 15px;">👤 Full Name <span style="color: #e74c3c;">*</span></label>
                        <input type="text" name="admin_name" required placeholder="Enter admin name" 
                               style="padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; font-family: inherit;"
                               onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-weight: 600; color: #333; font-size: 15px;">📧 Email Address <span style="color: #e74c3c;">*</span></label>
                        <input type="email" name="admin_email" required placeholder="admin@example.com" 
                               style="padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; font-family: inherit;"
                               onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-weight: 600; color: #333; font-size: 15px;">🔒 Password <span style="color: #e74c3c;">*</span></label>
                        <input type="password" name="admin_password" required placeholder="Minimum 6 characters" 
                               style="padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; font-family: inherit;"
                               onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e0e0e0'">
                        <small style="color: #666; font-size: 13px;">Password must be at least 6 characters long</small>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="font-weight: 600; color: #333; font-size: 15px;">🎓 Department <span style="color: #e74c3c;">*</span></label>
                        <select name="admin_department" required 
                                style="padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; font-family: inherit; background: white;"
                                onfocus="this.style.borderColor='#8B0000'" onblur="this.style.borderColor='#e0e0e0'">
                            <option value="">-- Select Department --</option>
                            <option value="Administration">Administration</option>
                            <option value="IT Department">IT Department</option>
                            <option value="Physical Education">Physical Education</option>
                            <option value="Student Affairs">Student Affairs</option>
                            <option value="College of Agriculture">College of Agriculture</option>
                            <option value="College of Architecture">College of Architecture</option>
                            <option value="College of Asian and Islamic Studies">College of Asian and Islamic Studies</option>
                            <option value="College of Computing Studies">College of Computing Studies</option>
                            <option value="College of Education">College of Education</option>
                            <option value="College of Engineering">College of Engineering</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 16px; margin-top: 10px;">
                        ✅ Create Admin Account
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h2>🔒 Password Change Logs</h2><p>Recent password modifications</p></div></div>
            <div class="card-body">
                <?php if($password_logs->num_rows > 0): ?>
                <table>
                    <thead><tr><th>User</th><th>Email</th><th>Changed At</th></tr></thead>
                    <tbody>
                    <?php while($log = $password_logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['name']); ?></td>
                        <td><?= htmlspecialchars($log['email']); ?></td>
                        <td><?= htmlspecialchars($log['changed_at']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state"><div class="empty-state-icon">🔐</div><p>No password changes recorded yet.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer><p style="margin: 0; font-size: 14px; opacity: 0.9;">© <?= date('Y'); ?> Western Mindanao State University</p><p style="margin: 8px 0 0 0; font-size: 13px; opacity: 0.7;">WMSU Gym Reservation System - Admin Panel</p></footer>

    <script>
    function confirmDelete(type, id, name) {
        if (confirm('Are you sure you want to delete this ' + type + '?\n\n' + (name ? 'Name: ' + name : 'ID: ' + id))) {
            window.location.href = '?delete_type=' + type + '&delete_id=' + id;
        }
    }

    function exportReservationsCSV() {
        let csv = [];
        const table = document.getElementById("reservationsTable");
        const rows = table.querySelectorAll("tr");
        for (let row of rows) {
            const cols = row.querySelectorAll("td, th");
            let rowData = [];
            for (let i = 0; i < cols.length - 1; i++) {
                rowData.push('"' + cols[i].innerText.replace(/"/g, '""') + '"');
            }
            csv.push(rowData.join(","));
        }
        const csvString = csv.join("\n");
        const blob = new Blob([csvString], { type: "text/csv" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "reservations_" + new Date().toISOString().split('T')[0] + ".csv";
        link.click();
    }

    function exportUsersCSV() {
        let csv = [];
        const table = document.getElementById("usersTable");
        const rows = table.querySelectorAll("tr");
        for (let row of rows) {
            const cols = row.querySelectorAll("td, th");
            let rowData = [];
            for (let i = 0; i < cols.length - 1; i++) {
                rowData.push('"' + cols[i].innerText.replace(/"/g, '""') + '"');
            }
            csv.push(rowData.join(","));
        }
        const csvString = csv.join("\n");
        const blob = new Blob([csvString], { type: "text/csv" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "users_" + new Date().toISOString().split('T')[0] + ".csv";
        link.click();
    }

    async function generateComprehensivePDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        
        doc.setFont("helvetica", "bold");
        doc.setFontSize(22);
        doc.setTextColor(139, 0, 0);
        doc.text("WMSU Gym Reservation System", doc.internal.pageSize.getWidth() / 2, 50, { align: 'center' });
        
        doc.setFontSize(16);
        doc.text("Administrative Report", doc.internal.pageSize.getWidth() / 2, 75, { align: 'center' });
        
        doc.setFontSize(10);
        doc.setTextColor(0, 0, 0);
        doc.setFont("helvetica", "normal");
        doc.text("Generated: " + new Date().toLocaleString(), doc.internal.pageSize.getWidth() / 2, 95, { align: 'center' });
        doc.text("Report by: <?= htmlspecialchars($_SESSION['name']); ?>", doc.internal.pageSize.getWidth() / 2, 110, { align: 'center' });

        const kpiData = [
            ["Total Users", "<?= $total_users; ?>"],
            ["Total Reservations", "<?= $total_reservations; ?>"],
            ["Pending", "<?= $total_pending; ?>"],
            ["Approved", "<?= $total_approved; ?>"],
            ["Completed", "<?= $total_completed; ?>"],
            ["Declined", "<?= $total_declined; ?>"],
            ["Cancelled", "<?= $total_cancelled; ?>"]
        ];
        
        doc.autoTable({
            startY: 130,
            head: [["Metric", "Value"]],
            body: kpiData,
            styles: { fontSize: 11, halign: 'center' },
            theme: 'striped',
            headStyles: { fillColor: [139, 0, 0], textColor: [255, 255, 255] }
        });

        doc.addPage();
        doc.setFontSize(14);
        doc.setFont("helvetica", "bold");
        doc.setTextColor(139, 0, 0);
        doc.text("Registered Users", 40, 50);
        
        const usersTable = document.getElementById("usersTable");
        const usersHeaders = Array.from(usersTable.querySelectorAll("th")).slice(0, -1).map(th => th.innerText);
        const usersBody = Array.from(usersTable.querySelectorAll("tbody tr")).map(row =>
            Array.from(row.querySelectorAll("td")).slice(0, -1).map(td => td.innerText)
        );

        doc.autoTable({
            startY: 60,
            head: [usersHeaders],
            body: usersBody,
            styles: { fontSize: 9 },
            headStyles: { fillColor: [139, 0, 0], textColor: [255, 255, 255] },
            theme: 'striped'
        });

        doc.addPage();
        doc.setFontSize(14);
        doc.setFont("helvetica", "bold");
        doc.setTextColor(139, 0, 0);
        doc.text("All Reservations", 40, 50);
        
        const reservationsTable = document.getElementById("reservationsTable");
        const reservationsHeaders = Array.from(reservationsTable.querySelectorAll("th")).slice(0, -1).map(th => th.innerText);
        const reservationsBody = Array.from(reservationsTable.querySelectorAll("tbody tr")).map(row =>
            Array.from(row.querySelectorAll("td")).slice(0, -1).map(td => td.innerText)
        );

        doc.autoTable({
            startY: 60,
            head: [reservationsHeaders],
            body: reservationsBody,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [139, 0, 0], textColor: [255, 255, 255] },
            theme: 'striped'
        });

        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(128, 128, 128);
            doc.text(
                'Page ' + i + ' of ' + pageCount,
                doc.internal.pageSize.getWidth() / 2,
                doc.internal.pageSize.getHeight() - 20,
                { align: 'center' }
            );
        }

        doc.save("WMSU_Gym_Admin_Report_" + new Date().toISOString().split('T')[0] + ".pdf");
    }
    </script>
</body>
</html>
