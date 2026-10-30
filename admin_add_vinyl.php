<?php
require_once 'db.php';
require_once 'helpers.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = mysqli_real_escape_string($conn, trim($_POST['title']));
    $artist_id    = intval($_POST['artist_id']);
    $type_id      = intval($_POST['type_id']);
    $price        = floatval($_POST['price']);
    $stock        = intval($_POST['stock']);
    $release_date = mysqli_real_escape_string($conn, trim($_POST['release_date']));
    $itunes_id    = mysqli_real_escape_string($conn, trim($_POST['itunes_id']));
    
    $genre_ids    = isset($_POST['genre_ids']) ? $_POST['genre_ids'] : [];
    $new_genres_text = trim($_POST['new_genres']);

    $artistName = '';
    $artistQuery = "SELECT artist_name FROM Artists WHERE artist_id = $artist_id";
    $artistResult = mysqli_query($conn, $artistQuery);
    if ($artistResult && $row = mysqli_fetch_assoc($artistResult)) {
        $artistName = $row['artist_name'];
    }

    if (empty($itunes_id)) {
        $itunes_id = discoverItunesId($title, $artistName);
    }

    $insertQuery = "
        INSERT INTO Vinyls (title, artist_id, type_id, price, stock, release_date, itunes_id) 
        VALUES ('$title', $artist_id, $type_id, $price, $stock, '$release_date', '$itunes_id')
    ";

    if (mysqli_query($conn, $insertQuery)) {
        $new_vinyl_id = mysqli_insert_id($conn);
        $final_genre_ids = [];

        foreach ($genre_ids as $gid) {
            $gid = intval($gid);
            if ($gid > 0) {
                $final_genre_ids[] = $gid;
            }
        }

        if ($new_genres_text !== '') {
            $raw_tags = explode(',', $new_genres_text);
            foreach ($raw_tags as $tag) {
                $clean_tag = mysqli_real_escape_string($conn, trim($tag));
                if ($clean_tag !== '') {
                    $checkGenre = mysqli_query($conn, "SELECT genre_id FROM Genres WHERE genre_name = '$clean_tag'");
                    if ($checkGenre && mysqli_num_rows($checkGenre) > 0) {
                        $existingGenre = mysqli_fetch_assoc($checkGenre);
                        $final_genre_ids[] = intval($existingGenre['genre_id']);
                    } else {
                        $insertGenre = "INSERT INTO Genres (genre_name) VALUES ('$clean_tag')";
                        if (mysqli_query($conn, $insertGenre)) {
                            $final_genre_ids[] = mysqli_insert_id($conn);
                        }
                    }
                }
            }
        }

        foreach (array_unique($final_genre_ids) as $gid) {
            mysqli_query($conn, "INSERT IGNORE INTO vinyl_genres (vinyl_id, genre_id) VALUES ($new_vinyl_id, $gid)");
        }

        $savedCoverPath = '';
        
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $savedCoverPath = uploadUploadedFileToOracle($_FILES['cover_image'], $new_vinyl_id);
        }

        if (!$savedCoverPath || $savedCoverPath === 'images/default-cover.png') {
            $savedCoverPath = downloadAndStoreCoverToOracle($title, $artistName, $new_vinyl_id);
        }
        
        $updateQuery = "UPDATE Vinyls SET cover_path = '$savedCoverPath' WHERE vinyl_id = $new_vinyl_id";
        mysqli_query($conn, $updateQuery);
        
        $success = "Vinyl record, multi-genre indexing, and artwork processed successfully!";
    } else {
        $error = "Failed to add vinyl record: " . mysqli_error($conn);
    }
}

$artists_list = [];
$artistsResult = mysqli_query($conn, "SELECT artist_id, artist_name FROM Artists ORDER BY artist_name ASC");
if ($artistsResult) {
    $artists_list = mysqli_fetch_all($artistsResult, MYSQLI_ASSOC);
}

$formats_list = [];
$formatsResult = mysqli_query($conn, "SELECT type_id, type_name FROM Format_Type ORDER BY type_name ASC");
if ($formatsResult) {
    $formats_list = mysqli_fetch_all($formatsResult, MYSQLI_ASSOC);
}

$genres_list = [];
$genresResult = mysqli_query($conn, "SELECT genre_id, genre_name FROM Genres ORDER BY genre_name ASC");
if ($genresResult) {
    $genres_list = mysqli_fetch_all($genresResult, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vinyl | Admin</title>
    <link rel="stylesheet" href="style.css">
    
    <style>
        .admin-form input[type="text"],
        .admin-form input[type="number"],
        .admin-form input[type="date"],
        .admin-form input[type="file"],
        .admin-form select {
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
        .genre-hint {
            font-size: 0.8rem;
            color: #888;
            margin-top: 4px;
            display: block;
        }
        .cancel-btn-container {
            text-align: center;
            margin-top: 15px;
        }
        .cancel-btn-container a {
            color: #888;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .cancel-btn-container a:hover {
            color: #fff;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>   
<div class="account-container" style="max-width: 500px; margin: 40px auto; padding: 30px; background: #111; border-radius: 8px;">
    <h2>Add New Record</h2>
    
    <?php if (isset($error)): ?>
        <p class="error" style="color: #ff3333; margin-bottom: 15px; font-weight: bold;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <p class="success" style="color: #00cc66; margin-bottom: 15px; font-weight: bold;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    
    <form method="POST" action="admin_add_vinyl.php" enctype="multipart/form-data" class="admin-form">
        
        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Album Title</label>
            <input type="text" name="title" id="vinyl_title" placeholder="e.g. Animals" required>
        </div>
        
        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Artist</label>
            <select name="artist_id" id="artist_select" required>
                <option value="">Select Artist</option>
                <?php foreach ($artists_list as $a): ?>
                    <option value="<?= $a['artist_id'] ?>">
                        <?= htmlspecialchars($a['artist_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Format</label>
            <select name="type_id" id="type_select" required>
                <option value="">Select Format</option>
                <?php foreach ($formats_list as $f): ?>
                    <option value="<?= $f['type_id'] ?>">
                        <?= htmlspecialchars($f['type_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Genres (Hold Ctrl/Cmd to select multiple)</label>
            <select name="genre_ids[]" id="genre_select" style="height: 120px;" multiple>
                <?php foreach ($genres_list as $g): ?>
                    <option value="<?= $g['genre_id'] ?>">
                        <?= htmlspecialchars($g['genre_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; color: #e65c00;">Add New Genres</label>
            <input type="text" name="new_genres" id="new_genres" placeholder="e.g. Dream Pop, Shoegaze">
            <span class="genre-hint">Separate multiple new genres with commas</span>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Price ($)</label>
            <input type="number" step="0.01" name="price" placeholder="0.00" required>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Stock Quantity</label>
            <input type="number" name="stock" placeholder="0" required>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Release Date</label>
            <input type="date" name="release_date" id="release_date" required>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">iTunes ID (Optional)</label>
            <input type="text" name="itunes_id" id="itunes_id" maxlength="30" placeholder="Leave blank to lookup automatically">
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; color: #e65c00;">Upload Cover Image (Optional)</label>
            <input type="file" name="cover_image" accept="image/*">
            <span class="genre-hint">Supported formats: JPG, JPEG, PNG, WEBP</span>
        </div>

        <button type="submit" class="page-btn" style="width:100%; padding:12px; background:#e65c00; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">Save Record</button>
        
        <div class="cancel-btn-container">
            <a href="admin_dashboard.php">Cancel</a>
        </div>
    </form>
</div>

<?php renderAdminFormAutomation(); ?>
</body>
</html>