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

require_once __DIR__ . '/recent_orders_klant.inc.php';