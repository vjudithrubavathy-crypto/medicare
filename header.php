<?php
/*
 * Shared top part of every page.
 * Before including this file, a page sets:
 *   $pageTitle   - big heading, e.g. "Patients"
 *   $breadcrumb  - small text above it, e.g. "Patient directory"
 *   $activeNav   - which sidebar link is highlighted
 */
$pageTitle  = $pageTitle  ?? 'MediCare';
$breadcrumb = $breadcrumb ?? '';
$activeNav  = $activeNav  ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · MediCare Hospital Management System</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="layout">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <main class="main">
        <header class="topbar">
            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open menu">☰</button>
            <div class="topbar-title">
                <div class="breadcrumb">Workspace<?= $breadcrumb !== '' ? ' / ' . e($breadcrumb) : '' ?></div>
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <div class="topbar-right">
                <div class="date-chip"><?= e(date('D, d M, Y')) ?></div>
                <div class="avatar-chip" title="Administrator">AD</div>
            </div>
        </header>

        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
                <span><?= e($flash['message']) ?></span>
                <button type="button" class="alert-close" aria-label="Close">×</button>
            </div>
        <?php endforeach; ?>
