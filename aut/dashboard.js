// Modern Dashboard JavaScript - js/dashboard.js

class ModernDashboard {
    constructor() {
        this.currentPage = 'dashboard';
        this.sidebarCollapsed = false;
        this.isMobile = window.innerWidth <= 768;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeComponents();
        this.handleResize();
        this.showWelcomeToast();
        
        // Hide loading screen after initialization
        setTimeout(() => {
            this.hideLoadingScreen();
        }, 1000);
    }

    bindEvents() {
        // Sidebar navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = link.dataset.page;
                this.navigateToPage(page);
            });
        });

        // Quick action cards
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', (e) => {
                const page = card.dataset.page;
                if (page) {
                    this.navigateToPage(page);
                }
            });
        });

        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            this.toggleSidebar();
        });

        // Mobile sidebar toggle
        document.getElementById('mobileSidebarToggle').addEventListener('click', () => {
            this.toggleMobileSidebar();
        });

        // User menu
        document.getElementById('userMenuBtn').addEventListener('click', () => {
            this.toggleUserMenu();
        });

        // Header actions
        document.getElementById('notificationsBtn').addEventListener('click', () => {
            this.showToast('Notifications feature coming soon!', 'info');
        });

        document.getElementById('settingsBtn').addEventListener('click', () => {
            this.showToast('Settings panel coming soon!', 'info');
        });

        // User dropdown items
        document.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const text = e.currentTarget.textContent.trim();
                this.handleDropdownAction(text);
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.user-info') && !e.target.closest('.user-dropdown')) {
                this.closeUserMenu();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            this.handleResize();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
    }

    initializeComponents() {
        // Animate counter numbers
        this.animateCounters();
        
        // Set initial page title
        this.updatePageTitle('Dashboard Overview');
        
        // Load user preferences
        this.loadUserPreferences();
    }

    hideLoadingScreen() {
        const loadingScreen = document.getElementById('loadingScreen');
        loadingScreen.classList.add('hide');
        
        setTimeout(() => {
            loadingScreen.style.display = 'none';
        }, 500);
    }

    navigateToPage(pageId) {
        // Update navigation
        this.setActiveNavLink(pageId);
        
        // Show page content
        this.showPageContent(pageId);
        
        // Update page title
        this.updatePageTitle(this.getPageTitle(pageId));
        
        // Update current page
        this.currentPage = pageId;
        
        // Save to localStorage
        localStorage.setItem('dashboard_current_page', pageId);
        
        // Close mobile sidebar if open
        if (this.isMobile) {
            this.closeMobileSidebar();
        }
    }

    setActiveNavLink(pageId) {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        const activeLink = document.querySelector(`[data-page="${pageId}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }

    showPageContent(pageId) {
        document.querySelectorAll('.page-content').forEach(page => {
            page.classList.remove('active');
        });
        
        const targetPage = document.getElementById(`${pageId}-page`);
        if (targetPage) {
            targetPage.classList.add('active');
        }
    }

    updatePageTitle(title) {
        const pageTitle = document.getElementById('pageTitle');
        if (pageTitle) {
            pageTitle.textContent = title;
        }
    }

    getPageTitle(pageId) {
        const titles = {
            dashboard: 'Dashboard Overview',
            domains: 'Domain Management',
            content: 'Content Import',
            ads: 'Ads & Analytics',
            seo: 'SEO Settings',
            themes: 'Theme Management'
        };
        
        return titles[pageId] || 'Dashboard';
    }

    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
        this.sidebarCollapsed = !this.sidebarCollapsed;
        
        // Save preference
        localStorage.setItem('dashboard_sidebar_collapsed', this.sidebarCollapsed);
    }

    toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('show');
    }

    closeMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.remove('show');
    }

    toggleUserMenu() {
        const userDropdown = document.getElementById('userDropdown');
        userDropdown.classList.toggle('show');
    }

    closeUserMenu() {
        const userDropdown = document.getElementById('userDropdown');
        userDropdown.classList.remove('show');
    }

    handleDropdownAction(action) {
        this.closeUserMenu();
        
        switch(action) {
            case 'Profile':
                this.showToast('Profile page coming soon!', 'info');
                break;
            case 'Settings':
                this.showToast('Settings page coming soon!', 'info');
                break;
            case 'Logout':
                this.handleLogout();
                break;
        }
    }

    handleLogout() {
        if (confirm('Are you sure you want to logout?')) {
            this.showToast('Logging out...', 'info');
            // Add logout logic here
            setTimeout(() => {
                window.location.href = '/login';
            }, 1500);
        }
    }

    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 768;
        
        // If switching from mobile to desktop, close mobile sidebar
        if (wasMobile && !this.isMobile) {
            this.closeMobileSidebar();
        }
        
        // If switching to mobile and sidebar is collapsed, uncollapse it
        if (!wasMobile && this.isMobile && this.sidebarCollapsed) {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.remove('collapsed');
            this.sidebarCollapsed = false;
        }
    }

    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + B to toggle sidebar
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            if (!this.isMobile) {
                this.toggleSidebar();
            }
        }
        
        // Escape to close modals/dropdowns
        if (e.key === 'Escape') {
            this.closeUserMenu();
        }
        
        // Number keys for quick navigation (1-6)
        if (e.key >= '1' && e.key <= '6' && !e.ctrlKey && !e.metaKey) {
            const pages = ['dashboard', 'domains', 'content', 'ads', 'seo', 'themes'];
            const pageIndex = parseInt(e.key) - 1;
            if (pages[pageIndex]) {
                this.navigateToPage(pages[pageIndex]);
            }
        }
    }

    animateCounters() {
        const counters = document.querySelectorAll('.stat-number[data-count]');
        
        counters.forEach(counter => {
            const target = parseInt(counter.dataset.count);
            const duration = 2000; // 2 seconds
            const step = target / (duration / 16); // 60fps
            let current = 0;
            
            const updateCounter = () => {
                current += step;
                if (current < target) {
                    counter.textContent = Math.floor(current).toLocaleString();
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = target.toLocaleString();
                }
            };
            
            // Start animation after a small delay
            setTimeout(() => {
                updateCounter();
            }, 500);
        });
    }

    showWelcomeToast() {
        setTimeout(() => {
            this.showToast('Welcome to your Movie Database Dashboard!', 'success');
        }, 1500);
    }

    showToast(message, type = 'success', duration = 5000) {
        const toast = document.getElementById('toast');
        const messageElement = toast.querySelector('.toast-message p');
        const iconElement = toast.querySelector('.toast-icon i');
        
        // Update message
        messageElement.textContent = message;
        
        // Update icon based on type
        const icons = {
            success: 'fas fa-check-circle',
            info: 'fas fa-info-circle',
            warning: 'fas fa-exclamation-triangle',
            error: 'fas fa-exclamation-circle'
        };
        
        const colors = {
            success: '#10b981',
            info: '#06b6d4',
            warning: '#f59e0b',
            error: '#ef4444'
        };
        
        iconElement.className = icons[type] || icons.success;
        toast.querySelector('.toast-icon').style.background = colors[type] || colors.success;
        
        // Show toast
        toast.classList.add('show');
        
        // Auto hide
        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    }

    loadUserPreferences() {
        // Load saved page
        const savedPage = localStorage.getItem('dashboard_current_page');
        if (savedPage && savedPage !== 'dashboard') {
            this.navigateToPage(savedPage);
        }
        
        // Load sidebar state
        const sidebarCollapsed = localStorage.getItem('dashboard_sidebar_collapsed');
        if (sidebarCollapsed === 'true' && !this.isMobile) {
            this.toggleSidebar();
        }
    }

    // Utility methods for future use
    showLoading(element) {
        if (element) {
            element.classList.add('loading');
        }
    }

    hideLoading(element) {
        if (element) {
            element.classList.remove('loading');
        }
    }

    formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    formatDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }

    // API simulation methods (for future real API integration)
    async fetchDashboardStats() {
        // Simulate API call
        return new Promise(resolve => {
            setTimeout(() => {
                resolve({
                    domains: 5,
                    movies: 12456,
                    tvShows: 3287,
                    pendingImports: 234
                });
            }, 1000);
        });
    }

    async fetchRecentActivity() {
        // Simulate API call
        return new Promise(resolve => {
            setTimeout(() => {
                resolve([
                    {
                        type: 'domain_added',
                        message: 'New domain added: moviehub.com',
                        time: new Date(Date.now() - 2 * 60 * 60 * 1000),
                        icon: 'plus',
                        status: 'success'
                    },
                    {
                        type: 'import_completed',
                        message: 'TMDB import completed: 150 movies imported',
                        time: new Date(Date.now() - 4 * 60 * 60 * 1000),
                        icon: 'download',
                        status: 'info'
                    }
                ]);
            }, 800);
        });
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new ModernDashboard();
});

// Export for potential module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModernDashboard;
}
