<?php 
session_start(); 

require_once 'db.php'; 

$per_page = 5;
$page = 1;

try {
    $result = mysqli_query($conn, "
        SELECT v.vinyl_id, v.title, v.price, v.cover_path, a.artist_name 
        FROM vinyls v
        LEFT JOIN Artists a ON a.artist_id = v.artist_id 
        ORDER BY v.vinyl_id DESC
        LIMIT $per_page
    ");
    $vinyls = mysqli_fetch_all($result, MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    echo "Database Error: " . $e->getMessage();
    $vinyls = null;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vinyl Archive | Premium Analog Sound</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'menu.php'; ?>
    <?php include 'cart_sidebar.php'; ?>
    <header class="hero">
        <h1>Rare Pressings & Classic Cuts</h1>
        <p>Curated analog excellence for the modern listener.</p>
        <a href="#shop" class="btn">DISCOVER NOW</a>
    </header>

    <main class="container" id="shop">
        <div>
            <h2 class="section-title">Latest From Our Collection</h2>
        </div>
        
        <div class="vinyl-grid" id="latest-vinyls-container">
            <?php if (!$vinyls): ?>
                <p style="color: red; text-align: center; grid-column: 1/-1;">Error loading latest records.</p>
            <?php elseif (empty($vinyls)): ?>
                <p style="color: #aaa; text-align: center; grid-column: 1/-1;">No records found.</p>
            <?php else: ?>
                <?php foreach ($vinyls as $vinyl): ?>
                    <?php 
                        $cover = !empty($vinyl['cover_path']) ? $vinyl['cover_path'] : 'images/default-cover.png'; 
                    ?>
                    <a href="details.php?id=<?= $vinyl['vinyl_id'] ?>" class="card-link">
                        <div class="vinyl-card">
                            <img class="cover-art" src="<?= $cover ?>" alt="cover art">
                            <div class="album-info">
                                <h3><?= $vinyl['title'] ?></h3>
                                <p><?= $vinyl['artist_name'] ?? 'Unknown Artist' ?></p>
                                <div class="price">$<?= $vinyl['price'] ?></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; Sajra & Harun</p>
    </footer>

</body>
</html>


