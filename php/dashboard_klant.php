<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database/cnn.php';

function klantRedirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></noscript>';
    }
    exit;
}

function klantFlash(string $message, string $type): void
{
    $_SESSION['dashboard_message'] = $message;
    $_SESSION['dashboard_message_type'] = $type;
}

function translateOrderStatus(string $status): string
{
    return match ($status) {
        'offerte'      => 'Offerte',
        'bevestigd'    => 'Bevestigd',
        'ingepland'    => 'Ingepland',
        'onderweg'     => 'Onderweg',
        'geleverd'     => 'Geleverd',
        'gefactureerd' => 'Gefactureerd',
        'geannuleerd'  => 'Geannuleerd',
        default        => ucfirst($status),
    };
}

function orderStatusClass(string $status): string
{
    return match ($status) {
        'offerte'      => 'status-offerte',
        'bevestigd'    => 'status-bevestigd',
        'ingepland'    => 'status-ingepland',
        'onderweg'     => 'status-onderweg',
        'geleverd'     => 'status-geleverd',
        'gefactureerd' => 'status-gefactureerd',
        'geannuleerd'  => 'status-geannuleerd',
        default        => '',
    };
}

$userId = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);

if (!$userId) {
    klantRedirect('../index.php?page=login');
}

$userId = (int) $userId;

$customerQuery = $conn->prepare("
    SELECT
        c.user_id,
        c.type,
        c.status,
        c.companyname,
        c.first_name,
        c.last_name,
        c.phonenumber,
        c.iban,
        c.street,
        c.housenumber,
        c.postalcode,
        c.city_id,
        c.signed_up,
        u.email,
        ci.name AS city_name,
        l.name  AS country_name
    FROM customer c
    INNER JOIN users u
        ON u.user_id = c.user_id
    LEFT JOIN city ci
        ON ci.city_id = c.city_id
    LEFT JOIN land l
        ON l.land_id = ci.land_id
    WHERE c.user_id = :user_id
      AND c.status = 'actief'
    LIMIT 1
");

$customerQuery->execute([':user_id' => $userId]);

$customer = $customerQuery->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    klantRedirect('../index.php?page=login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {

        $firstName   = trim($_POST['first_name'] ?? '');
        $lastName    = trim($_POST['last_name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phoneNumber = trim($_POST['phonenumber'] ?? '');
        $street      = trim($_POST['street'] ?? '');
        $houseNumber = trim($_POST['housenumber'] ?? '');
        $postalCode  = trim($_POST['postalcode'] ?? '');
        $cityName    = trim($_POST['city_name'] ?? '');

        if (
            $firstName === '' ||
            $lastName === '' ||
            $email === '' ||
            $phoneNumber === '' ||
            $street === '' ||
            $houseNumber === '' ||
            $postalCode === '' ||
            $cityName === ''
        ) {
            klantFlash('Vul alle verplichte velden in.', 'error');
            klantRedirect('../index.php?page=dashboard_klant');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            klantFlash('Vul een geldig e-mailadres in.', 'error');
            klantRedirect('../index.php?page=dashboard_klant');
        }

        $emailQuery = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE LOWER(email) = LOWER(:email)
              AND user_id <> :user_id
            LIMIT 1
        ");

        $emailQuery->execute([
            ':email'   => $email,
            ':user_id' => $userId,
        ]);

        if ($emailQuery->fetch(PDO::FETCH_ASSOC)) {
            klantFlash('Dit e-mailadres is al in gebruik. Kies een ander e-mailadres.', 'error');
            klantRedirect('../index.php?page=dashboard_klant');
        }

        $phoneQuery = $conn->prepare("
            SELECT user_id
            FROM customer
            WHERE phonenumber = :phonenumber
              AND user_id <> :user_id
            LIMIT 1
        ");

        $phoneQuery->execute([
            ':phonenumber' => $phoneNumber,
            ':user_id'     => $userId,
        ]);

        if ($phoneQuery->fetch(PDO::FETCH_ASSOC)) {
            klantFlash('Dit telefoonnummer is al in gebruik.', 'error');
            klantRedirect('../index.php?page=dashboard_klant');
        }

        $cityQuery = $conn->prepare("
            SELECT city_id
            FROM city
            WHERE LOWER(TRIM(name)) = LOWER(TRIM(:city_name))
            LIMIT 1
        ");

        $cityQuery->execute([':city_name' => $cityName]);

        $city = $cityQuery->fetch(PDO::FETCH_ASSOC);

        if (!$city) {
            klantFlash('De gekozen plaats bestaat niet.', 'error');
            klantRedirect('../index.php?page=dashboard_klant');
        }

        $cityId = (int) $city['city_id'];

        try {
            $conn->beginTransaction();

            $updateUser = $conn->prepare("
                UPDATE users
                SET email = :email
                WHERE user_id = :user_id
            ");

            $updateUser->execute([
                ':email'   => $email,
                ':user_id' => $userId,
            ]);

            $updateCustomer = $conn->prepare("
                UPDATE customer
                SET
                    first_name  = :first_name,
                    last_name   = :last_name,
                    phonenumber = :phonenumber,
                    street      = :street,
                    housenumber = :housenumber,
                    postalcode  = :postalcode,
                    city_id     = :city_id
                WHERE user_id = :user_id
            ");

            $updateCustomer->execute([
                ':first_name'  => $firstName,
                ':last_name'   => $lastName,
                ':phonenumber' => $phoneNumber,
                ':street'      => $street,
                ':housenumber' => $houseNumber,
                ':postalcode'  => $postalCode,
                ':city_id'     => $cityId,
                ':user_id'     => $userId,
            ]);

            $conn->commit();

            klantFlash('Je gegevens zijn opgeslagen.', 'success');

        } catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            klantFlash('Er is iets misgegaan bij het opslaan van je gegevens.', 'error');
        }

        klantRedirect('../index.php?page=dashboard_klant');
    }

    if ($action === 'change_password') {

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            klantFlash('Vul alle wachtwoordvelden in.', 'error');
            klantRedirect('../index.php?page=dashboard_klant');
        }

        if ($newPassword !== $confirmPassword) {
            klantFlash('De nieuwe wachtwoorden komen niet overeen.', 'error');
            klantRedirect('../index.php?page=dashboard_klant');
        }

        if (strlen($newPassword) < 8) {
            klantFlash('Het nieuwe wachtwoord moet minimaal 8 tekens bevatten.', 'error');
            klantRedirect('../index.php?page=dashboard_klant');
        }

        $passwordQuery = $conn->prepare("
            SELECT password_hash
            FROM users
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $passwordQuery->execute([':user_id' => $userId]);

        $passwordData = $passwordQuery->fetch(PDO::FETCH_ASSOC);

        if (
            !$passwordData ||
            !password_verify($currentPassword, $passwordData['password_hash'])
        ) {
            klantFlash('Het huidige wachtwoord is niet correct.', 'error');
            klantRedirect('../index.php?page=dashboard_klant');
        }

        $updatePassword = $conn->prepare("
            UPDATE users
            SET password_hash = :password_hash
            WHERE user_id = :user_id
        ");

        $updatePassword->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':user_id'       => $userId,
        ]);

        klantFlash('Je wachtwoord is gewijzigd.', 'success');
        klantRedirect('../index.php?page=dashboard_klant');
    }
}

$citiesQuery = $conn->query("
    SELECT
        ci.city_id,
        ci.name AS city_name,
        l.name  AS country_name
    FROM city ci
    LEFT JOIN land l
        ON l.land_id = ci.land_id
    ORDER BY l.name, ci.name
");

$cities = $citiesQuery->fetchAll(PDO::FETCH_ASSOC);

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
        l.name  AS destination_country
    FROM orders o
    LEFT JOIN city ci
        ON ci.city_id = o.dest_city_id
    LEFT JOIN land l
        ON l.land_id = ci.land_id
    WHERE o.customer_id = :customer_id
    ORDER BY o.order_date DESC, o.order_id DESC
    LIMIT 5
");

$orderQuery->execute([':customer_id' => $userId]);

$recentOrders = $orderQuery->fetchAll(PDO::FETCH_ASSOC);

$countQuery = $conn->prepare("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(status IN ('offerte', 'bevestigd', 'ingepland', 'onderweg')), 0) AS open_orders
    FROM orders
    WHERE customer_id = :customer_id
");

$countQuery->execute([':customer_id' => $userId]);

$counts = $countQuery->fetch(PDO::FETCH_ASSOC) ?: [];

$totalOrders = (int) ($counts['total_orders'] ?? 0);
$openOrders  = (int) ($counts['open_orders'] ?? 0);

$canDeleteAccount = ($openOrders === 0);

foreach ($recentOrders as &$order) {

    $order['status_text']  = translateOrderStatus((string) $order['status']);
    $order['status_class'] = orderStatusClass((string) $order['status']);

    $order['total_price'] =
        ((float) $order['unit_price'] * (int) $order['pallet_count'])
        + (float) $order['surcharge'];
}

unset($order);