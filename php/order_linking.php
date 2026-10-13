<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../database/cnn.php';
require_once __DIR__ . '/../lib/auth.php';

$user = current_user($conn);

if ($user === null || $user['role'] !== 'planner') {
    header('Location: ../index.php?page=login');
    exit;
}

$plannerId = (int) $user['employee_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=dashboard_planner');
    exit;
}

$sequence = $_POST['sequence'] ?? [];
$rideDate = trim((string) ($_POST['departure_date'] ?? ''));
$departureTime = trim((string) ($_POST['departure_time'] ?? ''));
$truckId  = (int) ($_POST['truck_id'] ?? 0);
$driverId = (int) ($_POST['driver_id'] ?? 0);

$orderIds = array_map('intval', array_keys($sequence));
$orderIds = array_values(array_filter($orderIds));

$errors = [];

function back_to_linking(array $errors, array $orderIds, array $old): never
{
    $_SESSION['errors']   = $errors;
    $_SESSION['ride_old'] = $old;

    if ($orderIds === []) {
        header('Location: ../index.php?page=dashboard_planner');
        exit;
    }

    $url = '../index.php?page=order_linking&orders=' . implode(',', $orderIds);

    if (!empty($old['departure_date'])) {
        $url = $url . '&date=' . urlencode($old['departure_date']);
    }

    header('Location: ' . $url);
    exit;
}

function customer_name(array $row): string
{
    if (!empty($row['companyname'])) {
        return (string) $row['companyname'];
    }

    return $row['first_name'] . ' ' . $row['last_name'];
}

$old = [
    'departure_date' => $rideDate,
    'truck_id'       => $truckId,
    'driver_id'      => $driverId,
    'departure-time' => $departureTime,
];


if ($orderIds === []) {
    $errors[] = 'Er zijn geen orders geselecteerd.';
}

if ($rideDate === '') {
    $errors[] = 'Vul een vertrekdatum in.';
} elseif ($rideDate < date('Y-m-d')) {
    $errors[] = 'De vertrekdatum kan niet in het verleden liggen.';
}

if ($truckId < 1) {
    $errors[] = 'Kies een vrachtwagen.';
}

if ($driverId < 1) {
    $errors[] = 'Kies een chauffeur.';
}

if ($errors !== []) {
    back_to_linking($errors, $orderIds, $old);
}

$placeholders = implode(',', array_fill(0, count($orderIds), '?'));

$sqlOrders = "SELECT o.order_id, o.pallet_count, o.status,
                     ci.land_id, ci.name AS city_name,
                     c.companyname, c.first_name, c.last_name, distance_km
              FROM orders o
              JOIN city ci ON ci.city_id = o.dest_city_id
              JOIN customer c ON c.user_id = o.customer_id
              WHERE o.order_id IN ($placeholders)";

$stmtOrders = $conn->prepare($sqlOrders);
$stmtOrders->execute($orderIds);
$orders = $stmtOrders->fetchAll();

if (count($orders) !== count($orderIds)) {
    $errors[] = 'Een of meer orders bestaan niet.';
}

$totalPallets = 0;
$totalDistance = 0;
$landIds = [];

foreach ($orders as $order) {

    if ($order['status'] !== 'bevestigd') {
        $errors[] = 'De order van ' . customer_name($order)
            . ' naar ' . $order['city_name']
            . ' is niet bevestigd.';
    }

    $totalPallets = $totalPallets + (int) $order['pallet_count'];
    $totalDistance = $totalDistance + (float) $order['distance_km'];
    $landIds[] = (int) $order['land_id'];
}

$landIds = array_unique($landIds);

$sqlTruck = "SELECT t.truck_id, t.in_service,
                    tt.pallet_capacity, tt.required_license
             FROM truck t
             JOIN trucktype tt ON tt.trucktype_id = t.trucktype_id
             WHERE t.truck_id = :truck_id";

$stmtTruck = $conn->prepare($sqlTruck);
$stmtTruck->execute([':truck_id' => $truckId]);
$truck = $stmtTruck->fetch();

if ($truck === false) {

    $errors[] = 'De gekozen vrachtwagen bestaat niet.';
} else {

    if (!$truck['in_service']) {
        $errors[] = 'Deze vrachtwagen is niet beschikbaar.';
    }

    if ($totalPallets > (int) $truck['pallet_capacity']) {
        $errors[] = 'Er passen maar ' . (int) $truck['pallet_capacity']
            . ' pallets in deze vrachtwagen, u heeft er ' . $totalPallets . '.';
    }
}

$driverName = '';

$sqlDriver = "SELECT d.employee_id, d.license_type, d.license_expiry,
                     e.first_name, e.last_name, e.in_service
              FROM driver d
              JOIN employee e ON e.employee_id = d.employee_id
              WHERE d.employee_id = :driver_id";

$stmtDriver = $conn->prepare($sqlDriver);
$stmtDriver->execute([':driver_id' => $driverId]);
$driver = $stmtDriver->fetch();

if ($driver === false) {

    $errors[] = 'De gekozen chauffeur bestaat niet.';
} else {

    $driverName = $driver['first_name'] . ' ' . $driver['last_name'];

    if (!$driver['in_service']) {
        $errors[] = $driverName . ' is niet in dienst.';
    }

    if ($driver['license_expiry'] < date('Y-m-d')) {
        $errors[] = 'Het rijbewijs van ' . $driverName . ' is verlopen.';
    }

    if (
        $truck !== false
        && $truck['required_license'] === 'D'
        && $driver['license_type'] !== 'D'
    ) {
        $errors[] = $driverName . ' heeft geen D-rijbewijs en mag deze vrachtwagen niet rijden.';
    }
}

if ($driver !== false && $landIds !== []) {

    $landPlaceholders = implode(',', array_fill(0, count($landIds), '?'));

    $sqlLands = "SELECT land_id
                 FROM driver_land
                 WHERE employee_id = ?
                   AND land_id IN ($landPlaceholders)";

    $params = array_merge([$driverId], array_values($landIds));

    $stmtLands = $conn->prepare($sqlLands);
    $stmtLands->execute($params);
    $allowedLands = $stmtLands->fetchAll();

    if (count($allowedLands) !== count($landIds)) {
        $errors[] = $driverName . ' mag niet in alle landen van deze rit rijden.';
    }
}

// Rijdt deze chauffeur die dag al een rit?
$sqlBusy = "SELECT COUNT(*)
            FROM ride
            WHERE driver_id = :driver_id
              AND ride_date = :ride_date
              AND status <> 'geannuleerd'";

$stmtBusy = $conn->prepare($sqlBusy);
$stmtBusy->execute([
    ':driver_id' => $driverId,
    ':ride_date' => $rideDate
]);

if ((int) $stmtBusy->fetchColumn() > 0) {
    $errors[] = 'Deze chauffeur heeft op die datum al een rit.';
}

// Rijdt deze vrachtwagen die dag al een rit?
$sqlTruckBusy = "SELECT COUNT(*)
                 FROM ride
                 WHERE truck_id = :truck_id
                   AND ride_date = :ride_date
                   AND status <> 'geannuleerd'";

$stmtTruckBusy = $conn->prepare($sqlTruckBusy);
$stmtTruckBusy->execute([
    ':truck_id'  => $truckId,
    ':ride_date' => $rideDate
]);

if ((int) $stmtTruckBusy->fetchColumn() > 0) {
    $errors[] = 'Deze vrachtwagen heeft op die datum al een rit.';
}

if ($errors !== []) {
    back_to_linking($errors, $orderIds, $old);
}

try {

    $conn->beginTransaction();
    // bezig 
    $sqlRide = "INSERT INTO ride (truck_id, driver_id, planner_id, ride_date, departure_time, total_distance_km, `status`)
                    VALUES      (:truck_id, :driver_id, :planner_id, :ride_date, :departure_time, :total_distance_km, :status)";
    $stmtRide = $conn->prepare($sqlRide);

    $stmtRide->execute([
        ':truck_id'          => $truckId,
        ':driver_id'         => $driverId,
        ':planner_id'        => $plannerId,
        ':ride_date'         => $rideDate,
        ':departure_time'    => $departureTime,
        ':total_distance_km' => $totalDistance,
        ':status'            => 'gepland'
    ]);

    $rideId = (int) $conn->lastInsertId();

    $sqlStop = "INSERT INTO ride_order (ride_id, order_id, stop_sequence)
                VALUES (:ride_id, :order_id, :stop_sequence)";

    $stmtStop = $conn->prepare($sqlStop);

    $sqlStatus = "UPDATE orders
                  SET `status` = 'ingepland',
                      planner_id = :planner_id,
                      delivery_date = :ride_date
                  WHERE order_id = :order_id";

    $stmtStatus = $conn->prepare($sqlStatus);

    foreach ($orderIds as $orderId) {

        $stopNumber = (int) ($sequence[$orderId] ?? 1);

        $stmtStop->execute([
            ':ride_id'       => $rideId,
            ':order_id'      => $orderId,
            ':stop_sequence' => $stopNumber
        ]);

        $stmtStatus->execute([
            ':planner_id' => $plannerId,
            ':ride_date'  => $rideDate,
            ':order_id'   => $orderId
        ]);
    }

    $conn->commit();
} catch (Throwable $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Ride creation failed: ' . $exception->getMessage());

    back_to_linking(
        ['Er ging iets mis bij het opslaan van de rit.'],
        $orderIds,
        $old
    );
}

// Alles gelukt: terug naar het dashboard.
unset($_SESSION['ride_old']);

$_SESSION['success'] = 'Rit ' . $rideId . ' is aangemaakt met '
    . count($orderIds) . ' stops.';

header('Location: ../index.php?page=dashboard_planner');
exit;
