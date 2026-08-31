<?php
// Fichier partagé, inclus en tout début de page (avant tout HTML).
// Attend une variable optionnelle $titrePage (string) définie par la page appelante.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$utilisateurConnecte = $utilisateurConnecte ?? currentUser();

$palettesCouleurs = [
    'bleu'   => ['#0d6efd', '13,110,253'],
    'vert'   => ['#198754', '25,135,84'],
    'violet' => ['#6f42c1', '111,66,193'],
    'orange' => ['#fd7e14', '253,126,20'],
    'rouge'  => ['#dc3545', '220,53,69'],
];
$couleurChoisie = $utilisateurConnecte['theme_couleur'] ?? 'bleu';
[$couleurHex, $couleurRgb] = $palettesCouleurs[$couleurChoisie] ?? $palettesCouleurs['bleu'];
$modeSombre = !empty($utilisateurConnecte['mode_sombre']);
?><!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $modeSombre ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($titrePage) ? e($titrePage) . ' - ' : '' ?>Reporting IT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bs-primary: <?= $couleurHex ?>;
            --bs-primary-rgb: <?= $couleurRgb ?>;
            --bs-link-color: <?= $couleurHex ?>;
            --bs-link-hover-color: <?= $couleurHex ?>;
            --bs-border-radius: 0.6rem;
            --bs-border-radius-sm: 0.45rem;
            --bs-border-radius-lg: 0.85rem;
            --ombre-carte: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --ombre-carte-hover: 0 4px 12px rgba(0,0,0,0.08);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            letter-spacing: -0.01em;
        }
        [data-bs-theme="light"] body { background-color: #f4f6f9; }

        h1, h2, h3, h4, h5, h6 { font-weight: 600; letter-spacing: -0.02em; }

        /* Navbar */
        .navbar.bg-dark { background-color: <?= $couleurHex ?> !important; }
        .navbar { box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
        .navbar-brand { font-weight: 700; }
        .nav-link { border-radius: var(--bs-border-radius-sm); transition: background-color .15s ease; }
        .nav-link:hover { background-color: rgba(255,255,255,0.12); }

        /* Cartes */
        .card {
            border: none;
            border-radius: var(--bs-border-radius-lg);
            box-shadow: var(--ombre-carte);
            transition: box-shadow .2s ease;
        }
        .card:hover { box-shadow: var(--ombre-carte-hover); }
        .card-header { font-weight: 600; background-color: rgba(var(--bs-primary-rgb), 0.06); border-bottom: 1px solid rgba(0,0,0,0.06); }

        /* Boutons */
        .btn { border-radius: var(--bs-border-radius-sm); font-weight: 500; transition: filter .15s ease, transform .1s ease; }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background-color: <?= $couleurHex ?>; border-color: <?= $couleurHex ?>; }
        .btn-primary:hover, .btn-primary:focus { filter: brightness(0.9); background-color: <?= $couleurHex ?>; border-color: <?= $couleurHex ?>; }
        .btn-outline-primary { color: <?= $couleurHex ?>; border-color: <?= $couleurHex ?>; }
        .btn-outline-primary:hover { background-color: <?= $couleurHex ?>; border-color: <?= $couleurHex ?>; }

        a { color: <?= $couleurHex ?>; }

        /* Badges et tableaux */
        .badge.bg-primary { background-color: <?= $couleurHex ?> !important; }
        .badge { font-weight: 500; border-radius: var(--bs-border-radius-sm); }
        .table { border-radius: var(--bs-border-radius); overflow: hidden; }
        .table thead th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6c757d; border-bottom-width: 1px; }

        /* Formulaires */
        .form-control, .form-select { border-radius: var(--bs-border-radius-sm); }
        .form-control:focus, .form-select:focus { border-color: <?= $couleurHex ?>; box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.15); }

        /* Responsive : espacements resserrés sur mobile */
        @media (max-width: 576px) {
            .container { padding-left: 12px; padding-right: 12px; }
            h3 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
