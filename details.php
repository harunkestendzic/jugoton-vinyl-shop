<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_action'])) {
    $action = $_POST['cart_action'];
    $current_page = $_POST['redirect_to'] ?? 'index.php';

    if ($action === 'add') {
        $vinyl_id = intval($_POST['vinyl_id']);
        
        if (isset($_SESSION['cart'][$vinyl_id])) {
            $_SESSION['cart'][$vinyl_id]['quantity'] += 1;
        } else {
            $itemQuery = "
                SELECT v.title, v.price, v.cover_path, a.artist_name 
                FROM vinyls v 
                LEFT JOIN Artists a ON a.artist_id = v.artist_id 
                WHERE v.vinyl_id = $vinyl_id
            ";
            $itemResult = mysqli_query($conn, $itemQuery);
            $vinyl = $itemResult ? mysqli_fetch_assoc($itemResult) : null;
            
            if ($vinyl) {
                $_SESSION['cart'][$vinyl_id] = [
                    'title'    => $vinyl['title'],
                    'artist'   => $vinyl['artist_name'] ?? 'Unknown Artist',
                    'price'    => floatval($vinyl['price']),
                    'image'    => $vinyl['cover_path'],
                    'quantity' => 1
                ];
            }
        }
    }

    if ($action === 'remove') {
        $vinyl_id = intval($_POST['vinyl_id']);
        if (isset($_SESSION['cart'][$vinyl_id])) {
            unset($_SESSION['cart'][$vinyl_id]);
        }
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
    }


    header("Location: " . $current_page);
    exit;
}

$cart_items = $_SESSION['cart'];
$total_quantity = 0;
$total_price = 0.00;

foreach ($cart_items as $item) {
    $total_quantity += $item['quantity'];
    $total_price += ($item['price'] * $item['quantity']);
}

$current_script = basename($_SERVER['PHP_SELF']);
$query_string = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
$redirect_target = $current_script . $query_string;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vinyl = null;
$reviews = [];
$review_error = null;

if ($id > 0) {
    try {
        $query = "
            SELECT v.vinyl_id, v.title, v.price, v.stock, v.itunes_id, v.release_date, v.cover_path,
            a.artist_name, a.bio AS artist_bio, ft.type_name,
            GROUP_CONCAT(g.genre_name ORDER BY g.genre_name SEPARATOR ',') AS genres
            FROM Vinyls v
            JOIN Artists a ON a.artist_id  = v.artist_id
            JOIN Format_Type ft ON ft.type_id   = v.type_id
            LEFT JOIN Vinyl_Genres vg ON vg.vinyl_id = v.vinyl_id
            LEFT JOIN Genres g ON g.genre_id  = vg.genre_id
            WHERE v.vinyl_id = $id
            GROUP BY v.vinyl_id
        ";
        
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);

        if ($row) {
            $itunesId = $row['itunes_id'] ?? null;
            $formatted_date = !empty($row['release_date']) ? date('F j, Y', strtotime($row['release_date'])) : 'Unknown';

            $vinyl = [
                'id'           => (int)$row['vinyl_id'],
                'title'        => $row['title'],
                'artist'       => $row['artist_name'],
                'artist_bio'   => $row['artist_bio'] ?? null,
                'format'       => $row['type_name'],
                'price'        => (float)$row['price'],
                'stock'        => (int)$row['stock'],
                'release_date' => $formatted_date,
                'genres'       => $row['genres'] ? explode(',', $row['genres']) : [],
                'cover_art'    => !empty($row['cover_path']) ? $row['cover_path'] : 'images/default-cover.png',
                'itunes_embed' => $itunesId ? "https://embed.music.apple.com/us/album/placeholder/{$itunesId}?theme=dark" : null
            ];

            $reviewQuery = "
                SELECT r.rating, r.comment, r.created_at, u.f_name, u.l_name
                FROM Reviews r
                JOIN Users u ON u.user_id = r.user_id
                WHERE r.vinyl_id = $id
                ORDER BY r.created_at DESC
                LIMIT 10
            ";
            
            $reviewResult = mysqli_query($conn, $reviewQuery);
            $reviews = mysqli_fetch_all($reviewResult, MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        $vinyl = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    if (!isset($_SESSION['user_id'])) {
        $review_error = "You must be logged in to leave a review.";
    } else {
        $user_id = intval($_SESSION['user_id']);
        $vinyl_id = isset($_POST['vinyl_id']) ? intval($_POST['vinyl_id']) : 0;
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 5;
        $comment = mysqli_real_escape_string($conn, trim($_POST['comment'] ?? ''));

        if ($vinyl_id > 0 && !empty($comment)) {
            $insert_query = "
                INSERT INTO Reviews (vinyl_id, user_id, rating, comment, created_at) 
                VALUES ($vinyl_id, $user_id, $rating, '$comment', NOW())
                ON DUPLICATE KEY UPDATE 
                    rating = VALUES(rating),
                    comment = VALUES(comment),
                    created_at = NOW()
            ";
            
            if (mysqli_query($conn, $insert_query)) {
                header("Location: details.php?id=" . $vinyl_id);
                exit;
            } else {
                $review_error = "Failed to save your review. Please try again.";
            }
        } else {
            $review_error = "Please fill out all required review fields.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $vinyl ? htmlspecialchars($vinyl['title'] . ' - ' . $vinyl['artist']) : 'Vinyl Details | Record Store' ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .album-page { background-color: #0b0b0b; color: #fff; font-family: system-ui, -apple-system, sans-serif; }
        .album-container { max-width: 800px; margin: 40px auto; padding: 0 20px; box-sizing: border-box; }
        
        .top-cover-wrapper { width: 100%; display: flex; justify-content: center; margin-bottom: 35px; }
        .top-cover-wrapper img { width: 100%; max-width: 450px; aspect-ratio: 1; object-fit: cover; border-radius: 8px; box-shadow: 0 20px 45px rgba(0,0,0,0.7); }
        
        .album-header-section { margin-bottom: 30px; text-align: center; }
        .album-header-section h1 { font-size: 3rem; margin: 0 0 8px 0; font-weight: 800; letter-spacing: -1px; line-height: 1.1; }
        .album-header-section h3 { font-size: 1.6rem; color: #e65c00; margin: 0 0 20px 0; font-weight: 500; }
        
        .meta-badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-bottom: 12px; }
        .badge-alt { background: #141414; border: 1px solid #2a2a2a; padding: 5px 12px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #bbb; }
        .release-date { font-size: 0.85rem; color: #666; display: block; }
        
        .purchase-action-panel { background: #121212; border: 1px solid #222; padding: 25px; border-radius: 6px; text-align: center; margin-bottom: 40px; }
        .price-tag { font-size: 2rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
        .stock-tag { font-size: 0.85rem; color: #777; margin-bottom: 20px; }
        .stock-tag strong { color: #fff; }
        
        .add-to-cart-btn-big { background: #e65c00; color: #fff; border: none; width: 100%; max-width: 300px; padding: 14px; border-radius: 4px; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: background 0.15s ease; text-transform: uppercase; letter-spacing: 1px; }
        .add-to-cart-btn-big:hover { background: #ff6a00; }
        
        .album-description { font-size: 1.05rem; line-height: 1.65; color: #ccc; margin: 0 0 45px 0; text-align: justify; }
        .preview-heading { margin: 0 0 15px 0; color: #fc3c44; font-size: 0.85rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }
        .preview-container { background: #111; border-radius: 10px; padding: 2px; border: 1px solid #222; margin-bottom: 60px; }

        .review-layout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; border-top: 1px solid #1a1a1a; padding-top: 50px; }
        @media (max-width: 768px) { .review-layout-grid { grid-template-columns: 1fr; gap: 40px; } }
        
        .review-form-container h3, .reviews-section h2 { font-size: 1.3rem; margin-top: 0; margin-bottom: 25px; font-weight: 700; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 0.85rem; color: #888; text-transform: uppercase; }
        .form-group select, .form-group textarea { width: 100%; background: #141414; border: 1px solid #222; border-radius: 4px; color: #fff; padding: 12px; font-size: 0.95rem; box-sizing: border-box; }
        .form-group textarea { height: 120px; resize: vertical; }
        .form-group select:focus, .form-group textarea:focus { border-color: #e65c00; outline: none; }
        
        .review-card { background: #141414; border-radius: 6px; padding: 20px; margin-bottom: 15px; border: 1px solid #222; border-left: 3px solid #e65c00; }
        .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .review-user { font-weight: 600; font-size: 0.95rem; }
        .review-rating { color: #ffcc00; letter-spacing: 2px; font-size: 0.9rem; }
        .review-comment { margin: 0 0 12px 0; color: #ccc; font-style: italic; font-size: 0.95rem; line-height: 1.5; }
        .review-date { color: #555; font-size: 0.8rem; }
        .login-prompt { background: #141414; border: 1px dashed #333; padding: 30px; text-align: center; border-radius: 6px; }
        .login-prompt a { color: #e65c00; text-decoration: none; font-weight: 600; }
        .album-container { max-width: 800px; margin: 20px auto; padding: 0 20px; box-sizing: border-box; }

        .top-cover-wrapper { width: 100%; display: flex; justify-content: center; margin-bottom: 16px; }
        .top-cover-wrapper img { width: 100%; max-width: 350px; aspect-ratio: 1; object-fit: cover; border-radius: 8px; box-shadow: 0 20px 45px rgba(0,0,0,0.7); }

        .album-header-section { margin-bottom: 20px; text-align: center; }
        .album-header-section h1 { font-size: 2.4rem; margin: 0 0 4px 0; font-weight: 800; letter-spacing: -1px; line-height: 1.1; }
        .album-header-section h3 { font-size: 1.3rem; color: #e65c00; margin: 0 0 12px 0; font-weight: 500; }

        .meta-badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-bottom: 8px; }

        .purchase-action-panel { background: #121212; border: 1px solid #222; padding: 18px; border-radius: 6px; text-align: center; margin-bottom: 28px; }

        .album-description { font-size: 1.05rem; line-height: 1.65; color: #ccc; margin: 0 0 28px 0; text-align: justify; }

        .preview-container { background: #111; border-radius: 10px; padding: 2px; border: 1px solid #222; margin-bottom: 36px; }

        .review-layout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; border-top: 1px solid #1a1a1a; padding-top: 32px; }
    </style>
</head>
<body class="album-page">

<input type="checkbox" id="menu-toggle">
<label for="menu-toggle" class="vinyl-handle"><img src="hamburger.png" alt="menu icon"></label>

<?php include 'menu.php'; ?>
<?php include 'cart_sidebar.php'; ?>

<nav>
    <div class="logo" id="nav-title"><?= $vinyl ? htmlspecialchars(strtoupper($vinyl['title'])) : 'VINYL DETAILS' ?></div>
</nav>

<main class="album-container" id="details-content">
    <?php if ($id <= 0): ?>
        <h2 style="text-align: center; padding: 40px 0;">No vinyl selected.</h2>
    <?php elseif (!$vinyl): ?>
        <h2 style="text-align: center; padding: 40px 0;">Vinyl not found.</h2>
    <?php else: ?>
        
        <div class="top-cover-wrapper">
            <img src="<?= htmlspecialchars($vinyl['cover_art']) ?>" alt="<?= htmlspecialchars($vinyl['title']) ?>">
        </div>

        <header class="album-header-section">
            <h1><?= htmlspecialchars($vinyl['title']) ?></h1>
            <h3><?= htmlspecialchars($vinyl['artist']) ?></h3>
            <div class="meta-badges">
                <span class="badge-alt"><?= htmlspecialchars($vinyl['format']) ?></span>
                <?php foreach ($vinyl['genres'] as $genre): ?>
                    <span class="badge-alt"><?= htmlspecialchars($genre) ?></span>
                <?php endforeach; ?>
            </div>
            <span class="release-date">Released: <?= htmlspecialchars($vinyl['release_date']) ?></span>
        </header>
        
        <div class="purchase-action-panel">
            <div class="price-tag">$<?= number_format($vinyl['price'], 2) ?></div>
            <p class="stock-tag">
                Status: <strong><?= $vinyl['stock'] > 0 ? $vinyl['stock'] . ' Units Available' : 'Out of Stock' ?></strong>
            </p>
            <form action="details.php?id=<?= $vinyl['id'] ?>" method="POST" style="margin: 0;">
                <input type="hidden" name="cart_action" value="add">
                <input type="hidden" name="vinyl_id" value="<?= $vinyl['id'] ?>">
                <input type="hidden" name="redirect_to" value="details.php?id=<?= $vinyl['id'] ?>&added=1">
                <button type="submit" class="add-to-cart-btn-big">
                    ADD TO CART
                </button>
            </form>
        </div>
        
        <p class="album-description">
            <?= $vinyl['artist_bio'] ? htmlspecialchars($vinyl['artist_bio']) : "A classic addition to any collection. Experience the warmth of this " . htmlspecialchars($vinyl['format']) . " release." ?>
        </p>

        <?php if ($vinyl['itunes_embed']): ?>
            <div class="preview-container">
                <iframe
                    src="<?= htmlspecialchars($vinyl['itunes_embed']) ?>"
                    width="100%"
                    height="450"
                    frameborder="0"
                    sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation"
                    allow="autoplay *; encrypted-media *; fullscreen *"
                    style="border-radius: 8px; overflow: hidden; background: transparent; display: block;">
                </iframe>
            </div>
        <?php endif; ?>

        <div class="review-layout-grid">
            <div class="review-form-container">
                <h3>Leave a Review</h3>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($review_error): ?>
                        <p style="color: #ff3333; margin-bottom: 15px; font-size: 0.9rem;"><?= htmlspecialchars($review_error) ?></p>
                    <?php endif; ?>

                    <form action="details.php?id=<?= $vinyl['id'] ?>" method="POST">
                        <input type="hidden" name="action" value="submit_review">
                        <input type="hidden" name="vinyl_id" value="<?= $vinyl['id'] ?>">

                        <div class="form-group">
                            <label for="rev-rating">Rating</label>
                            <select id="rev-rating" name="rating" required>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Good</option>
                                <option value="3">3 - Average</option>
                                <option value="2">2 - Poor</option>
                                <option value="1">1 - Terrible</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="rev-comment">Your Review</label>
                            <textarea id="rev-comment" name="comment" placeholder="What are your thoughts on this record?" required></textarea>
                        </div>
                        <button type="submit" class="add-to-cart-btn-big" style="width: auto; padding: 12px 30px;">POST REVIEW</button>
                    </form>
                <?php else: ?>
                    <div class="login-prompt">
                        <p><a href="login.php">Login</a> to leave a review for this album.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="reviews-section">
                <h2>Customer Reviews</h2>
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $rev): ?>
                        <?php $stars = str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']); ?>
                        <div class="review-card">
                            <div class="review-header">
                                <span class="review-user"><?= htmlspecialchars($rev['f_name'] . ' ' . $rev['l_name']) ?></span>
                                <span class="review-rating"><?= $stars ?></span>
                            </div>
                            <p class="review-comment">"<?= htmlspecialchars($rev['comment']) ?>"</p>
                            <small class="review-date"><?= date('n/j/Y', strtotime($rev['created_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; font-style: italic; padding: 10px 0;">No reviews yet. Be the first to rate this record!</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<footer>
    <p style="text-align: center; color: #444; padding: 60px 0 20px 0; font-size: 0.85rem;">© Sajra & Harun</p>
</footer>

<?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
    <script>
        document.getElementById('cart-sidebar').classList.add('open');
        document.getElementById('cart-overlay').classList.add('show');
    </script>
<?php endif; ?>

</body>
</html>