<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);

$userQuery = "SELECT f_name, l_name, balance FROM Users WHERE user_id = $user_id";
$userResult = mysqli_query($conn, $userQuery);
$user = $userResult ? mysqli_fetch_assoc($userResult) : null;

if (!$user) {
    die("User not found");
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: index.php");
    exit;
}

$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}
$total = round($total, 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $street      = mysqli_real_escape_string($conn, trim($_POST['street'] ?? ''));
    $city        = mysqli_real_escape_string($conn, trim($_POST['city'] ?? ''));
    $country     = mysqli_real_escape_string($conn, trim($_POST['country'] ?? ''));
    $postal_code = mysqli_real_escape_string($conn, trim($_POST['postal_code'] ?? ''));

    if ($total > $user['balance']) {
        echo "<script>alert('Insufficient balance!');</script>";
    } elseif (!$street || !$city) {
        echo "<script>alert('Please fill in all fields');</script>";
    } else {
        
        $addressQuery = "
            INSERT INTO addresses (user_id, street, city, country, postal_code) 
            VALUES ($user_id, '$street', '$city', '$country', '$postal_code')
        ";
        
        if (mysqli_query($conn, $addressQuery)) {
            $address_id = mysqli_insert_id($conn);
            $order_failed = false;

            $updateUserQuery = "UPDATE Users SET balance = balance - $total WHERE user_id = $user_id";
            mysqli_query($conn, $updateUserQuery);

            foreach ($cart as $id => $item) {
                $vinyl_id = intval($id);
                $quantity = intval($item['quantity']);

                mysqli_query($conn, "SET @p_success = 0");
                mysqli_query($conn, "SET @p_message = ''");

                $procedureQuery = "CALL sp_place_order($user_id, $address_id, $vinyl_id, $quantity, @p_success, @p_message)";
                mysqli_query($conn, $procedureQuery);

                $res = mysqli_query($conn, "SELECT @p_success AS success, @p_message AS message");
                $output = mysqli_fetch_assoc($res);

                if ($output['success'] == 0) {
                    $order_failed = true;
                    echo "<script>alert('" . mysqli_real_escape_string($conn, $output['message']) . "');</script>";
                    break;
                }
            }

            if (!$order_failed) {
                $_SESSION['cart'] = [];
                echo "<script>alert('Order placed successfully!'); window.location.href = 'index.php';</script>";
            }
        } else {
            echo "<script>alert('Failed to save shipping address.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout | Vinyl Store</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'menu.php'; ?>

<div class="checkout-container">

    <div class="form-section">

        <form id="checkout-form" method="POST">

            <h2>Shipping Details</h2>

            <div class="form-group">
                <input type="text" name="street" placeholder="Street Address" required>
            </div>

            <div class="form-group">
                <input type="text" name="city" placeholder="City" required>
            </div>

            <div class="checkout-inline-inputs">
                <input type="text" name="country" placeholder="Country" required>
                <input type="text" name="postal_code" placeholder="Postal Code" required>
            </div>

            <div class="card-dummy">

                <h3 class="dummy-title">PAYMENT (Sparkasse bank only)</h3>

                <div class="form-group">
                    <input type="text" placeholder="Card Number (XXXX XXXX XXXX XXXX)">
                </div>

                <div class="checkout-inline-inputs">
                    <input type="text" placeholder="MM/YY">
                    <input type="text" placeholder="CVV">
                </div>

            </div>

            <button type="submit" name="place_order" class="checkout-submit-btn">
                PLACE ORDER
            </button>

        </form>

    </div>

    <div class="order-summary">

        <h2>Order Summary</h2>

        <?php foreach ($cart as $item): ?>

            <div class="checkout-item">

                <span>
                    <?= $item['title'] ?>
                    (x<?= $item['quantity'] ?>)
                </span>

                <span>
                    $<?= number_format($item['price'] * $item['quantity'], 2) ?>
                </span>

            </div>

        <?php endforeach; ?>

        <hr class="checkout-divider">

        <div class="checkout-total">

            <strong>Total:</strong>

            <strong class="checkout-accent">
                $<?= number_format($total, 2) ?>
            </strong>

        </div>

        <p class="balance-after">
            Balance after:
            $<?= number_format($user['balance'] - $total, 2) ?>
        </p>

    </div>

</div>

</body>
</html>