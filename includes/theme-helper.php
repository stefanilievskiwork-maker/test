<?php
/**
 * Theme Helper Functions
 * Used by themes to interact with the database and display content
 */

/**
 * Get movies for current site
 */
function getMovies($limit = 20, $offset = 0, $genre = null, $year = null) {
    $db = getDB();
    
    $sql = "SELECT m.*, GROUP_CONCAT(g.name) as genres 
            FROM movies m 
            LEFT JOIN movie_genres mg ON m.id = mg.movie_id 
            LEFT JOIN genres g ON mg.genre_id = g.id 
            WHERE m.site_id = ?";
    
    $params = [CURRENT_SITE_ID];
    
    if ($genre) {
        $sql .= " AND g.name = ?";
        $params[] = $genre;
    }
    
    if ($year) {
        $sql .= " AND m.year = ?";
        $params[] = $year;
    }
    
    $sql .= " GROUP BY m.id ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get single movie
 */
function getMovie($id) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT m.*, GROUP_CONCAT(g.name) as genres 
        FROM movies m 
        LEFT JOIN movie_genres mg ON m.id = mg.movie_id 
        LEFT JOIN genres g ON mg.genre_id = g.id 
        WHERE m.id = ? AND m.site_id = ? 
        GROUP BY m.id
    ");
    $stmt->execute([$id, CURRENT_SITE_ID]);
    return $stmt->fetch();
}

/**
 * Get TV shows for current site
 */
function getTVShows($limit = 20, $offset = 0) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM tv_shows 
        WHERE site_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([CURRENT_SITE_ID, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Get ad zones for current site
 */
function getAdZones() {
    static $ad_zones = null;
    
    if ($ad_zones === null) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT az.zone_key, a.ad_code, a.priority 
            FROM ad_zones az 
            LEFT JOIN ads a ON az.id = a.ad_zone_id AND a.is_active = 1 
            WHERE az.site_id = ? 
            ORDER BY a.priority DESC
        ");
        $stmt->execute([CURRENT_SITE_ID]);
        
        $zones = [];
        while ($row = $stmt->fetch()) {
            if (!isset($zones[$row['zone_key']])) {
                $zones[$row['zone_key']] = [];
            }
            if ($row['ad_code']) {
                $zones[$row['zone_key']][] = $row['ad_code'];
            }
        }
        $ad_zones = $zones;
    }
    
    return $ad_zones;
}

/**
 * Display ads for a specific zone
 */
function showAd($zone_key) {
    $ad_zones = getAdZones();
    
    if (isset($ad_zones[$zone_key]) && !empty($ad_zones[$zone_key])) {
        // Show the highest priority ad for this zone
        echo $ad_zones[$zone_key][0];
    }
}

/**
 * Get site meta settings
 */
function getSiteMeta() {
    static $site_meta = null;
    
    if ($site_meta === null) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM site_meta_settings WHERE site_id = ?");
        $stmt->execute([CURRENT_SITE_ID]);
        $site_meta = $stmt->fetch() ?: [];
    }
    
    return $site_meta;
}

/**
 * Generate meta tags for pages
 */
function generateMetaTags($type, $data = []) {
    $db = getDB();
    $site_meta = getSiteMeta();
    
    // Get SEO template
    $stmt = $db->prepare("
        SELECT * FROM seo_templates 
        WHERE site_id = ? AND content_type = ? 
        ORDER BY is_default DESC 
        LIMIT 1
    ");
    $stmt->execute([CURRENT_SITE_ID, $type]);
    $template = $stmt->fetch();
    
    if ($template) {
        $title = replacePlaceholders($template['title_template'], $data, $site_meta);
        $description = replacePlaceholders($template['description_template'], $data, $site_meta);
        
        echo "<title>" . htmlspecialchars($title) . "</title>\n";
        echo "<meta name='description' content='" . htmlspecialchars($description) . "'>\n";
        echo "<meta property='og:title' content='" . htmlspecialchars($title) . "'>\n";
        echo "<meta property='og:description' content='" . htmlspecialchars($description) . "'>\n";
        
        if (isset($data['poster_url'])) {
            echo "<meta property='og:image' content='" . htmlspecialchars($data['poster_url']) . "'>\n";
        }
    } else {
        // Fallback
        $title = $data['title'] ?? CURRENT_SITE_NAME;
        echo "<title>" . htmlspecialchars($title) . "</title>\n";
    }
}

/**
 * Replace placeholders in templates
 */
function replacePlaceholders($template, $data, $site_meta) {
    $replacements = [
        '{site_name}' => CURRENT_SITE_NAME,
        '{title}' => $data['title'] ?? '',
        '{year}' => $data['year'] ?? '',
        '{plot_short}' => substr($data['plot'] ?? '', 0, 150) . '...',
        '{actors}' => $data['actors'] ?? '',
        '{genre}' => $data['genres'] ?? '',
        '{name}' => $data['name'] ?? '', // For TV shows
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

/**
 * Get theme configuration
 */
function getThemeConfig($key = null, $default = null) {
    $config = defined('THEME_CONFIG') ? THEME_CONFIG : [];
    
    if ($key === null) {
        return $config;
    }
    
    return $config[$key] ?? $default;
}

/**
 * Include theme template
 */
function includeTemplate($template, $vars = []) {
    extract($vars);
    $template_path = __DIR__ . '/../' . CURRENT_THEME_PATH . '/' . $template . '.php';
    
    if (file_exists($template_path)) {
        include $template_path;
    } else {
        echo "Template not found: " . $template;
    }
}

/**
 * Generate breadcrumbs
 */
function getBreadcrumbs() {
    $breadcrumbs = [
        ['name' => 'Home', 'url' => '/']
    ];
    
    $uri = $_SERVER['REQUEST_URI'];
    
    if (preg_match('/^\/movie\/(\d+)/', $uri, $matches)) {
        $movie = getMovie($matches[1]);
        if ($movie) {
            $breadcrumbs[] = ['name' => 'Movies', 'url' => '/movies'];
            $breadcrumbs[] = ['name' => $movie['title'], 'url' => ''];
        }
    } elseif (preg_match('/^\/genre\/([^\/]+)/', $uri, $matches)) {
        $breadcrumbs[] = ['name' => 'Genres', 'url' => '/genres'];
        $breadcrumbs[] = ['name' => ucfirst($matches[1]), 'url' => ''];
    }
    
    return $breadcrumbs;
}
?>
