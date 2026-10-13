<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database/cnn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);

if (!$userId) {
    header('Location: ../index.php');
    exit;
}

$userId = (int) $userId;

try {
    $conn->beginTransaction();

    $lockCustomer = $conn->prepare("
        SELECT user_id
        FROM customer
        WHERE user_id = :user_id
          AND status = 'actief'
        FOR UPDATE
    ");

    $lockCustomer->execute([
        ':user_id' => $userId
    ]);

    if (!$lockCustomer->fetch(PDO::FETCH_ASSOC)) {
        $conn->rollBack();

        header('Location: ../index.php?page=dashboard_klant');
        exit;
    }

    $openOrdersQuery = $conn->prepare("
        SELECT COUNT(*)
        FROM orders
        WHERE customer_id = :customer_id
          AND status IN ('offerte', 'bevestigd', 'ingepland', 'onderweg')
    ");

    $openOrdersQuery->execute([
        ':customer_id' => $userId
    ]);

    if ((int) $openOrdersQuery->fetchColumn() > 0) {
        $conn->rollBack();

        $_SESSION['dashboard_message'] = 'Als je een bestelling open hebt staan, kun je je account niet verwijderen.';
        $_SESSION['dashboard_message_type'] = 'error';

        header('Location: ../index.php?page=dashboard_klant');
        exit;
    }

    $deleteCustomer = $conn->prepare("
        UPDATE customer
        SET status = 'inactief'
        WHERE user_id = :user_id
          AND status = 'actief'
    ");

    $deleteCustomer->execute([
        ':user_id' => $userId
    ]);

    if ($deleteCustomer->rowCount() < 1) {
        $conn->rollBack();

        header('Location: ../index.php?page=dashboard_klant');
        exit;
    }

    $conn->commit();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: ../index.php');
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $_SESSION['dashboard_message'] = 'Er is iets misgegaan bij het verwijderen van je account.';
    $_SESSION['dashboard_message_type'] = 'error';

    header('Location: ../index.php?page=dashboard_klant');
    exit;
}