<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../database/cnn.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/pdf.php';

$user = current_user($conn);

if ($user === null || $user['role'] !== 'klant') {
    header('Location: ../index.php?page=login');
    exit;
}

function back_to_quote(string $message): never
{
    $_SESSION['order_errors'] = [$message];
    header('Location: ../index.php?page=offerte');
    exit;
}

$orderId = filter_var($_POST['order_id'] ?? '', FILTER_VALIDATE_INT);

if ($orderId === false) {
    back_to_quote('Er is geen geldige order gekozen.');
}

$stmt = $conn->prepare("
    SELECT o.order_id, o.pallet_count,
           o.dest_street, o.dest_housenumber, o.dest_postalcode,
           dc.name AS dest_city, dl.name AS land_name,
           o.distance_km, o.unit_price, o.surcharge,
           ROUND(o.distance_km * o.pallet_count * o.unit_price + o.surcharge, 2) AS total_price,
           o.status,
           c.first_name, c.last_name, c.companyname,
           c.street, c.housenumber, c.postalcode, cc.name AS city_name
    FROM orders o
    JOIN city dc ON dc.city_id = o.dest_city_id
    JOIN land dl ON dl.land_id = dc.land_id
    JOIN customer c ON c.user_id = o.customer_id
    JOIN city cc ON cc.city_id = c.city_id
    WHERE o.order_id = :order_id AND c.user_id = :user_id
");
$stmt->execute([':order_id' => $orderId, ':user_id' => $user['user_id']]);
$order = $stmt->fetch();

if ($order === false) {
    back_to_quote('Deze order hoort niet bij uw account.');
}

if ($order['status'] === 'geannuleerd') {
    back_to_quote('Deze offerte is afgewezen en kan niet meer worden bevestigd.');
}

if ($order['status'] !== 'offerte') {
    back_to_quote('Deze order is al bevestigd.');
}

try {
    $pdf = order_confirmation_pdf($order);
} catch (Throwable) {
    back_to_quote('De orderbevestiging kon niet worden gemaakt. Probeer het opnieuw.');
}

$pdf = order_confirmation_pdf($order);

$_SESSION['order_confirmed'] = [
    'order_id'    => (int) $order['order_id'],
    'total_price' => $order['total_price'],
    'dest_city'   => $order['dest_city'],
    'mail_to'     => $user['email'],
    'mail_sent'   => send_order_confirmation_mail($user['email'], $user['name'], $order, $pdf),
];

header('Location: ../index.php?page=offerte');
exit;
