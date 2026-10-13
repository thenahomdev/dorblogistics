<?php
session_start();

require_once __DIR__ . '/../database/cnn.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/mail.php';

$user = current_user($conn);

if ($user === null || $user['role'] !== 'manager') {
    header('Location: ../index.php?page=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=manage_customers');
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

if ($action === 'approve') {
    $status = 'actief';
    $label  = 'goedgekeurd';
} elseif ($action === 'block') {
    $status = 'geblokkeerd';
    $label  = 'geblokkeerd';
} elseif ($action === 'unblock') {
    $status = 'actief';
    $label  = 'gedeblokkeerd';
} else {
    $_SESSION['errors'] = ['Onbekende actie.'];
    header('Location: ../index.php?page=manage_customers');
    exit;
}

$sqlCustomer = "SELECT c.companyname, c.first_name, c.last_name, u.email
                FROM customer c
                JOIN users u ON u.user_id = c.user_id
                WHERE c.user_id = :user_id";

$stmtCustomer = $conn->prepare($sqlCustomer);
$stmtCustomer->execute([
    ':user_id' => $userId
]);
$customer = $stmtCustomer->fetch();

if ($customer === false) {
    $_SESSION['errors'] = ['Deze klant bestaat niet.'];
    header('Location: ../index.php?page=manage_customers');
    exit;
}

if (!empty($customer['companyname'])) {
    $name = $customer['companyname'];
} else {
    $name = $customer['first_name'] . ' ' . $customer['last_name'];
}

$sqlUpdate = "UPDATE customer
              SET `status` = :status
              WHERE user_id = :user_id";

$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->execute([
    ':status'  => $status,
    ':user_id' => $userId
]);

$message = 'Klant ' . $name . ' is ' . $label . '.';

if ($action === 'approve') {
    $mailSent = send_approval_mail($customer['email'], $name);
} elseif ($action === 'block') {
    $mailSent = send_blocked_mail($customer['email'], $name);
} else {
    $mailSent = send_unblocked_mail($customer['email'], $name);
}

if ($mailSent) {
    $message = $message . ' Er is een e-mail gestuurd naar ' . $customer['email'] . '.';
} else {
    $message = $message . ' Let op: de e-mail kon niet worden verstuurd.';
}

$_SESSION['success'] = $message;

header('Location: ../index.php?page=manage_customers');
exit;