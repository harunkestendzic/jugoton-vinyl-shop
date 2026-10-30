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
?>

<div id="cart-sidebar" class="cart-sidebar">
    <div class="cart-header">
        <h2>Your Cart (<?= $total_quantity ?>)</h2>
        <button id="close-cart" class="close-btn" onclick="document.getElementById('cart-sidebar').classList.remove('open'); document.getElementById('cart-overlay').classList.remove('show');">✕</button>
    </div>
   
    <div id="cart-items" class="cart-items">
        <?php if (empty($cart_items)): ?>
            <p style="text-align:center; padding: 3rem 1rem; color:#888;">Your cart is empty</p>
        <?php else: ?>
            <?php foreach ($cart_items as $id => $item): ?>
                <?php $item_total = $item['price'] * $item['quantity']; ?>
                <div class="cart-item" style="display: flex; align-items: center; margin-bottom: 15px;">
                    <img src="<?= !empty($item['image']) ? $item['image'] : 'images/default-cover.png' ?>" alt="<?= $item['title'] ?>" style="width: 50px; height: 50px; object-fit: cover;">
                    <div style="flex:1; padding: 0 10px;">
                        <strong><?= $item['title'] ?></strong><br>
                        <small><?= $item['artist'] ?></small><br>
                        <small>Qty: <?= $item['quantity'] ?> × $<?= number_format($item['price'], 2) ?></small>
                    </div>
                    <div style="text-align:right">
                        <strong>$<?= number_format($item_total, 2) ?></strong><br>
                        <form action="cart_sidebar.php" method="POST" style="margin: 0;">
                            <input type="hidden" name="cart_action" value="remove">
                            <input type="hidden" name="vinyl_id" value="<?= $id ?>">
                            <input type="hidden" name="redirect_to" value="<?= $redirect_target ?>">
                            <button type="submit" style="color:#ff4d4d; background:none; border:none; margin-top:8px; cursor:pointer; font-size:0.9rem;">Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="cart-footer">
        <div class="cart-total">
            <strong>Total:</strong>
            <span>$<?= number_format($total_price, 2) ?></span>
        </div>
        
        <div class="cart-buttons">
            <form action="cart_sidebar.php" method="POST" onsubmit="return confirm('Clear entire cart?');" style="display: inline; margin: 0;">
                <input type="hidden" name="cart_action" value="clear">
                <input type="hidden" name="redirect_to" value="<?= $redirect_target ?>">
                <button type="submit" class="clear-cart-btn">Clear Cart</button>
            </form>
            <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
        </div>
    </div>
</div>

<div id="cart-overlay" class="cart-overlay" onclick="document.getElementById('cart-sidebar').classList.remove('open'); document.getElementById('cart-overlay').classList.remove('show');"></div>