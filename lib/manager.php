<?php
declare(strict_types=1);

const MANAGER_PERIODS = ['week' => 'Deze week', 'maand' => 'Deze maand', 'jaar' => 'Dit jaar'];

const MANAGER_ROLES = ['chauffeur', 'planner', 'manager'];

const MANAGER_STATUSES = [
    'offerte',
    'bevestigd',
    'ingepland',
    'onderweg',
    'geleverd',
    'gefactureerd',
    'geannuleerd',
];

const MANAGER_SERVICE = ['in' => 'In dienst', 'uit' => 'Uit dienst'];

const MANAGER_OPEN_STATUSES = "'offerte','bevestigd','ingepland','onderweg'";

const MANAGER_DELIVERED_STATUSES = "'geleverd','gefactureerd'";

const MANAGER_PER_PAGE = 10;

const MANAGER_PAGE_RESET = [
    'functie'         => 'pagina_medewerker',
    'dienst'          => 'pagina_medewerker',
    'zoek_medewerker' => 'pagina_medewerker',
    'status'          => 'pagina_order',
    'periode_order'   => 'pagina_order',
    'zoek_order'      => 'pagina_order',
];

function manager_period(string $period): string
{
    return array_key_exists($period, MANAGER_PERIODS) ? $period : 'week';
}

function manager_period_condition(string $period): string
{
    return match (manager_period($period)) {
        'maand' => 'YEAR(o.order_date) = YEAR(CURDATE()) AND MONTH(o.order_date) = MONTH(CURDATE())',
        'jaar'  => 'YEAR(o.order_date) = YEAR(CURDATE())',
        default => 'YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1)',
    };
}

function manager_page_number(mixed $value): int
{
    $page = filter_var($value, FILTER_VALIDATE_INT);

    return $page === false || $page < 1 ? 1 : $page;
}

function manager_url(array $filters, array $overrides = [], string $anchor = ''): string
{
    $params = array_merge($filters, $overrides);

    foreach (array_keys($overrides) as $key) {
        if (array_key_exists($key, MANAGER_PAGE_RESET)) {
            $params[MANAGER_PAGE_RESET[$key]] = '';
        }
    }

    $params['page'] = 'dashboard_manager';

    $params = array_filter($params, static fn ($value): bool => $value !== '' && $value !== null);

    return 'index.php?' . http_build_query($params) . ($anchor === '' ? '' : '#' . $anchor);
}

function manager_paginate(
    PDO $conn,
    string $select,
    string $from,
    string $order,
    array $params,
    int $page,
    int $perPage = MANAGER_PER_PAGE
): array {
    $countStmt = $conn->prepare("SELECT COUNT(*) $from");
    $countStmt->execute($params);

    $total  = (int) $countStmt->fetchColumn();
    $pages  = max(1, (int) ceil($total / $perPage));
    $page   = min(max(1, $page), $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare("SELECT $select $from $order LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);

    $rows = $stmt->fetchAll();

    return [
        'rows'  => $rows,
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
        'from'  => $total === 0 ? 0 : $offset + 1,
        'to'    => $offset + count($rows),
    ];
}

function manager_stats(PDO $conn, string $period): array
{
    $condition = manager_period_condition($period);
    $open      = MANAGER_OPEN_STATUSES;
    $delivered = MANAGER_DELIVERED_STATUSES;

    $row = $conn->query("
        SELECT
            COALESCE(SUM(o.status IN ($open)), 0) AS open_orders,
            COALESCE(SUM(o.status IN ($delivered)), 0) AS delivered_orders,
            COALESCE(SUM(CASE WHEN o.status IN ($delivered) THEN o.pallet_count ELSE 0 END), 0) AS delivered_pallets,
            COALESCE(SUM(CASE WHEN o.status IN ($delivered)
                THEN ROUND(o.distance_km * o.pallet_count * o.unit_price + o.surcharge, 2)
                ELSE 0 END), 0) AS revenue
        FROM orders o
        WHERE $condition
    ")->fetch();

    return [
        'open_orders'       => (int) ($row['open_orders'] ?? 0),
        'delivered_orders'  => (int) ($row['delivered_orders'] ?? 0),
        'delivered_pallets' => (int) ($row['delivered_pallets'] ?? 0),
        'revenue'           => (float) ($row['revenue'] ?? 0),
    ];
}

function manager_employees(PDO $conn, string $role = '', string $service = '', string $search = '', int $page = 1): array
{
    $from = "
        FROM employee e
        JOIN users u ON u.user_id = e.user_id
        JOIN role r ON r.role_id = u.role_id
        LEFT JOIN driver d ON d.employee_id = e.employee_id
    ";

    $conditions = [];
    $params     = [];

    if (in_array($role, MANAGER_ROLES, true)) {
        $conditions[]    = 'r.name = :role';
        $params[':role'] = $role;
    }

    if ($service === 'in') {
        $conditions[] = 'e.in_service = 1';
    } elseif ($service === 'uit') {
        $conditions[] = 'e.in_service = 0';
    }

    if ($search !== '') {
        $conditions[] = "(CONCAT(e.first_name, ' ', e.last_name) LIKE :name OR u.email LIKE :email)";

        $params[':name']  = '%' . $search . '%';
        $params[':email'] = '%' . $search . '%';
    }

    if ($conditions !== []) {
        $from .= ' WHERE ' . implode(' AND ', $conditions);
    }

    return manager_paginate(
        $conn,
        'e.employee_id, e.first_name, e.last_name, e.in_service, r.name AS role, d.license_type',
        $from,
        'ORDER BY r.name, e.last_name, e.first_name',
        $params,
        $page
    );
}

function manager_orders(PDO $conn, string $status = '', string $period = '', string $search = '', int $page = 1): array
{
    $from = "
        FROM orders o
        JOIN customer c ON c.user_id = o.customer_id
        JOIN city ci ON ci.city_id = o.dest_city_id
        JOIN land l ON l.land_id = ci.land_id
    ";

    $conditions = [];
    $params     = [];

    if (in_array($status, MANAGER_STATUSES, true)) {
        $conditions[]      = 'o.status = :status';
        $params[':status'] = $status;
    }

    if (array_key_exists($period, MANAGER_PERIODS)) {
        $conditions[] = manager_period_condition($period);
    }

    if ($search !== '') {
        $conditions[] = "(COALESCE(c.companyname, CONCAT(c.first_name, ' ', c.last_name)) LIKE :customer
                          OR ci.name LIKE :city
                          OR o.dest_postalcode LIKE :postalcode
                          OR o.order_id = :order_id)";

        $params[':customer']   = '%' . $search . '%';
        $params[':city']       = '%' . $search . '%';
        $params[':postalcode'] = '%' . $search . '%';
        $params[':order_id']   = ctype_digit($search) ? (int) $search : 0;
    }

    if ($conditions !== []) {
        $from .= ' WHERE ' . implode(' AND ', $conditions);
    }

    return manager_paginate(
        $conn,
        "o.order_id,
         o.order_date,
         o.status,
         o.pallet_count,
         COALESCE(c.companyname, CONCAT(c.first_name, ' ', c.last_name)) AS customer_name,
         CONCAT(o.dest_street, ' ', o.dest_housenumber) AS dest_address,
         o.dest_postalcode,
         ci.name AS dest_city,
         l.name AS land_name,
         ROUND(o.distance_km * o.pallet_count * o.unit_price + o.surcharge, 2) AS total_price",
        $from,
        'ORDER BY o.order_date DESC, o.order_id DESC',
        $params,
        $page
    );
}

function manager_employee(PDO $conn, int $employeeId): ?array
{
    $stmt = $conn->prepare("
        SELECT e.employee_id,
               e.first_name,
               e.last_name,
               e.phonenumber,
               e.hire_date,
               e.in_service,
               r.name AS role,
               u.email,
               u.last_login,
               d.license_type,
               d.license_number,
               d.license_expiry,
               (SELECT GROUP_CONCAT(l.name ORDER BY l.name SEPARATOR ', ')
                FROM driver_land dl
                JOIN land l ON l.land_id = dl.land_id
                WHERE dl.employee_id = e.employee_id) AS countries,
               (SELECT COUNT(*) FROM ride WHERE driver_id = e.employee_id) AS ride_count,
               (SELECT COUNT(*) FROM orders WHERE planner_id = e.employee_id) AS claimed_orders
        FROM employee e
        JOIN users u ON u.user_id = e.user_id
        JOIN role r ON r.role_id = u.role_id
        LEFT JOIN driver d ON d.employee_id = e.employee_id
        WHERE e.employee_id = :id
    ");
    $stmt->execute([':id' => $employeeId]);

    return $stmt->fetch() ?: null;
}

function manager_order(PDO $conn, int $orderId): ?array
{
    $stmt = $conn->prepare("
        SELECT o.order_id,
               o.order_date,
               o.delivery_date,
               o.status,
               o.pallet_count,
               o.distance_km,
               o.unit_price,
               o.surcharge,
               ROUND(o.distance_km * o.pallet_count * o.unit_price + o.surcharge, 2) AS total_price,
               CONCAT(o.dest_street, ' ', o.dest_housenumber) AS dest_address,
               o.dest_postalcode,
               ci.name AS dest_city,
               l.name AS land_name,
               COALESCE(c.companyname, CONCAT(c.first_name, ' ', c.last_name)) AS customer_name,
               c.first_name AS customer_first_name,
               c.last_name AS customer_last_name,
               c.phonenumber AS customer_phone,
               c.iban,
               u.email AS customer_email,
               CONCAT(p.first_name, ' ', p.last_name) AS planner_name,
               r.ride_id,
               r.ride_date,
               r.status AS ride_status,
               CONCAT(dr.first_name, ' ', dr.last_name) AS driver_name,
               t.license_plate,
               i.invoice_number,
               i.invoice_date,
               i.status AS invoice_status
        FROM orders o
        JOIN customer c ON c.user_id = o.customer_id
        JOIN users u ON u.user_id = c.user_id
        JOIN city ci ON ci.city_id = o.dest_city_id
        JOIN land l ON l.land_id = ci.land_id
        LEFT JOIN employee p ON p.employee_id = o.planner_id
        LEFT JOIN ride_order ro ON ro.order_id = o.order_id
        LEFT JOIN ride r ON r.ride_id = ro.ride_id
        LEFT JOIN employee dr ON dr.employee_id = r.driver_id
        LEFT JOIN truck t ON t.truck_id = r.truck_id
        LEFT JOIN invoice i ON i.order_id = o.order_id
        WHERE o.order_id = :id
    ");
    $stmt->execute([':id' => $orderId]);

    return $stmt->fetch() ?: null;
}
