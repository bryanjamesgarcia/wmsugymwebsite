<?php
include 'db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $role = 'user';

    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $error = "❌ Email already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashed, $role);
        if ($stmt->execute()) {
            $success = "✅ Account created successfully!";
        } else {
            $error = "⚠️ Error creating account: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account | WMSU Gym Reservation</title>
</head>
<body style="margin:0; font-family:Arial, sans-serif; background-color:#f7f7f7; display:flex; justify-content:center; align-items:center; height:100vh;">

    <div style="background-color:white; width:100%; max-width:450px; padding:30px; border-radius:10px; box-shadow:0 0 15px rgba(0,0,0,0.1); text-align:center;">
        <h1 style="margin:0; color:#8B0000;">🧾 Create Account</h1>
        <p style="color:#555; margin:5px 0 20px 0;">WMSU Gym Reservation System</p>

        <?php if(isset($error)): ?>
            <p style="color:red; font-weight:bold; background-color:#ffe5e5; border:1px solid red; padding:10px; border-radius:5px;">
                <?= htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <?php if(isset($success)): ?>
            <p style="color:green; font-weight:bold; background-color:#e5ffe5; border:1px solid green; padding:10px; border-radius:5px;">
                <?= htmlspecialchars($success); ?>
            </p>
        <?php endif; ?>

        <form method="POST" style="display:flex; flex-direction:column; text-align:left; gap:12px;">
            <label style="font-weight:bold;">Name:</label>
            <input type="text" name="name" required
                style="padding:10px; border:1px solid #ccc; border-radius:5px; font-size:15px;">

            <label style="font-weight:bold;">Email:</label>
            <input type="email" name="email" required
                style="padding:10px; border:1px solid #ccc; border-radius:5px; font-size:15px;">

            <label style="font-weight:bold;">Password:</label>
            <input type="password" name="password" required
                style="padding:10px; border:1px solid #ccc; border-radius:5px; font-size:15px;">

            <button type="submit"
                style="margin-top:15px; background-color:#8B0000; color:white; border:none; padding:10px; border-radius:5px; font-size:16px; font-weight:bold; cursor:pointer;">
                Create Account
            </button>
        </form>

        <button onclick="window.location.href='index.php'"
            style="margin-top:15px; background-color:#ccc; color:#333; border:none; padding:10px; border-radius:5px; font-size:15px; cursor:pointer;">
            ← Back to Home
        </button>

        <footer style="margin-top:25px; font-size:12px; color:#777;">
            © <?= date('Y'); ?> WMSU Gym Reservation System
        </footer>
    </div>

</body>
</html>
