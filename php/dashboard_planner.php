<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../database/cnn.php';
require_once __DIR__ . '/order_filter.php';

$currentUserId = isset($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : 0;

$currentEmployeeId = 0;

if ($currentUserId > 0) {

    $employeeSql = "
        SELECT e.employee_id
        FROM employee e
        JOIN users u ON u.user_id = e.user_id
        JOIN role r ON r.role_id = u.role_id
        WHERE e.user_id = :user_id
          AND r.name = 'planner'
          AND e.in_service = 1
        LIMIT 1
    ";

    $employeeStmt = $conn->prepare($employeeSql);

    $employeeStmt->execute([
        ':user_id' => $currentUserId
    ]);

    $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $currentEmployeeId = (int) $employee['employee_id'];
    }
}

$sql = "
    SELECT
        o.order_id,
        o.customer_id,

        c.first_name AS customer_first_name,
        c.last_name AS customer_last_name,
        c.companyname AS customer_companyname,

        o.pallet_count,

        o.dest_street,
        o.dest_housenumber,
        o.dest_postalcode,

        ci.name AS city_name,
        l.name AS landnaam,
        l.country_code,

        o.distance_km,
        o.unit_price,
        o.surcharge,

        o.order_date,
        o.delivery_date,
        o.status,

        o.planner_id,

        CONCAT(
            e.first_name,
            ' ',
            e.last_name
        ) AS medewerker_naam

    FROM orders o

    LEFT JOIN customer c
        ON c.user_id = o.customer_id

    LEFT JOIN city ci
        ON ci.city_id = o.dest_city_id

    LEFT JOIN land l
        ON l.land_id = ci.land_id

    LEFT JOIN employee e
        ON e.employee_id = o.planner_id

    ORDER BY
        CASE l.country_code
            WHEN 'NL' THEN 1
            WHEN 'DE' THEN 2
            WHEN 'LU' THEN 3
            WHEN 'BE' THEN 4
            ELSE 5
        END,

        ci.name,
        o.delivery_date IS NULL,
        o.delivery_date ASC,
        o.order_id ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute();

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orderCount = count($orders);

$classOpenstaand = 'active';
$classAfgehandeld = '';
$checkedMijnOrders = '';
$currentFilter = 'openstaand';

$orderRows = '';

$previousLand = '';

foreach ($orders as $order) {

    $orderId = (int) $order['order_id'];

    $customerId = (int) $order['customer_id'];

    $palletCount = (int) $order['pallet_count'];

    $plannerId = !empty($order['planner_id'])
        ? (int) $order['planner_id']
        : 0;

    $isMine = (
        $currentEmployeeId > 0 &&
        $plannerId === $currentEmployeeId
    );

    $rowClass = $isMine
        ? 'order-row assigned-row'
        : 'order-row';

    if (!empty($order['customer_companyname'])) {

        $customerName = (string) $order['customer_companyname'];

    } else {

        $customerName = trim(
            (string) $order['customer_first_name'] . ' ' .
            (string) $order['customer_last_name']
        );
    }

    $customerName = htmlspecialchars(
        $customerName,
        ENT_QUOTES,
        'UTF-8'
    );

    $address = trim(
        (string) $order['dest_street'] . ' ' .
        (string) $order['dest_housenumber']
    );

    $address = htmlspecialchars(
        $address,
        ENT_QUOTES,
        'UTF-8'
    );

    $postalcode = htmlspecialchars(
        (string) $order['dest_postalcode'],
        ENT_QUOTES,
        'UTF-8'
    );

    $city = htmlspecialchars(
        (string) $order['city_name'],
        ENT_QUOTES,
        'UTF-8'
    );

    $landNaam = !empty($order['landnaam'])
        ? htmlspecialchars(
            (string) $order['landnaam'],
            ENT_QUOTES,
            'UTF-8'
        )
        : 'Onbekend';

    $statusRaw = (string) $order['status'];

    $statusLabels = [
        'offerte' => 'Offerte',
        'bevestigd' => 'Ingepland',
        'ingepland' => 'Ingepland',
        'onderweg' => 'Onderweg',
        'geleverd' => 'Geleverd',
        'gefactureerd' => 'Gefactureerd',
        'geannuleerd' => 'Geannuleerd'
    ];

    $status = htmlspecialchars(
        $statusLabels[$statusRaw] ?? $statusRaw,
        ENT_QUOTES,
        'UTF-8'
    );

    $statusType = getOrderFilterStatus(
        $statusRaw
    );

    $claimableStatuses = [
        'offerte',
        'bevestigd'
    ];

    $isClaimable = in_array(
        $statusRaw,
        $claimableStatuses,
        true
    );

    $isPlannable = ($statusRaw === 'bevestigd');

    if ($isPlannable) {

        $checkbox = '
            <input
                type="checkbox"
                name="selected_orders[]"
                value="' . $orderId . '"
                onchange="updateSelection()"
            >
        ';

    } else {

        $checkbox = '<span class="no-select">&ndash;</span>';
    }

    if (!empty($order['delivery_date'])) {

        $timestamp = strtotime(
            (string) $order['delivery_date']
        );

        if ($timestamp !== false) {

            $deliveryDate = date(
                'd-m-Y',
                $timestamp
            );

        } else {

            $deliveryDate = '-';
        }

    } else {

        $deliveryDate = '-';
    }

    $distance = number_format(
        (float) $order['distance_km'],
        2,
        ',',
        '.'
    );

    $employeeName = !empty($order['medewerker_naam'])
        ? htmlspecialchars(
            (string) $order['medewerker_naam'],
            ENT_QUOTES,
            'UTF-8'
        )
        : 'Onbekend';

    if ($plannerId > 0) {

        if ($isMine) {

            $assignedText = 'Mijn order';

        } else {

            $assignedText = $employeeName;
        }

    } else {

        $assignedText = 'Niet toegewezen';
    }

    if (!$isClaimable) {

        $claimButton = '
            <span class="claimed-by-other">
                Niet beschikbaar
            </span>
        ';

    } elseif ($plannerId === 0) {

        $claimButton = '
            <button
                type="button"
                class="btn btn-claim"
                data-order-id="' . $orderId . '"
                onclick="claimOrder(' . $orderId . ', this)"
            >
                Inplannen
            </button>
        ';

    } elseif ($isMine) {

        $claimButton = '
            <span class="claimed-by-me">
                Mijn order
            </span>
        ';

    } else {

        $claimButton = '
            <span class="claimed-by-other">
                Bezet
            </span>
        ';
    }

    if ($landNaam !== $previousLand) {

        $orderRows .= '
            <tr class="land-row">
                <td colspan="10">
                    ' . $landNaam . '
                </td>
            </tr>
        ';

        $previousLand = $landNaam;
    }

    $orderRows .= '
        <tr
            class="' . $rowClass . '"
            data-order-id="' . $orderId . '"
            data-assigned="' . ($isMine ? '1' : '0') . '"
            data-planner-id="' . $plannerId . '"
            data-pallets="' . $palletCount . '"
            data-status="' . htmlspecialchars(
                $statusType,
                ENT_QUOTES,
                'UTF-8'
            ) . '"
        >

            <td class="col-select">
                ' . $checkbox . '
            </td>

            <td>
                ' . $customerName . '
            </td>

            <td>
                ' . $address . '<br>
                ' . $postalcode . ' ' . $city . '
            </td>

            <td>
                ' . $landNaam . '
            </td>

            <td>
                ' . $palletCount . '
            </td>

            <td>
                ' . $deliveryDate . '
            </td>

            <td>
                <span class="status">
                    ' . $status . '
                </span>
            </td>

            <td class="assigned-cell">
                ' . $assignedText . '
            </td>

            <td>
                ' . $distance . ' km
            </td>

            <td class="claim-cell">
                ' . $claimButton . '
            </td>

        </tr>
    ';
}

if ($orderCount === 0) {

    $orderRows = '
        <tr class="empty-row">

            <td colspan="10">

                Er zijn geen orders gevonden in de database.

            </td>

        </tr>
    ';
}