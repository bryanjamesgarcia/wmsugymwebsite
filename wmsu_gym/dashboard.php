<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle reservation deletion
if (isset($_GET['delete_reservation_id'])) {
    $delete_id = (int)$_GET['delete_reservation_id'];
    
    $verify_stmt = $conn->prepare("SELECT id FROM reservations WHERE id=? AND user_id=?");
    $verify_stmt->bind_param("ii", $delete_id, $_SESSION['user_id']);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM reservations WHERE id=?");
        $delete_stmt->bind_param("i", $delete_id);
        $delete_stmt->execute();
    }
    
    header("Location: dashboard.php");
    exit();
}

// Auto-update reservation statuses
$conn->query("UPDATE reservations SET status = 'Completed' WHERE status = 'Approved' AND date < CURDATE()");
$conn->query("UPDATE gym_info SET status = (CASE WHEN EXISTS (SELECT 1 FROM reservations WHERE status='Approved' AND date = CURDATE()) THEN 'Reserved' ELSE 'Available' END)");

// Fetch gym info
$gym_info = $conn->query("SELECT * FROM gym_info WHERE id=1")->fetch_assoc();

// Calculate stats
$current = $conn->query("SELECT COALESCE(SUM(num_people), 0) AS total FROM reservations WHERE status='Approved' AND date = CURDATE() AND CURTIME() BETWEEN start_time AND end_time");
$current_people = $current->fetch_assoc()['total'];

$today_total = $conn->query("SELECT COALESCE(SUM(num_people), 0) AS total FROM reservations WHERE status='Approved' AND date = CURDATE()");
$today_reserved = $today_total->fetch_assoc()['total'];

$capacity = (int)$gym_info['capacity'];
$is_full = ($today_reserved >= $capacity);
$available_slots = max(0, $capacity - $today_reserved);

// Fetch upcoming reservations
$today = date('Y-m-d');
$upcoming = $conn->query("SELECT date, start_time, end_time FROM reservations WHERE status='Approved' AND date >= '$today' ORDER BY date ASC, start_time ASC LIMIT 5");

// Fetch user reservations
$res_stmt = $conn->prepare("SELECT * FROM reservations WHERE user_id=? ORDER BY date DESC");
$res_stmt->bind_param("i", $_SESSION['user_id']);
$res_stmt->execute();
$res_result = $res_stmt->get_result();

// Fetch user info
$user_stmt = $conn->prepare("SELECT name, email, department FROM users WHERE id=?");
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Handle password change
$pass_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['old_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $user_stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $user_stmt->bind_param("i", $_SESSION['user_id']);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result()->fetch_assoc();
    $old_hash = $user_result['password'];

    if (!password_verify($old, $old_hash)) {
        $pass_message = "<div class='alert alert-danger'><span style='font-size:20px;'>❌</span><div><strong>Error</strong><p style='margin:5px 0 0 0;'>Incorrect old password.</p></div></div>";
    } elseif ($new !== $confirm) {
        $pass_message = "<div class='alert alert-danger'><span style='font-size:20px;'>⚠️</span><div><strong>Error</strong><p style='margin:5px 0 0 0;'>New passwords do not match.</p></div></div>";
    } elseif (strlen($new) < 6) {
        $pass_message = "<div class='alert alert-danger'><span style='font-size:20px;'>⚠️</span><div><strong>Error</strong><p style='margin:5px 0 0 0;'>Password must be at least 6 characters.</p></div></div>";
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $update_stmt->bind_param("si", $new_hash, $_SESSION['user_id']);
        $update_stmt->execute();

        $log_stmt = $conn->prepare("INSERT INTO password_changes (user_id, old_password_hash, new_password_hash) VALUES (?, ?, ?)");
        $log_stmt->bind_param("iss", $_SESSION['user_id'], $old_hash, $new_hash);
        $log_stmt->execute();

        $pass_message = "<div class='alert alert-success'><span style='font-size:20px;'>✅</span><div><strong>Success!</strong><p style='margin:5px 0 0 0;'>Password updated successfully!</p></div></div>";
    }
}

// Count reservations by status
$pending_count = $conn->prepare("SELECT COUNT(*) as c FROM reservations WHERE user_id=? AND status='Pending'");
$pending_count->bind_param("i", $_SESSION['user_id']);
$pending_count->execute();
$pending = $pending_count->get_result()->fetch_assoc()['c'];

$approved_count = $conn->prepare("SELECT COUNT(*) as c FROM reservations WHERE user_id=? AND status='Approved'");
$approved_count->bind_param("i", $_SESSION['user_id']);
$approved_count->execute();
$approved = $approved_count->get_result()->fetch_assoc()['c'];

$completed_count = $conn->prepare("SELECT COUNT(*) as c FROM reservations WHERE user_id=? AND status='Completed'");
$completed_count->bind_param("i", $_SESSION['user_id']);
$completed_count->execute();
$completed = $completed_count->get_result()->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | WMSU Gym Reservation System</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 4px solid #8B0000; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 36px; margin-bottom: 10px; }
        .stat-value { font-size: 32px; font-weight: 700; color: #8B0000; margin: 8px 0; }
        .stat-label { font-size: 14px; color: #666; font-weight: 500; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 25px; }
        .card-header { background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 20px 25px; }
        .card-header h2 { font-size: 22px; font-weight: 600; margin-bottom: 5px; }
        .card-header p { font-size: 14px; opacity: 0.9; margin: 0; }
        .card-body { padding: 25px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: start; gap: 12px; }
        .alert-danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert-success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert-info { background: #e3f2fd; border-left: 4px solid #2196F3; color: #0c5460; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .info-item { display: flex; align-items: center; gap: 10px; }
        .info-icon { font-size: 24px; color: #8B0000; }
        .info-content strong { display: block; font-size: 12px; color: #666; margin-bottom: 3px; }
        .info-content span { font-size: 16px; font-weight: 600; color: #333; }
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
        .btn-cancel { background: #6c757d; color: white; }
        .btn-cancel:hover { background: #5a6268; }
        .btn-reschedule { background: #8B0000; color: white; }
        .btn-reschedule:hover { background: #6d0000; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 14px; }
        .form-control { width: 100%; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: inherit; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: #8B0000; box-shadow: 0 0 0 3px rgba(139,0,0,0.1); }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state-icon { font-size: 48px; margin-bottom: 15px; }
        footer { background: #2c2c2c; color: white; text-align: center; padding: 25px 20px; margin-top: 60px; }
        @media (max-width: 768px) { .header-content { flex-direction: column; text-align: center; } .stats-grid, .info-grid { grid-template-columns: 1fr; } table { font-size: 12px; } th, td { padding: 8px; } }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <img src="assets/images/wmsu_logo.png" alt="WMSU Logo" style="width: 80px; height: 55px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                <div>
                    <h1 style="font-size: 24px; font-weight: 700; margin-bottom: 5px;">👋 Welcome, <?= htmlspecialchars($_SESSION['name']); ?>!</h1>
                    <p style="font-size: 14px; opacity: 0.9;">WMSU Gym Reservation Dashboard</p>
                </div>
            </div>
            <div class="header-buttons">
                <a href="reserve.php" class="btn btn-primary">📅 Make Reservation</a>
                <?php if ($_SESSION['role'] == 'admin'): ?><a href="admin.php" class="btn btn-secondary">🛠 Admin Panel</a><?php endif; ?>
                <a href="logout.php" class="btn btn-logout">🚪 Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-value"><?= $pending; ?></div><div class="stat-label">Pending Reservations</div></div>
            <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value"><?= $approved; ?></div><div class="stat-label">Approved Reservations</div></div>
            <div class="stat-card"><div class="stat-icon">🏆</div><div class="stat-value"><?= $completed; ?></div><div class="stat-label">Completed Sessions</div></div>
            <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value"><?= $available_slots; ?></div><div class="stat-label">Available Slots Today</div></div>
        </div>

        <div class="card">
            <div class="card-header"><h2>🏛️ Gym Information & Schedule</h2><p>Real-time gym status, availability, and upcoming reservations</p></div>
            <div class="card-body">
                <?php if ($is_full): ?>
                <div class="alert alert-danger"><span style="font-size: 24px;">🚫</span><div><strong style="font-size: 16px;">GYM IS FULL FOR TODAY!</strong><p style="margin: 5px 0 0 0; font-size: 14px;">All <?= $capacity; ?> slots reserved. Try another day.</p></div></div>
                <?php elseif ($available_slots <= 20 && $available_slots > 0): ?>
                <div class="alert alert-warning"><span style="font-size: 24px;">⚠️</span><div><strong style="font-size: 16px;">LIMITED SLOTS!</strong><p style="margin: 5px 0 0 0; font-size: 14px;">Only <?= $available_slots; ?> slot(s) remaining today.</p></div></div>
                <?php else: ?>
                <div class="alert alert-success"><span style="font-size: 24px;">✅</span><div><strong style="font-size: 16px;">Gym Available!</strong><p style="margin: 5px 0 0 0; font-size: 14px;"><?= $available_slots; ?> slots available today.</p></div></div>
                <?php endif; ?>

                <div class="info-grid">
                    <div class="info-item"><div class="info-icon">🏋️</div><div class="info-content"><strong>Capacity</strong><span><?= $gym_info['capacity']; ?> people</span></div></div>
                    <div class="info-item"><div class="info-icon">🕐</div><div class="info-content"><strong>Operating Hours</strong><span><?= $gym_info['operating_hours']; ?></span></div></div>
                    <div class="info-item"><div class="info-icon">📊</div><div class="info-content"><strong>Today's Reserved</strong><span><?= $today_reserved; ?> / <?= $capacity; ?></span></div></div>
                    <div class="info-item"><div class="info-icon"><?= strtolower($gym_info['status']) === 'available' ? '🟢' : '🟠'; ?></div><div class="info-content"><strong>Status</strong><span style="color: <?= strtolower($gym_info['status']) === 'available' ? 'green' : 'orange'; ?>;"><?= $gym_info['status']; ?></span></div></div>
                </div>

                <div style="border-top: 2px solid #e0e0e0; margin: 25px 0; padding-top: 25px;">
                    <h3 style="color: #8B0000; margin-bottom: 15px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                        📅 <span>Upcoming Reservations</span>
                    </h3>
                    <?php if ($upcoming->num_rows > 0): ?>
                    <table><thead><tr><th>Date</th><th>Start Time</th><th>End Time</th></tr></thead><tbody>
                    <?php while($res = $upcoming->fetch_assoc()): ?>
                    <tr><td><?= date('F d, Y', strtotime($res['date'])); ?></td><td><?= date('h:i A', strtotime($res['start_time'])); ?></td><td><?= date('h:i A', strtotime($res['end_time'])); ?></td></tr>
                    <?php endwhile; ?>
                    </tbody></table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #999; background: #f9f9f9; border-radius: 8px;">
                        <div style="font-size: 32px; margin-bottom: 10px;">📭</div>
                        <p style="margin: 0;">No upcoming reservations. The gym is available for booking!</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-top: 15px;"><strong style="color: #8B0000; font-size: 14px;">📋 Rules & Guidelines:</strong><p style="margin: 10px 0 0 0; color: #555; font-size: 13px; line-height: 1.6;"><?= nl2br(htmlspecialchars($gym_info['rules'])); ?></p></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>📋 My Reservations</h2><p>View and manage your gym reservations</p></div>
            <div class="card-body">
                <div class="alert alert-info"><span style="font-size: 20px;">💡</span><div><strong style="font-size: 14px;">Tip:</strong><p style="margin: 5px 0 0 0; font-size: 13px;">Delete old/cancelled reservations to keep your list clean.</p></div></div>
                <?php if ($res_result->num_rows == 0): ?>
                <div class="empty-state"><div class="empty-state-icon">📭</div><p>No reservations yet.</p><a href="reserve.php" class="btn btn-primary" style="margin-top: 15px;">📅 Make Reservation</a></div>
                <?php else: ?>
                <table><thead><tr><th>Date</th><th>Start</th><th>End</th><th>People</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                <?php while ($row = $res_result->fetch_assoc()): ?>
                <tr><td><?= date('M d, Y', strtotime($row['date'])); ?></td><td><?= date('h:i A', strtotime($row['start_time'])); ?></td><td><?= date('h:i A', strtotime($row['end_time'])); ?></td><td><?= $row['num_people']; ?></td><td><span class="status-badge status-<?= strtolower($row['status']); ?>"><?= $row['status']; ?></span></td><td><div class="action-buttons">
                <?php if (in_array($row['status'], ['Pending', 'Approved'])): ?>
                <a href="cancel_reservation.php?id=<?= $row['id']; ?>" class="btn-action btn-cancel" onclick="return confirm('Cancel this reservation?');">Cancel</a>
                <a href="reschedule_reservation.php?id=<?= $row['id']; ?>" class="btn-action btn-reschedule">Reschedule</a>
                <?php endif; ?>
                <a href="?delete_reservation_id=<?= $row['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Delete permanently?');">🗑️</a>
                </div></td></tr>
                <?php endwhile; ?>
                </tbody></table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>👤 My Profile</h2><p>View and update your account</p></div>
            <div class="card-body">
                <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                    <div class="info-grid">
                        <div class="info-item"><div class="info-icon">👤</div><div class="info-content"><strong>Name</strong><span><?= htmlspecialchars($user['name']); ?></span></div></div>
                        <div class="info-item"><div class="info-icon">📧</div><div class="info-content"><strong>Email</strong><span><?= htmlspecialchars($user['email']); ?></span></div></div>
                        <div class="info-item"><div class="info-icon">🎓</div><div class="info-content"><strong>Department</strong><span><?= htmlspecialchars($user['department']); ?></span></div></div>
                        <div class="info-item"><div class="info-icon">🔐</div><div class="info-content"><strong>Role</strong><span><?= ucfirst($_SESSION['role']); ?></span></div></div>
                    </div>
                </div>
                <h3 style="color: #8B0000; margin-bottom: 15px; font-size: 18px;">🔒 Change Password</h3>
                <?= $pass_message; ?>
                <form method="POST">
                    <div class="form-group"><label>Old Password</label><input type="password" name="old_password" required class="form-control"></div>
                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" required class="form-control"></div>
                    <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required class="form-control"></div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">💾 Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <footer><p style="margin: 0; font-size: 14px; opacity: 0.9;">© <?= date('Y'); ?> Western Mindanao State University</p><p style="margin: 8px 0 0 0; font-size: 13px; opacity: 0.7;">WMSU Gym Reservation System</p></footer>

    <script>
    function confirmDelete(date, time) {
        return confirm('Delete this reservation permanently?\n\nDate: ' + date + '\nTime: ' + time + '\n\nThis cannot be undone!');
    }
    </script>
</body>
</html>
