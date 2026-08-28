<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/journal.php';

if (isLoggedIn()) {
    journaliser((int) $_SESSION['user_id'], 'deconnexion');
}

session_destroy();
header('Location: login.php');
exit;
