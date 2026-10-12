<?php
/* Sidebar navigation. The Token Queue badge shows how many patients are waiting today. */
$waitingStmt = db()->prepare("SELECT COUNT(*) FROM token_queue WHERE queue_date = ? AND status = 'Waiting'");
$waitingStmt->execute([today()]);
$waitingBadge = (int) $waitingStmt->fetchColumn();

$navItems = [
    'dashboard'    => ['Dashboard',    '',              'dashboard'],
    'patients'     => ['Patients',     'patients/',     'patients'],
    'doctors'      => ['Doctors',      'doctors/',      'doctors'],
    'appointments' => ['Appointments', 'appointments/', 'calendar'],
    'queue'        => ['Token Queue',  'queue/',        'queue'],
    'billing'      => ['Billing',      'billing/',      'billing'],
];
?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(url('')) ?>">
        <span class="brand-logo">+</span>
        <span>
            <span class="brand-name">MediCare</span>
            <span class="brand-sub">Hospital Suite</span>
        </span>
    </a>

    <div class="nav-label">Workspace</div>
    <nav class="nav">
        <?php foreach ($navItems as $key => [$label, $path, $iconName]): ?>
            <a href="<?= e(url($path)) ?>" class="nav-link<?= $activeNav === $key ? ' active' : '' ?>">
                <?= icon($iconName) ?>
                <span><?= e($label) ?></span>
                <?php if ($key === 'queue' && $waitingBadge > 0): ?>
                    <span class="nav-badge" title="Patients waiting today"><?= $waitingBadge ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-card">
        <div class="sidebar-card-title"><?= icon('database') ?> Live database</div>
        <div class="sidebar-card-text">All data is saved in MySQL (<b>medicare_db</b>).</div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
