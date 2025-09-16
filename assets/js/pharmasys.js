// PharmaSys - Main JavaScript Module
class PharmaSys {
    constructor() {
        this.app = {
            currentPage: 'dashboard',
            user: {
                name: 'Utilisateur',
                role: 'vendeur'
            }
        };

        this.pageConfig = {
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

        this.init();
    }

    // Initialize the application
    init() {
        this.setFavicon();
        this.setupSidebar();
        this.loadPageContent(this.app.currentPage);
        this.initializeLucideIcons();
    }

    // Set favicon
    setFavicon() {
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

    // Setup sidebar functionality
    setupSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('menuToggle');
        const sidebarClose = document.getElementById('sidebarClose');

        if (!sidebar || !overlay || !menuToggle || !sidebarClose) {
            console.warn('Sidebar elements not found. Make sure to include the sidebar component.');
            return;
        }

        const showSidebar = () => {
            sidebar.classList.add('show');
            overlay.classList.add('show');
        };

        const hideSidebar = () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        };

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
                    this.navigateToPage(page);
                    if (window.innerWidth < 768) {
                        hideSidebar();
                    }
                }
            });
        });
    }

    // Navigate to a specific page
    navigateToPage(page) {
        this.app.currentPage = page;
        
        // Update active link
        document.querySelectorAll('.sidebar-menu-link').forEach(link => {
            link.classList.remove('active');
        });
        
        const activeLink = document.querySelector(`[data-page="${page}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }

        // Update page title and description
        this.updatePageInfo(page);

        // Load page content
        this.loadPageContent(page);
    }

    // Update page information in header
    updatePageInfo(page) {
        const config = this.pageConfig[page];
        if (!config) return;

        const titleElement = document.getElementById('pageTitle');
        const descriptionElement = document.getElementById('pageDescription');

        if (titleElement) titleElement.textContent = config.title;
        if (descriptionElement) descriptionElement.textContent = config.description;
        
        document.title = `PharmaSys - ${config.title}`;
    }

    // Load page content (to be extended by individual pages)
    loadPageContent(page) {
        const contentArea = document.getElementById('dashboardContent') || document.getElementById('pageContent');
        if (!contentArea) return;
        
        switch (page) {
            case 'dashboard':
                contentArea.innerHTML = this.getDashboardContent();
                break;
                
            case 'new-sale':
                // Redirect to seller dashboard or load inline content
                if (typeof this.loadNewSaleContent === 'function') {
                    this.loadNewSaleContent(contentArea);
                } else {
                    window.location.href = 'seller-dashboard.html';
                    return;
                }
                break;
                
            default:
                contentArea.innerHTML = this.getDefaultContent(page);
        }
        
        // Re-initialize Lucide icons
        this.initializeLucideIcons();
    }

    // Get dashboard content
    getDashboardContent() {
        return `
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
    }

    // Get default content for unimplemented pages
    getDefaultContent(page) {
        return `
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

    // Initialize Lucide icons
    initializeLucideIcons() {
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    }

    // Update user information
    updateUser(userData) {
        this.app.user = { ...this.app.user, ...userData };
        
        // Update UI elements
        const nameElement = document.querySelector('.user-info .name');
        const roleElement = document.querySelector('.user-info .role');
        const avatarElement = document.querySelector('.avatar');
        
        if (nameElement) nameElement.textContent = this.app.user.name;
        if (roleElement) roleElement.textContent = this.app.user.role;
        if (avatarElement) avatarElement.textContent = this.app.user.name.charAt(0).toUpperCase();
    }

    // Get current page
    getCurrentPage() {
        return this.app.currentPage;
    }

    // Get current user
    getCurrentUser() {
        return this.app.user;
    }

    // Add custom page handler
    addPageHandler(page, handler) {
        if (typeof handler === 'function') {
            this[`load${page.charAt(0).toUpperCase() + page.slice(1)}Content`] = handler;
        }
    }

    // Show notification (can be extended)
    showNotification(message, type = 'info') {
        console.log(`${type.toUpperCase()}: ${message}`);
        // This can be extended to show actual UI notifications
    }
}

// Initialize PharmaSys when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.pharmaSys = new PharmaSys();
});