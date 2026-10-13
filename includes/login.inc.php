<?php
$user = $user ?? null;

if ($user !== null) {
    header('Location: index.php?page=' . dashboard_page($user['role']));
    exit;
}

$error = $_SESSION['login_error'] ?? '';
$email = $_SESSION['login_email'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_email']);
?>

<section class="section">
    <div class="container">
        <div class="form-card">
            <h1 class="section-title">Inloggen</h1>

            <?php if ($error !== ''): ?>
                <p class="form-error"><?= e($error) ?></p>
            <?php endif; ?>

            <form action="php/login.php" method="post">
                <div class="field">
                    <label for="email">E-mailadres</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Wachtwoord</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-accent">Inloggen</button>
            </form>

            <p class="form-link">
                <a href="index.php?page=wachtwoord-vergeten">Wachtwoord vergeten?</a>
            </p>
        </div>
    </div>
</section>
