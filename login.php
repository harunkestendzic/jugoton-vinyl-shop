<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT * FROM Users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['f_name']  = $user['f_name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['balance'] = $user['balance'];
            
            header("Location: account.php");
            exit;
        } else {
            $error = "Invalid email or password!";
        }
    } else {
        $error = "Database error!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Vinyl Archive</title>
    <link rel="stylesheet" href="style.css">
    
</head>
<body>

<?php include 'menu.php'; ?>   

<div class="account-container">
    <h2>Sign In</h2>
    
    <?php if (isset($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <input type="email" name="email" placeholder="Email Address" required>
        </div>
        <div class="form-group">
            <input type="password" name="password" placeholder="Password" required>
        </div>
        <button type="submit">LOGIN</button>
    </form>
    
    <p style="margin-top: 25px;">
        Don't have an account? <a href="register.php">Register here</a>
    </p>
</div>

</body>
</html>