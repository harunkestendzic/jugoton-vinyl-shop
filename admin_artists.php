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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_artist') {
    $delete_id = intval($_POST['id']);
    
    $checkQuery = "SELECT COUNT(*) AS total FROM Vinyls WHERE artist_id = $delete_id";
    $checkResult = mysqli_query($conn, $checkQuery);
    $checkRow = mysqli_fetch_assoc($checkResult);
    
    if (($checkRow['total'] ?? 0) > 0) {
        $error = "Cannot delete this artist because they still have vinyl records listed in your inventory!";
    } else {
        $delete_query = "DELETE FROM Artists WHERE artist_id = $delete_id";
        if (mysqli_query($conn, $delete_query)) {
            $success = "Artist successfully removed.";
        } else {
            $error = "Failed to delete artist. Please try again.";
        }
    }
}

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$whereClause = !empty($search) ? "WHERE a.artist_name LIKE '%$search%' OR a.bio LIKE '%$search%'" : "";

$artistsQuery = "
    SELECT a.artist_id, a.artist_name, a.bio, COUNT(v.vinyl_id) AS total_albums
    FROM Artists a
    LEFT JOIN Vinyls v ON v.artist_id = a.artist_id
    $whereClause
    GROUP BY a.artist_id
    ORDER BY a.artist_name ASC
";
$artistsResult = mysqli_query($conn, $artistsQuery);
$artists_list = [];

if ($artistsResult) {
    $artists_list = mysqli_fetch_all($artistsResult, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Artists | Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .search-container {
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            width: 100%;
        }
        .search-input {
            flex-grow: 1;
            padding: 12px;
            background: #222;
            border: 1px solid #444;
            color: #fff;
            border-radius: 6px;
            font-size: 1rem;
        }
        .search-btn {
            padding: 12px 24px;
            background: #e65c00;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .search-btn:hover {
            background: #ff6a00;
        }
        .clear-btn {
            padding: 12px 20px;
            background: #333;
            color: #aaa;
            text-decoration: none;
            border-radius: 6px;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }
        .clear-btn:hover {
            background: #444;
            color: #fff;
        }
    </style>
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

<main class="container dashboard-main">
    
    <header class="dash-header">
        <h1 class="section-title">Manage Artists</h1>
        <a href="admin_add_artist.php" class="btn">Add Artist</a>
    </header>

    <?php if ($error): ?>
        <p style="color: #ff3333; margin-bottom: 20px; font-weight: bold; background: rgba(255,51,51,0.1); padding: 15px; border-radius: 6px; border: 1px solid rgba(255,51,51,0.2);"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color: #00cc66; margin-bottom: 20px; font-weight: bold; background: rgba(0,204,102,0.1); padding: 15px; border-radius: 6px; border: 1px solid rgba(0,204,102,0.2);"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="GET" action="admin_artists.php" class="search-container">
        <input type="text" name="search" class="search-input" placeholder="Search by artist name or biography content..." value="<?= htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : '') ?>">
        <button type="submit" class="search-btn">Search</button>
        <?php if (!empty($search)): ?>
            <a href="admin_artists.php" class="clear-btn">Clear</a>
        <?php endif; ?>
    </form>

    <section class="inventory-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Artist Details</th>
                    <th>Biography</th>
                    <th>Catalog</th>
                    <th style="text-align: right; padding-right: 24px;">Management</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($artists_list)): ?>
                    <?php foreach ($artists_list as $artist): ?>
                        <tr>
                            <td style="white-space: nowrap; font-weight: bold;">
                                <?= htmlspecialchars($artist['artist_name']) ?>
                            </td>
                            <td style="color: #aaa; font-size: 0.9rem; max-width: 400px; overflow: hidden; text-overflow: ellipsis;">
                                <?= $artist['bio'] ? htmlspecialchars($artist['bio']) : '<em style="color:#555;">No biography available.</em>' ?>
                            </td>
                            <td>
                                <span class="badge <?= $artist['total_albums'] == 0 ? 'badge-danger' : 'badge-success' ?>">
                                    <?= intval($artist['total_albums']) ?> records
                                </span>
                            </td>
                            <td style="text-align: right; padding-right: 24px; width: 1%;">
                                <div class="action-group" style="display: inline-flex; align-items: center; gap: 20px; white-space: nowrap;">
                                    <a href="admin_edit_artist.php?id=<?= $artist['artist_id'] ?>" class="edit-action">EDIT</a>
                                    
                                    <form action="admin_artists.php" method="POST" onsubmit="return confirm('Are you sure you want to remove this artist? This action cannot be undone.');" style="display: inline; margin: 0; padding: 0;">
                                        <input type="hidden" name="action" value="delete_artist">
                                        <input type="hidden" name="id" value="<?= $artist['artist_id'] ?>">
                                        <button type="submit" class="delete-action" style="background: none; border: none; padding: 0; margin: 0; font-family: inherit; font-size: inherit; cursor: pointer; display: inline-block; vertical-align: middle;">DELETE</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center" style="padding: 40px; color: #aaa;">No artists found in the archive.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<footer>
    <p>&copy; 2026 Vinyl Store Admin System</p>
</footer>

</body>
</html>