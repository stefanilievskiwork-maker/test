<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-film"></i>
            <span class="logo-text">MovieDB</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index" class="nav-link <?= $current_page === 'index' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="domains" class="nav-link <?= $current_page === 'domains' ? 'active' : '' ?>">
                    <i class="fas fa-globe"></i>
                    <span class="nav-text">Domains</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="content" class="nav-link <?= $current_page === 'content' ? 'active' : '' ?>">
                    <i class="fas fa-database"></i>
                    <span class="nav-text">Content Import</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="ads" class="nav-link <?= $current_page === 'ads' ? 'active' : '' ?>">
                    <i class="fas fa-ad"></i>
                    <span class="nav-text">Ads & Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="seo" class="nav-link <?= $current_page === 'seo' ? 'active' : '' ?>">
                    <i class="fas fa-search-plus"></i>
                    <span class="nav-text">SEO Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="themes" class="nav-link <?= $current_page === 'themes' ? 'active' : '' ?>">
                    <i class="fas fa-palette"></i>
                    <span class="nav-text">Themes</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info" onclick="toggleUserMenu()">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                <span class="user-role"><?= ucfirst($_SESSION['user_role'] ?? 'Administrator') ?></span>
            </div>
            <button class="user-menu-btn" id="userMenuBtn">
                <i class="fas fa-chevron-up"></i>
            </button>
        </div>
        
        <div class="user-dropdown" id="userDropdown">
            <a href="profile" class="dropdown-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="settings" class="dropdown-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <div class="dropdown-divider"></div>
            <a href="logout" class="dropdown-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</aside>

<script>
// Simple user menu toggle
function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    const userInfo = e.target.closest('.user-info');
    const dropdown = document.getElementById('userDropdown');
    
    if (!userInfo && dropdown) {
        dropdown.classList.remove('show');
    }
});
</script>
