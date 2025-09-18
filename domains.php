<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('/login');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    try {
        $db = getDB();
        
        switch ($action) {
            case 'add_domain':
                $domain = sanitizeInput($_POST['domain'] ?? '');
                $name = sanitizeInput($_POST['name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $theme_id = (int)($_POST['theme_id'] ?? 1);
                $status = sanitizeInput($_POST['status'] ?? 'active');
                
                // Validation
                if (empty($domain) || empty($name)) {
                    throw new Exception('Domain and site name are required');
                }
                
                if (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid domain format');
                }
                
                // Check if domain exists
                $stmt = $db->prepare("SELECT id FROM sites WHERE domain = ?");
                $stmt->execute([$domain]);
                if ($stmt->fetch()) {
                    throw new Exception('Domain already exists');
                }
                
                // Insert domain
                $stmt = $db->prepare("
                    INSERT INTO sites (domain, name, description, theme_id, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$domain, $name, $description, $theme_id, $status]);
                $site_id = $db->lastInsertId();
                
                // Create default ad zones for the site
                $defaultZones = [
                    ['header', 'Header Banner', 'Top of page banner ad', '728x90'],
                    ['sidebar_top', 'Sidebar Top', 'Top of sidebar', '300x250'],
                    ['sidebar_bottom', 'Sidebar Bottom', 'Bottom of sidebar', '300x250'],
                    ['footer', 'Footer Banner', 'Footer banner ad', '728x90'],
                    ['mobile_banner', 'Mobile Banner', 'Mobile responsive banner', '320x50'],
                    ['content_top', 'Content Top', 'Above content area', '728x90'],
                    ['content_bottom', 'Content Bottom', 'Below content area', '728x90'],
                    ['in_content', 'In-Content', 'Within content area', '300x250']
                ];
                
                $zoneStmt = $db->prepare("
                    INSERT INTO ad_zones (site_id, zone_key, name, description, dimensions) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($defaultZones as $zone) {
                    $zoneStmt->execute([$site_id, $zone[0], $zone[1], $zone[2], $zone[3]]);
                }
                
                // Create default site meta settings
                $metaStmt = $db->prepare("
                    INSERT INTO site_meta_settings (site_id, site_name, site_description) 
                    VALUES (?, ?, ?)
                ");
                $metaStmt->execute([$site_id, $name, $description]);
                
                // Log activity
                logActivity(getCurrentUserId(), 'domain_added', "Added domain: $domain");
                
                $response['success'] = true;
                $response['message'] = 'Domain added successfully';
                $response['domain'] = [
                    'id' => $site_id,
                    'domain' => $domain,
                    'name' => $name,
                    'description' => $description,
                    'status' => $status,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'update_domain':
                $id = (int)($_POST['id'] ?? 0);
                $domain = sanitizeInput($_POST['domain'] ?? '');
                $name = sanitizeInput($_POST['name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $theme_id = (int)($_POST['theme_id'] ?? 1);
                $status = sanitizeInput($_POST['status'] ?? 'active');
                
                if (!$id || empty($domain) || empty($name)) {
                    throw new Exception('Invalid data provided');
                }
                
                // Check if domain exists (excluding current)
                $stmt = $db->prepare("SELECT id FROM sites WHERE domain = ? AND id != ?");
                $stmt->execute([$domain, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Domain already exists');
                }
                
                // Update domain
                $stmt = $db->prepare("
                    UPDATE sites 
                    SET domain = ?, name = ?, description = ?, theme_id = ?, status = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$domain, $name, $description, $theme_id, $status, $id]);
                
                // Update site meta settings
                $stmt = $db->prepare("
                    UPDATE site_meta_settings 
                    SET site_name = ?, site_description = ? 
                    WHERE site_id = ?
                ");
                $stmt->execute([$name, $description, $id]);
                
                logActivity(getCurrentUserId(), 'domain_updated', "Updated domain: $domain");
                
                $response['success'] = true;
                $response['message'] = 'Domain updated successfully';
                break;
                
            case 'delete_domain':
                $id = (int)($_POST['id'] ?? 0);
                
                if (!$id) {
                    throw new Exception('Invalid domain ID');
                }
                
                // Get domain info for logging
                $stmt = $db->prepare("SELECT domain FROM sites WHERE id = ?");
                $stmt->execute([$id]);
                $domain = $stmt->fetchColumn();
                
                if (!$domain) {
                    throw new Exception('Domain not found');
                }
                
                // Delete domain (cascade will handle related records)
                $stmt = $db->prepare("DELETE FROM sites WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity(getCurrentUserId(), 'domain_deleted', "Deleted domain: $domain");
                
                $response['success'] = true;
                $response['message'] = 'Domain deleted successfully';
                break;
                
            case 'get_domain':
                $id = (int)($_POST['id'] ?? 0);
                
                if (!$id) {
                    throw new Exception('Invalid domain ID');
                }
                
                $stmt = $db->prepare("
                    SELECT s.*, t.name as theme_name,
                           (SELECT COUNT(*) FROM movies WHERE site_id = s.id) as movies_count,
                           (SELECT COUNT(*) FROM tv_shows WHERE site_id = s.id) as shows_count
                    FROM sites s 
                    LEFT JOIN themes t ON s.theme_id = t.id 
                    WHERE s.id = ?
                ");
                $stmt->execute([$id]);
                $domain = $stmt->fetch();
                
                if (!$domain) {
                    throw new Exception('Domain not found');
                }
                
                $response['success'] = true;
                $response['domain'] = $domain;
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        if (DEBUG) {
            $response['debug'] = $e->getTraceAsString();
        }
    }
    
    echo json_encode($response);
    exit;
}

// Get domains for display
try {
    $db = getDB();
    
    // Get domains with stats
    $stmt = $db->prepare("
        SELECT s.*, t.name as theme_name,
               (SELECT COUNT(*) FROM movies WHERE site_id = s.id) as movies_count,
               (SELECT COUNT(*) FROM tv_shows WHERE site_id = s.id) as shows_count
        FROM sites s 
        LEFT JOIN themes t ON s.theme_id = t.id 
        ORDER BY s.created_at DESC
    ");
    $stmt->execute();
    $domains = $stmt->fetchAll();
    
    // Get available themes
    $stmt = $db->prepare("SELECT * FROM themes WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $themes = $stmt->fetchAll();
    
} catch (Exception $e) {
    $domains = [];
    $themes = [];
    if (DEBUG) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Management - <?= SITE_NAME ?></title>
    <meta name="description" content="Manage your movie database domains">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Styles -->
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/domains.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
</head>
<body>
    <!-- Include dashboard sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i class="fas fa-globe"></i> Domain Management</h1>
                    <p class="header-subtitle">Manage your movie database websites</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" id="addDomainBtn">
                        <i class="fas fa-plus"></i>
                        <span>Add New Domain</span>
                    </button>
                </div>
            </div>
        </header>
        
        <!-- Stats Summary -->
        <div class="stats-summary">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-globe-americas"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= count($domains) ?></span>
                    <span class="stat-label">Total Domains</span>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= count(array_filter($domains, fn($d) => $d['status'] === 'active')) ?></span>
                    <span class="stat-label">Active Domains</span>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-film"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= array_sum(array_column($domains, 'movies_count')) ?></span>
                    <span class="stat-label">Total Movies</span>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-tv"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= array_sum(array_column($domains, 'shows_count')) ?></span>
                    <span class="stat-label">Total TV Shows</span>
                </div>
            </div>
        </div>
        
        <!-- Domains Grid -->
        <div class="domains-section">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Error loading domains: <?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="domains-grid" id="domainsGrid">
                <?php if (empty($domains)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-globe-americas"></i>
                        </div>
                        <h3>No domains yet</h3>
                        <p>Add your first domain to start building movie database websites</p>
                        <button class="btn btn-primary" onclick="openAddDomainModal()">
                            <i class="fas fa-plus"></i>
                            Add Your First Domain
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($domains as $domain): ?>
                        <div class="domain-card" data-domain-id="<?= $domain['id'] ?>">
                            <div class="domain-header">
                                <div class="domain-info">
                                    <h3 class="domain-name"><?= htmlspecialchars($domain['domain']) ?></h3>
                                    <p class="site-name"><?= htmlspecialchars($domain['name']) ?></p>
                                    <?php if ($domain['description']): ?>
                                        <p class="domain-description"><?= htmlspecialchars($domain['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="domain-status">
                                    <span class="status-badge status-<?= $domain['status'] ?>">
                                        <?= ucfirst($domain['status']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="domain-stats">
                                <div class="stat">
                                    <i class="fas fa-palette"></i>
                                    <span><?= htmlspecialchars($domain['theme_name'] ?? 'Default') ?></span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-film"></i>
                                    <span><?= number_format($domain['movies_count']) ?> movies</span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-tv"></i>
                                    <span><?= number_format($domain['shows_count']) ?> shows</span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= date('M j, Y', strtotime($domain['created_at'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="domain-actions">
                                <a href="http://<?= htmlspecialchars($domain['domain']) ?>" 
                                   target="_blank" 
                                   rel="noopener"
                                   class="btn btn-secondary btn-sm">
                                    <i class="fas fa-external-link-alt"></i>
                                    <span>Visit Site</span>
                                </a>
                                <button class="btn btn-primary btn-sm" 
                                        onclick="editDomain(<?= $domain['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                    <span>Edit</span>
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deleteDomain(<?= $domain['id'] ?>, '<?= htmlspecialchars($domain['domain']) ?>')">
                                    <i class="fas fa-trash-alt"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Add/Edit Domain Modal -->
    <div class="modal" id="domainModal">
        <div class="modal-backdrop" onclick="closeDomainModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Domain</h2>
                <button class="modal-close" onclick="closeDomainModal()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="domainForm" class="modal-body">
                <input type="hidden" id="domainId" name="id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="domain">
                            <i class="fas fa-globe"></i>
                            Domain Name *
                        </label>
                        <input type="text" 
                               id="domain" 
                               name="domain" 
                               class="form-control" 
                               placeholder="example.com"
                               required>
                        <small class="form-help">Enter domain without http:// or www</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="siteName">
                            <i class="fas fa-tag"></i>
                            Site Name *
                        </label>
                        <input type="text" 
                               id="siteName" 
                               name="name" 
                               class="form-control" 
                               placeholder="My Movie Database"
                               required>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label for="siteDescription">
                            <i class="fas fa-align-left"></i>
                            Site Description
                        </label>
                        <textarea id="siteDescription" 
                                  name="description" 
                                  class="form-control" 
                                  rows="3"
                                  placeholder="Brief description of your movie database site"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="themeSelect">
                            <i class="fas fa-palette"></i>
                            Theme
                        </label>
                        <select id="themeSelect" name="theme_id" class="form-control">
                            <?php foreach ($themes as $theme): ?>
                                <option value="<?= $theme['id'] ?>"><?= htmlspecialchars($theme['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="statusSelect">
                            <i class="fas fa-toggle-on"></i>
                            Status
                        </label>
                        <select id="statusSelect" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </form>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDomainModal()">
                    <span>Cancel</span>
                </button>
                <button type="submit" form="domainForm" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i>
                    <span>Save Domain</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Processing...</p>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <div class="toast-content">
            <div class="toast-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="toast-message"></div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-backdrop" onclick="closeConfirmModal()"></div>
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Confirm Action</h3>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">Are you sure you want to delete this domain?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>This action cannot be undone. All associated data will be permanently deleted.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                <button class="btn btn-danger" id="confirmBtn">Delete</button>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="js/domains.js"></script>
</body>
</html>
