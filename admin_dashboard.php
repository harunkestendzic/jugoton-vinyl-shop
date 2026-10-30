<?php
session_start();
require_once 'db.php';

$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

$query = "SELECT is_admin FROM Users WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);
$user = $result ? mysqli_fetch_assoc($result) : null;

if (!$user || $user['is_admin'] != 1) {
    header("Location: index.php");
    exit("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_vinyl') {
    $delete_id = intval($_POST['id']);
    mysqli_query($conn, "DELETE FROM Vinyls WHERE vinyl_id = $delete_id");
    header("Location: admin_dashboard.php");
    exit;
}

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$whereClause = !empty($search) ? "WHERE v.title LIKE '%$search%' OR a.artist_name LIKE '%$search%'" : "";

$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($currentPage - 1) * $perPage;

$countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM Vinyls v LEFT JOIN Artists a ON a.artist_id = v.artist_id $whereClause");
$countRow = mysqli_fetch_assoc($countResult);
$totalRecords = $countRow['total'] ?? 0;
$totalPages = max(1, ceil($totalRecords / $perPage));

$inventoryResult = mysqli_query($conn, "SELECT v.vinyl_id, v.title, v.price, v.stock, a.artist_name FROM Vinyls v JOIN Artists a ON a.artist_id = v.artist_id $whereClause ORDER BY v.title ASC LIMIT $perPage OFFSET $offset");
$inventory_list = $inventoryResult ? mysqli_fetch_all($inventoryResult, MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .search-container { margin-bottom: 20px; display: flex; gap: 10px; }
        .search-input { flex-grow: 1; padding: 10px; background: #222; border: 1px solid #444; color: #fff; border-radius: 6px; }
        .search-btn { padding: 10px 20px; background: #e65c00; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .clear-btn { padding: 10px 15px; background: #333; color: #aaa; text-decoration: none; border-radius: 6px; display: flex; align-items: center; }
        .three-column-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .address-sub { font-size: 0.75rem; color: #888; display: block; }
        
        .scrollable-card { 
            max-height: 165px; 
            overflow-y: auto; 
            padding-right: 5px;
        }
        .scrollable-card::-webkit-scrollbar { width: 6px; }
        .scrollable-card::-webkit-scrollbar-track { background: #111; }
        .scrollable-card::-webkit-scrollbar-thumb { background: #444; border-radius: 3px; }

        /* Realignment fixes based on image_e16360.png */
        .text-center { text-align: center !important; }
        .action-group-centered {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            width: 100%;
        }
        </style>
</head>
<body>
    
    <nav>
        <div class="logo">ADMIN PANEL</div>
        <div class="nav-links">
            <a href="index.php">View Site</a>
            <a href="admin_artists.php">Manage Artists</a>
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </nav>
    
    <main class="container dashboard-main">
        <header class="dash-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 class="section-title" style="margin: 0;">Dashboard</h1>
            <div style="display: flex; gap: 10px;">
                <a href="reports.php" class="btn secondary-btn" style="background-color: blue; color: white;">Reports</a>
                <a href="admin_add_artist.php" class="btn secondary-btn">Add Artist</a>
            <a href="admin_add_vinyl.php" class="btn">Add Record</a>
        </div>
    </header>

    <form method="GET" action="admin_dashboard.php" class="search-container">
        <input type="text" name="search" class="search-input" placeholder="Search title or artist..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <button type="submit" class="search-btn">Search</button>
        <?php if (!empty($search)): ?>
            <a href="admin_dashboard.php" class="clear-btn">Clear</a>
        <?php endif; ?>
    </form>

    <section class="three-column-grid">
        <article class="card">
            <span class="card-title">Best Sellers</span>
            <table class="data-table">
                <?php
                $bestResult = mysqli_query($conn, "SELECT v.title, SUM(oi.quantity) as sold FROM Order_Items oi JOIN Vinyls v ON oi.vinyl_id = v.vinyl_id GROUP BY v.vinyl_id ORDER BY sold DESC LIMIT 3");
                if ($bestResult):
                    while ($r = mysqli_fetch_assoc($bestResult)): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['title']) ?></td>
                            <td class="val-cell text-right"><?= intval($r['sold']) ?> Sold</td>
                        </tr>
                    <?php endwhile;
                endif; ?>
            </table>
        </article>

        
        <article class="card">
            <span class="card-title">VIP Customers</span>
            <table class="data-table">
                <?php
        $vipResult = mysqli_query($conn, "
        SELECT 
        u.f_name, 
        SUM(o.total_price) as spent,
        fn_determine_user_tier(u.user_id) as user_tier
        FROM Users u 
        JOIN Orders o ON u.user_id = o.user_id 
        GROUP BY u.user_id 
        HAVING spent > 100 
        ORDER BY spent DESC 
        LIMIT 3
        ");
        
        if (!($vipResult == null)):
            while ($r = mysqli_fetch_assoc($vipResult)): ?>
                <tr>
                    <td><?= htmlspecialchars($r['f_name']) ?></td>
                    <td><span class="badge"><?= htmlspecialchars($r['user_tier']) ?></span></td>
                    <td class="val-cell text-right">$<?= number_format($r['spent'], 2) ?></td>
                </tr>
                <?php endwhile;
        endif; ?>
    </table>
</article>

<article class="card">
    <span class="card-title">Recent Orders</span>
    <div class="scrollable-card">
        <table class="data-table">
            <?php
                    $ordersResult = mysqli_query($conn, "SELECT o.order_id, o.total_price, u.f_name, ad.street, ad.city FROM Orders o JOIN Users u ON o.user_id = u.user_id JOIN Addresses ad ON o.address_id = ad.address_id ORDER BY o.order_id DESC LIMIT 10");
                    if ($ordersResult):
                        while ($order = mysqli_fetch_assoc($ordersResult)): ?>
                            <tr>
                                <td><strong>#<?= $order['order_id'] ?></strong> <?= htmlspecialchars($order['f_name']) ?></td>
                                <td><span class="address-sub"><?= htmlspecialchars($order['street'] . ', ' . $order['city']) ?></span></td>
                                <td class="val-cell text-right" style="color:#e65c00;">$<?= number_format($order['total_price'], 2) ?></td>
                            </tr>
                            <?php endwhile;
                    endif; ?>
                </table>
            </div>
        </article>
    </section>
    
    <section class="inventory-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Status</th>
                    <th>Price</th>
                    <th class="text-center">Management</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($inventory_list)): ?>
                    <?php foreach ($inventory_list as $v): ?>
                        <tr>
                            <td>
                                <span class="title-cell"><?= htmlspecialchars($v['title']) ?></span>
                                <span class="sub-cell"><?= htmlspecialchars($v['artist_name']) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $v['stock'] <= 4 ? 'badge-danger' : 'badge-success' ?>">
                                    <?= intval($v['stock']) ?> units
                                </span>
                            </td>
                            <td class="val-cell">$<?= number_format($v['price'], 2) ?></td>
                            <td>
                                <div class="action-group-centered">
                                    <a href="admin_edit_vinyl.php?id=<?= $v['vinyl_id'] ?>" class="edit-action">EDIT</a>
                                    <form action="admin_dashboard.php" method="POST" onsubmit="return confirm('Are you sure?');" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_vinyl">
                                        <input type="hidden" name="id" value="<?= $v['vinyl_id'] ?>">
                                        <button type="submit" class="delete-action" style="background: none; border: none; cursor: pointer; padding: 0;">DELETE</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center" style="padding: 20px; color: #aaa;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <?php if ($totalPages > 1): ?>
        <div class="pagination" style="text-align:center; margin: 20px 0;">
            <div class="page-numbers">
                <?php for ($i = 1; $i <= $totalPages; $i++): 
                    $searchUrlParam = !empty($search) ? "&search=" . urlencode(stripslashes($search)) : ""; ?>
                    <?php if ($i === $currentPage): ?>
                        <span class="page-btn active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="admin_dashboard.php?page=<?= $i . $searchUrlParam ?>" class="page-btn"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<footer>
    <p>&copy; 2026 Vinyl Store Admin System</p>
</footer>

</body>
</html>