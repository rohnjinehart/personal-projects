<?php
/**
 * Session Security Configuration
 * 
 * Sets secure cookie parameters BEFORE starting the session
 * This must be called before session_start()
 */
session_set_cookie_params([
    'lifetime' => 86400,    // Cookie expires after 1 day (in seconds)
    'path' => '/',          // Accessible across entire domain
    'domain' => $_SERVER['HTTP_HOST'],  // Current host domain
    'secure' => true,       // Only send cookie over HTTPS (enable in production)
    'httponly' => true,     // Prevent JavaScript access to cookie
    'samesite' => 'Strict'  // Prevent CSRF attacks by restricting cross-site sends
]);

// Initialize or resume existing session
session_start();

/**
 * Authentication Check Function
 * 
 * Verifies user is logged in before granting access to protected pages
 * Redirects to homepage if not authenticated
 */
function requireLogin() {
    // Check if 'logged_in' session flag exists and is true
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        // Immediately redirect unauthenticated users
        header("Location: index.html");
        exit(); // Terminate script execution
    }
}