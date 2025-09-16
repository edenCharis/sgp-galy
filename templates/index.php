<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Système de Gestion</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ecfdf5 0%, #f0fdfa 50%, #ecfeff 100%);
            min-height: 100vh;
        }

        /* Header Styles */
        .header {
            position: sticky;
            top: 0;
            z-index: 50;
            width: 100%;
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
        }

        .header-content {
            display: flex;
            height: 4rem;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .menu-toggle {
            display: none;
            padding: 0.5rem;
            background: transparent;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .menu-toggle:hover {
            background: #f3f4f6;
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo {
            width: 2rem;
            height: 2rem;
            background: linear-gradient(135deg, #059669, #0d9488);
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .page-info h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
        }

        .page-info p {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .search-container {
            display: none;
            flex: 1;
            max-width: 28rem;
            margin: 0 2rem;
            position: relative;
        }

        @media (min-width: 768px) {
            .search-container {
                display: block;
            }
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 0.75rem 0.75rem 2.5rem;
            background: rgba(249, 250, 251, 0.5);
            border: 1px solid rgba(229, 231, 235, 0.5);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            background: white;
            border-color: #059669;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            width: 1rem;
            height: 1rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-btn {
            padding: 0.5rem;
            background: transparent;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-btn:hover {
            background: #f3f4f6;
        }

        .notification-badge {
            position: absolute;
            top: -0.25rem;
            right: -0.25rem;
            background: #dc2626;
            color: white;
            font-size: 0.75rem;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            background: transparent;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .user-menu:hover {
            background: #f3f4f6;
        }

        .avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: none;
        }

        @media (min-width: 1024px) {
            .user-info {
                display: block;
                text-align: left;
            }

            .user-info .name {
                font-size: 0.875rem;
                font-weight: 500;
                color: #111827;
            }

            .user-info .role {
                font-size: 0.75rem;
                color: #6b7280;
                text-transform: capitalize;
            }
        }

        /* Sidebar Styles */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(4px);
            z-index: 40;
        }

        .sidebar-overlay.show {
            display: block;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 16rem;
            background: linear-gradient(180deg, white 0%, rgba(249, 250, 251, 0.3) 100%);
            border-right: 1px solid rgba(229, 231, 235, 0.5);
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            z-index: 50;
            overflow-y: auto;
        }

        .sidebar.show {
            transform: translateX(0);
        }

        @media (min-width: 768px) {
            .sidebar {
                position: relative;
                transform: translateX(0);
            }
        }

        .sidebar-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
            padding: 0.75rem;
            border-radius: 0.5rem;
            transition: background 0.2s;
        }

        .sidebar-brand:hover {
            background: rgba(249, 250, 251, 0.5);
        }

        .brand-logo {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, #059669, #0d9488);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            transition: transform 0.2s;
        }

        .sidebar-brand:hover .brand-logo {
            transform: scale(1.05);
        }

        .brand-text {
            background: linear-gradient(90deg, #2563eb, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.125rem;
            font-weight: 600;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 400;
        }

        .sidebar-close {
            display: flex;
            padding: 0.5rem;
            background: transparent;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .sidebar-close:hover {
            background: #f3f4f6;
        }

        @media (min-width: 768px) {
            .sidebar-close {
                display: none;
            }
        }

        .sidebar-content {
            padding: 1rem 0;
        }

        .sidebar-group {
            margin-bottom: 1rem;
        }

        .sidebar-menu {
            padding: 0 0.5rem;
        }

        .sidebar-menu-item {
            margin-bottom: 0.25rem;
        }

        .sidebar-menu-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: #374151;
            border-radius: 0.5rem;
            transition: all 0.2s;
            position: relative;
        }

        .sidebar-menu-link:hover {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .sidebar-menu-link.active {
            background: #dbeafe;
            color: #1d4ed8;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-right: 2px solid #2563eb;
        }

        .sidebar-menu-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #2563eb;
            border-radius: 0 2px 2px 0;
        }

        .menu-icon {
            width: 1.25rem;
            height: 1.25rem;
            transition: transform 0.2s;
        }

        .sidebar-menu-link:hover .menu-icon {
            transform: scale(1.1);
        }

        .menu-content {
            flex: 1;
        }

        .menu-title {
            font-weight: 500;
            font-size: 0.875rem;
        }

        .menu-description {
            font-size: 0.75rem;
            color: #6b7280;
            transition: color 0.2s;
        }

        .sidebar-menu-link:hover .menu-description {
            color: #1d4ed8;
        }

        .menu-badge {
            background: #dc2626;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.375rem;
            border-radius: 9999px;
            font-weight: 500;
        }

        .menu-badge.info {
            background: #2563eb;
        }

        .sidebar-separator {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
            margin: 1rem 0;
        }

        /* Main Layout */
        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .content-area {
            flex: 1;
            padding: 1.5rem;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 2fr 1fr;
            }
        }

        /* Card Styles */
        .card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 0.5rem;
            border: 1px solid #a7f3d0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(90deg, #059669 0%, #0d9488 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        /* Utility Classes */
        .hidden { display: none; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-3 { gap: 0.75rem; }
        .text-sm { font-size: 0.875rem; }
        .font-medium { font-weight: 500; }
        .text-gray-500 { color: #6b7280; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <div class="flex items-center justify-between">
                    <a href="/" class="sidebar-brand">
                        <div class="brand-logo">
                            <i data-lucide="cross"></i>
                        </div>
                        <div>
                            <div class="brand-text">PharmaSys</div>
                            <div class="brand-subtitle">Gestion Pharmacie</div>
                        </div>
                    </a>
                    <button id="sidebarClose" class="sidebar-close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            </div>

            <div class="sidebar-content">
                <!-- Main Navigation -->
                <div class="sidebar-group">
                    <div class="sidebar-menu">
                        <div class="sidebar-menu-item">
                            <a href="/dashboard" class="sidebar-menu-link active" data-page="dashboard">
                                <i data-lucide="home" class="menu-icon"></i>
                                <div class="menu-content">
                                    <div class="menu-title">Tableau de bord</div>
                                    <div class="menu-description">Vue d'ensemble</div>
                                </div>
                            </a>
                        </div>
                        <div class="sidebar-menu-item">
                            <a href="/new-sale" class="sidebar-menu-link" data-page="new-sale">
                                <i data-lucide="shopping-cart" class="menu-icon"></i>
                                <div class="menu-content">
                                    <div class="menu-title">Nouvelle vente</div>
                                    <div class="menu-description">Créer une vente</div>
                                </div>
                                <div class="menu-badge info">Rapide</div>
                            </a>
                        </div>
                        <div class="sidebar-menu-item">
                            <a href="/sales-history" class="sidebar-menu-link" data-page="sales-history">
                                <i data-lucide="history" class="menu-icon"></i>
                                <div class="menu-content">
                                    <div class="menu-title">Historique des ventes</div>
                                    <div class="menu-description">Toutes les ventes</div>
                                </div>
                            </a>
                        </div>
                        <div class="sidebar-menu-item">
                            <a href="/products" class="sidebar-menu-link" data-page="products">
                                <i data-lucide="package" class="menu-icon"></i>
                                <div class="menu-content">
                                    <div class="menu-title">Produits</div>
                                    <div class="menu-description">Gestion stock</div>
                                </div>
                                <div class="menu-badge">7</div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="sidebar-separator"></div>

                <!-- Analytics -->
                <div class="sidebar-group">
                    <div class="sidebar-menu">
                        <div class="sidebar-menu-item">
                            <a href="/reports" class="sidebar-menu-link" data-page="reports">
                                <i data-lucide="bar-chart" class="menu-icon"></i>
                                <div class="menu-content">
                                    <div class="menu-title">Rapports</div>
                                    <div class="menu-description">Analytics détaillés</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="sidebar-separator"></div>

                <!-- Settings -->
                <div class="sidebar-group">
                    <div class="sidebar-menu">
                        <div class="sidebar-menu-item">
                            <a href="/settings" class="sidebar-menu-link" data-page="settings">
                                <i data-lucide="settings" class="menu-icon"></i>
                                <div class="menu-content">
                                    <div class="menu-title">Paramètres</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
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
                    <div class="search-container">
                        <input type="text" class="search-input" placeholder="Rechercher produits, clients, commandes...">
                        <i data-lucide="search" class="search-icon"></i>
                    </div>

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
                            <div class="avatar">U</div>
                            <div class="user-info">
                                <div class="name">Utilisateur</div>
                                <div class="role">Vendeur</div>
                            </div>
                            <i data-lucide="chevron-down"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="content-area">
                <div id="dashboardContent" class="dashboard-grid">
                    <!-- This will be populated by JavaScript based on the current page -->
                </div>
            </main>
        </div>
    </div>

    <script>
        // Global state
        const app = {
            currentPage: 'dashboard',
            user: {
                name: 'Utilisateur',
                role: 'vendeur'
            }
        };

        // Page configurations
        const pageConfig = {
            'dashboard': {
                title: 'Tableau de bord',
                description: 'Vue d\'ensemble de votre pharmacie'
            },
            'new-sale': {
                title: 'Nouvelle vente',
                description: 'Créer une nouvelle vente'
            },
            'sales-history': {
                title: 'Historique des ventes',
                description: 'Historique de toutes vos ventes'
            },
            'products': {
                title: 'Produits',
                description: 'Gestion de votre inventaire'
            },
            'reports': {
                title: 'Rapports',
                description: 'Analytics et rapports détaillés'
            },
            'settings': {
                title: 'Paramètres',
                description: 'Paramètres et configuration'
            }
        };

        // Set favicon
        function setFavicon() {
            const svgData = `
                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <path d="M60 20 L140 20 L140 60 L180 60 L180 140 L140 140 L140 180 L60 180 L60 140 L20 140 L20 60 L60 60 Z" fill="#059669"/>
                    <path d="M75 35 L125 35 L125 75 L165 75 L165 125 L125 125 L125 165 L75 165 L75 125 L35 125 L35 75 L75 75 Z" fill="white"/>
                    <g fill="#059669">
                        <rect x="97" y="50" width="6" height="100"/>
                        <rect x="50" y="97" width="100" height="6"/>
                    </g>
                </svg>
            `;
            
            const favicon = `data:image/svg+xml;base64,${btoa(svgData)}`;
            
            const existingFavicon = document.querySelector('link[rel="icon"]') || document.querySelector('link[rel="shortcut icon"]');
            if (existingFavicon) {
                existingFavicon.remove();
            }
            
            const link = document.createElement('link');
            link.rel = 'icon';
            link.type = 'image/svg+xml';
            link.href = favicon;
            document.head.appendChild(link);
        }

        // Sidebar functionality
        function setupSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            const sidebarClose = document.getElementById('sidebarClose');

            function showSidebar() {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            }

            function hideSidebar() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }

            menuToggle.addEventListener('click', showSidebar);
            sidebarClose.addEventListener('click', hideSidebar);
            overlay.addEventListener('click', hideSidebar);

            // Navigation
            const navLinks = document.querySelectorAll('.sidebar-menu-link');
            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = link.dataset.page;
                    if (page) {
                        navigateToPage(page);
                        if (window.innerWidth < 768) {
                            hideSidebar();
                        }
                    }
                });
            });
        }

        // Navigation
        function navigateToPage(page) {
            app.currentPage = page;
            
            // Update active link
            document.querySelectorAll('.sidebar-menu-link').forEach(link => {
                link.classList.remove('active');
            });
            
            const activeLink = document.querySelector(`[data-page="${page}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }

            // Update page title and description
            const config = pageConfig[page];
            if (config) {
                document.getElementById('pageTitle').textContent = config.title;
                document.getElementById('pageDescription').textContent = config.description;
                document.title = `PharmaSys - ${config.title}`;
            }

            // Load page content
            loadPageContent(page);
        }

        // Load page content
        function loadPageContent(page) {
            const contentArea = document.getElementById('dashboardContent');
            
            switch (page) {
                case 'dashboard':
                    contentArea.innerHTML = `
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i data-lucide="home"></i>
                                    Tableau de bord
                                </div>
                            </div>
                            <div class="card-content">
                                <p>Bienvenue sur votre tableau de bord PharmaSys!</p>
                                <p>Utilisez le menu de gauche pour naviguer entre les différentes sections.</p>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'new-sale':
                    // Load the seller dashboard content here
                    window.location.href = 'seller-dashboard.html';
                    return;
                    
                default:
                    contentArea.innerHTML = `
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i data-lucide="construction"></i>
                                    Page en construction
                                </div>
                            </div>
                            <div class="card-content">
                                <p>Cette page est en cours de développement.</p>
                                <p>Page demandée: <strong>${page}</strong></p>
                            </div>
                        </div>
                    `;
            }
            
            // Re-initialize Lucide icons
            lucide.createIcons();
        }

        // Initialize app
        function initApp() {
            setFavicon();
            setupSidebar();
            
            // Load initial page
            loadPageContent(app.currentPage);
            
            // Initialize Lucide icons
            lucide.createIcons();
        }

        // Start the app when DOM is loaded
        document.addEventListener('DOMContentLoaded', initApp);
    </script>
</body>
</html>