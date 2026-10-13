<?php
declare(strict_types=1);

function city_id(PDO $conn, string $name, int $landId): int
{
    $conn->prepare("
        INSERT INTO city (name, land_id)
        VALUES (:name, :land_id)
        ON DUPLICATE KEY UPDATE city_id = LAST_INSERT_ID(city_id)
    ")->execute([':name' => $name, ':land_id' => $landId]);

    return (int) $conn->lastInsertId();
}
