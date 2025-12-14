<?php
include 'db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 1) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | WMSU Gym Reservation System</title>
</head>
<body style="margin:0; font-family:Arial, sans-serif; background-color:#f7f7f7; display:flex; justify-content:center; align-items:center; height:100vh;">

  <div style="background-color:white; width:100%; max-width:400px; padding:30px; border-radius:10px; box-shadow:0 0 15px rgba(0,0,0,0.1); text-align:center;">
    
    <div style="margin-bottom:25px;">
          <img src="assets/images/wmsu_logo.png" alt="WMSU Logo"
         style="width:70px; height:auto; border-radius:5px;">
      <h1 style="margin:0; color:#8B0000;">🔐 WMSU Gym Reservation System</h1>
      <p style="margin:5px 0; color:#555; font-style:italic;">Western Mindanao State University</p>
    </div>

    <h2 style="color:#8B0000; margin-bottom:20px;">Login</h2>

    <?php if (!empty($error)): ?>
      <p style="color:red; font-weight:bold; margin-bottom:15px;"><?= htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" style="display:flex; flex-direction:column; text-align:left; gap:10px;">
      <label for="email" style="font-weight:bold;">Email</label>
      <input type="email" id="email" name="email" required 
             style="padding:10px; border:1px solid #ccc; border-radius:5px; font-size:15px;">

      <label for="password" style="font-weight:bold;">Password</label>
      <input type="password" id="password" name="password" required 
             style="padding:10px; border:1px solid #ccc; border-radius:5px; font-size:15px;">

      <button type="submit" 
              style="margin-top:15px; background-color:#8B0000; color:white; border:none; padding:10px; border-radius:5px; font-size:16px; font-weight:bold; cursor:pointer;">
        Login
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
