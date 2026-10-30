<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$query = "SELECT * FROM Users WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
$isAdmin = ($user['is_admin'] == 1);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account | Vinyl Archive</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'menu.php'; ?>
<?php include 'cart_sidebar.php'; ?>

<div class="account-container">

    <h2>
        Welcome back,
        <?= $user['f_name'] ?>!
        <?php if ($isAdmin): ?>
            <span class="admin-badge">(Admin)</span>
        <?php endif; ?>
    </h2>

    <div class="account-balance">
        Balance: $<?= number_format($user['balance'], 2) ?>
    </div>

    <div class="account-info">
        <p><strong>Name:</strong> <?= $user['f_name'] . ' ' . $user['l_name'] ?></p>
        <p><strong>Email:</strong> <?=$user['email'] ?></p>
        <p><strong>Date of Birth:</strong> <?= $user['dob'] ?></p>
    </div>

    <?php if ($isAdmin): ?>
        <hr class="account-divider">
        <div class="admin-section">
            <h3>Administrative Actions</h3>
            <p>As an administrator, you can manage the archive and view system reports.</p>
            <div class="admin-links">
                <a href="admin_dashboard.php" class="admin-btn">Go to Admin Panel (CRUD)</a>
            </div>
        </div>
    <?php endif; ?>

    <hr class="account-divider">

    <a href="logout.php" class="logout-link">Logout</a>

</div>

</body>
</html>