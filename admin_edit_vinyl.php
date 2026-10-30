<?php
require_once 'db.php';
require_once 'helpers.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vinyl_id   = intval($_POST['vinyl_id']);
    $title      = mysqli_real_escape_string($conn, trim($_POST['title']));
    $artist_id  = intval($_POST['artist_id']);
    $type_id    = intval($_POST['type_id']);
    $price      = floatval($_POST['price']);
    $stock      = intval($_POST['stock']);
    $itunes_id  = mysqli_real_escape_string($conn, trim($_POST['itunes_id'])); 
    
    $genre_ids  = isset($_POST['genre_ids']) ? $_POST['genre_ids'] : [];
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

    $savedCoverPath = '';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $savedCoverPath = uploadUploadedFileToOracle($_FILES['cover_image'], $vinyl_id);
    }

    if ($savedCoverPath !== '' && $savedCoverPath !== 'images/default-cover.png') {
        $updateQuery = "
            UPDATE Vinyls 
            SET title = '$title', 
                artist_id = $artist_id, 
                type_id = $type_id, 
                price = $price, 
                stock = $stock,
                itunes_id = '$itunes_id',
                cover_path = '$savedCoverPath' 
            WHERE vinyl_id = $vinyl_id
        ";
    } else {
        $updateQuery = "
            UPDATE Vinyls 
            SET title = '$title', 
                artist_id = $artist_id, 
                type_id = $type_id, 
                price = $price, 
                stock = $stock,
                itunes_id = '$itunes_id' 
            WHERE vinyl_id = $vinyl_id
        ";
    }
    
    if (mysqli_query($conn, $updateQuery)) {
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

        mysqli_query($conn, "DELETE FROM vinyl_genres WHERE vinyl_id = $vinyl_id");

        foreach (array_unique($final_genre_ids) as $gid) {
            mysqli_query($conn, "INSERT IGNORE INTO vinyl_genres (vinyl_id, genre_id) VALUES ($vinyl_id, $gid)");
        }

        header("Location: admin_dashboard.php");
        exit;
    } else {
        $error = "Error updating record: " . mysqli_error($conn);
    }
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$vinylQuery = "SELECT * FROM Vinyls WHERE vinyl_id = $id";
$vinylResult = mysqli_query($conn, $vinylQuery);
$vinyl = $vinylResult ? mysqli_fetch_assoc($vinylResult) : null;

if (!$vinyl) {
    die("Vinyl not found.");
}

$current_genres = [];
$assignedGenresResult = mysqli_query($conn, "SELECT genre_id FROM vinyl_genres WHERE vinyl_id = $id");
if ($assignedGenresResult) {
    while ($row = mysqli_fetch_assoc($assignedGenresResult)) {
        $current_genres[] = intval($row['genre_id']);
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
    <title>Edit Vinyl</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-form input[type="text"],
        .admin-form input[type="number"],
        .admin-form input[type="url"],
        .admin-form input[type="file"],
        .admin-form select {
            width: 100% ;
            box-sizing: border-box ;
            padding: 12px ;
            background: #222 ;
            border: 1px solid #444 ;
            color: #fff ;
            border-radius: 6px ;
            font-size: 1rem ;
            font-family: inherit ;
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
<div class="account-container">
    <h2>Edit: <?= htmlspecialchars($vinyl['title']) ?></h2>
    
    <?php if (isset($error)): ?>
        <p class="error" style="color: var(--danger); margin-bottom: 15px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    
    <form id="edit-form" method="POST" action="admin_edit_vinyl.php?id=<?= $vinyl['vinyl_id'] ?>" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="vinyl_id" value="<?= $vinyl['vinyl_id'] ?>">
        
        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Album Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($vinyl['title']) ?>" required>
        </div>
        
        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Artist</label>
            <select name="artist_id" id="artist_select">
                <?php foreach ($artists_list as $a): ?>
                    <option value="<?= $a['artist_id'] ?>" <?= $a['artist_id'] == $vinyl['artist_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['artist_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Format</label>
            <select name="type_id" id="type_select">
                <?php foreach ($formats_list as $f): ?>
                    <option value="<?= $f['type_id'] ?>" <?= $f['type_id'] == $vinyl['type_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['type_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Genres (Hold Ctrl/Cmd to select multiple)</label>
            <select name="genre_ids[]" id="genre_select" style="height: 120px;" multiple>
                <?php foreach ($genres_list as $g): ?>
                    <option value="<?= $g['genre_id'] ?>" <?= in_array($g['genre_id'], $current_genres) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['genre_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; color: #e65c00;">Add Missing Genres</label>
            <input type="text" name="new_genres" placeholder="e.g. Synthwave, Post-Punk">
            <span class="genre-hint">Separate multiple new genres with commas</span>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Price ($)</label>
            <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($vinyl['price']) ?>" required>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Stock</label>
            <input type="number" name="stock" value="<?= htmlspecialchars($vinyl['stock']) ?>" required>
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">Itunes ID</label>
            <input type="text" name="itunes_id" maxlength="30" value="<?= htmlspecialchars($vinyl['itunes_id'] ?? '') ?>" placeholder="e.g. 0ETFjACtuP2ADo6LFhL6HN">
        </div>

        <div class="form-group" style="text-align: left; margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; color: #e65c00;">Replace Cover Image (Optional)</label>
            <input type="file" name="cover_image" accept="image/*">
            <?php if (!empty($vinyl['cover_path'])): ?>
                <span class="genre-hint" style="color: #00cc66;">Current path: <?= htmlspecialchars($vinyl['cover_path']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" class="page-btn">Update Record</button>
        <a href="admin_dashboard.php" style="color: #888; margin-left: 15px;">Cancel</a>
    </form>
</div>
</body>
</html>