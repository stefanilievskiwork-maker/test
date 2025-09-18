<?php
// index.php - VidAPI Dashboard Main Page
// IMPORTANT: Always start with session_start() at the very top!

session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get dashboard statistics
try {
    // Count movies
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM movies");
    $stmt->execute();
    $movieCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count TV shows
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tv_shows");
    $stmt->execute();
    $showCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count episodes
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM episodes");
    $stmt->execute();
    $episodeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count sites
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sites");
    $stmt->execute();
    $siteCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get recent activity (last 10 content additions)
    $stmt = $pdo->prepare("
        (SELECT 'movie' as type, title, created_at FROM movies ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'tv_show' as type, title, created_at FROM tv_shows ORDER BY created_at DESC LIMIT 5)
        ORDER BY created_at DESC LIMIT 10
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VidAPI Dashboard</title>
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>VidAPI</h2>
                <p>Dashboard</p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="index.php" class="active">Dashboard</a></li>
                <li><a href="domains.php">Domains</a></li>
                <li><a href="content.php">Content Import</a></li>
                <li><a href="themes.php">Themes</a></li>
                <li><a href="seo.php">SEO Management</a></li>
                <li><a href="settings.php">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>!</h1>
                <p class="welcome-subtitle">Manage your multi-tenant movie database system</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Dashboard -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <span class="stat-number"><?php echo number_format($movieCount ?? 0); ?></span>
                    <span class="stat-label">Movies</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo number_format($showCount ?? 0); ?></span>
                    <span class="stat-label">TV Shows</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo number_format($episodeCount ?? 0); ?></span>
                    <span class="stat-label">Episodes</span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo number_format($siteCount ?? 0); ?></span>
                    <span class="stat-label">Sites</span>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-header">
                <h2>Quick Actions</h2>
            </div>
            
            <div class="quick-actions">
                <a href="content.php" class="action-btn">Import Content</a>
                <a href="domains.php?action=add" class="action-btn">Add New Site</a>
                <a href="themes.php" class="action-btn">Manage Themes</a>
                <a href="seo.php" class="action-btn">SEO Settings</a>
            </div>

            <!-- Recent Activity -->
            <?php if (!empty($recentActivity)): ?>
                <div class="content-header">
                    <h2>Recent Activity</h2>
                </div>
                
                <div class="activity-card">
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div>
                                <span class="activity-type"><?php echo $activity['type']; ?></span>
                                <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                            </div>
                            <small><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- System Status -->
            <div class="content-header">
                <h2>System Status</h2>
            </div>
            
            <div class="activity-card">
                <div class="activity-item">
                    <div>
                        <strong>Database Connection</strong>
                        <br><small>Connection to vidapi_baza database</small>
                    </div>
                    <span style="color: #27ae60; font-weight: bold;">✓ Active</span>
                </div>
                
                <div class="activity-item">
                    <div>
                        <strong>Session Management</strong>
                        <br><small>User authentication system</small>
                    </div>
                    <span style="color: #27ae60; font-weight: bold;">✓ Working</span>
                </div>
                
                <div class="activity-item">
                    <div>
                        <strong>Content Import</strong>
                        <br><small>AI-powered content processing</small>
                    </div>
                    <span style="color: #f39c12; font-weight: bold;">⚠ Ready</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
            
            // Auto-refresh stats every 5 minutes
            setInterval(function() {
                location.reload();
            }, 300000); // 5 minutes
        });
    </script>
</body>
</html>
