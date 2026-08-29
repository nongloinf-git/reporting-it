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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bs-primary: <?= $couleurHex ?>;
            --bs-primary-rgb: <?= $couleurRgb ?>;
            --bs-link-color: <?= $couleurHex ?>;
            --bs-link-hover-color: <?= $couleurHex ?>;
        }
        .navbar.bg-dark { background-color: <?= $couleurHex ?> !important; }
        .btn-primary { background-color: <?= $couleurHex ?>; border-color: <?= $couleurHex ?>; }
        .btn-primary:hover, .btn-primary:focus { filter: brightness(0.9); background-color: <?= $couleurHex ?>; border-color: <?= $couleurHex ?>; }
        .btn-outline-primary { color: <?= $couleurHex ?>; border-color: <?= $couleurHex ?>; }
        .btn-outline-primary:hover { background-color: <?= $couleurHex ?>; border-color: <?= $couleurHex ?>; }
        a { color: <?= $couleurHex ?>; }
        .badge.bg-primary { background-color: <?= $couleurHex ?> !important; }
    </style>
</head>
<body>
