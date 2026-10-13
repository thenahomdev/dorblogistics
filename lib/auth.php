<?php
declare(strict_types=1);

function current_user(PDO $conn): ?array
{
    static $user = null;
    static $fetched = false;

    if ($fetched) {
        return $user;
    }
    $fetched = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT u.user_id,
               u.email,
               r.name       AS role,
               c.user_id    AS customer_id,
               c.companyname,
               c.first_name AS customer_first_name,
               c.last_name  AS customer_last_name,
               e.employee_id,
               e.first_name AS employee_first_name,
               e.last_name  AS employee_last_name
        FROM users u
        JOIN role r ON r.role_id = u.role_id
        LEFT JOIN customer c ON c.user_id = u.user_id
        LEFT JOIN employee e ON e.user_id = u.user_id
        WHERE u.user_id = :id
    ");
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        unset($_SESSION['user_id']);
        return null;
    }

    if ($row['employee_id'] !== null) {
        $name = $row['employee_first_name'] . ' ' . $row['employee_last_name'];
    } elseif ($row['customer_id'] !== null) {
        $name = $row['companyname'] ?: $row['customer_first_name'] . ' ' . $row['customer_last_name'];
    } else {
        $name = $row['email'];
    }

    $user = [
        'user_id'     => (int) $row['user_id'],
        'employee_id' => $row['employee_id'] !== null ? (int) $row['employee_id'] : null,
        'email'       => $row['email'],
        'role'        => $row['role'],
        'name'        => $name,
    ];

    return $user;
}

function may_view(?array $user, array $allowed): bool
{
    if ($allowed === []) {
        return true;
    }

    return $user !== null && in_array($user['role'], $allowed, true);
}

function dashboard_page(?string $role): string
{
    return match ($role) {
        'klant'     => 'dashboard_klant',
        'chauffeur' => 'dashboard_driver',
        'planner'   => 'dashboard_planner',
        'manager'   => 'dashboard_manager',
        default     => 'home',
    };
}
