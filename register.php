<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f_name   = trim($_POST['f_name']);
    $l_name   = trim($_POST['l_name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $dob      = $_POST['dob'];

    if (!$f_name || !$l_name || !$email || !$password || !$dob) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        try {
            $checkQuery = "SELECT user_id FROM Users WHERE email = '$email'";
            $checkResult = mysqli_query($conn, $checkQuery);
            
            if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                $error = "Email already registered!";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                $insertQuery = "
                    INSERT INTO Users (f_name, l_name, dob, email, password, balance) 
                    VALUES ('$f_name', '$l_name', '$dob', '$email', '$hashed', 0.00)
                ";
                
                if (mysqli_query($conn, $insertQuery)) {
                    $success = "Registration successful! <a href='login.php'>Login now</a>";
                } else {
                    $error = "Error: " . mysqli_error($conn);
                }
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Vinyl Archive</title>
    <link rel="stylesheet" href="style.css">
    
    <style>
        .admin-form input[type="text"],
        .admin-form input[type="email"],
        .admin-form input[type="date"],
        .admin-form input[type="password"] {
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 12px !important;
            background: #222 !important;
            border: 1px solid #444 !important;
            color: #fff !important;
            border-radius: 6px !important;
            font-size: 1rem !important;
            font-family: inherit !important;
        }
        .form-group {
            width: 100%;
        }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="account-container">
    <h2>Create Account</h2>
    
    <?php if (isset($error)): ?>
        <p class="error" style="color: #ff4444; margin-bottom: 15px;"><?= $error ?></p>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <p class="success" style="color: #00c851; margin-bottom: 15px;"><?= $success ?></p>
    <?php endif; ?>

    <form method="POST" class="admin-form">
        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">First Name</label>
            <input type="text" name="f_name" placeholder="First Name" required>
        </div>
        
        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Last Name</label>
            <input type="text" name="l_name" placeholder="Last Name" required>
        </div>
        
        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Email Address</label>
            <input type="email" name="email" placeholder="Email Address" required>
        </div>
        
        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Date of Birth</label>
            <input type="date" name="dob" required>
        </div>
        
        <div class="form-group" style="text-align: left; margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px;">Password</label>
            <input type="password" name="password" placeholder="Password" required minlength="6">
        </div>
        
        <button type="submit" class="page-btn" style="width: 100%;">REGISTER</button>
    </form>
    
    <p style="text-align:center; margin-top:25px;">
        Already have an account? <a href="login.php">Login here</a>
    </p>
</div>

</body>
</html>