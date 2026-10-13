<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

function jsonResponse(bool $success, array $data = [], int $statusCode = 200): never
{
    http_response_code($statusCode);

    echo json_encode(
        array_merge(
            ['success' => $success],
            $data
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {
    require_once __DIR__ . '/../database/cnn.php';

    if (!isset($conn) || !($conn instanceof PDO)) {
        jsonResponse(false, [
            'message' => 'De databaseverbinding kon niet worden geladen.'
        ], 500);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, [
            'message' => 'Ongeldige aanvraag.'
        ], 405);
    }

    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, [
            'message' => 'Je bent niet ingelogd. Log opnieuw in.'
        ], 401);
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
        jsonResponse(false, [
            'message' => 'Je hebt geen toestemming om medewerkers aan te maken.'
        ], 403);
    }

    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['employee_csrf'] ?? '');

    if (
        $sessionToken === '' ||
        $postedToken === '' ||
        !hash_equals($sessionToken, $postedToken)
    ) {
        jsonResponse(false, [
            'message' => 'Je sessie is verlopen of het formulier is ongeldig. Vernieuw de pagina en probeer het opnieuw.'
        ], 419);
    }

    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $roleId = (int) ($_POST['role_id'] ?? 0);
    $hireDate = trim((string) ($_POST['hire_date'] ?? ''));

    $licenseType = trim((string) ($_POST['license_type'] ?? ''));
    $licenseNumber = trim((string) ($_POST['license_number'] ?? ''));
    $licenseExpiry = trim((string) ($_POST['license_expiry'] ?? ''));

    $driverLandIds = $_POST['driver_land_ids'] ?? [];

    $password = (string) ($_POST['password'] ?? '');
    $passwordRepeat = (string) ($_POST['password_repeat'] ?? '');

    if (!is_array($driverLandIds)) {
        $driverLandIds = [];
    }

    $driverLandIds = array_values(
        array_unique(
            array_filter(
                array_map('intval', $driverLandIds),
                static fn (int $id): bool => $id > 0
            )
        )
    );

    $old = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'role_id' => $roleId,
        'hire_date' => $hireDate,
        'license_type' => $licenseType,
        'license_number' => $licenseNumber,
        'license_expiry' => $licenseExpiry,
        'driver_land_ids' => $driverLandIds
    ];

    $errors = [];

    if ($firstName === '') {
        $errors[] = 'Voornaam is verplicht.';
    } elseif (mb_strlen($firstName) > 60) {
        $errors[] = 'Voornaam mag maximaal 60 tekens bevatten.';
    }

    if ($lastName === '') {
        $errors[] = 'Achternaam is verplicht.';
    } elseif (mb_strlen($lastName) > 80) {
        $errors[] = 'Achternaam mag maximaal 80 tekens bevatten.';
    }

    if ($email === '') {
        $errors[] = 'E-mailadres is verplicht.';
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        mb_strlen($email) > 255
    ) {
        $errors[] = 'Vul een geldig e-mailadres in.';
    }

    if ($phone === '') {
        $errors[] = 'Telefoonnummer is verplicht.';
    } elseif (!preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
        $errors[] = 'Vul een geldig telefoonnummer in.';
    }

    if ($hireDate === '') {
        $errors[] = 'Datum indiensttreding is verplicht.';
    } else {
        $hireDateObject = DateTime::createFromFormat(
            'Y-m-d',
            $hireDate
        );

        if (
            !$hireDateObject ||
            $hireDateObject->format('Y-m-d') !== $hireDate
        ) {
            $errors[] = 'De datum indiensttreding is ongeldig.';
        }
    }

    if ($password === '') {
        $errors[] = 'Wachtwoord is verplicht.';
    } else {
        if ($password !== $passwordRepeat) {
            $errors[] = 'De wachtwoorden komen niet overeen.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Het wachtwoord moet minimaal 8 tekens bevatten.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Het wachtwoord moet minimaal één hoofdletter bevatten.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Het wachtwoord moet minimaal één cijfer bevatten.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Het wachtwoord moet minimaal één speciaal teken bevatten.';
        }
    }

    $selectedRole = null;

    if ($roleId <= 0) {
        $errors[] = 'Kies een functie.';
    } else {
        $stmtSelectedRole = $conn->prepare(
            "SELECT role_id, name
             FROM role
             WHERE role_id = :role_id
               AND name IN ('chauffeur', 'planner', 'manager')
             LIMIT 1"
        );

        $stmtSelectedRole->execute([
            ':role_id' => $roleId
        ]);

        $selectedRoleData = $stmtSelectedRole->fetch(PDO::FETCH_ASSOC);

        if ($selectedRoleData === false) {
            $errors[] = 'De gekozen functie bestaat niet of is niet toegestaan.';
        } else {
            $selectedRole = (string) $selectedRoleData['name'];
        }
    }

    $landen = $conn
        ->query(
            'SELECT land_id, country_code
             FROM land'
        )
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    $finalLandIds = [];

    if ($selectedRole === 'chauffeur') {
        if ($licenseType === '') {
            $errors[] = 'Rijbewijscategorie is verplicht.';
        } elseif (!in_array($licenseType, ['C', 'D'], true)) {
            $errors[] = 'Rijbewijscategorie moet C of D zijn.';
        }

        if ($licenseNumber === '') {
            $errors[] = 'Rijbewijsnummer is verplicht.';
        } elseif (mb_strlen($licenseNumber) > 30) {
            $errors[] = 'Rijbewijsnummer mag maximaal 30 tekens bevatten.';
        }

        if ($licenseExpiry === '') {
            $errors[] = 'De vervaldatum van het rijbewijs is verplicht.';
        } else {
            $expiryDate = DateTime::createFromFormat(
                'Y-m-d',
                $licenseExpiry
            );

            if (
                !$expiryDate ||
                $expiryDate->format('Y-m-d') !== $licenseExpiry
            ) {
                $errors[] = 'De vervaldatum van het rijbewijs is ongeldig.';
            } elseif ($expiryDate < new DateTime('today')) {
                $errors[] = 'Het rijbewijs is verlopen.';
            }
        }

        foreach ($driverLandIds as $landId) {
            if (array_key_exists($landId, $landen)) {
                $finalLandIds[] = $landId;
            }
        }

        $nlId = array_search('NL', $landen, true);

        if ($nlId !== false) {
            $finalLandIds[] = (int) $nlId;
        } else {
            $errors[] = 'Het land Nederland ontbreekt in de database.';
        }

        $finalLandIds = array_values(
            array_unique($finalLandIds)
        );
    }

    if ($email !== '') {
        $stmtEmail = $conn->prepare(
            'SELECT user_id
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $stmtEmail->execute([
            ':email' => $email
        ]);

        if ($stmtEmail->fetchColumn() !== false) {
            $errors[] = 'Dit e-mailadres is al in gebruik.';
        }
    }

    if (
        $selectedRole === 'chauffeur' &&
        $licenseNumber !== ''
    ) {
        $stmtLicense = $conn->prepare(
            'SELECT employee_id
             FROM driver
             WHERE license_number = :license_number
             LIMIT 1'
        );

        $stmtLicense->execute([
            ':license_number' => $licenseNumber
        ]);

        if ($stmtLicense->fetchColumn() !== false) {
            $errors[] = 'Dit rijbewijsnummer is al in gebruik.';
        }
    }

    if ($errors !== []) {
        $_SESSION['employee_errors'] = $errors;
        $_SESSION['employee_old'] = $old;

        jsonResponse(false, [
            'message' => 'Controleer de ingevulde gegevens.',
            'errors' => $errors
        ], 422);
    }

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    if ($passwordHash === false) {
        jsonResponse(false, [
            'message' => 'Het wachtwoord kon niet veilig worden verwerkt.'
        ], 500);
    }

    $conn->beginTransaction();

    $stmtUser = $conn->prepare(
        'INSERT INTO users (
            role_id,
            email,
            password_hash
        ) VALUES (
            :role_id,
            :email,
            :password_hash
        )'
    );

    $stmtUser->execute([
        ':role_id' => $roleId,
        ':email' => $email,
        ':password_hash' => $passwordHash
    ]);

    $newUserId = (int) $conn->lastInsertId();

    $stmtEmployee = $conn->prepare(
        'INSERT INTO employee (
            user_id,
            first_name,
            last_name,
            phonenumber,
            hire_date,
            in_service
        ) VALUES (
            :user_id,
            :first_name,
            :last_name,
            :phonenumber,
            :hire_date,
            :in_service
        )'
    );

    $stmtEmployee->execute([
        ':user_id' => $newUserId,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':phonenumber' => $phone,
        ':hire_date' => $hireDate,
        ':in_service' => 1
    ]);

    $newEmployeeId = (int) $conn->lastInsertId();

    if ($selectedRole === 'chauffeur') {
        $stmtDriver = $conn->prepare(
            'INSERT INTO driver (
                employee_id,
                license_type,
                license_number,
                license_expiry
            ) VALUES (
                :employee_id,
                :license_type,
                :license_number,
                :license_expiry
            )'
        );

        $stmtDriver->execute([
            ':employee_id' => $newEmployeeId,
            ':license_type' => $licenseType,
            ':license_number' => $licenseNumber,
            ':license_expiry' => $licenseExpiry
        ]);

        $stmtDriverLand = $conn->prepare(
            'INSERT INTO driver_land (
                employee_id,
                land_id
            ) VALUES (
                :employee_id,
                :land_id
            )'
        );

        foreach ($finalLandIds as $landId) {
            $stmtDriverLand->execute([
                ':employee_id' => $newEmployeeId,
                ':land_id' => $landId
            ]);
        }
    }

    $conn->commit();

    unset(
        $_SESSION['employee_csrf'],
        $_SESSION['employee_errors'],
        $_SESSION['employee_old']
    );

    $_SESSION['employee_success'] =
        'De medewerker is succesvol aangemaakt en kan direct inloggen.';

    jsonResponse(true, [
        'message' => 'De medewerker is succesvol aangemaakt en kan direct inloggen.',
        'redirect' => '/dorblogistics/index.php?page=medewerkers_admin'
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
    }

    error_log(
        'Medewerker aanmaken mislukt: ' .
        $e->getMessage() .
        ' | Bestand: ' .
        $e->getFile() .
        ' | Regel: ' .
        $e->getLine()
    );

    $_SESSION['employee_errors'] = [
        'De medewerker kon niet worden aangemaakt.'
    ];

    $_SESSION['employee_old'] = $old ?? [];

    jsonResponse(false, [
        'message' => 'Er is een fout opgetreden bij het aanmaken van de medewerker.',
        'errors' => [
            $e->getMessage()
        ]
    ], 500);
}