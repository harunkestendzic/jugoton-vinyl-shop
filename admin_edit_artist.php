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

$artist_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$artist = null;

if ($artist_id > 0) {
    $artistQuery = "SELECT * FROM Artists WHERE artist_id = $artist_id";
    $artistResult = mysqli_query($conn, $artistQuery);
    $artist = $artistResult ? mysqli_fetch_assoc($artistResult) : null;
}

if (!$artist) {
    header("Location: admin_artists.php");
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_artist'])) {
    $artist_name = mysqli_real_escape_string($conn, trim($_POST['artist_name'] ?? ''));
    $bio         = mysqli_real_escape_string($conn, trim($_POST['bio'] ?? ''));

    if (!$artist_name) {
        $error = "Artist name is required.";
    } else {
        $checkQuery = "SELECT artist_id FROM Artists WHERE artist_name = '$artist_name' AND artist_id != $artist_id";
        $checkResult = mysqli_query($conn, $checkQuery);

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            $error = "Another artist with that name already exists.";
        } else {
            $updateQuery = "UPDATE Artists SET artist_name = '$artist_name', bio = '$bio' WHERE artist_id = $artist_id";
            
            if (mysqli_query($conn, $updateQuery)) {
                $success = "Artist updated successfully!";
                
                $artist['artist_name'] = $artist_name;
                $artist['bio'] = $bio;
            } else {
                $error = "Failed to update artist. Please try again.";
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
    <title>Edit Artist | Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
    <div class="logo">ADMIN PANEL</div>
    <div class="nav-links">
        <a href="admin_artists.php">Manage Artists</a>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="index.php">View Site</a>
        <a href="logout.php" class="logout-link">Logout</a>
    </div>
</nav>

<main class="container dashboard-main" style="max-width: 600px; margin: 40px auto; padding: 0 20px;">
    
    <header class="dash-header" style="margin-bottom: 25px;">
        <h1 class="section-title">Edit Artist: <?= htmlspecialchars($artist['artist_name']) ?></h1>
    </header>

    <div class="form-section" style="background: #181818; padding: 35px; border-radius: 12px; border: 1px solid #282828;">
        
        <?php if ($error): ?>
            <p style="color: #ff3333; margin-bottom: 15px; font-weight: bold;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p style="color: #00cc66; margin-bottom: 15px; font-weight: bold;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <form method="POST" action="admin_edit_artist.php?id=<?= $artist_id ?>">
            <input type="hidden" name="edit_artist" value="1">

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; color: #fff; margin-bottom: 8px; font-weight: bold;">Artist Name *</label>
                <input type="text" name="artist_name" value="<?= htmlspecialchars($artist['artist_name']) ?>" required style="width: 100%; box-sizing: border-box; padding: 14px 16px; background: #222; border: 1px solid #333; color: #fff; border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 25px;">
                <label style="display: block; color: #fff; margin-bottom: 8px; font-weight: bold;">Biography / Description</label>
                <textarea name="bio" style="width: 100%; box-sizing: border-box; padding: 14px 16px; background: #222; border: 1px solid #333; color: #fff; border-radius: 8px; height: 150px; resize: vertical; font-family: inherit; font-size: 0.95rem;"><?= htmlspecialchars($artist['bio'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="checkout-submit-btn" style="width: 100%; padding: 16px; background: #e65c00; border: none; color: #fff; font-size: 1rem; font-weight: bold; border-radius: 8px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px;">
                UPDATE ARTIST
            </button>
        </form>
        
    </div>
</main>

<footer>
    <p>&copy; 2026 Vinyl Store Admin System</p>
</footer>

</body>
</html>