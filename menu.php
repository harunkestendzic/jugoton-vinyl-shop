<input type="checkbox" id="menu-toggle">
<label for="menu-toggle" class="vinyl-handle">
    <img src="images/hamburger.png" alt="menu icon">
</label>

<div class="vinyl-menu">
    <h2>MENU</h2>
    <a href="index.php">HOME</a>
    <a href="new_arrivals.php">NEW ARRIVALS</a>
    <a href="genres.php">GENRES</a>
</div>

<nav class="site-navbar">
    <div class="logo">
        <a href="index.php">JUGOTON</a>
    </div>
    
    <div class="nav-right-section">
        <div class="cart-icon-wrapper" onclick="document.getElementById('cart-sidebar').classList.add('open'); document.getElementById('cart-overlay').classList.add('show');">
            <span class="cart-emoji">🛒</span>
        </div>

        <div class="nav-account">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-profile-group">
                    <a href="account.php" class="account-link">
                        <span class="user-emoji">👤</span>
                        <span class="user-name"><?= htmlspecialchars($_SESSION['f_name'] ?? '') ?></span>
                    </a>
                    <span class="nav-divider">|</span>
                    <a href="logout.php" class="logout-link">Logout</a>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-link">Login</a>
                <a href="register.php" class="register-link">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>