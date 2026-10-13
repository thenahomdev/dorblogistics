<?php
declare(strict_types=1);

const STATS_QUICK = [
    'week'     => 'Deze week',
    'maand'    => 'Deze maand',
    'kwartaal' => 'Dit kwartaal',
    'jaar'     => 'Dit jaar',
];

const STATS_DELIVERED = "o.status IN ('geleverd','gefactureerd') AND o.delivery_date BETWEEN :from AND :to";

const STATS_MAX_BUCKETS = 200;

const STATS_MONTHS = [
    1 => 'jan', 2 => 'feb', 3 => 'mrt', 4 => 'apr', 5 => 'mei', 6 => 'jun',
    7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec',
];

function stats_quick_range(string $quick): array
{
    $today = new DateTimeImmutable('today');

    return match ($quick) {
        'week'     => [$today->modify('monday this week'), $today->modify('sunday this week')],
        'kwartaal' => stats_quarter($today),
        'jaar'     => [$today->modify('first day of january'), $today->modify('last day of december')],
        default    => [$today->modify('first day of this month'), $today->modify('last day of this month')],
    };
}

function stats_quarter(DateTimeImmutable $today): array
{
    $firstMonth = (intdiv((int) $today->format('n') - 1, 3) * 3) + 1;
    $start      = $today->setDate((int) $today->format('Y'), $firstMonth, 1);

    return [$start, $start->modify('+2 months')->modify('last day of this month')];
}

function stats_date(mixed $value): ?DateTimeImmutable
{
    if (!is_string($value) || $value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
}

function stats_period(array $query): array
{
    $from = stats_date($query['van'] ?? null);
    $to   = stats_date($query['tot'] ?? null);

    if ($from !== null && $to !== null) {
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from, 'to' => $to, 'quick' => ''];
    }

    $quick = (string) ($query['snel'] ?? 'jaar');

    if (!array_key_exists($quick, STATS_QUICK)) {
        $quick = 'jaar';
    }

    [$start, $end] = stats_quick_range($quick);

    return ['from' => $start, 'to' => $end, 'quick' => $quick];
}

function stats_grouping(DateTimeImmutable $from, DateTimeImmutable $to): string
{
    return $from->diff($to)->days <= 92 ? 'week' : 'maand';
}

function stats_buckets(DateTimeImmutable $from, DateTimeImmutable $to, string $grouping): array
{
    $buckets = [];

    if ($grouping === 'week') {
        $cursor = $from->modify('monday this week');
        $last   = $to->modify('monday this week');

        while ($cursor <= $last && count($buckets) < STATS_MAX_BUCKETS) {
            $buckets[$cursor->format('o') . $cursor->format('W')] = 'wk ' . $cursor->format('W');
            $cursor = $cursor->modify('+1 week');
        }

        return $buckets;
    }

    $cursor = $from->modify('first day of this month');
    $last   = $to->modify('first day of this month');

    while ($cursor <= $last && count($buckets) < STATS_MAX_BUCKETS) {
        $buckets[$cursor->format('Y-m')] = STATS_MONTHS[(int) $cursor->format('n')] . ' ' . $cursor->format('Y');
        $cursor = $cursor->modify('+1 month');
    }

    return $buckets;
}

function stats_rides(PDO $conn, string $from, string $to): array
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS rides,
               COALESCE(SUM(r.total_distance_km), 0) AS driven_km,
               COALESCE(SUM(r.billed_km), 0) AS billed_km,
               COALESCE(SUM(r.stops), 0) AS stops,
               COALESCE(SUM(r.pallets), 0) AS pallets,
               COALESCE(SUM(r.capacity), 0) AS capacity
        FROM (
            SELECT ride.ride_id,
                   ride.total_distance_km,
                   tt.pallet_capacity AS capacity,
                   (SELECT COUNT(*) FROM ride_order ro WHERE ro.ride_id = ride.ride_id) AS stops,
                   (SELECT COALESCE(SUM(o.pallet_count), 0)
                    FROM ride_order ro
                    JOIN orders o ON o.order_id = ro.order_id
                    WHERE ro.ride_id = ride.ride_id) AS pallets,
                   (SELECT COALESCE(SUM(o.distance_km), 0)
                    FROM ride_order ro
                    JOIN orders o ON o.order_id = ro.order_id
                    WHERE ro.ride_id = ride.ride_id) AS billed_km
            FROM ride
            JOIN truck t ON t.truck_id = ride.truck_id
            JOIN trucktype tt ON tt.trucktype_id = t.trucktype_id
            WHERE ride.status = 'afgerond'
              AND ride.total_distance_km IS NOT NULL
              AND ride.ride_date BETWEEN :from AND :to
        ) AS r
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);

    $row = $stmt->fetch() ?: [];

    $rides    = (int) ($row['rides'] ?? 0);
    $driven   = (float) ($row['driven_km'] ?? 0);
    $billed   = (float) ($row['billed_km'] ?? 0);
    $capacity = (int) ($row['capacity'] ?? 0);

    return [
        'rides'            => $rides,
        'driven_km'        => $driven,
        'billed_km'        => $billed,
        'difference_km'    => $billed - $driven,
        'difference_pct'   => $billed > 0 ? ($billed - $driven) / $billed * 100 : null,
        'stops'            => (int) ($row['stops'] ?? 0),
        'orders_per_ride'  => $rides > 0 ? (int) ($row['stops'] ?? 0) / $rides : null,
        'occupancy_pct'    => $capacity > 0 ? (int) ($row['pallets'] ?? 0) / $capacity * 100 : null,
    ];
}

function stats_totals(PDO $conn, string $from, string $to): array
{
    $delivered = STATS_DELIVERED;

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS orders,
               COALESCE(SUM(o.pallet_count), 0) AS pallets,
               COALESCE(SUM(ROUND(o.distance_km * o.pallet_count * o.unit_price + o.surcharge, 2)), 0) AS revenue
        FROM orders o
        WHERE $delivered
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);

    $row = $stmt->fetch() ?: [];

    return [
        'orders'  => (int) ($row['orders'] ?? 0),
        'pallets' => (int) ($row['pallets'] ?? 0),
        'revenue' => (float) ($row['revenue'] ?? 0),
    ];
}

function stats_series(PDO $conn, string $from, string $to, array $buckets, string $grouping): array
{
    $delivered = STATS_DELIVERED;
    $key       = $grouping === 'week'
        ? "CAST(YEARWEEK(o.delivery_date, 1) AS CHAR)"
        : "DATE_FORMAT(o.delivery_date, '%Y-%m')";

    $stmt = $conn->prepare("
        SELECT $key AS bucket,
               COALESCE(SUM(ROUND(o.distance_km * o.pallet_count * o.unit_price + o.surcharge, 2)), 0) AS revenue,
               COALESCE(SUM(o.pallet_count), 0) AS pallets
        FROM orders o
        WHERE $delivered
        GROUP BY bucket
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);

    $found = [];

    foreach ($stmt->fetchAll() as $row) {
        $found[(string) $row['bucket']] = $row;
    }

    $labels   = [];
    $revenue  = [];
    $pallets  = [];

    foreach ($buckets as $bucket => $label) {
        $labels[]  = $label;
        $revenue[] = round((float) ($found[$bucket]['revenue'] ?? 0), 2);
        $pallets[] = (int) ($found[$bucket]['pallets'] ?? 0);
    }

    return ['labels' => $labels, 'revenue' => $revenue, 'pallets' => $pallets];
}

function stats_by_land(PDO $conn, string $from, string $to): array
{
    $delivered = STATS_DELIVERED;

    $stmt = $conn->prepare("
        SELECT l.name AS land,
               COALESCE(SUM(ROUND(o.distance_km * o.pallet_count * o.unit_price + o.surcharge, 2)), 0) AS revenue,
               COUNT(o.order_id) AS orders
        FROM land l
        LEFT JOIN city ci ON ci.land_id = l.land_id
        LEFT JOIN orders o ON o.dest_city_id = ci.city_id AND $delivered
        GROUP BY l.land_id, l.name
        ORDER BY revenue DESC, l.name
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);

    return $stmt->fetchAll();
}

function stats_top_customers(PDO $conn, string $from, string $to, int $limit = 5): array
{
    $delivered = STATS_DELIVERED;
    $limit     = max(1, $limit);

    $stmt = $conn->prepare("
        SELECT COALESCE(c.companyname, CONCAT(c.first_name, ' ', c.last_name)) AS customer_name,
               COUNT(o.order_id) AS orders,
               COALESCE(SUM(o.pallet_count), 0) AS pallets,
               COALESCE(SUM(ROUND(o.distance_km * o.pallet_count * o.unit_price + o.surcharge, 2)), 0) AS revenue
        FROM orders o
        JOIN customer c ON c.user_id = o.customer_id
        WHERE $delivered
        GROUP BY c.user_id, customer_name
        ORDER BY revenue DESC, customer_name
        LIMIT $limit
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);

    return $stmt->fetchAll();
}
