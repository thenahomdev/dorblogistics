<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database/cnn.php';

$userId = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

$userId = (int) $userId;

$customerQuery = $conn->prepare("
    SELECT
        c.user_id,
        c.status
    FROM customer c
    WHERE c.user_id = :user_id
      AND c.status = 'actief'
    LIMIT 1
");

$customerQuery->execute([
    ':user_id' => $userId
]);

$customer = $customerQuery->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: index.php?page=login');
    exit;
}

$orderQuery = $conn->prepare("
    SELECT
        o.order_id,
        o.customer_id,
        o.tariff_id,
        o.planner_id,
        o.pallet_count,
        o.dest_street,
        o.dest_housenumber,
        o.dest_postalcode,
        o.dest_city_id,
        o.distance_km,
        o.unit_price,
        o.surcharge,
        o.order_date,
        o.delivery_date,
        o.status,
        ci.name AS destination_city,
        l.name AS destination_country
    FROM orders o
    LEFT JOIN city ci
        ON ci.city_id = o.dest_city_id
    LEFT JOIN land l
        ON l.land_id = ci.land_id
    WHERE o.customer_id = :customer_id
    ORDER BY o.order_date DESC, o.order_id DESC
");

$orderQuery->execute([
    ':customer_id' => $userId
]);

$orders = $orderQuery->fetchAll(PDO::FETCH_ASSOC);

$statusTexts = [
    'offerte' => 'Offerte',
    'bevestigd' => 'Bevestigd',
    'ingepland' => 'Ingepland',
    'onderweg' => 'Onderweg',
    'geleverd' => 'Geleverd',
    'gefactureerd' => 'Gefactureerd',
    'geannuleerd' => 'Geannuleerd'
];

$statusClasses = [
    'offerte' => 'status-offerte',
    'bevestigd' => 'status-bevestigd',
    'ingepland' => 'status-ingepland',
    'onderweg' => 'status-onderweg',
    'geleverd' => 'status-geleverd',
    'gefactureerd' => 'status-gefactureerd',
    'geannuleerd' => 'status-geannuleerd'
];

$openStatuses = [
    'offerte',
    'bevestigd',
    'ingepland',
    'onderweg'
];

foreach ($orders as &$order) {
    $order['total_price'] =
        ((float) $order['unit_price'] * (int) $order['pallet_count'])
        + (float) $order['surcharge'];

    $order['status_text'] =
        $statusTexts[$order['status']] ?? ucfirst($order['status']);

    $order['status_class'] =
        $statusClasses[$order['status']] ?? 'status-default';

    $order['order_type'] = in_array(
        $order['status'],
        $openStatuses,
        true
    ) ? 'lopend' : 'afgerond';
}

unset($order);
?>

<div class="customer-orders">

    <div class="customer-orders-header">
        <div>
            <h1>Mijn bestellingen</h1>
            <p>Bekijk hier al je bestellingen en de actuele status van je zendingen.</p>
        </div>

        <a href="index.php?page=offerte" class="btn btn-accent">
            Nieuwe bestelling
        </a>
    </div>

    <div class="orders-filter">
        <button type="button" class="orders-filter-button active" data-filter="alle">
            Alle
        </button>

        <button type="button" class="orders-filter-button" data-filter="lopend">
            Lopend
        </button>

        <button type="button" class="orders-filter-button" data-filter="afgerond">
            Afgerond
        </button>
    </div>

    <?php if (empty($orders)): ?>

        <div class="customer-orders-empty">
            <h2>Je hebt nog geen bestellingen</h2>
            <p>
                Zodra je een bestelling hebt geplaatst, verschijnt deze hier.
            </p>

            <a href="index.php?page=offerte" class="btn btn-accent">
                Nieuwe bestelling plaatsen
            </a>
        </div>

    <?php else: ?>

        <div class="customer-orders-table-wrapper">

            <table class="customer-orders-table">
                <thead>
                    <tr>
                        <th>Ordernummer</th>
                        <th>Datum</th>
                        <th>Bestemming</th>
                        <th>Pallets</th>
                        <th>Prijs</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($orders as $order): ?>

                    <tr
                        class="order-row"
                        data-status-group="<?= e($order['order_type']) ?>"
                        data-order-id="<?= (int) $order['order_id'] ?>"
                    >
                        <td>
                            #<?= (int) $order['order_id'] ?>
                        </td>

                        <td>
                            <?= e(date('d-m-Y', strtotime((string) $order['order_date']))) ?>
                        </td>

                        <td>
                            <strong><?= e($order['destination_city'] ?? '') ?></strong>

                            <?php if (!empty($order['destination_country'])): ?>
                                <span>
                                    <?= e($order['destination_country']) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= (int) $order['pallet_count'] ?>
                        </td>

                        <td>
                            <?= money((float) $order['total_price']) ?>
                        </td>

                        <td>
                            <span class="order-status <?= e($order['status_class']) ?>">
                                <?= e($order['status_text']) ?>
                            </span>
                        </td>

                        <td>
                            <button
                                type="button"
                                class="order-detail-button"
                                data-order-id="<?= (int) $order['order_id'] ?>"
                            >
                                Details
                            </button>
                        </td>
                    </tr>

                    <tr
                        class="order-detail-row"
                        id="order-detail-<?= (int) $order['order_id'] ?>"
                        style="display: none;"
                    >
                        <td colspan="7">

                            <div class="order-detail-content">

                                <div class="order-detail-item">
                                    <span>Adres:</span>
                                    <strong>
                                        <?= e($order['dest_street'] ?? '') ?>
                                        <?= e($order['dest_housenumber'] ?? '') ?>
                                    </strong>
                                </div>

                                <div class="order-detail-item">
                                    <span>Postcode:</span>
                                    <strong>
                                        <?= e($order['dest_postalcode'] ?? '') ?>
                                    </strong>
                                </div>

                                <div class="order-detail-item">
                                    <span>Bestemming:</span>
                                    <strong>
                                        <?= e($order['destination_city'] ?? '') ?>,
                                        <?= e($order['destination_country'] ?? '') ?>
                                    </strong>
                                </div>

                                <div class="order-detail-item">
                                    <span>Afstand:</span>
                                    <strong>
                                        <?= number_format((float) $order['distance_km'], 0, ',', '.') ?> km
                                    </strong>
                                </div>

                                <div class="order-detail-item">
                                    <span>Prijs per pallet:</span>
                                    <strong>
                                        <?= money((float) $order['unit_price']) ?>
                                    </strong>
                                </div>

                                <div class="order-detail-item">
                                    <span>Toeslag:</span>
                                    <strong>
                                        <?= money((float) $order['surcharge']) ?>
                                    </strong>
                                </div>

                                <div class="order-detail-item">
                                    <span>Totale prijs:</span>
                                    <strong>
                                        <?= money((float) $order['total_price']) ?>
                                    </strong>
                                </div>

                                <div class="order-detail-item">
                                    <span>Leverdatum:</span>
                                    <strong>
                                        <?php if (!empty($order['delivery_date'])): ?>
                                            <?= e(date('d-m-Y', strtotime((string) $order['delivery_date']))) ?>
                                        <?php else: ?>
                                            Nog niet gepland
                                        <?php endif; ?>
                                    </strong>
                                </div>

                            </div>

                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        </div>

        <div class="orders-no-results" style="display: none;">
            <p>Er zijn geen bestellingen binnen deze categorie.</p>
        </div>

    <?php endif; ?>

</div>