<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

$query = "SELECT * FROM v_vinyl_sales_analytics ORDER BY total_units_sold DESC LIMIT 10";
$result = mysqli_query($conn, $query);
$analytics = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Analytics Report</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background: #0b0b0b; color: #fff; font-family: sans-serif; ;">
<?php include 'menu.php'; ?>
<div style="padding: 50px;">
    <h1>Top Selling Vinyl Records</h1>
    <table border="1" cellpadding="12" style="border-collapse: collapse; width: 100%; background: #141414; border-color: #222;">
        <thead>
            <tr style="background: #1c1c1c; color: #e65c00;">
                <th>Album Title</th>
                <th>Artist</th>
                <th>Format</th>
                <th>Units Sold</th>
                <th>Total Revenue</th>
                <th>Avg Rating</th>
                <th>Total Reviews</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($analytics as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['vinyl_title']) ?></strong></td>
                    <td><?= htmlspecialchars($row['artist_name']) ?></td>
                    <td><?= htmlspecialchars($row['format_type']) ?></td>
                    <td><?= $row['total_units_sold'] ?></td>
                    <td>$<?= number_format($row['total_revenue_generated'], 2) ?></td>
                    <td>★ <?= number_format($row['average_user_rating'], 1) ?></td>
                    <td><?= $row['total_reviews_received'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
    </div>
    </body>
    </html>