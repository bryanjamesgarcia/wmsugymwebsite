<?php
include 'db_connect.php';
session_start();

// Handle account request submission
$request_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_account'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $college = trim($_POST['college']);
    $reason = trim($_POST['reason']);

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($college)) {
        $request_message = "<div style='background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px 20px; border-radius: 6px; margin-bottom: 20px;'>
            <div style='display: flex; align-items: start; gap: 12px;'>
                <span style='font-size: 20px; color: #dc3545;'>❌</span>
                <div>
                    <strong style='color: #721c24; font-size: 15px;'>Error</strong>
                    <p style='margin: 5px 0 0 0; color: #721c24; font-size: 14px;'>All required fields must be filled out.</p>
                </div>
            </div>
        </div>";
    } else {
        // Check if email already exists in users table
        $check_user = $conn->prepare("SELECT id FROM users WHERE email=?");
        $check_user->bind_param("s", $email);
        $check_user->execute();
        
        if ($check_user->get_result()->num_rows > 0) {
            $request_message = "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; border-radius: 6px; margin-bottom: 20px;'>
                <div style='display: flex; align-items: start; gap: 12px;'>
                    <span style='font-size: 20px; color: #ffc107;'>⚠️</span>
                    <div>
                        <strong style='color: #856404; font-size: 15px;'>Already Registered</strong>
                        <p style='margin: 5px 0 0 0; color: #856404; font-size: 14px;'>This email is already registered. Please <a href='login.php' style='color: #8B0000; font-weight: bold;'>login</a> instead.</p>
                    </div>
                </div>
            </div>";
        } else {
            // Check if there's already a pending request with this email
            $check_request = $conn->prepare("SELECT id, status FROM account_requests WHERE email=?");
            $check_request->bind_param("s", $email);
            $check_request->execute();
            $existing = $check_request->get_result()->fetch_assoc();
            
            if ($existing) {
                if ($existing['status'] === 'Pending') {
                    $request_message = "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; border-radius: 6px; margin-bottom: 20px;'>
                        <div style='display: flex; align-items: start; gap: 12px;'>
                            <span style='font-size: 20px; color: #ffc107;'>⏳</span>
                            <div>
                                <strong style='color: #856404; font-size: 15px;'>Request Pending</strong>
                                <p style='margin: 5px 0 0 0; color: #856404; font-size: 14px;'>You already have a pending account request. Please wait for administrator approval.</p>
                            </div>
                        </div>
                    </div>";
                } else {
                    $request_message = "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; border-radius: 6px; margin-bottom: 20px;'>
                        <div style='display: flex; align-items: start; gap: 12px;'>
                            <span style='font-size: 20px; color: #ffc107;'>ℹ️</span>
                            <div>
                                <strong style='color: #856404; font-size: 15px;'>Previous Request Found</strong>
                                <p style='margin: 5px 0 0 0; color: #856404; font-size: 14px;'>You have a previous account request that was {$existing['status']}. Please contact the administrator.</p>
                            </div>
                        </div>
                    </div>";
                }
            } else {
                // Insert the account request
                $stmt = $conn->prepare("INSERT INTO account_requests (name, email, phone, department, reason, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                $stmt->bind_param("sssss", $name, $email, $phone, $college, $reason);
                
                if ($stmt->execute()) {
                    $request_message = "<div style='background: #d4edda; border-left: 4px solid #28a745; padding: 15px 20px; border-radius: 6px; margin-bottom: 20px;'>
                        <div style='display: flex; align-items: start; gap: 12px;'>
                            <span style='font-size: 20px; color: #28a745;'>✅</span>
                            <div>
                                <strong style='color: #155724; font-size: 15px;'>Request Submitted Successfully!</strong>
                                <p style='margin: 5px 0 0 0; color: #155724; font-size: 14px;'>Your account request has been submitted. You will receive an email once your request is approved by the administrator.</p>
                            </div>
                        </div>
                    </div>";
                } else {
                    $request_message = "<div style='background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px 20px; border-radius: 6px; margin-bottom: 20px;'>
                        <div style='display: flex; align-items: start; gap: 12px;'>
                            <span style='font-size: 20px; color: #dc3545;'>❌</span>
                            <div>
                                <strong style='color: #721c24; font-size: 15px;'>Submission Failed</strong>
                                <p style='margin: 5px 0 0 0; color: #721c24; font-size: 14px;'>Failed to submit request. Please try again later.</p>
                            </div>
                        </div>
                    </div>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU Gym Reservation System</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa;">

    <!-- Header/Navigation -->
    <header style="background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.15); position: sticky; top: 0; z-index: 1000;">
        <nav style="max-width: 1200px; margin: 0 auto; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <img src="assets/images/wmsu_logo.png" alt="WMSU Logo" style="width: 80px; height: 55px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                <div>
                    <h1 style="margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.5px;">WMSU Gym Reservation</h1>
                    <p style="margin: 3px 0 0 0; font-size: 12px; opacity: 0.85; font-weight: 400;">Western Mindanao State University</p>
                </div>
            </div>
            <a href="login.php" style="background: white; color: #8B0000; padding: 12px 28px; border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.3s; box-shadow: 0 3px 8px rgba(0,0,0,0.15);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 12px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 3px 8px rgba(0,0,0,0.15)';">
                🔐 Login
            </a>
        </nav>
    </header>

    <!-- Hero Section -->
    <section style="background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 80px 20px; text-align: center; position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 30px 30px; opacity: 0.3;"></div>
        <div style="max-width: 800px; margin: 0 auto; position: relative; z-index: 1;">
            <h2 style="margin: 0 0 20px 0; font-size: 42px; font-weight: 700; line-height: 1.2;">Welcome to WMSU Gym</h2>
            <p style="margin: 0 0 35px 0; font-size: 20px; opacity: 0.95; line-height: 1.6;">Reserve your gym slot online. Fast, easy, and efficient facility management.</p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="#request-account" style="background: white; color: #8B0000; padding: 16px 36px; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); transition: all 0.3s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 16px rgba(0,0,0,0.3)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)';">
                    📝 Request Account
                </a>
                <a href="login.php" style="background: rgba(255,255,255,0.15); color: white; padding: 16px 36px; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 16px; border: 2px solid white; transition: all 0.3s; backdrop-filter: blur(10px);" onmouseover="this.style.background='rgba(255,255,255,0.25)';" onmouseout="this.style.background='rgba(255,255,255,0.15)';">
                    🚪 Login
                </a>
            </div>
        </div>
    </section>

    <!-- Main Content Container -->
    <main style="max-width: 1200px; margin: -40px auto 60px; padding: 0 20px; position: relative; z-index: 10;">
        
        <!-- Quick Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px;">
            <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; border-top: 4px solid #8B0000;">
                <div style="font-size: 48px; margin-bottom: 10px;">🏋️</div>
                <h3 style="margin: 0 0 8px 0; color: #333; font-size: 20px; font-weight: 600;">Modern Facilities</h3>
                <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">State-of-the-art gymnasium equipment and spacious facilities</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; border-top: 4px solid #8B0000;">
                <div style="font-size: 48px; margin-bottom: 10px;">⏰</div>
                <h3 style="margin: 0 0 8px 0; color: #333; font-size: 20px; font-weight: 600;">Flexible Hours</h3>
                <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">Open daily from 8:00 AM to 5:00 PM for your convenience</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; border-top: 4px solid #8B0000;">
                <div style="font-size: 48px; margin-bottom: 10px;">📱</div>
                <h3 style="margin: 0 0 8px 0; color: #333; font-size: 20px; font-weight: 600;">Easy Booking</h3>
                <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.6;">Reserve online anytime, anywhere with our simple system</p>
            </div>
        </div>

        <!-- Account Request Form -->
        <section id="request-account" style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 40px;">
            <div style="background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 30px;">
                <h2 style="margin: 0 0 10px 0; font-size: 28px; font-weight: 600;">📝 Request an Account</h2>
                <p style="margin: 0; opacity: 0.9; font-size: 15px;">Fill out the form below to request access to the gym reservation system</p>
            </div>
            
            <div style="padding: 40px 30px;">
                <?= $request_message; ?>
                
                <form method="POST" style="display: grid; gap: 24px; max-width: 700px; margin: 0 auto;">
                    <input type="hidden" name="request_account" value="1">

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="name" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">👤</span> Full Name <span style="color: #e74c3c;">*</span>
                        </label>
                        <input type="text" id="name" name="name" required placeholder="Enter your full name" style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;" onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="email" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">📧</span> Email Address <span style="color: #e74c3c;">*</span>
                        </label>
                        <input type="email" id="email" name="email" required placeholder="your.email@example.com" style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;" onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="phone" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">📱</span> Phone Number <span style="color: #e74c3c;">*</span>
                        </label>
                        <input type="tel" id="phone" name="phone" required placeholder="09XX XXX XXXX" style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit;" onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="college" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">🎓</span> College / Department <span style="color: #e74c3c;">*</span>
                        </label>
                        <select id="college" name="college" required style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit; background: white; cursor: pointer;" onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                            <option value="">-- Select Your College --</option>
                            <option>College of Agriculture</option>
                            <option>College of Architecture</option>
                            <option>College of Asian and Islamic Studies (CAIS)</option>
                            <option>College of Communications and Humanities (CCH)</option>
                            <option>College of Computing Studies (CCS)</option>
                            <option>College of Criminal Justice Education (CCJE)</option>
                            <option>College of Education (COE)</option>
                            <option>College of Engineering (COEng)</option>
                            <option>College of Forestry and Environmental Studies (CFES)</option>
                            <option>College of Home Economics (CHE)</option>
                            <option>College of Law</option>
                            <option>College of Liberal Arts (CLA)</option>
                            <option>College of Medicine</option>
                            <option>College of Nursing (CN)</option>
                            <option>College of Physical Education, Recreation and Sports (CPERS)</option>
                            <option>College of Public Administration and Development Studies (CPADS)</option>
                            <option>College of Science and Mathematics (CSM)</option>
                            <option>College of Social Sciences (CSS)</option>
                            <option>College of Social Work and Community Development (CSWCD)</option>
                            <option>College of Teacher Education (CTE)</option>
                        </select>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="reason" style="font-weight: 600; color: #333; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #8B0000;">📝</span> Reason for Account Request
                        </label>
                        <textarea id="reason" name="reason" rows="4" placeholder="Briefly explain your purpose for requesting access to the gym reservation system..." style="padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: all 0.3s; outline: none; font-family: inherit; resize: vertical;" onfocus="this.style.borderColor='#8B0000'; this.style.boxShadow='0 0 0 3px rgba(139,0,0,0.1)';" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';"></textarea>
                    </div>

                    <button type="submit" style="background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 16px 32px; border: none; border-radius: 8px; font-size: 17px; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(139,0,0,0.3); margin-top: 10px;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(139,0,0,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(139,0,0,0.3)';">
                        ✅ Submit Request
                    </button>
                </form>
            </div>
        </section>

        <!-- Already Have Account Section -->
        <section style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; padding: 40px 30px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 24px; font-weight: 600;">Already have an account?</h3>
            <p style="margin: 0 0 25px 0; color: #666; font-size: 16px;">Login to make reservations, view your booking history, and manage your account.</p>
            <a href="login.php" style="display: inline-block; background: linear-gradient(135deg, #8B0000 0%, #6d0000 100%); color: white; padding: 14px 36px; border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 16px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(139,0,0,0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(139,0,0,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(139,0,0,0.3)';">
                🚀 Login Now
            </a>
        </section>
    </main>

    <!-- Footer -->
    <footer style="background: #2c2c2c; color: white; padding: 40px 20px 25px;">
        <div style="max-width: 1200px; margin: 0 auto; text-align: center;">
            <img src="assets/images/wmsu_logo.png" alt="WMSU Logo" alt="WMSU Logo" style="width: 80px; height: 55px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
            <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">Western Mindanao State University</h3>
            <p style="margin: 0 0 20px 0; font-size: 14px; opacity: 0.8;">Gymnasium Reservation System</p>
            <p style="margin: 0; font-size: 13px; opacity: 0.7;">© <?= date('Y'); ?> WMSU. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
