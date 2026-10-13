<?php
declare(strict_types=1);

$geoConfig = __DIR__ . '/config.php';

if (is_file($geoConfig)) {
    require_once $geoConfig;
}

function ors_ready(): bool
{
    return defined('ORS_API_KEY')
        && defined('ORS_BASE_URL')
        && defined('ORS_ORIGIN_LON')
        && defined('ORS_ORIGIN_LAT')
        && ORS_API_KEY !== ''
        && !str_starts_with(ORS_API_KEY, 'zet-hier');
}

function ors_request(string $path, array $query, string $accept = 'application/json'): ?array
{
    if (!ors_ready()) {
        return null;
    }

    $query['api_key'] = ORS_API_KEY;

    $context = stream_context_create([
        'http' => [
            'timeout'       => 10,
            'ignore_errors' => true,
            'header'        => 'Accept: ' . $accept . "\r\n",
        ],
    ]);

    $body = @file_get_contents(ORS_BASE_URL . $path . '?' . http_build_query($query), false, $context);

    if ($body === false) {
        return null;
    }

    $data = json_decode($body, true);

    return is_array($data) ? $data : null;
}

function ors_first_coordinates(?array $data): ?array
{
    $coordinates = $data['features'][0]['geometry']['coordinates'] ?? null;

    if (!is_array($coordinates) || count($coordinates) < 2) {
        return null;
    }

    return [(float) $coordinates[0], (float) $coordinates[1]];
}

function geocode_address(
    string $street,
    string $housenumber,
    string $postalcode,
    string $city,
    string $countryCode
): ?array {
    $structured = ors_request('/geocode/search/structured', [
        'address'    => $street . ' ' . $housenumber,
        'postalcode' => $postalcode,
        'locality'   => $city,
        'country'    => $countryCode,
        'size'       => 1,
    ]);

    $coordinates = ors_first_coordinates($structured);

    if ($coordinates !== null) {
        return $coordinates;
    }

    $free = ors_request('/geocode/search', [
        'text'             => $street . ' ' . $housenumber . ', ' . $postalcode . ' ' . $city,
        'boundary.country' => $countryCode,
        'size'             => 1,
    ]);

    return ors_first_coordinates($free);
}

function route_distance_km(array $from, array $to): ?float
{
    $data = ors_request(
        '/v2/directions/driving-hgv',
        [
            'start' => $from[0] . ',' . $from[1],
            'end'   => $to[0] . ',' . $to[1],
        ],
        'application/geo+json'
    );

    $meters = $data['features'][0]['properties']['summary']['distance'] ?? null;

    if (!is_numeric($meters)) {
        return null;
    }

    return round(((float) $meters) / 1000, 2);
}

function delivery_distance_km(
    string $street,
    string $housenumber,
    string $postalcode,
    string $city,
    string $countryCode
): ?float {
    $destination = geocode_address($street, $housenumber, $postalcode, $city, $countryCode);

    if ($destination === null) {
        return null;
    }

    $distance = route_distance_km([ORS_ORIGIN_LON, ORS_ORIGIN_LAT], $destination);

    if ($distance === null || $distance <= 0 || $distance > 9999.99) {
        return null;
    }

    return $distance;
}
