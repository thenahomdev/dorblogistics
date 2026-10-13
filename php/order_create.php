<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../database/cnn.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/city.php';
require_once __DIR__ . '/../lib/geo.php';

$user = current_user($conn);

if ($user === null || $user['role'] !== 'klant') {
    header('Location: ../index.php?page=login');
    exit;
}

$stmt = $conn->prepare("SELECT user_id FROM customer WHERE user_id = :id AND status = 'actief'");
$stmt->execute([':id' => $user['user_id']]);
$customerId = $stmt->fetchColumn();

if ($customerId === false) {
    header('Location: ../index.php');
    exit;
}

$input = [
    'dest_street'      => trim((string) ($_POST['dest_street'] ?? '')),
    'dest_housenumber' => trim((string) ($_POST['dest_housenumber'] ?? '')),
    'dest_postalcode'  => trim((string) ($_POST['dest_postalcode'] ?? '')),
    'dest_city'        => trim((string) ($_POST['dest_city'] ?? '')),
    'dest_land_id'     => trim((string) ($_POST['dest_land_id'] ?? '')),
    'pallet_count'     => trim((string) ($_POST['pallet_count'] ?? '')),
];

$errors = [];

if ($input['dest_street'] === '' || mb_strlen($input['dest_street']) > 120) {
    $errors[] = 'Vul een straatnaam in van maximaal 120 tekens.';
}

if (!preg_match('/^[0-9]{1,5}[a-zA-Z0-9 \-]{0,5}$/', $input['dest_housenumber'])) {
    $errors[] = 'Vul een geldig huisnummer in, bijvoorbeeld 12 of 12a.';
}

if ($input['dest_postalcode'] === '' || mb_strlen($input['dest_postalcode']) > 10) {
    $errors[] = 'Vul een postcode in van maximaal 10 tekens.';
}

if ($input['dest_city'] === '' || mb_strlen($input['dest_city']) > 80) {
    $errors[] = 'Vul een plaats in van maximaal 80 tekens.';
}

$pallets = filter_var($input['pallet_count'], FILTER_VALIDATE_INT);

if ($pallets === false || $pallets < 1 || $pallets > 30) {
    $errors[] = 'Het aantal pallets moet tussen 1 en 30 liggen.';
}

$landId = filter_var($input['dest_land_id'], FILTER_VALIDATE_INT);
$land   = false;

if ($landId !== false) {
    $stmt = $conn->prepare("SELECT land_id, name, country_code, surcharge FROM land WHERE land_id = :id");
    $stmt->execute([':id' => $landId]);
    $land = $stmt->fetch();
}

if ($land === false) {
    $errors[] = 'Kies een land waar DORB naartoe rijdt.';
}

$tariff = false;

if ($pallets !== false && $pallets >= 1 && $pallets <= 30) {
    $stmt = $conn->prepare("
        SELECT tariff_id, price_per_km_per_pallet
        FROM tariff
        WHERE :pallets BETWEEN min_pallets AND COALESCE(max_pallets, 255)
          AND valid_from <= CURDATE()
          AND (valid_until IS NULL OR valid_until >= CURDATE())
        ORDER BY valid_from DESC
        LIMIT 1
    ");
    $stmt->execute([':pallets' => $pallets]);
    $tariff = $stmt->fetch();

    if ($tariff === false) {
        $errors[] = 'Voor dit aantal pallets is geen tarief gevonden. Neem contact op met DORB.';
    }
}

$distance = null;

if ($errors === []) {
    $distance = delivery_distance_km(
        $input['dest_street'],
        $input['dest_housenumber'],
        $input['dest_postalcode'],
        $input['dest_city'],
        $land['country_code']
    );

    if ($distance === null) {
        $errors[] = 'Wij konden de afstand naar dit adres niet berekenen. '
            . 'Controleer het adres of probeer het later opnieuw.';
    }
}

if ($errors !== []) {
    $_SESSION['order_errors'] = $errors;
    $_SESSION['order_old']    = $input;
    header('Location: ../index.php?page=offerte');
    exit;
}

$unitPrice = (float) $tariff['price_per_km_per_pallet'];
$surcharge = (float) $land['surcharge'];
$cityId    = city_id($conn, $input['dest_city'], (int) $land['land_id']);

$stmt = $conn->prepare("
    INSERT INTO orders
        (customer_id, tariff_id, pallet_count,
         dest_street, dest_housenumber, dest_postalcode, dest_city_id,
         distance_km, unit_price, surcharge, status)
    VALUES
        (:customer_id, :tariff_id, :pallet_count,
         :dest_street, :dest_housenumber, :dest_postalcode, :dest_city_id,
         :distance_km, :unit_price, :surcharge, 'offerte')
");
$stmt->execute([
    ':customer_id'      => $customerId,
    ':tariff_id'        => $tariff['tariff_id'],
    ':pallet_count'     => $pallets,
    ':dest_street'      => $input['dest_street'],
    ':dest_housenumber' => $input['dest_housenumber'],
    ':dest_postalcode'  => $input['dest_postalcode'],
    ':dest_city_id'     => $cityId,
    ':distance_km'      => $distance,
    ':unit_price'       => $unitPrice,
    ':surcharge'        => $surcharge,
]);

$_SESSION['order_success'] = [
    'order_id'     => (int) $conn->lastInsertId(),
    'pallet_count' => $pallets,
    'dest_city'    => $input['dest_city'],
    'land_name'    => $land['name'],
    'distance_km'  => $distance,
    'unit_price'   => $unitPrice,
    'surcharge'    => $surcharge,
    'total_price'  => round($distance * $pallets * $unitPrice + $surcharge, 2),
];

header('Location: ../index.php?page=offerte');
exit;
