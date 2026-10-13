<?php
require_once __DIR__ . '/../lib/manager.php';

$orderId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$order   = $orderId === false ? null : manager_order($conn, $orderId);
?>

<section class="section">
    <div class="container">

        <?php if ($order === null): ?>
            <h1 class="section-title">Bestelling niet gevonden</h1>
            <p class="section-intro">Deze bestelling bestaat niet of is verwijderd.</p>
            <a class="btn btn-accent" href="index.php?page=dashboard_manager">Terug naar het overzicht</a>
        <?php else: ?>
            <h1 class="section-title">Bestelling <?= (int) $order['order_id'] ?></h1>
            <p class="section-intro">
                Besteld op <?= e(date('d-m-Y H:i', strtotime((string) $order['order_date']))) ?>
                door <?= e($order['customer_name']) ?>.
            </p>

            <div class="manager-detail">

                <div class="panel">
                    <h2 class="panel-title">Zending</h2>

                    <div class="manager-row">
                        <span>Status</span>
                        <span class="manager-status manager-status-<?= e($order['status']) ?>">
                            <?= e($order['status']) ?>
                        </span>
                    </div>
                    <div class="manager-row">
                        <span>Bestemming</span>
                        <span>
                            <?= e($order['dest_address']) ?><br>
                            <?= e($order['dest_postalcode'] . ' ' . $order['dest_city']) ?>
                            (<?= e($order['land_name']) ?>)
                        </span>
                    </div>
                    <div class="manager-row">
                        <span>Aantal pallets</span>
                        <span><?= (int) $order['pallet_count'] ?></span>
                    </div>
                    <div class="manager-row">
                        <span>Afstand vanaf Arnhem</span>
                        <span><?= e(number_format((float) $order['distance_km'], 2, ',', '.')) ?> km</span>
                    </div>
                    <div class="manager-row">
                        <span>Leverdatum</span>
                        <span>
                            <?= $order['delivery_date'] === null
                                ? 'nog niet gepland'
                                : e(date('d-m-Y', strtotime((string) $order['delivery_date']))) ?>
                        </span>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title">Prijs</h2>

                    <div class="manager-row">
                        <span>Tarief</span>
                        <span><?= money($order['unit_price']) ?> per km per pallet</span>
                    </div>
                    <div class="manager-row">
                        <span>Vervoer</span>
                        <span><?= money((float) $order['total_price'] - (float) $order['surcharge']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>Toeslag <?= e($order['land_name']) ?></span>
                        <span><?= money($order['surcharge']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span><strong>Totaal</strong></span>
                        <span><strong><?= money($order['total_price']) ?></strong></span>
                    </div>
                    <div class="manager-row">
                        <span>Factuur</span>
                        <span>
                            <?= $order['invoice_number'] === null
                                ? 'nog niet gefactureerd'
                                : e($order['invoice_number'] . ' (' . $order['invoice_status'] . ')') ?>
                        </span>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title">Klant</h2>

                    <div class="manager-row">
                        <span>Naam</span>
                        <span><?= e($order['customer_name']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>Contactpersoon</span>
                        <span><?= e($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>E-mailadres</span>
                        <span><?= e($order['customer_email']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>Telefoonnummer</span>
                        <span><?= e($order['customer_phone']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>IBAN</span>
                        <span><?= e($order['iban']) ?></span>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title">Planning</h2>

                    <div class="manager-row">
                        <span>Planner</span>
                        <span><?= $order['planner_name'] === null ? 'nog niet toegewezen' : e($order['planner_name']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>Rit</span>
                        <span>
                            <?= $order['ride_id'] === null
                                ? 'nog niet ingepland'
                                : e('rit ' . $order['ride_id'] . ' op ' . date('d-m-Y', strtotime((string) $order['ride_date']))) ?>
                        </span>
                    </div>
                    <div class="manager-row">
                        <span>Chauffeur</span>
                        <span><?= $order['driver_name'] === null ? '-' : e($order['driver_name']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>Vrachtwagen</span>
                        <span><?= $order['license_plate'] === null ? '-' : e($order['license_plate']) ?></span>
                    </div>
                </div>

            </div>

            <p class="note">
                <a href="index.php?page=dashboard_manager">Terug naar het overzicht</a>
            </p>
        <?php endif; ?>

    </div>
</section>
