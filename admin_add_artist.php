<?php
require_once 'db.php';
session_start();

$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

$query = "SELECT is_admin FROM Users WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);
$user = $result ? mysqli_fetch_assoc($result) : null;

if (!$user || $user['is_admin'] != 1) {
    header("Location: index.php");
    exit("Unauthorized");
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_artist'])) {
    $artist_name = mysqli_real_escape_string($conn, trim($_POST['artist_name'] ?? ''));
    $bio         = mysqli_real_escape_string($conn, trim($_POST['bio'] ?? ''));

    if (!$artist_name) {
        $error = "Artist name is required.";
    } else {
        $checkQuery = "SELECT artist_id FROM Artists WHERE artist_name = '$artist_name'";
        $checkResult = mysqli_query($conn, $checkQuery);

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            $error = "An artist with that name already exists.";
        } else {
            $insertQuery = "INSERT INTO Artists (artist_name, bio) VALUES ('$artist_name', '$bio')";
            
            if (mysqli_query($conn, $insertQuery)) {
                $success = "Artist successfully added!";
            } else {
                $error = "Failed to add artist. Please try again.";
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
    <title>Add Artist | Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav>
    <div class="logo">ADMIN PANEL</div>
    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="index.php">View Site</a>
        <a href="logout.php" class="logout-link">Logout</a>
    </div>
</nav>

<main class="container dashboard-main" style="max-width: 600px; margin: 40px auto; padding: 0 20px;">
    
    <header class="dash-header" style="margin-bottom: 25px;">
        <h1 class="section-title">Add New Artist</h1>
    </header>

    <div class="form-section" style="background: #181818; padding: 35px; border-radius: 12px; border: 1px solid #282828;">
        
        <?php if ($error): ?>
            <p style="color: #ff3333; margin-bottom: 15px; font-weight: bold;"><?=$error ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p style="color: #00cc66; margin-bottom: 15px; font-weight: bold;"><?= $success ?></p>
        <?php endif; ?>

        <form method="POST" action="admin_add_artist.php">
            <input type="hidden" name="add_artist" value="1">

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; color: #fff; margin-bottom: 8px; font-weight: bold;">Artist Name *</label>
                <input type="text" name="artist_name" placeholder="e.g. Led Zeppelin" required style="width: 100%; box-sizing: border-box; padding: 14px 16px; background: #222; border: 1px solid #333; color: #fff; border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 25px;">
                <label style="display: block; color: #fff; margin-bottom: 8px; font-weight: bold;">Biography / Description</label>
                <textarea name="bio" placeholder="Write a short summary about the artist..." style="width: 100%; box-sizing: border-box; padding: 14px 16px; background: #222; border: 1px solid #333; 
                color: #fff; border-radius: 8px; height: 150px; resize: vertical; font-family: inherit; font-size: 0.95rem;"></textarea>
            </div>

            <button type="submit" class="checkout-submit-btn" style="width: 100%; padding: 16px; background: #e65c00; border: none; color: #fff; font-size: 1rem; font-weight: bold; border-radius: 8px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px;">
                SAVE ARTIST
            </button>
        </form>
        
    </div>
</main>

<footer>
    <p>&copy; 2026 Vinyl Store Admin System</p>
</footer>

</body>
</html>