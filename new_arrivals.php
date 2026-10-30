<?php
session_start();

require_once 'db.php';

$per_page = 8;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    if ($search !== '') {
        $secure_search = mysqli_real_escape_string($conn, $search);

        $count_query = "SELECT COUNT(*) as total FROM vinyls v LEFT JOIN Artists a 
        ON a.artist_id = v.artist_id WHERE v.title LIKE '%$secure_search%' OR a.artist_name LIKE '%$secure_search%'";
    
        $query = "SELECT v.vinyl_id, v.title, v.price, v.cover_path, a.artist_name FROM vinyls v 
        LEFT JOIN Artists a ON a.artist_id = v.artist_id WHERE v.title LIKE '%$secure_search%' 
        OR a.artist_name LIKE '%$secure_search%' ORDER BY v.vinyl_id DESC LIMIT $per_page OFFSET $offset";
    } else {
        $count_query = "SELECT COUNT(*) as total FROM vinyls";
        
        $query = "SELECT v.vinyl_id, v.title, v.price, v.cover_path, a.artist_name FROM vinyls v 
        LEFT JOIN Artists a ON a.artist_id = v.artist_id ORDER BY v.vinyl_id DESC LIMIT $per_page OFFSET $offset";
    }

    $count_result = mysqli_query($conn, $count_query);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_records = $count_row['total'];
    $total_pages = ceil($total_records / $per_page);

    $result = mysqli_query($conn, $query);
    $vinyls = mysqli_fetch_all($result, MYSQLI_ASSOC);

} catch (mysqli_sql_exception $e) {
    $vinyls = null;
    $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Arrivals | Vinyl Archive</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <input type="checkbox" id="menu-toggle">
    <label for="menu-toggle" class="vinyl-handle">
        <img src="hamburger.png" alt="menu icon">
    </label>

    <?php include 'menu.php'; ?>
    <?php include 'cart_sidebar.php'; ?>

    <nav>
        <div class="logo" id="nav-title">NEW ARRIVALS</div>
    </nav>

    <form method="GET" action="new_arrivals.php" class="search-section">
        <input type="text" name="search" id="search-input" value="<?= htmlspecialchars($search) ?>" placeholder="Search for an album or artist...">
        <button type="submit" class="page-btn">Search</button>
    </form>

    <div class="vinyl-grid" id="vinyl-container">
        <?php if (!$vinyls): ?>
            <p style="color: red; text-align: center; grid-column: 1/-1;">Error loading data.</p>
        <?php elseif (empty($vinyls)): ?>
            <p style="color: #aaa; text-align: center; grid-column: 1/-1; padding: 60px;">
                No vinyls found matching "<?= htmlspecialchars($search) ?>".
            </p>
        <?php else: ?>
            <?php foreach ($vinyls as $vinyl): ?>
                <?php 
                    $cover = !empty($vinyl['cover_path']) ? htmlspecialchars($vinyl['cover_path']) : 'images/default-cover.png'; 
                ?>
                <a href="details.php?id=<?= $vinyl['vinyl_id'] ?>" class="card-link">
                    <div class="vinyl-card">
                        <img class="cover-art" src="<?= $cover ?>" alt="<?= htmlspecialchars($vinyl['title']) ?>">
                        <div class="album-info">
                            <h3><?= htmlspecialchars($vinyl['title']) ?></h3>
                            <p><?= htmlspecialchars($vinyl['artist_name'] ?? 'Unknown Artist') ?></p>
                            <div class="price">$<?= number_format($vinyl['price'], 2) ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="pagination" class="pagination" style="text-align:center; margin: 40px 0;">
        <div class="page-numbers">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i === $page): ?>
                    <button class="page-btn active"><?= $i ?></button>
                <?php else: ?>
                    <a href="new_arrivals.php?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="page-btn"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>

    <footer>
        <p>© Sajra & Harun</p>
    </footer>
</body>
</html>