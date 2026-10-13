<?php

$driverId    = (int) $user['employee_id'];
$perPage     = 15;
$currentPage = (int) ($_GET['p'] ?? 1);

if ($currentPage < 1) {
    $currentPage = 1;
}

$status          = trim((string) ($_GET['status'] ?? ''));
$allowedStatuses = ['gepland', 'onderweg', 'afgerond'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$conditions = ['r.driver_id = :driver_id'];
$params     = [':driver_id' => $driverId];

if ($status !== '') {
    $conditions[]      = 'r.status = :status';
    $params[':status'] = $status;
}

$whereSql = 'WHERE ' . implode(' AND ', $conditions);

$classAlle     = '';
$classGepland  = '';
$classOnderweg = '';
$classAfgerond = '';

if ($status === 'gepland') {
    $classGepland = 'active';
} elseif ($status === 'onderweg') {
    $classOnderweg = 'active';
} elseif ($status === 'afgerond') {
    $classAfgerond = 'active';
} else {
    $classAlle = 'active';
}

$sqlTotal = "SELECT COUNT(*)
             FROM ride r
             $whereSql";

$stmtTotal = $conn->prepare($sqlTotal);
$stmtTotal->execute($params);

$totalRides = (int) $stmtTotal->fetchColumn();
$totalPages = (int) ceil($totalRides / $perPage);

if ($totalPages < 1) {
    $totalPages = 1;
}

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $perPage;

$sqlRides = "SELECT r.ride_id, r.ride_date, r.departure_time, r.status, t.license_plate,
                    COUNT(ro.order_id) AS stop_count,
                    COALESCE(SUM(o.pallet_count), 0) AS total_pallets
             FROM ride r
             JOIN truck t ON t.truck_id = r.truck_id
             LEFT JOIN ride_order ro ON ro.ride_id = r.ride_id
             LEFT JOIN orders o ON o.order_id = ro.order_id
             $whereSql
             GROUP BY r.ride_id, r.ride_date, r.departure_time, r.status, t.license_plate
             ORDER BY r.ride_date DESC, r.departure_time DESC
             LIMIT :limit OFFSET :offset";

$stmtRides = $conn->prepare($sqlRides);

foreach ($params as $key => $value) {
    $stmtRides->bindValue($key, $value);
}

$stmtRides->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtRides->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtRides->execute();

$rides = $stmtRides->fetchAll();

$stopsByRide = [];

if ($rides !== []) {

    $rideIds = [];

    foreach ($rides as $ride) {
        $rideIds[] = (int) $ride['ride_id'];
    }

    $placeholders = implode(',', array_fill(0, count($rideIds), '?'));

    $sqlStops = "SELECT ro.ride_id, ro.stop_sequence, ro.delivered_at, o.pallet_count,
                        o.dest_street, o.dest_housenumber, o.dest_postalcode,
                        ci.name AS city_name, l.name AS land_name,
                        c.companyname, c.first_name, c.last_name
                 FROM ride_order ro
                 JOIN orders o ON o.order_id = ro.order_id
                 JOIN customer c ON c.user_id = o.customer_id
                 JOIN city ci ON ci.city_id = o.dest_city_id
                 JOIN land l ON l.land_id = ci.land_id
                 WHERE ro.ride_id IN ($placeholders)
                 ORDER BY ro.ride_id, ro.stop_sequence";

    $stmtStops = $conn->prepare($sqlStops);
    $stmtStops->execute($rideIds);

    foreach ($stmtStops->fetchAll() as $stop) {
        $stopsByRide[(int) $stop['ride_id']][] = $stop;
    }
}

$urlExtra = '';

if ($status !== '') {
    $urlExtra = '&status=' . urlencode($status);
}

?>

<div class="page">

    <div class="titlebar">
        <div class="title-content">
            <div>
                <h1>Mijn ritten</h1>
            </div>
        </div>

        <div class="toolbar">
            <div class="badge">
                <span class="badge-dot"></span>
                <?= $totalRides ?> ritten
            </div>
        </div>
    </div>

    <div class="filterbar">

        <a class="btn btn-filter <?= $classAlle ?>"
           href="index.php?page=dashboard_driver">
            Alle
        </a>

        <a class="btn btn-filter <?= $classGepland ?>"
           href="index.php?page=dashboard_driver&status=gepland">
            Te bezorgen
        </a>

        <a class="btn btn-filter <?= $classOnderweg ?>"
           href="index.php?page=dashboard_driver&status=onderweg">
            Onderweg
        </a>

        <a class="btn btn-filter <?= $classAfgerond ?>"
           href="index.php?page=dashboard_driver&status=afgerond">
            Bezorgd
        </a>

    </div>

    <?php if ($rides === []) { ?>

        <div class="klant-empty">
            <div class="klant-empty-icon">&#128666;</div>
            <h3>Geen ritten gevonden</h3>
            <p>Er zijn geen ritten gevonden met de gekozen filter.</p>
        </div>

    <?php } else { ?>

        <div class="table-container">
            <table class="orders-table">

                <thead>
                    <tr>
                        <th>Kenteken</th>
                        <th>Aantal stops</th>
                        <th>Totaal pallets</th>
                        <th>Status</th>
                        <th>Datum</th>
                        <th>Vertrektijd</th>
                        <th>Acties</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($rides as $ride) { ?>

                        <?php
                        $rideId   = (int) $ride['ride_id'];
                        $rideDate = date('d-m-Y', strtotime($ride['ride_date']));

                        if (!empty($ride['departure_time'])) {
                            $rideTime = date('H:i', strtotime($ride['departure_time']));
                        } else {
                            $rideTime = '-';
                        }

                        if ($ride['status'] === 'gepland') {
                            $statusClass = 'klant-status-open';
                            $statusText  = 'Te bezorgen';
                        } elseif ($ride['status'] === 'onderweg') {
                            $statusClass = 'klant-status-progress';
                            $statusText  = 'Onderweg';
                        } elseif ($ride['status'] === 'afgerond') {
                            $statusClass = 'klant-status-completed';
                            $statusText  = 'Bezorgd';
                        } else {
                            $statusClass = 'klant-status-cancelled';
                            $statusText  = ucfirst($ride['status']);
                        }
                        ?>

                        <tr>
                            <td><strong><?= e($ride['license_plate']) ?></strong></td>
                            <td><?= (int) $ride['stop_count'] ?></td>
                            <td><?= (int) $ride['total_pallets'] ?></td>
                            <td>
                                <span class="klant-status <?= $statusClass ?>">
                                    <?= e($statusText) ?>
                                </span>
                            </td>
                            <td><?= e($rideDate) ?></td>
                            <td><?= e($rideTime) ?></td>
                            <td>
                                <button type="button"
                                        class="btn btn-primary"
                                        popovertarget="stops-<?= $rideId ?>">
                                    Stops inzien
                                </button>
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>
        </div>

        <?php foreach ($rides as $ride) { ?>

            <?php
            $rideId   = (int) $ride['ride_id'];
            $rideDate = date('d-m-Y', strtotime($ride['ride_date']));

            if (isset($stopsByRide[$rideId])) {
                $stops = $stopsByRide[$rideId];
            } else {
                $stops = [];
            }

            if ($ride['status'] === 'gepland' || $ride['status'] === 'onderweg') {
                $showRoute = true;
            } else {
                $showRoute = false;
            }

            $addresses = [];

            foreach ($stops as $stop) {
                $addresses[] = $stop['dest_street'] . ' ' . $stop['dest_housenumber'] . ', '
                             . $stop['dest_postalcode'] . ' ' . $stop['city_name'];
            }

            $routeUrl = '';

            if ($showRoute && $addresses !== []) {

                $destination = array_pop($addresses);

                $routeUrl = 'https://www.google.com/maps/dir/?api=1'
                          . '&origin=' . urlencode('Zijpendaalseweg 167, 6814 CJ Arnhem')
                          . '&destination=' . urlencode($destination);

                if ($addresses !== []) {
                    $routeUrl = $routeUrl . '&waypoints=' . urlencode(implode('|', $addresses));
                }
            }
            ?>

            <div id="stops-<?= $rideId ?>" popover class="ride-popup">

                <div class="ride-popup-header">
                    <h2 class="ride-popup-title">
                        Rit <?= $rideId ?> &middot; <?= e($rideDate) ?>
                    </h2>

                    <button type="button"
                            class="ride-popup-close"
                            popovertarget="stops-<?= $rideId ?>"
                            popovertargetaction="hide"
                            aria-label="Sluiten">
                        &times;
                    </button>
                </div>

                <p class="subtitle">
                    <?= e($ride['license_plate']) ?> &middot;
                    <?= (int) $ride['stop_count'] ?> stops &middot;
                    <?= (int) $ride['total_pallets'] ?> pallets
                </p>

                <?php if ($stops === []) { ?>

                    <p>Deze rit heeft nog geen stops.</p>

                <?php } else { ?>

                    <div class="table-container">
                        <table class="orders-table">

                            <thead>
                                <tr>
                                    <th>Nr.</th>
                                    <th>Klant</th>
                                    <th>Adres</th>
                                    <th>Pallets</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($stops as $stop) { ?>

                                    <?php
                                    if (!empty($stop['companyname'])) {
                                        $customerName = $stop['companyname'];
                                    } else {
                                        $customerName = $stop['first_name'] . ' ' . $stop['last_name'];
                                    }

                                    $address = $stop['dest_street'] . ' ' . $stop['dest_housenumber'] . ', '
                                             . $stop['dest_postalcode'] . ' ' . $stop['city_name'];

                                    if (!empty($stop['delivered_at'])) {
                                        $stopText  = 'Geleverd';
                                        $stopClass = 'klant-status-completed';
                                    } else {
                                        $stopText  = 'Nog leveren';
                                        $stopClass = 'klant-status-open';
                                    }
                                    ?>

                                    <tr>
                                        <td><?= (int) $stop['stop_sequence'] ?></td>
                                        <td><?= e($customerName) ?></td>
                                        <td><?= e($address) ?></td>
                                        <td><?= (int) $stop['pallet_count'] ?></td>
                                        <td>
                                            <span class="klant-status <?= $stopClass ?>">
                                                <?= $stopText ?>
                                            </span>
                                        </td>
                                    </tr>

                                <?php } ?>

                            </tbody>

                        </table>
                    </div>

                <?php } ?>

                <?php if ($routeUrl !== '') { ?>

                    <div class="ride-popup-actions">
                        <a class="btn btn-primary"
                           href="<?= e($routeUrl) ?>"
                           target="_blank"
                           rel="noopener">
                            Route plannen
                        </a>
                    </div>

                <?php } ?>

            </div>

        <?php } ?>

    <?php } ?>

    <?php if ($totalPages > 1) { ?>

        <div class="filterbar">

            <?php if ($currentPage > 1) { ?>
                <a class="btn btn-filter"
                   href="index.php?page=dashboard_driver&p=<?= $currentPage - 1 ?><?= e($urlExtra) ?>">
                    &larr; Vorige
                </a>
            <?php } ?>

            <span class="filter-label">
                Pagina <?= $currentPage ?> van <?= $totalPages ?>
            </span>

            <?php if ($currentPage < $totalPages) { ?>
                <a class="btn btn-filter"
                   href="index.php?page=dashboard_driver&p=<?= $currentPage + 1 ?><?= e($urlExtra) ?>">
                    Volgende &rarr;
                </a>
            <?php } ?>

        </div>

    <?php } ?>

</div>