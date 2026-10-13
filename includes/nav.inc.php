<?php
$user = $user ?? null;
$role = $user['role'] ?? null;

$links = match ($role) {
    'klant' => [
        'index.php?page=dashboard_klant' => 'Overzicht',
        'index.php?page=recent_orders_klant' => 'Mijn zendingen',
        'index.php?page=offerte' => 'Offerte aanvragen',
    ],
    'chauffeur' => [
        'index.php?page=dashboard_driver' => 'Mijn ritten',
    ],
    'planner' => [
        'index.php?page=dashboard_planner' => 'Planning',
        'index.php?page=orders'   => 'Orders',
        'index.php#wagenpark'     => 'Wagenpark',
    ],
    'manager' => [
        'index.php?page=dashboard_manager'    => 'Overzicht',
        'index.php?page=manage_customers'     => 'Klanten',
        'index.php?page=medewerkers_admin'    => 'Medewerkers',
    ],
    default => [
        'index.php#tarieven'  => 'Tarieven',
        'index.php#wagenpark' => 'Wagenpark',
        'index.php#contact'   => 'Contact',
    ],
};
?>
<nav class="site-nav" id="siteNav">
    <div class="container nav-inner">
        <a class="nav-brand" href="index.php">DORB <span class="brand-accent">Logistics</span></a>

        <button class="nav-toggle" type="button" aria-controls="navLinks" aria-expanded="false" aria-label="Menu">
            <span></span>
        </button>

        <ul class="nav-links" id="navLinks">
            <?php foreach ($links as $url => $label): ?>
                <li><a href="<?= e($url) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>

            <?php if ($user !== null): ?>
                <li class="nav-user"><?= e($user['name']) ?></li>
                <li><a href="php/logout.php" class="btn btn-accent">Uitloggen</a></li>
            <?php else: ?>
                <li><a href="index.php?page=login" class="btn btn-accent">Login</a></li>
                 <li><a href="index.php?page=klant/register" class="btn btn-accent">Registreren</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
