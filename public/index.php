<?php
// public/index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'superadmin') {
        header("Location: /superadmin/kelola_pekerjaan.php");
        exit;
    } elseif ($role === 'admin') {
        header("Location: /admin/dashboard.php");
        exit;
    } elseif ($role === 'pekerja') {
        header("Location: /pekerja/index.php");
        exit;
    }
}

header("Location: /auth/login.php");
exit;
