<?php
include 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $num_people = (int)$_POST['num_people'];

    // 🔹 Fetch gym capacity from database
    $gym = $conn->query("SELECT capacity FROM gym_info WHERE id = 1")->fetch_assoc();
    $capacity = (int)$gym['capacity'];

    // 🔹 Validation: Prevent over-capacity booking
    if ($num_people > $capacity) {
        echo "<script>alert('❌ Reservation failed: You cannot reserve for more than {$capacity} people.'); window.history.back();</script>";
        exit;
    }

    // ✅ Validation: Time must be between 8:00 AM and 5:00 PM
    if ($start_time < '08:00' || $end_time > '17:00') {
        echo "<script>alert('❌ Reservation time must be between 8:00 AM and 5:00 PM.'); window.history.back();</script>";
        exit;
    }

    // ✅ Validation: End time must be after start time
    if (strtotime($end_time) <= strtotime($start_time)) {
        echo "<script>alert('❌ End time must be after start time.'); window.history.back();</script>";
        exit;
    }

    // ✅ Validation: Date cannot be in the past
    if ($date < date('Y-m-d')) {
        echo "<script>alert('❌ You cannot reserve a date in the past.'); window.history.back();</script>";
        exit;
    }

    // ✅ Validation: Check for time conflicts with existing approved reservations
    $conflict_check = $conn->prepare("
        SELECT id, start_time, end_time 
        FROM reservations 
        WHERE date = ? 
        AND status IN ('Approved', 'Pending')
        AND (
            (start_time < ? AND end_time > ?) OR
            (start_time < ? AND end_time > ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");
    $conflict_check->bind_param("sssssss", $date, $end_time, $start_time, $start_time, $start_time, $start_time, $end_time);
    $conflict_check->execute();
    $conflict_result = $conflict_check->get_result();

    if ($conflict_result->num_rows > 0) {
        // Get the conflicting reservation details
        $conflict = $conflict_result->fetch_assoc();
        $conflict_start = date('h:i A', strtotime($conflict['start_time']));
        $conflict_end = date('h:i A', strtotime($conflict['end_time']));
        
        echo "<script>
            alert('❌ Reservation Conflict!\\n\\nThe gym is already reserved during this time.\\n\\nConflicting reservation: {$conflict_start} - {$conflict_end}\\n\\nPlease choose a different time slot.');
            window.history.back();
        </script>";
        exit;
    }

    // 🔹 Proceed if valid
    $stmt = $conn->prepare("INSERT INTO reservations (user_id, date, start_time, end_time, num_people, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([$user_id, $date, $start_time, $end_time, $num_people]);

    echo "<script>alert('✅ Reservation submitted successfully!'); window.location='dashboard.php';</script>";
    exit;
}

// Fetch all approved/pending reservations for display
$reservations_query = $conn->query("
    SELECT date, start_time, end_time 
    FROM reservations 
    WHERE status IN ('Approved', 'Pending') 
    AND date >= CURDATE()
    ORDER BY date ASC, start_time ASC
");

// Fetch gym info for capacity display
$gym_info = $conn->query("SELECT capacity FROM gym_info WHERE id=1")->fetch_assoc();
$capacity = $gym_info['capacity'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Reservation | WMSU Gym</title>
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

        <!-- Existing Reservations Alert -->
        <?php if ($reservations_query->num_rows > 0): ?>
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="margin: 0 0 12px 0; color: #856404; font-size: 18px;">📅 Existing Reservations</h3>
            <p style="margin: 0 0 12px 0; color: #555; font-size: 14px;">Please avoid these time slots when making your reservation:</p>
            <div style="max-height: 200px; overflow-y: auto; background: white; padding: 12px; border-radius: 6px;">
                <ul style="margin: 0; padding-left: 20px; color: #555; font-size: 14px; line-height: 1.8;">
                    <?php 
                    $reservations_query->data_seek(0);
                    while($res = $reservations_query->fetch_assoc()): 
                    ?>
                        <li>
                            <strong><?= date('F d, Y', strtotime($res['date'])); ?></strong> - 
                            <?= date('h:i A', strtotime($res['start_time'])); ?> to 
                            <?= date('h:i A', strtotime($res['end_time'])); ?>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reservation Card -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden;">
            
            <!-- Card Header -->
            <div style="background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 25px 30px;">
                <h2 style="margin: 0; font-size: 26px; font-weight: 600;">📅 Make a Reservation</h2>
                <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 14px;">Reserve your gym slot for your scheduled activities</p>
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
                                Your reservation will be set to <strong>Pending</strong> status and requires administrator approval before confirmation. 
                                Maximum capacity: <strong><?= $capacity; ?> people</strong>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Reservation Form -->
                <form method="POST" onsubmit="return validateReservation();" style="display: flex; flex-direction: column; gap: 24px;">
                    
                    <!-- Date Field -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="date" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">📅</span> Reservation Date <span style="color: #e74c3c;">*</span>
                        </label>
                        <input 
                            type="date" 
                            id="date" 
                            name="date" 
                            required 
                            min="<?= date('Y-m-d'); ?>"
                            style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;"
                            onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';"
                            onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        <small style="color: #666; font-size: 13px; margin-top: 4px;">Select a date from today onwards</small>
                    </div>

                    <!-- Start Time Field -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="start_time" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">🕐</span> Start Time <span style="color: #e74c3c;">*</span>
                        </label>
                        <input 
                            type="time" 
                            id="start_time" 
                            name="start_time" 
                            required 
                            min="08:00" 
                            max="17:00"
                            style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;"
                            onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';"
                            onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        <small style="color: #666; font-size: 13px; margin-top: 4px;">Operating hours: 8:00 AM - 5:00 PM</small>
                    </div>

                    <!-- End Time Field -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="end_time" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">🕔</span> End Time <span style="color: #e74c3c;">*</span>
                        </label>
                        <input 
                            type="time" 
                            id="end_time" 
                            name="end_time" 
                            required 
                            min="08:00" 
                            max="17:00"
                            style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;"
                            onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';"
                            onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        <small style="color: #666; font-size: 13px; margin-top: 4px;">Must be after start time</small>
                    </div>

                    <!-- Number of People Field -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="num_people" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">👥</span> Number of People <span style="color: #e74c3c;">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="num_people" 
                            name="num_people" 
                            required 
                            min="1" 
                            max="<?= $capacity; ?>"
                            value="1"
                            style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;"
                            onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';"
                            onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        <small style="color: #666; font-size: 13px; margin-top: 4px;">Maximum capacity: <?= $capacity; ?> people</small>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 12px; margin-top: 10px; flex-wrap: wrap;">
                        <button 
                            type="submit" 
                            style="flex: 1; min-width: 200px; background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 16px 24px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(139,0,0,0.3);"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(139,0,0,0.4)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(139,0,0,0.3)';">
                            ✅ Submit Reservation
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
            <h3 style="margin: 0 0 15px 0; color: #8B0000; font-size: 18px; font-weight: 600;">📋 Reservation Guidelines</h3>
            <ul style="margin: 0; padding-left: 20px; color: #555; line-height: 1.8; font-size: 14px;">
                <li>The gymnasium operates from <strong>8:00 AM to 5:00 PM</strong></li>
                <li>Maximum capacity is <strong><?= $capacity; ?> people</strong> per reservation</li>
                <li>Reservations cannot overlap with existing approved bookings</li>
                <li>All reservations require <strong>administrator approval</strong></li>
                <li>You can view and manage your reservations from the dashboard</li>
                <li>Please arrive on time and follow all gym rules and regulations</li>
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
    function validateReservation() {
        const num = document.getElementById('num_people').value;
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        const date = document.getElementById('date').value;
        const today = new Date().toISOString().split('T')[0];
        const openTime = "08:00";
        const closeTime = "17:00";
        const capacity = <?= $capacity; ?>;

        // Check capacity
        if (num > capacity) {
            alert("❌ You cannot reserve the gym for more than " + capacity + " people.");
            return false;
        }

        // Check date
        if (date < today) {
            alert("❌ You cannot reserve a date in the past.");
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

        return confirm("Confirm your reservation?\n\nDate: " + date + "\nTime: " + startTime + " - " + endTime + "\nPeople: " + num + "\n\nThis will require admin approval.");
    }
    </script>

</body>
</html>
