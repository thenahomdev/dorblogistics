<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database/cnn.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmtRole = $conn->prepare(
    'SELECT r.name
     FROM users u
     INNER JOIN role r ON r.role_id = u.role_id
     WHERE u.user_id = :user_id
     LIMIT 1'
);

$stmtRole->execute([
    ':user_id' => $userId
]);

$currentRole = $stmtRole->fetchColumn();

if ($currentRole !== 'manager') {
    header('Location: ../index.php');
    exit;
}

if (empty($_SESSION['employee_csrf'])) {
    $_SESSION['employee_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['employee_csrf'];

$stmtRoles = $conn->prepare(
    "SELECT role_id, name
     FROM role
     WHERE name IN ('chauffeur', 'planner', 'manager')
     ORDER BY
        CASE name
            WHEN 'chauffeur' THEN 1
            WHEN 'planner' THEN 2
            WHEN 'manager' THEN 3
        END"
);

$stmtRoles->execute();

$employeeRoles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

$stmtCountries = $conn->prepare(
    "SELECT land_id, name, country_code
     FROM land
     ORDER BY
        CASE country_code
            WHEN 'NL' THEN 1
            WHEN 'BE' THEN 2
            WHEN 'DE' THEN 3
            WHEN 'LU' THEN 4
            ELSE 5
        END,
        name"
);

$stmtCountries->execute();

$employeeCountries = $stmtCountries->fetchAll(PDO::FETCH_ASSOC);

$errors = $_SESSION['employee_errors'] ?? [];
$old = $_SESSION['employee_old'] ?? [];
$success = $_SESSION['employee_success'] ?? null;

unset(
    $_SESSION['employee_errors'],
    $_SESSION['employee_old'],
    $_SESSION['employee_success']
);

if (!is_array($errors)) {
    $errors = [];
}

if (!is_array($old)) {
    $old = [];
}

if (!function_exists('employee_old')) {
    function employee_old(string $field, array $values): string
    {
        return isset($values[$field])
            ? htmlspecialchars((string) $values[$field], ENT_QUOTES, 'UTF-8')
            : '';
    }
}