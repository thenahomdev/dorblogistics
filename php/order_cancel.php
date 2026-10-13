<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../database/cnn.php';
require_once __DIR__ . '/../lib/auth.php';

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
    SELECT o.order_id, o.status
    FROM orders o
    JOIN customer c ON c.user_id = o.customer_id
    WHERE o.order_id = :order_id AND c.user_id = :user_id
");
$stmt->execute([':order_id' => $orderId, ':user_id' => $user['user_id']]);
$order = $stmt->fetch();

if ($order === false) {
    back_to_quote('Deze order hoort niet bij uw account.');
}

if ($order['status'] !== 'offerte') {
    back_to_quote('Deze offerte kan niet meer worden afgewezen.');
}

$update = $conn->prepare("UPDATE orders SET status = 'geannuleerd' WHERE order_id = :id AND status = 'offerte'");
$update->execute([':id' => $orderId]);

if ($update->rowCount() === 0) {
    back_to_quote('Deze offerte kan niet meer worden afgewezen.');
}

$_SESSION['order_cancelled'] = ['order_id' => (int) $order['order_id']];

header('Location: ../index.php?page=offerte');
exit;
