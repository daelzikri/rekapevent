<?php
// public/index.php

require_once __DIR__ . '/../config/helpers.php';
init_session();

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
