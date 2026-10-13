<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../database/cnn.php';

if (!isset($conn)) {

    echo json_encode([
        'success' => false,
        'message' => 'Databaseverbinding ontbreekt.'
    ]);

    exit;
}

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Geen user_id in sessie.'
    ]);

    exit;
}


$currentUserId = (int) $_SESSION['user_id'];

$orderId = isset($_POST['order_id'])
    ? (int) $_POST['order_id']
    : 0;


if ($orderId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Geen geldige order_id ontvangen.'
    ]);

    exit;
}

$sql = "
    SELECT
        e.employee_id,
        e.first_name,
        e.last_name,
        r.name AS role,
        e.in_service
    FROM employee e
    JOIN users u ON u.user_id = e.user_id
    JOIN role r ON r.role_id = u.role_id
    WHERE e.user_id = :user_id
      AND r.name = 'planner'
      AND e.in_service = 1
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ':user_id' => $currentUserId
]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$employee) {

    echo json_encode([
        'success' => false,
        'message' => 'Geen actieve planner gevonden.',
        'user_id' => $currentUserId
    ]);

    exit;
}


$currentEmployeeId = (int) $employee['employee_id'];

$sql = "
    SELECT
        order_id,
        planner_id,
        status
    FROM orders
    WHERE order_id = :order_id
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ':order_id' => $orderId
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$order) {

    echo json_encode([
        'success' => false,
        'message' => 'Order bestaat niet.',
        'order_id' => $orderId
    ]);

    exit;
}

$claimableStatuses = [
    'offerte',
    'bevestigd',
    'ingepland',
    'onderweg'
];

$orderStatus = (string) $order['status'];


if (!in_array(
    $orderStatus,
    $claimableStatuses,
    true
)) {

    echo json_encode([
        'success' => false,
        'message' => 'Deze order kan niet meer worden geclaimd.',
        'order_id' => $orderId,
        'status' => $orderStatus
    ]);

    exit;
}

$existingEmployeeId = !empty($order['planner_id'])
    ? (int) $order['planner_id']
    : 0;


if ($existingEmployeeId > 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Deze order is al geclaimd.',
        'order_id' => $orderId,
        'employee_id' => $existingEmployeeId
    ]);

    exit;
}

$sql = "
    UPDATE orders
    SET planner_id = :employee_id
    WHERE order_id = :order_id
      AND planner_id IS NULL
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ':employee_id' => $currentEmployeeId,
    ':order_id' => $orderId
]);

$sql = "
    SELECT
        planner_id,
        status
    FROM orders
    WHERE order_id = :order_id
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ':order_id' => $orderId
]);

$check = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$check) {

    echo json_encode([
        'success' => false,
        'message' => 'Order kon na update niet worden gevonden.'
    ]);

    exit;
}


$databaseEmployeeId = (int) $check['planner_id'];

if ($databaseEmployeeId === $currentEmployeeId) {

    echo json_encode([
        'success' => true,
        'message' => 'Order succesvol geclaimd.',
        'order_id' => $orderId,
        'employee_id' => $currentEmployeeId,
        'employee_name' =>
            $employee['first_name'] . ' ' .
            $employee['last_name']
    ]);

    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'De order kon niet worden geclaimd.',
    'order_id' => $orderId,
    'current_employee_id' => $currentEmployeeId,
    'database_employee_id' => $databaseEmployeeId
]);

exit;
