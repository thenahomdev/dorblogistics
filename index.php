<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/database/cnn.php';
require_once __DIR__ . '/lib/auth.php';

$user = current_user($conn);

$pages = [
    'home'                      => ['title' => 'Home',                'roles' => []],
    'login'                     => ['title' => 'Inloggen',            'roles' => []],
    'klant/register'            => ['title' => 'Account aanmaken',    'roles' => []],
    'klant/register_success'    => ['title' => 'Account aangemaakt',  'roles' => []],
   
    'wachtwoord-vergeten'       => ['title' => 'Wachtwoord vergeten', 'roles' => []],

    'dashboard_klant'     => ['title' => 'Dashboard',           'roles' => ['klant']],
    'dashboard_driver'    => ['title' => 'Dashboard',           'roles' => ['chauffeur']],
    'dashboard_planner'   => ['title' => 'Dashboard',           'roles' => ['planner']],
    'dashboard_manager'   => ['title' => 'Dashboard',           'roles' => ['manager']],

    'recent_orders_klant' => ['title' => 'Mijn zendingen',      'roles' => ['klant']],
    'offerte'             => ['title' => 'Offerte aanvragen',   'roles' => ['klant']],
    'facturen'            => ['title' => 'Mijn facturen',       'roles' => ['klant']],
    'ritten'              => ['title' => 'Mijn ritten',         'roles' => ['chauffeur']],
    'planning'            => ['title' => 'Planning',            'roles' => ['planner']],
    'orders'              => ['title' => 'Orders',              'roles' => ['planner', 'manager']],
    'klanten'             => ['title' => 'Klanten',             'roles' => ['manager']],
    'medewerkers_admin'   => ['title' => 'Medewerkers',         'roles' => ['manager']],
    'medewerker'          => ['title' => 'Medewerker',          'roles' => ['manager']],
    'statistieken'        => ['title' => 'Statistieken',        'roles' => ['manager']],
    'order'               => ['title' => 'Bestelling',          'roles' => ['manager']],
    'order_linking'       => ['title' => 'Nieuwe rit samenstellen', 'roles' => ['planner']],
    'manage_customers'    => ['title' => 'Klantenoverzicht', 'roles' => ['manager']],
];

$page = (string) ($_GET['page'] ?? 'home');

if (!array_key_exists($page, $pages)) {
    http_response_code(404);
    $page  = '404';
    $title = 'Pagina niet gevonden';
} elseif (!may_view($user, $pages[$page]['roles'])) {
    header('Location: index.php' . ($user === null ? '?page=login' : ''));
    exit;
} else {
    $title = $pages[$page]['title'];
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function money(float|string $amount, int $decimals = 2): string
{
    return '&euro; ' . number_format((float) $amount, $decimals, ',', '.');
}

$view = __DIR__ . '/includes/' . $page . '.inc.php';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> &middot; DORB Logistics</title>
    <meta name="description" content="DORB Logistics vervoert pallets door de Benelux en Duitsland. Vaste kilometerprijs per pallet, betaling achteraf.">
    <link rel="stylesheet" href="css/style.css">
    <link rel="javascript" href="js/script.js">
</head>
<body>

<?php 
include __DIR__ . '/includes/nav.inc.php'; 
include __DIR__ . '/includes/alerts.inc.php';
?>

<main>
    <?php
    if (is_file($view)) {
        include $view;
    } elseif ($page === '404') {
        echo '<div class="container page-error"><h1>Pagina niet gevonden</h1>'
            . '<a class="btn btn-accent" href="index.php">Terug naar home</a></div>';
    } else {
        include __DIR__ . '/includes/todo.inc.php';
    }
    ?>
</main>

<?php include __DIR__ . '/includes/footer.inc.php'; ?>

<script src="js/script.js"></script>
</body>
</html>
