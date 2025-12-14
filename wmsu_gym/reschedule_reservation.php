<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch the reservation to reschedule
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM reservations WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: dashboard.php");
        exit();
    }
    
    $reservation = $result->fetch_assoc();
} else {
    header("Location: dashboard.php");
    exit();
}

// Handle reschedule form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Show that we received POST
    echo "<script>console.log('POST request received!');</script>";
    
    $id = (int)$_POST['id'];
    $new_date = $_POST['new_date'];
    $new_start = $_POST['new_start'];
    $new_end = $_POST['new_end'];
    $user_id = $_SESSION['user_id'];

    // Show values in alert for debugging
    echo "<script>
        alert('DEBUG - Form submitted!\\n\\nID: $id\\nUser ID: $user_id\\nDate: $new_date\\nStart: $new_start\\nEnd: $new_end');
    </script>";

    // DEBUG: Log the values
    error_log("Reschedule attempt - ID: $id, User: $user_id, Date: $new_date, Start: $new_start, End: $new_end");

    // Validation
    if ($new_date < date('Y-m-d')) {
        echo "<script>alert('❌ You cannot reschedule to a date in the past.'); window.history.back();</script>";
        exit();
    }

    if ($new_start < '08:00' || $new_end > '17:00') {
        echo "<script>alert('❌ Reservation time must be between 8:00 AM and 5:00 PM.'); window.history.back();</script>";
        exit();
    }

    if (strtotime($new_end) <= strtotime($new_start)) {
        echo "<script>alert('❌ End time must be after start time.'); window.history.back();</script>";
        exit();
    }

    // Check for conflicts (excluding current reservation)
    $conflict_check = $conn->prepare("
        SELECT id, start_time, end_time 
        FROM reservations 
        WHERE date = ? 
        AND id != ?
        AND status IN ('Approved', 'Pending')
        AND NOT (end_time <= ? OR start_time >= ?)
    ");
    $conflict_check->bind_param("siss", $new_date, $id, $new_start, $new_end);
    $conflict_check->execute();
    $conflict_result = $conflict_check->get_result();

    if ($conflict_result->num_rows > 0) {
        $conflict = $conflict_result->fetch_assoc();
        $conflict_start = date('h:i A', strtotime($conflict['start_time']));
        $conflict_end = date('h:i A', strtotime($conflict['end_time']));
        
        echo "<script>
            alert('❌ Time slot conflict!\\n\\nThe gym is already reserved: {$conflict_start} - {$conflict_end}\\n\\nPlease choose a different time.');
            window.history.back();
        </script>";
        exit();
    }

    // Update the reservation
    $update_stmt = $conn->prepare("UPDATE reservations SET date=?, start_time=?, end_time=?, status='Pending' WHERE id=? AND user_id=?");
    $update_stmt->bind_param("sssii", $new_date, $new_start, $new_end, $id, $user_id);
    
    // DEBUG: Log before execution
    error_log("Executing UPDATE with: date=$new_date, start=$new_start, end=$new_end, id=$id, user_id=$user_id");
    
    if ($update_stmt->execute()) {
        $affected = $update_stmt->affected_rows;
        error_log("Update executed. Affected rows: $affected");
        
        // Check if any rows were actually updated
        if ($affected > 0) {
            $_SESSION['success_message'] = "Reservation rescheduled successfully!";
            header("Location: dashboard.php");
            exit();
        } else {
            echo "<script>
                alert('❌ DEBUG INFO:\\n\\nID: $id\\nUser ID: $user_id\\nAffected Rows: $affected\\n\\nThe reservation may not exist or you may not have permission.');
                window.history.back();
            </script>";
            exit();
        }
    } else {
        $error = $conn->error;
        error_log("Update failed: $error");
        echo "<script>alert('❌ Database error: " . addslashes($error) . "'); window.history.back();</script>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Reservation | WMSU Gym</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%); min-height: 100vh;">

    <!-- Header -->
    <header style="background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 20px 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <img src="assets/images/wmsu_logo.png" alt="WMSU Logo" style="width: 60px; height: 60px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                <div>
                    <h1 style="margin: 0; font-size: 24px; font-weight: 700;">WMSU Gym Reservation</h1>
                    <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">Western Mindanao State University</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
        
        <!-- Breadcrumb -->
        <nav style="margin-bottom: 20px;">
            <a href="dashboard.php" style="color: #8B0000; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 5px;">
                <span style="font-size: 18px;">←</span> Back to Dashboard
            </a>
        </nav>

        <!-- Current Reservation Info -->
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="margin: 0 0 12px 0; color: #856404; font-size: 18px;">📌 Current Reservation Details</h3>
            <div style="color: #555; font-size: 14px; line-height: 1.8;">
                <p style="margin: 5px 0;"><strong>Date:</strong> <?= date('F d, Y', strtotime($reservation['date'])); ?></p>
                <p style="margin: 5px 0;"><strong>Time:</strong> <?= date('h:i A', strtotime($reservation['start_time'])); ?> - <?= date('h:i A', strtotime($reservation['end_time'])); ?></p>
                <p style="margin: 5px 0;"><strong>Number of People:</strong> <?= $reservation['num_people']; ?></p>
                <p style="margin: 5px 0;"><strong>Status:</strong> <span style="color: <?= $reservation['status'] === 'Approved' ? 'green' : 'orange'; ?>; font-weight: bold;"><?= $reservation['status']; ?></span></p>
            </div>
        </div>

        <!-- Reschedule Card -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden;">
            
            <!-- Card Header -->
            <div style="background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 25px 30px;">
                <h2 style="margin: 0; font-size: 26px; font-weight: 600;">📅 Reschedule Reservation</h2>
                <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 14px;">Update your reservation details below</p>
            </div>

            <!-- Card Body -->
            <div style="padding: 35px 30px;">
                
                <!-- Information Alert -->
                <div style="background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px 20px; border-radius: 6px; margin-bottom: 30px;">
                    <div style="display: flex; align-items: start; gap: 12px;">
                        <span style="font-size: 20px; color: #2196F3;">ℹ️</span>
                        <div>
                            <strong style="color: #1976D2; font-size: 15px;">Important Information</strong>
                            <p style="margin: 5px 0 0 0; color: #555; font-size: 14px; line-height: 1.5;">
                                Your reservation will be set to <strong>Pending</strong> status after rescheduling and must be approved by an administrator again.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Reschedule Form -->
                <form method="POST" style="display: flex; flex-direction: column; gap: 24px;">
                    
                    <input type="hidden" name="id" value="<?= $reservation['id']; ?>">

                    <!-- Date Field -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="new_date" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">📅</span> New Date
                        </label>
                        <input 
                            type="date" 
                            id="new_date" 
                            name="new_date" 
                            required 
                            min="<?= date('Y-m-d'); ?>"
                            value="<?= $reservation['date']; ?>"
                            style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;"
                            onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';"
                            onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        <small style="color: #666; font-size: 13px; margin-top: 4px;">Select a date from today onwards</small>
                    </div>

                    <!-- Start Time Field -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="new_start" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">🕐</span> New Start Time
                        </label>
                        <input 
                            type="time" 
                            id="new_start" 
                            name="new_start" 
                            required 
                            min="08:00" 
                            max="17:00"
                            value="<?= $reservation['start_time']; ?>"
                            style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;"
                            onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';"
                            onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        <small style="color: #666; font-size: 13px; margin-top: 4px;">Operating hours: 8:00 AM - 5:00 PM</small>
                    </div>

                    <!-- End Time Field -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="new_end" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">🕔</span> New End Time
                        </label>
                        <input 
                            type="time" 
                            id="new_end" 
                            name="new_end" 
                            required 
                            min="08:00" 
                            max="17:00"
                            value="<?= $reservation['end_time']; ?>"
                            style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;"
                            onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';"
                            onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        <small style="color: #666; font-size: 13px; margin-top: 4px;">Must be after start time</small>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 12px; margin-top: 10px; flex-wrap: wrap;">
                        <button 
                            type="submit" 
                            style="flex: 1; min-width: 200px; background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 16px 24px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(139,0,0,0.3);"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(139,0,0,0.4)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(139,0,0,0.3)';">
                            ✅ Confirm Reschedule
                        </button>
                        <button 
                            type="button" 
                            onclick="window.location.href='dashboard.php';"
                            style="flex: 1; min-width: 200px; background: #f5f5f5; color: #555; padding: 16px 24px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s;"
                            onmouseover="this.style.background='#e8e8e8'; this.style.borderColor='#d0d0d0';"
                            onmouseout="this.style.background='#f5f5f5'; this.style.borderColor='#e0e0e0';">
                            ❌ Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Guidelines Card -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-top: 25px; padding: 25px 30px;">
            <h3 style="margin: 0 0 15px 0; color: #8B0000; font-size: 18px; font-weight: 600;">📋 Rescheduling Guidelines</h3>
            <ul style="margin: 0; padding-left: 20px; color: #555; line-height: 1.8; font-size: 14px;">
                <li>You can only reschedule reservations with <strong>Pending</strong> or <strong>Approved</strong> status</li>
                <li>The gym operates from <strong>8:00 AM to 5:00 PM</strong></li>
                <li>Ensure your new time slot does not conflict with existing reservations</li>
                <li>After rescheduling, your reservation will need admin approval again</li>
                <li>You can reschedule as many times as needed before your reservation date</li>
            </ul>
        </div>
    </main>

    <!-- Footer -->
    <footer style="background: #2c2c2c; color: white; text-align: center; padding: 25px 20px; margin-top: 60px;">
        <p style="margin: 0; font-size: 14px; opacity: 0.9;">© <?= date('Y'); ?> Western Mindanao State University</p>
        <p style="margin: 8px 0 0 0; font-size: 13px; opacity: 0.7;">WMSU Gym Reservation System</p>
    </footer>

    <!-- Client-side Validation Script -->
    <script>
    function validateReschedule() {
        const startTime = document.getElementById('new_start').value;
        const endTime = document.getElementById('new_end').value;
        const date = document.getElementById('new_date').value;
        const today = new Date().toISOString().split('T')[0];
        const openTime = "08:00";
        const closeTime = "17:00";

        // Check date
        if (date < today) {
            alert("❌ You cannot reschedule to a date in the past.");
            return false;
        }

        // Check time order
        if (endTime <= startTime) {
            alert("❌ End time must be after start time.");
            return false;
        }

        // Check if within allowed hours
        if (startTime < openTime || endTime > closeTime) {
            alert("❌ You can only reserve between 8:00 AM and 5:00 PM.");
            return false;
        }

        return confirm("Are you sure you want to reschedule this reservation?\n\nNew Date: " + date + "\nNew Time: " + startTime + " - " + endTime + "\n\nThis will require admin approval again.");
    }
    </script>

</body>
</html>