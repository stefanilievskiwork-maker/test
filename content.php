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
            case 'test_connection':
                // Use your database credentials
                $host = 'localhost';
                $database = 'vidapi_baza';
                $username = 'vidapi_user';
                $password = 'Suzukininja11@@';
                
                // Test connection to source database
                try {
                    $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
                    $source_db = new PDO($dsn, $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    
                    // Test tables and get counts
                    $counts = [];
                    
                    // Check movies table
                    $stmt = $source_db->prepare("SHOW TABLES LIKE 'movies'");
                    $stmt->execute();
                    if ($stmt->fetch()) {
                        $stmt = $source_db->prepare("SELECT COUNT(*) as total FROM movies");
                        $stmt->execute();
                        $counts['movies'] = $stmt->fetch()['total'];
                        
                        $stmt = $source_db->prepare("SELECT COUNT(*) as rewritten FROM movies WHERE plot_rewritten = 1");
                        $stmt->execute();
                        $counts['movies_rewritten'] = $stmt->fetch()['rewritten'];
                    } else {
                        $counts['movies'] = 0;
                        $counts['movies_rewritten'] = 0;
                    }
                    
                    // Check tv_shows table  
                    $stmt = $source_db->prepare("SHOW TABLES LIKE 'tv_shows'");
                    $stmt->execute();
                    if ($stmt->fetch()) {
                        $stmt = $source_db->prepare("SELECT COUNT(*) as total FROM tv_shows");
                        $stmt->execute();
                        $counts['tv_shows'] = $stmt->fetch()['total'];
                        
                        $stmt = $source_db->prepare("SELECT COUNT(*) as rewritten FROM tv_shows WHERE plot_rewritten = 1");
                        $stmt->execute();
                        $counts['tv_shows_rewritten'] = $stmt->fetch()['rewritten'];
                    } else {
                        $counts['tv_shows'] = 0;
                        $counts['tv_shows_rewritten'] = 0;
                    }
                    
                    // Check episodes table
                    $stmt = $source_db->prepare("SHOW TABLES LIKE 'episodes'");
                    $stmt->execute();
                    if ($stmt->fetch()) {
                        $stmt = $source_db->prepare("SELECT COUNT(*) as total FROM episodes");
                        $stmt->execute();
                        $counts['episodes'] = $stmt->fetch()['total'];
                        
                        $stmt = $source_db->prepare("SELECT COUNT(*) as rewritten FROM episodes WHERE plot_rewritten = 1");
                        $stmt->execute();
                        $counts['episodes_rewritten'] = $stmt->fetch()['rewritten'];
                    } else {
                        $counts['episodes'] = 0;
                        $counts['episodes_rewritten'] = 0;
                    }
                    
                    $total_items = $counts['movies'] + $counts['tv_shows'] + $counts['episodes'];
                    $total_rewritten = $counts['movies_rewritten'] + $counts['tv_shows_rewritten'] + $counts['episodes_rewritten'];
                    
                    $response['success'] = true;
                    $response['message'] = "Connection successful!";
                    $response['counts'] = $counts;
                    $response['total_items'] = $total_items;
                    $response['total_rewritten'] = $total_rewritten;
                    
                } catch (PDOException $e) {
                    throw new Exception('Database connection failed: ' . $e->getMessage());
                }
                break;
                
            case 'start_migration':
                $site_id = (int)($_POST['site_id'] ?? 0);
                $content_types = $_POST['content_types'] ?? [];
                $import_type = sanitizeInput($_POST['import_type'] ?? 'rewritten_only');
                $batch_size = min(100, max(10, (int)($_POST['batch_size'] ?? 50)));
                
                if (!$site_id) {
                    throw new Exception('Please select a site');
                }
                
                if (empty($content_types)) {
                    throw new Exception('Please select at least one content type');
                }
                
                // Connect to source database
                $dsn = "mysql:host=localhost;dbname=vidapi_baza;charset=utf8mb4";
                $source_db = new PDO($dsn, 'vidapi_user', 'Suzukininja11@@', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                
                // Calculate total items
                $total_items = 0;
                $where_clause = $import_type === 'rewritten_only' ? 'WHERE plot_rewritten = 1' : '';
                
                foreach ($content_types as $type) {
                    $count_stmt = $source_db->prepare("SELECT COUNT(*) as total FROM {$type} {$where_clause}");
                    $count_stmt->execute();
                    $total_items += $count_stmt->fetch()['total'];
                }
                
                if ($total_items == 0) {
                    throw new Exception('No content found matching the selected criteria');
                }
                
                // Create migration job record
                $job_stmt = $db->prepare("
                    INSERT INTO migration_jobs (site_id, total_items, status, import_type, content_types, created_at) 
                    VALUES (?, ?, 'pending', ?, ?, NOW())
                ");
                $job_stmt->execute([$site_id, $total_items, $import_type, implode(',', $content_types)]);
                $job_id = $db->lastInsertId();
                
                // Store connection info in session for batch processing
                $_SESSION['migration_connection'] = [
                    'host' => 'localhost',
                    'database' => 'vidapi_baza',
                    'username' => 'vidapi_user',
                    'password' => 'Suzukininja11@@'
                ];
                
                logActivity(getCurrentUserId(), 'migration_started', "Started migration job #{$job_id} for site #{$site_id}");
                
                $response['success'] = true;
                $response['message'] = "Migration started! Processing {$total_items} items...";
                $response['job_id'] = $job_id;
                $response['total_items'] = $total_items;
                break;
                
            case 'process_batch':
                $job_id = (int)($_POST['job_id'] ?? 0);
                $offset = (int)($_POST['offset'] ?? 0);
                $batch_size = min(100, max(10, (int)($_POST['batch_size'] ?? 50)));
                
                if (!$job_id) {
                    throw new Exception('Invalid job ID');
                }
                
                // Get job details
                $job_stmt = $db->prepare("SELECT * FROM migration_jobs WHERE id = ?");
                $job_stmt->execute([$job_id]);
                $job = $job_stmt->fetch();
                
                if (!$job) {
                    throw new Exception('Migration job not found');
                }
                
                // Update job status to processing
                if ($job['status'] === 'pending') {
                    $db->prepare("UPDATE migration_jobs SET status = 'processing', started_at = NOW() WHERE id = ?")
                       ->execute([$job_id]);
                }
                
                // Connect to source database
                $dsn = "mysql:host=localhost;dbname=vidapi_baza;charset=utf8mb4";
                $source_db = new PDO($dsn, 'vidapi_user', 'Suzukininja11@@', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                
                $content_types = explode(',', $job['content_types']);
                $where_clause = $job['import_type'] === 'rewritten_only' ? 'WHERE plot_rewritten = 1' : '';
                
                $imported_count = 0;
                $updated_count = 0;
                $skipped_count = 0;
                
                // Process each content type
                foreach ($content_types as $content_type) {
                    // Get batch of content
                    $content_stmt = $source_db->prepare("
                        SELECT * FROM {$content_type} 
                        {$where_clause}
                        ORDER BY id 
                        LIMIT {$batch_size} OFFSET {$offset}
                    ");
                    $content_stmt->execute();
                    $items = $content_stmt->fetchAll();
                    
                    foreach ($items as $item) {
                        try {
                            if ($content_type === 'movies') {
                                $result = $this->migrateMovie($db, $item, $job['site_id']);
                            } elseif ($content_type === 'tv_shows') {
                                $result = $this->migrateTVShow($db, $item, $job['site_id']);
                            } elseif ($content_type === 'episodes') {
                                $result = $this->migrateEpisode($db, $item, $job['site_id']);
                            }
                            
                            if ($result === 'imported') $imported_count++;
                            elseif ($result === 'updated') $updated_count++;
                            else $skipped_count++;
                            
                        } catch (Exception $e) {
                            $skipped_count++;
                            error_log("Migration error for {$content_type} ID {$item['id']}: " . $e->getMessage());
                        }
                    }
                }
                
                // Update job progress
                $processed_items = $offset + $batch_size;
                $progress = min(100, ($processed_items / $job['total_items']) * 100);
                
                $update_job_stmt = $db->prepare("
                    UPDATE migration_jobs SET 
                        processed_items = ?, 
                        imported_count = imported_count + ?, 
                        updated_count = updated_count + ?,
                        skipped_count = skipped_count + ?,
                        progress = ?
                    WHERE id = ?
                ");
                $update_job_stmt->execute([
                    $processed_items, $imported_count, $updated_count, $skipped_count, $progress, $job_id
                ]);
                
                // Check if migration is complete
                $is_complete = $processed_items >= $job['total_items'];
                if ($is_complete) {
                    $db->prepare("UPDATE migration_jobs SET status = 'completed', completed_at = NOW() WHERE id = ?")
                       ->execute([$job_id]);
                    
                    logActivity(getCurrentUserId(), 'migration_completed', "Migration job #{$job_id} completed");
                }
                
                $response['success'] = true;
                $response['message'] = "Processed batch: {$imported_count} imported, {$updated_count} updated, {$skipped_count} skipped";
                $response['imported'] = $imported_count;
                $response['updated'] = $updated_count;
                $response['skipped'] = $skipped_count;
                $response['progress'] = round($progress, 1);
                $response['is_complete'] = $is_complete;
                $response['next_offset'] = $processed_items;
                break;
                
            case 'get_sites':
                $stmt = $db->prepare("SELECT id, domain, name FROM sites WHERE status = 'active' ORDER BY name");
                $stmt->execute();
                $sites = $stmt->fetchAll();
                
                $response['success'] = true;
                $response['sites'] = $sites;
                break;
                
            case 'get_migration_jobs':
                $stmt = $db->prepare("
                    SELECT mj.*, s.domain, s.name as site_name 
                    FROM migration_jobs mj 
                    LEFT JOIN sites s ON mj.site_id = s.id 
                    ORDER BY mj.created_at DESC 
                    LIMIT 20
                ");
                $stmt->execute();
                $jobs = $stmt->fetchAll();
                
                $response['success'] = true;
                $response['jobs'] = $jobs;
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

// Migration helper methods
class MigrationHelper {
    
    public static function migrateMovie($db, $movie, $site_id) {
        // Check if movie already exists
        $check_stmt = $db->prepare("
            SELECT id FROM movies 
            WHERE site_id = ? AND tmdb_id = ?
        ");
        $check_stmt->execute([$site_id, $movie['tmdb_id']]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Update existing movie
            $update_stmt = $db->prepare("
                UPDATE movies SET 
                    title = ?, plot = ?, year = ?, poster_url = ?, backdrop_url = ?, 
                    actors = ?, country = ?, duration = ?, genre = ?, 
                    meta_title = ?, ai_summary = ?, ai_src_hash = ?, ai_updated_at = ?, 
                    plot_rewritten = ?, rewritten_at = ?, source = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $update_stmt->execute([
                $movie['title'], $movie['plot'], $movie['year'], 
                $movie['poster_url'], $movie['backdrop_url'], $movie['actors'],
                $movie['country'], $movie['duration'], $movie['genre'],
                $movie['meta_title'], $movie['ai_summary'], $movie['ai_src_hash'],
                $movie['ai_updated_at'], $movie['plot_rewritten'], $movie['rewritten_at'],
                $movie['source'] ?: 'migration', $existing['id']
            ]);
            
            return 'updated';
        } else {
            // Insert new movie
            $insert_stmt = $db->prepare("
                INSERT INTO movies (
                    site_id, tmdb_id, title, plot, year, poster_url, backdrop_url, 
                    actors, country, duration, genre, meta_title, ai_summary, ai_src_hash, 
                    ai_updated_at, plot_rewritten, rewritten_at, source, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $insert_stmt->execute([
                $site_id, $movie['tmdb_id'], $movie['title'], $movie['plot'], $movie['year'],
                $movie['poster_url'], $movie['backdrop_url'], $movie['actors'], $movie['country'],
                $movie['duration'], $movie['genre'], $movie['meta_title'], $movie['ai_summary'],
                $movie['ai_src_hash'], $movie['ai_updated_at'], $movie['plot_rewritten'],
                $movie['rewritten_at'], $movie['source'] ?: 'migration'
            ]);
            
            return 'imported';
        }
    }
    
    public static function migrateTVShow($db, $show, $site_id) {
        // Check if TV show already exists
        $check_stmt = $db->prepare("
            SELECT id FROM tv_shows 
            WHERE site_id = ? AND tmdb_id = ?
        ");
        $check_stmt->execute([$site_id, $show['tmdb_id']]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Update existing TV show
            $update_stmt = $db->prepare("
                UPDATE tv_shows SET 
                    name = ?, overview = ?, first_air_date = ?, poster_url = ?, backdrop_url = ?, 
                    genre = ?, country = ?, original_language = ?, 
                    ai_summary = ?, ai_src_hash = ?, ai_updated_at = ?, 
                    plot_rewritten = ?, rewritten_at = ?, source = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $update_stmt->execute([
                $show['title'], $show['plot'], $show['year'], 
                $show['poster_url'], $show['backdrop_url'], $show['genre'],
                $show['country'], 'en', $show['ai_summary'], $show['ai_src_hash'],
                $show['ai_updated_at'], $show['plot_rewritten'], $show['rewritten_at'],
                'migration', $existing['id']
            ]);
            
            return 'updated';
        } else {
            // Insert new TV show
            $insert_stmt = $db->prepare("
                INSERT INTO tv_shows (
                    site_id, tmdb_id, name, overview, first_air_date, poster_url, backdrop_url, 
                    genre, country, original_language, ai_summary, ai_src_hash, 
                    ai_updated_at, plot_rewritten, rewritten_at, source, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $insert_stmt->execute([
                $site_id, $show['tmdb_id'], $show['title'], $show['plot'], $show['year'],
                $show['poster_url'], $show['backdrop_url'], $show['genre'], $show['country'],
                'en', $show['ai_summary'], $show['ai_src_hash'], $show['ai_updated_at'],
                $show['plot_rewritten'], $show['rewritten_at'], 'migration'
            ]);
            
            return 'imported';
        }
    }
    
    public static function migrateEpisode($db, $episode, $site_id) {
        // For episodes, we need to find or create the TV show first
        // This is a simplified version - you might want to enhance this
        
        // Check if episode already exists
        $check_stmt = $db->prepare("
            SELECT id FROM tv_episodes 
            WHERE site_id = ? AND tmdb_id = ? AND season_number = ? AND episode_number = ?
        ");
        $check_stmt->execute([$site_id, $episode['tmdb_id'], $episode['season_number'], $episode['episode_number']]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Update existing episode
            $update_stmt = $db->prepare("
                UPDATE tv_episodes SET 
                    name = ?, overview = ?, air_date = ?, still_url = ?, 
                    ai_summary = ?, ai_src_hash = ?, ai_updated_at = ?, 
                    plot_rewritten = ?, rewritten_at = ?
                WHERE id = ?
            ");
            
            $update_stmt->execute([
                $episode['title'], $episode['plot'], $episode['air_date'], $episode['still_url'],
                $episode['ai_summary'], $episode['ai_src_hash'], $episode['ai_updated_at'],
                $episode['plot_rewritten'], $episode['rewritten_at'], $existing['id']
            ]);
            
            return 'updated';
        } else {
            // This would need more complex logic to handle TV show relationships
            // For now, we'll skip episodes that don't have a parent show
            return 'skipped';
        }
    }
}

// Get recent migration jobs for display
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT mj.*, s.domain, s.name as site_name 
        FROM migration_jobs mj 
        LEFT JOIN sites s ON mj.site_id = s.id 
        ORDER BY mj.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_jobs = $stmt->fetchAll();
    
    // Get active sites
    $stmt = $db->prepare("SELECT id, domain, name FROM sites WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $sites = $stmt->fetchAll();
    
} catch (Exception $e) {
    $recent_jobs = [];
    $sites = [];
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
    <title>Content Import - <?= SITE_NAME ?></title>
    <meta name="description" content="Import movie and TV show content">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Styles -->
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/content-import.css">
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
                    <h1><i class="fas fa-database"></i> Content Import</h1>
                    <p class="header-subtitle">Import movies, TV shows, and episodes from your existing database</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" id="testConnectionBtn">
                        <i class="fas fa-plug"></i>
                        <span>Test Database Connection</span>
                    </button>
                </div>
            </div>
        </header>
        
        <!-- Connection Status -->
        <div class="connection-status-card" id="connectionCard" style="display: none;">
            <div class="status-header">
                <h3><i class="fas fa-server"></i> Database Status</h3>
                <div class="connection-indicator" id="connectionIndicator">
                    <span class="indicator-dot"></span>
                    <span class="indicator-text">Not Connected</span>
                </div>
            </div>
            <div class="status-content" id="statusContent">
                <!-- Status content will be populated by JavaScript -->
            </div>
        </div>
        
        <!-- Import Configuration -->
        <div class="import-config-card" id="importCard" style="display: none;">
            <div class="card-header">
                <h3><i class="fas fa-cogs"></i> Migration Configuration</h3>
                <p>Configure your content migration settings</p>
            </div>
            
            <form id="migrationForm" class="migration-form">
                <div class="form-sections">
                    <!-- Target Site Selection -->
                    <div class="form-section">
                        <h4><i class="fas fa-globe"></i> Target Site</h4>
                        <div class="form-group">
                            <label for="targetSite">Destination Site *</label>
                            <select id="targetSite" name="site_id" class="form-control" required>
                                <option value="">Select destination site...</option>
                                <?php foreach ($sites as $site): ?>
                                    <option value="<?= $site['id'] ?>"><?= htmlspecialchars($site['domain']) ?> - <?= htmlspecialchars($site['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Content Type Selection -->
                    <div class="form-section">
                        <h4><i class="fas fa-list"></i> Content Types</h4>
                        <div class="checkbox-grid">
                            <label class="checkbox-card">
                                <input type="checkbox" name="content_types[]" value="movies" checked>
                                <div class="checkbox-content">
                                    <i class="fas fa-film"></i>
                                    <h5>Movies</h5>
                                    <span class="count" id="moviesCount">0</span>
                                </div>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="content_types[]" value="tv_shows" checked>
                                <div class="checkbox-content">
                                    <i class="fas fa-tv"></i>
                                    <h5>TV Shows</h5>
                                    <span class="count" id="tvShowsCount">0</span>
                                </div>
                            </label>
                            
                            <label class="checkbox-card">
                                <input type="checkbox" name="content_types[]" value="episodes">
                                <div class="checkbox-content">
                                    <i class="fas fa-play-circle"></i>
                                    <h5>Episodes</h5>
                                    <span class="count" id="episodesCount">0</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Import Type -->
                    <div class="form-section">
                        <h4><i class="fas fa-filter"></i> Import Filter</h4>
                        <div class="radio-group">
                            <label class="radio-card">
                                <input type="radio" name="import_type" value="rewritten_only" checked>
                                <div class="radio-content">
                                    <h5>AI-Rewritten Content Only</h5>
                                    <p>Import only content with rewritten plots</p>
                                    <span class="count" id="rewrittenCount">0</span>
                                </div>
                            </label>
                            
                            <label class="radio-card">
                                <input type="radio" name="import_type" value="all_content">
                                <div class="radio-content">
                                    <h5>All Content</h5>
                                    <p>Import everything (rewritten + original)</p>
                                    <span class="count" id="totalCount">0</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-success" id="startMigrationBtn" disabled>
                        <i class="fas fa-play"></i>
                        <span>Start Migration</span>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Migration Progress -->
        <div class="migration-progress" id="migrationProgress" style="display: none;">
            <div class="progress-card">
                <div class="progress-header">
                    <h3><i class="fas fa-cogs"></i> Migration in Progress</h3>
                    <div class="progress-stats">
                        <span class="stat" id="progressPercent">0%</span>
                        <span class="stat" id="progressItems">0 / 0</span>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                </div>
                
                <div class="progress-details">
                    <div class="detail-item">
                        <span class="label">Imported:</span>
                        <span class="value" id="importedCount">0</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Updated:</span>
                        <span class="value" id="updatedCount">0</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Skipped:</span>
                        <span class="value" id="skippedCount">0</span>
                    </div>
                </div>
                
                <div class="progress-log" id="progressLog"></div>
            </div>
        </div>
        
        <!-- Recent Jobs -->
        <?php if (!empty($recent_jobs)): ?>
        <div class="recent-jobs-section">
            <div class="section-header">
                <h3><i class="fas fa-history"></i> Recent Import Jobs</h3>
                <button class="btn btn-secondary btn-sm" onclick="refreshJobs()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <div class="jobs-grid" id="jobsGrid">
                <?php foreach ($recent_jobs as $job): ?>
                    <div class="job-card status-<?= $job['status'] ?>">
                        <div class="job-header">
                            <h4>#<?= $job['id'] ?> - <?= htmlspecialchars($job['site_name']) ?></h4>
                            <span class="job-status status-<?= $job['status'] ?>">
                                <?= ucfirst($job['status']) ?>
                            </span>
                        </div>
                        <div class="job-details">
                            <div class="job-stat">
                                <span class="label">Content:</span>
                                <span><?= str_replace(',', ', ', $job['content_types']) ?></span>
                            </div>
                            <div class="job-stat">
                                <span class="label">Type:</span>
                                <span><?= str_replace('_', ' ', ucfirst($job['import_type'])) ?></span>
                            </div>
                            <div class="job-stat">
                                <span class="label">Progress:</span>
                                <span><?= $job['processed_items'] ?> / <?= $job['total_items'] ?> (<?= round($job['progress'], 1) ?>%)</span>
                            </div>
                            <div class="job-stat">
                                <span class="label">Results:</span>
                                <span><?= $job['imported_count'] ?> imported, <?= $job['updated_count'] ?> updated</span>
                            </div>
                            <div class="job-stat">
                                <span class="label">Started:</span>
                                <span><?= date('M j, Y H:i', strtotime($job['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <div class="toast-content">
            <div class="toast-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="toast-message"></div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="js/content-import.js"></script>
</body>
</html>
