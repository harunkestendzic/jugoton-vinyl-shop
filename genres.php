<?php
    session_start();

require_once 'db.php';

$currentGenre = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;

if ($currentPage < 1) {
    $currentPage = 1;
}

$perPage = 8;
$offset = ($currentPage - 1) * $perPage;

$genres_list = [];
$genresResult = mysqli_query($conn, "SELECT genre_name FROM Genres ORDER BY genre_name ASC");
if ($genresResult) {
    $genres_list = mysqli_fetch_all($genresResult, MYSQLI_ASSOC);
}

$whereClause = "";
if (!empty($currentGenre)) {
    $whereClause = "WHERE g.genre_name = '$currentGenre'";
}

$countQuery = "
    SELECT COUNT(DISTINCT v.vinyl_id) AS total 
    FROM Vinyls v
    LEFT JOIN Vinyl_Genres vg ON vg.vinyl_id = v.vinyl_id
    LEFT JOIN Genres g ON g.genre_id  = vg.genre_id
    $whereClause
";

$countResult = mysqli_query($conn, $countQuery);
$countRow = mysqli_fetch_assoc($countResult);
$totalRecords = $countRow['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);
if ($totalPages < 1) {
    $totalPages = 1;
}

$vinylsQuery = "
    SELECT v.vinyl_id, v.title, v.price, v.cover_path, a.artist_name 
    FROM Vinyls v
    JOIN Artists a ON a.artist_id = v.artist_id
    LEFT JOIN Vinyl_Genres vg ON vg.vinyl_id = v.vinyl_id
    LEFT JOIN Genres g ON g.genre_id  = vg.genre_id
    $whereClause
    GROUP BY v.vinyl_id
    ORDER BY v.title ASC
    LIMIT $perPage OFFSET $offset
";

$vinylsResult = mysqli_query($conn, $vinylsQuery);
$vinyls_list = [];
if ($vinylsResult) {
    $vinyls_list = mysqli_fetch_all($vinylsResult, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Genres | Vinyl Archive</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="genres-page">
    <input type="checkbox" id="menu-toggle">
    <label for="menu-toggle" class="vinyl-handle">
        <img src="hamburger.png" alt="menu icon">
    </label>

    <?php include 'menu.php'; ?>
    <?php include 'cart_sidebar.php'; ?>

    <nav>
        <div class="logo" id="nav-title">GENRES</div>
    </nav>

    <div class="genre-filters" style="text-align:center; padding:20px;" id="genre-filters">
        <a href="genres.php" class="genre-tab <?= empty($currentGenre) ? 'active' : '' ?>">All Genres</a>
        <?php foreach ($genres_list as $genre): ?>
            <?php 
            $isActive = ($currentGenre === $genre['genre_name']);
            $genreUrl = "genres.php?genre=" . urlencode($genre['genre_name']);
            ?>
            <a href="<?= $genreUrl ?>" class="genre-tab <?= $isActive ? 'active' : '' ?>">
                <?= $genre['genre_name'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="vinyl-grid" id="vinyl-container">
        <?php if (!empty($vinyls_list)): ?>
            <?php foreach ($vinyls_list as $vinyl): ?>
                <?php $coverImage = !empty($vinyl['cover_path']) ? $vinyl['cover_path'] : 'images/default-cover.png'; ?>
                <a href="details.php?id=<?= $vinyl['vinyl_id'] ?>" class="card-link">
                    <div class="vinyl-card">
                        <img class="cover-art" src="<?= $coverImage ?>" alt="<?= $vinyl['title'] ?>">
                        <div class="album-info">
                            <h3><?= $vinyl['title'] ?></h3>
                            <p><?= $vinyl['artist_name'] ?></p>
                            <div class="price">$<?= number_format($vinyl['price'], 2) ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #aaa; text-align: center; grid-column: 1/-1; padding: 60px;">
                No vinyls found in this section.
            </p>
        <?php endif; ?>
    </div>


    <div id="pagination" class="pagination" style="text-align:center; margin: 30px 0;">
        <div class="page-numbers">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php 
                $pageUrl = "genres.php?page=" . $i;
                if (!empty($currentGenre)) {
                    $pageUrl .= "&genre=" . urlencode($currentGenre);
                }
                ?>
                <?php if ($i === $currentPage): ?>
                    <span class="page-btn active"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= $pageUrl ?>" class="page-btn"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>

    <footer>
        <p>© Sajra & Harun</p>
    </footer>
</body>
</html>