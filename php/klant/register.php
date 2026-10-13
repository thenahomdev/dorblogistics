<?php
session_start();
include '../../database/cnn.php';
include '../../lib/city.php';
include '../../lib/mail.php';   

const PAGE_REGISTER = '../../index.php?page=klant/register';
const PAGE_SUCCESS  = '../../index.php?page=klant/register_success';


function redirect_with_errors(array $errors, array $input): never
{
    unset($input['password'], $input['password_repeat']);

    $_SESSION['errors'] = $errors;
    $_SESSION['old']    = $input;

    header('Location: ' . PAGE_REGISTER);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . PAGE_REGISTER);
    exit;
}

$customerType   = trim($_POST['customer_type']    ?? '');
$companyName    = trim($_POST['company_name']     ?? '');
$firstName      = trim($_POST['first_name']       ?? '');
$lastName       = trim($_POST['last_name']        ?? '');
$email          = trim($_POST['email']            ?? '');
$phone          = trim($_POST['phone']            ?? '');
$street         = trim($_POST['street']           ?? '');
$housenumber    = trim($_POST['housenumber']      ?? '');
$postalCode     = trim($_POST['postal_code']      ?? '');
$city           = trim($_POST['city']             ?? '');
$countryId      = (int) ($_POST['country_id']     ?? 0);
$iban           = strtoupper(str_replace(' ', '', trim($_POST['iban'] ?? '')));
$password       =        $_POST['password']        ?? '';
$passwordRepeat =        $_POST['password_repeat'] ?? '';

$errors = [];


if (!in_array($customerType, ['bedrijf', 'particulier'], true)) {
    $errors[] = 'Kies of u een bedrijf of een particulier bent.';
}

if ($customerType === 'bedrijf' && $companyName === '') {
    $errors[] = 'Bedrijfsnaam is verplicht voor bedrijven.';
}

if ($customerType === 'particulier') {
    $companyName = null;
}

if ($firstName === '') {
    $errors[] = 'Voornaam is verplicht.';
}

if ($lastName === '') {
    $errors[] = 'Achternaam is verplicht.';
}

if ($email === '') {
    $errors[] = 'E-mailadres is verplicht.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Vul een geldig e-mailadres in.';
}

if ($phone === '') {
    $errors[] = 'Telefoonnummer is verplicht.';
} elseif (!preg_match('/^[0-9 +\-]{10,20}$/', $phone)) {
    $errors[] = 'Vul een geldig telefoonnummer in.';
}

if ($street === '') {
    $errors[] = 'Straat is verplicht.';
}

if ($housenumber === '') {
    $errors[] = 'Huisnummer is verplicht.';
} elseif (!preg_match('/^[0-9]{1,5}[a-zA-Z0-9 \-]{0,5}$/', $housenumber)) {
    $errors[] = 'Vul een geldig huisnummer in, bijvoorbeeld 12 of 12a.';
}

if ($postalCode === '') {
    $errors[] = 'Postcode is verplicht.';
}

if ($city === '') {
    $errors[] = 'Plaats is verplicht.';
}

if ($countryId < 1) {
    $errors[] = 'Kies een land.';
}

if ($password !== $passwordRepeat) {
    $errors[] = 'De wachtwoorden komen niet overeen.';
}

if (strlen($password) < 8) {
    $errors[] = 'Het wachtwoord moet minimaal 8 tekens bevatten.';
}

if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'Het wachtwoord moet minimaal 1 hoofdletter bevatten.';
}

if (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Het wachtwoord moet minimaal 1 cijfer bevatten.';
}

if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    $errors[] = 'Het wachtwoord moet minimaal 1 speciaal teken bevatten.';
}

if ($errors === []) {

    $statement = $conn->prepare(
        'SELECT COUNT(*) FROM users WHERE email = :email'
    );
    $statement->execute([
        ':email' => $email
    ]);

    if ((int) $statement->fetchColumn() > 0) {
        $errors[] = 'Dit e-mailadres is al geregistreerd.';
    }

    $statement = $conn->prepare(
        'SELECT COUNT(*) FROM customer WHERE phonenumber = :phonenumber'
    );
    $statement->execute([
        ':phonenumber' => $phone
    ]);

    if ((int) $statement->fetchColumn() > 0) {
        $errors[] = 'Dit telefoonnummer is al geregistreerd.';
    }

    // Controleer of land bestaat
    $statement = $conn->prepare(
        'SELECT COUNT(*) FROM land WHERE land_id = :id'
    );
    $statement->execute([
        ':id' => $countryId
    ]);

    if ((int) $statement->fetchColumn() === 0) {
        $errors[] = 'Het gekozen land bestaat niet.';
    }
}

if ($errors !== []) {
    redirect_with_errors($errors, $_POST);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {

    $conn->beginTransaction();

    $sqlUser = "INSERT INTO users (role_id, email, password_hash)
                VALUES ((SELECT role_id FROM role WHERE name = 'klant'), :email, :password_hash)";

    $stmtUser = $conn->prepare($sqlUser);

    $stmtUser->execute([
        ':email'         => $email,
        ':password_hash' => $passwordHash,
    ]);

    $userId = (int) $conn->lastInsertId();

    $cityId = city_id($conn, $city, $countryId);

    $sqlCustomer = "INSERT INTO customer
                        (user_id, `type`, `status`, companyname, first_name, last_name, phonenumber,
                         iban, street, housenumber, postalcode, city_id, signed_up)
                    VALUES
                        (:user_id, :type, 'aangemeld', :companyname, :first_name, :last_name, :phonenumber,
                         :iban, :street, :housenumber, :postalcode, :city_id, :signed_up)";

    $stmtCustomer = $conn->prepare($sqlCustomer);

    $stmtCustomer->execute([
        ':user_id'     => $userId,
        ':type'        => $customerType,
        ':companyname' => $companyName,
        ':first_name'  => $firstName,
        ':last_name'   => $lastName,
        ':phonenumber' => $phone,
        ':iban'        => $iban,
        ':street'      => $street,
        ':housenumber' => $housenumber,
        ':postalcode'  => $postalCode,
        ':city_id'     => $cityId,
        ':signed_up'   => date('Y-m-d')
    ]);

$conn->commit();


$fullName = $firstName . ' ' . $lastName;

$_SESSION['register_success'] = [
    'message'   => 'Uw account wordt zo snel mogelijk beoordeeld. U ontvangt een e-mail zodra uw account is goedgekeurd. Daarna kunt u inloggen met uw e-mailadres en wachtwoord.',
    'mail_to'   => $email,
    'mail_sent' => send_registration_mail($email, $fullName),
];


header('Location: ' . PAGE_SUCCESS);
exit;


} catch (Throwable $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Registration failed: ' . $exception->getMessage());

    redirect_with_errors(
        ['Er ging iets mis bij het aanmaken van uw account. Probeer het opnieuw.'],
        $_POST
    );
}
