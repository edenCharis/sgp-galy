
<header class="header">
    <div class="header-content">
        <!-- Left Section -->
        <div class="header-left">
            <button id="menuToggle" class="menu-toggle">
                <i data-lucide="menu"></i>
            </button>
            
            <div class="logo-section">
                <div class="logo">
                    <i data-lucide="cross"></i>
                </div>
                <div class="page-info">
                    <h1 id="pageTitle">Tableau de bord</h1>
                    <p id="pageDescription">Vue d'ensemble de votre pharmacie</p>
                </div>
            </div>
        </div>

        <!-- Center Search -->
     
        <!-- Right Section -->
        <div class="header-right">
            <!-- Mobile Search -->
            <button class="header-btn" style="display: none;">
                <i data-lucide="search"></i>
            </button>

            <!-- Notifications -->
            <button class="header-btn">
                <i data-lucide="bell"></i>
                <div class="notification-badge">3</div>
            </button>

            <!-- Theme Toggle -->
            <button class="header-btn">
                <i data-lucide="sun"></i>
            </button>

            <!-- User Menu -->
            <button class="user-menu" id="userMenuToggle">
                <div class="avatar"><?php echo strtoupper($_SESSION["username"][0]);?></div>
                <div class="user-info">
                    <div class="name"><?php echo $_SESSION["username"];?></div>
                    <div class="role"><?php echo $_SESSION["role"];?></div>
                </div>
                <i data-lucide="chevron-down"></i>
            </button>
        </div>
    </div>
</header>