<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../database/cnn.php';
require_once __DIR__ . '/../lib/auth.php';

const MAX_ATTEMPTS = 3;
const LOCK_MINUTES = 15;

$email    = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

function back_to_login(string $message, string $email): never
{
    $_SESSION['login_error'] = $message;
    $_SESSION['login_email'] = $email;
    header('Location: ../index.php?page=login');
    exit;
}

$genericError = 'E-mailadres of wachtwoord klopt niet.';

if ($email === '' || $password === '') {
    back_to_login('Vul uw e-mailadres en wachtwoord in.', $email);
}

$stmt = $conn->prepare("
    SELECT u.user_id, u.password_hash, u.failed_attempts,
           (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked,
           CEIL(TIMESTAMPDIFF(SECOND, NOW(), u.locked_until) / 60) AS minutes_left,
           r.name    AS role,
           c.user_id AS customer_id,
           c.status  AS customer_status,
           e.employee_id,
           e.in_service
    FROM users u
    JOIN role r ON r.role_id = u.role_id
    LEFT JOIN customer c ON c.user_id = u.user_id
    LEFT JOIN employee e ON e.user_id = u.user_id
    WHERE u.email = :email
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user) {
    back_to_login($genericError, $email);
}

if ((int) $user['is_locked'] === 1) {
    $minutes = max(1, (int) $user['minutes_left']);
    back_to_login('Te veel mislukte pogingen. Probeer het over ' . $minutes . ' minuten opnieuw.', $email);
}

if (!password_verify($password, $user['password_hash'])) {
    $attempts = (int) $user['failed_attempts'] + 1;

    if ($attempts >= MAX_ATTEMPTS) {
        $conn->prepare("
            UPDATE users
            SET failed_attempts = 0,
                locked_until = DATE_ADD(NOW(), INTERVAL :min MINUTE)
            WHERE user_id = :id
        ")->execute([':min' => LOCK_MINUTES, ':id' => $user['user_id']]);

        back_to_login(
            'Te veel mislukte pogingen. Probeer het over ' . LOCK_MINUTES . ' minuten opnieuw.',
            $email
        );
    }

    $conn->prepare("UPDATE users SET failed_attempts = :n WHERE user_id = :id")
         ->execute([':n' => $attempts, ':id' => $user['user_id']]);

    back_to_login($genericError, $email);
}

if ($user['employee_id'] === null && $user['customer_id'] === null) {
    back_to_login('Dit account hoort nog niet bij een klant of medewerker.', $email);
}

if ($user['employee_id'] !== null && (int) $user['in_service'] !== 1) {
    back_to_login('Dit account is niet meer actief. Neem contact op met DORB.', $email);
}

if ($user['employee_id'] === null && $user['customer_status'] !== 'actief') {
    $message = $user['customer_status'] === 'aangemeld'
        ? 'Uw aanmelding is nog niet goedgekeurd. U ontvangt bericht zodra dat gebeurd is.'
        : 'Dit account is niet actief. Neem contact op met DORB.';
    back_to_login($message, $email);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['user_id'];

$conn->prepare("
    UPDATE users
    SET last_login = NOW(), failed_attempts = 0, locked_until = NULL
    WHERE user_id = :id
")->execute([':id' => $user['user_id']]);

header('Location: ../index.php?page=' . dashboard_page($user['role'] ?? 'klant'));
exit;
