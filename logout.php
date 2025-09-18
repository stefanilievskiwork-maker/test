<?php
require_once 'config.php';

// Log activity if user was logged in
if (isLoggedIn()) {
    logActivity(getCurrentUserId(), 'logout', 'User logged out');
    
    // Remove remember token if exists
    if (isset($_COOKIE['remember_token'])) {
        try {
            $db = getDB();
            $token_hash = hash('sha256', $_COOKIE['remember_token']);
            
            $stmt = $db->prepare("DELETE FROM remember_tokens WHERE token = ?");
            $stmt->execute([$token_hash]);
            
            // Clear cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        } catch (Exception $e) {
            // Log error but continue logout
            if (DEBUG) {
                error_log('Error removing remember token: ' . $e->getMessage());
            }
        }
    }
}

// Clear all session data
session_unset();
session_destroy();

// Start new session for flash message
session_start();
$_SESSION['logout_success'] = 'You have been successfully logged out.';

// Redirect to login page
redirect('/login');
?>
