<?php
/**
 * Determines which Axialy UI environment the admin session is working with.
 * Falls back to “production” when nothing is set.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();                 // <-- wrapped to avoid “session already started” notices
}

$TARGET_ENV = !empty($_SESSION['admin_env'])
    ? $_SESSION['admin_env']
    : 'production';
