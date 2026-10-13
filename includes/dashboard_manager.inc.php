<?php
require_once __DIR__ . '/../lib/manager.php';

$period          = manager_period((string) ($_GET['periode'] ?? 'week'));
$roleFilter      = (string) ($_GET['functie'] ?? '');
$serviceFilter   = (string) ($_GET['dienst'] ?? '');
$employeeSearch  = trim((string) ($_GET['zoek_medewerker'] ?? ''));
$employeePage    = manager_page_number($_GET['pagina_medewerker'] ?? 1);
$statusFilter    = (string) ($_GET['status'] ?? '');
$orderPeriod     = (string) ($_GET['periode_order'] ?? '');
$orderSearch     = trim((string) ($_GET['zoek_order'] ?? ''));
$orderPage       = manager_page_number($_GET['pagina_order'] ?? 1);

if (!in_array($roleFilter, MANAGER_ROLES, true)) {
    $roleFilter = '';
}

if (!array_key_exists($serviceFilter, MANAGER_SERVICE)) {
    $serviceFilter = '';
}

if (!in_array($statusFilter, MANAGER_STATUSES, true)) {
    $statusFilter = '';
}

if (!array_key_exists($orderPeriod, MANAGER_PERIODS)) {
    $orderPeriod = '';
}

$stats     = manager_stats($conn, $period);
$employees = manager_employees($conn, $roleFilter, $serviceFilter, $employeeSearch, $employeePage);
$orders    = manager_orders($conn, $statusFilter, $orderPeriod, $orderSearch, $orderPage);

$filters = [
    'periode'         => $period,
    'functie'         => $roleFilter,
    'dienst'          => $serviceFilter,
    'zoek_medewerker' => $employeeSearch,
    'pagina_medewerker' => $employees['page'] > 1 ? (string) $employees['page'] : '',
    'status'          => $statusFilter,
    'periode_order'   => $orderPeriod,
    'zoek_order'      => $orderSearch,
    'pagina_order'    => $orders['page'] > 1 ? (string) $orders['page'] : '',
];

$employeeFiltered = $roleFilter !== '' || $serviceFilter !== '' || $employeeSearch !== '';
$orderFiltered    = $statusFilter !== '' || $orderPeriod !== '' || $orderSearch !== '';
?>

<section class="section">
    <div class="container">

        <h1 class="section-title">Managementoverzicht</h1>
        <p class="section-intro">
            De cijfers van DORB, het overzicht van alle medewerkers en alle bestellingen van klanten.
        </p>

        <div class="manager-filters">
            <?php foreach (MANAGER_PERIODS as $key => $label): ?>
                <a class="manager-filter <?= $period === $key ? 'is-active' : '' ?>"
                   href="<?= e(manager_url($filters, ['periode' => $key])) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="manager-stats">
            <div class="manager-stat">
                <span class="manager-stat-number"><?= (int) $stats['open_orders'] ?></span>
                <span class="manager-stat-label">Openstaande bestellingen</span>
            </div>
            <div class="manager-stat">
                <span class="manager-stat-number"><?= (int) $stats['delivered_orders'] ?></span>
                <span class="manager-stat-label">Geleverde bestellingen</span>
            </div>
            <div class="manager-stat">
                <span class="manager-stat-number"><?= (int) $stats['delivered_pallets'] ?></span>
                <span class="manager-stat-label">Bezorgde pallets</span>
            </div>
            <div class="manager-stat">
                <span class="manager-stat-number"><?= money($stats['revenue']) ?></span>
                <span class="manager-stat-label">Omzet</span>
            </div>
        </div>

        <p class="stats-link-row">
            <a class="btn btn-accent" href="index.php?page=statistieken">Bekijk de statistieken</a>
            <span class="note">Prestaties over een langere periode, met grafieken.</span>
        </p>

        <h2 class="section-title" id="medewerkers">Medewerkers</h2>

        <form class="manager-toolbar" method="get" action="index.php#medewerkers">
            <input type="hidden" name="page" value="dashboard_manager">
            <input type="hidden" name="periode" value="<?= e($period) ?>">
            <?php if ($statusFilter !== ''): ?>
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <?php endif; ?>
            <?php if ($orderPeriod !== ''): ?>
                <input type="hidden" name="periode_order" value="<?= e($orderPeriod) ?>">
            <?php endif; ?>
            <?php if ($orderSearch !== ''): ?>
                <input type="hidden" name="zoek_order" value="<?= e($orderSearch) ?>">
            <?php endif; ?>

            <div class="manager-field manager-field-grow">
                <label for="zoek_medewerker">Zoeken</label>
                <input type="search" id="zoek_medewerker" name="zoek_medewerker"
                       placeholder="Naam of e-mailadres" value="<?= e($employeeSearch) ?>">
            </div>

            <div class="manager-field">
                <label for="functie">Functie</label>
                <select id="functie" name="functie">
                    <option value="">Alle functies</option>
                    <?php foreach (MANAGER_ROLES as $role): ?>
                        <option value="<?= e($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>>
                            <?= e(ucfirst($role)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="manager-field">
                <label for="dienst">Dienstverband</label>
                <select id="dienst" name="dienst">
                    <option value="">Alle medewerkers</option>
                    <?php foreach (MANAGER_SERVICE as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $serviceFilter === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="manager-field">
                <button class="btn btn-accent" type="submit">Filteren</button>
            </div>

            <?php if ($employeeFiltered): ?>
                <a class="manager-reset"
                   href="<?= e(manager_url($filters, ['functie' => '', 'dienst' => '', 'zoek_medewerker' => ''], 'medewerkers')) ?>">
                    Wissen
                </a>
            <?php endif; ?>
        </form>

        <div class="panel table-scroll">
            <?php if ($employees['rows'] === []): ?>
                <p class="manager-empty">
                    <?= $employeeFiltered
                        ? 'Geen medewerkers gevonden met deze filters.'
                        : 'Er zijn nog geen medewerkers.' ?>
                </p>
            <?php else: ?>
                <table class="manager-table">
                    <thead>
                        <tr>
                            <th>Naam</th>
                            <th>Functie</th>
                            <th>Rijbewijs</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees['rows'] as $employee): ?>
                            <tr>
                                <td>
                                    <a href="index.php?page=medewerker&amp;id=<?= (int) $employee['employee_id'] ?>">
                                        <?= e($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                    </a>
                                </td>
                                <td><?= e(ucfirst($employee['role'])) ?></td>
                                <td><?= $employee['license_type'] === null ? '-' : e($employee['license_type']) ?></td>
                                <td>
                                    <span class="manager-status <?= (int) $employee['in_service'] === 1 ? 'manager-status-geleverd' : 'manager-status-geannuleerd' ?>">
                                        <?= (int) $employee['in_service'] === 1 ? 'In dienst' : 'Uit dienst' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($employees['total'] > 0): ?>
            <div class="manager-pager">
                <span class="manager-pager-info">
                    <?= (int) $employees['from'] ?> - <?= (int) $employees['to'] ?>
                    van <?= (int) $employees['total'] ?> medewerkers
                </span>

                <?php if ($employees['pages'] > 1): ?>
                    <div class="manager-pager-links">
                        <?php if ($employees['page'] > 1): ?>
                            <a class="manager-filter"
                               href="<?= e(manager_url($filters, ['pagina_medewerker' => (string) ($employees['page'] - 1)], 'medewerkers')) ?>">
                                &larr; Vorige
                            </a>
                        <?php else: ?>
                            <span class="manager-filter is-disabled">&larr; Vorige</span>
                        <?php endif; ?>

                        <span class="manager-pager-position">
                            Pagina <?= (int) $employees['page'] ?> van <?= (int) $employees['pages'] ?>
                        </span>

                        <?php if ($employees['page'] < $employees['pages']): ?>
                            <a class="manager-filter"
                               href="<?= e(manager_url($filters, ['pagina_medewerker' => (string) ($employees['page'] + 1)], 'medewerkers')) ?>">
                                Volgende &rarr;
                            </a>
                        <?php else: ?>
                            <span class="manager-filter is-disabled">Volgende &rarr;</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title" id="bestellingen">Bestellingen</h2>

        <form class="manager-toolbar" method="get" action="index.php#bestellingen">
            <input type="hidden" name="page" value="dashboard_manager">
            <input type="hidden" name="periode" value="<?= e($period) ?>">
            <?php if ($roleFilter !== ''): ?>
                <input type="hidden" name="functie" value="<?= e($roleFilter) ?>">
            <?php endif; ?>
            <?php if ($serviceFilter !== ''): ?>
                <input type="hidden" name="dienst" value="<?= e($serviceFilter) ?>">
            <?php endif; ?>
            <?php if ($employeeSearch !== ''): ?>
                <input type="hidden" name="zoek_medewerker" value="<?= e($employeeSearch) ?>">
            <?php endif; ?>

            <div class="manager-field manager-field-grow">
                <label for="zoek_order">Zoeken</label>
                <input type="search" id="zoek_order" name="zoek_order"
                       placeholder="Ordernummer, klant, plaats of postcode" value="<?= e($orderSearch) ?>">
            </div>

            <div class="manager-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Alle statussen</option>
                    <?php foreach (MANAGER_STATUSES as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                            <?= e(ucfirst($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="manager-field">
                <label for="periode_order">Periode</label>
                <select id="periode_order" name="periode_order">
                    <option value="">Alle periodes</option>
                    <?php foreach (MANAGER_PERIODS as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $orderPeriod === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="manager-field">
                <button class="btn btn-accent" type="submit">Filteren</button>
            </div>

            <?php if ($orderFiltered): ?>
                <a class="manager-reset"
                   href="<?= e(manager_url($filters, ['status' => '', 'periode_order' => '', 'zoek_order' => ''], 'bestellingen')) ?>">
                    Wissen
                </a>
            <?php endif; ?>
        </form>

        <div class="panel table-scroll">
            <?php if ($orders['rows'] === []): ?>
                <p class="manager-empty">
                    <?= $orderFiltered
                        ? 'Geen bestellingen gevonden met deze filters.'
                        : 'Er zijn nog geen bestellingen.' ?>
                </p>
            <?php else: ?>
                <table class="manager-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Datum</th>
                            <th>Klant</th>
                            <th>Bestemming</th>
                            <th class="manager-right">Pallets</th>
                            <th class="manager-right">Prijs</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders['rows'] as $order): ?>
                            <tr>
                                <td>
                                    <a href="index.php?page=order&amp;id=<?= (int) $order['order_id'] ?>">
                                        <?= (int) $order['order_id'] ?>
                                    </a>
                                </td>
                                <td><?= e(date('d-m-Y', strtotime((string) $order['order_date']))) ?></td>
                                <td><?= e($order['customer_name']) ?></td>
                                <td>
                                    <?= e($order['dest_address']) ?><br>
                                    <?= e($order['dest_postalcode'] . ' ' . $order['dest_city']) ?>
                                    (<?= e($order['land_name']) ?>)
                                </td>
                                <td class="manager-right"><?= (int) $order['pallet_count'] ?></td>
                                <td class="manager-right"><?= money($order['total_price']) ?></td>
                                <td>
                                    <span class="manager-status manager-status-<?= e($order['status']) ?>">
                                        <?= e($order['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($orders['total'] > 0): ?>
            <div class="manager-pager">
                <span class="manager-pager-info">
                    <?= (int) $orders['from'] ?> - <?= (int) $orders['to'] ?>
                    van <?= (int) $orders['total'] ?> bestellingen
                </span>

                <?php if ($orders['pages'] > 1): ?>
                    <div class="manager-pager-links">
                        <?php if ($orders['page'] > 1): ?>
                            <a class="manager-filter"
                               href="<?= e(manager_url($filters, ['pagina_order' => (string) ($orders['page'] - 1)], 'bestellingen')) ?>">
                                &larr; Vorige
                            </a>
                        <?php else: ?>
                            <span class="manager-filter is-disabled">&larr; Vorige</span>
                        <?php endif; ?>

                        <span class="manager-pager-position">
                            Pagina <?= (int) $orders['page'] ?> van <?= (int) $orders['pages'] ?>
                        </span>

                        <?php if ($orders['page'] < $orders['pages']): ?>
                            <a class="manager-filter"
                               href="<?= e(manager_url($filters, ['pagina_order' => (string) ($orders['page'] + 1)], 'bestellingen')) ?>">
                                Volgende &rarr;
                            </a>
                        <?php else: ?>
                            <span class="manager-filter is-disabled">Volgende &rarr;</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</section>
