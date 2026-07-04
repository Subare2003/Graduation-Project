<?php
/**
 * ESP32 Dashboard Authentication Guard
 * ===================================
 * 
 * PURPOSE:
 * This file acts as a security gate for all protected dashboard pages.
 * It MUST be included at the top of any page requiring authentication.
 * 
 * PROTECTION MECHANISM:
 * 1. Verifies user has valid authentication session
 * 2. Checks session hasn't expired due to inactivity
 * 3. Updates activity timestamp to extend session
 * 4. Redirects unauthorized users to login page
 * 
 * USAGE:
 * Include this file at the top of protected PHP pages:
 * require_once 'auth_check.php';
 * 
 * SECURITY FLOW:
 * Protected Page Access → Auth Check → Session Valid? → Allow Access
 *                                    → Session Invalid? → Redirect to Login
 */

// Initialize session to access authentication state
session_start();

// ================== SESSION CONFIGURATION ==================
$SESSION_TIMEOUT = 3600; // Session timeout: 1 hour (must match login.php)

// ================== AUTHENTICATION VERIFICATION ==================
/**
 * Primary authentication check: verify user has logged in
 * If no valid session exists, redirect immediately to login page
 */
if (!isset($_SESSION['dashboard_authenticated']) || $_SESSION['dashboard_authenticated'] !== true) {
    // User not authenticated - redirect to login page
    header('Location: login.php');
    exit();
}

// ================== SESSION TIMEOUT VERIFICATION ==================
/**
 * Check if session has expired due to inactivity
 * If expired: destroy session and redirect with expiration message
 * Timeout calculated from last recorded activity timestamp
 */
if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > $SESSION_TIMEOUT) {
    // Session has expired - clean up and redirect with message
    session_destroy();
    header('Location: login.php?expired=1');
    exit();
}

// ================== SESSION MAINTENANCE ==================
/**
 * Session is valid - update activity timestamp to extend session
 * This ensures active users don't get logged out unexpectedly
 */
$_SESSION['last_activity'] = time();

// ================== SESSION INFO FOR DASHBOARD DISPLAY ==================
/**
 * Calculate session statistics for display in dashboard header
 * These variables can be used by the including page for user feedback
 */
$login_time = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : time();
$session_duration = time() - $login_time;           // How long user has been logged in
$remaining_time = $SESSION_TIMEOUT - (time() - $_SESSION['last_activity']); // Time until timeout
?>