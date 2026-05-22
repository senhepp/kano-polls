<?php
require_once __DIR__ . '/config.php';
session_start();

function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}

function adminLogin($password) {
    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        return true;
    }
    return false;
}

function adminLogout() {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
}