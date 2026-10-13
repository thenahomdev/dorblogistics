<?php
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['register_success'])) {
    header('Location: index.php');
    exit;
}

$success = $_SESSION['register_success'];
unset($_SESSION['register_success']);

$message  = $success['message'];
$mailTo   = $success['mail_to'];
$mailSent = $success['mail_sent'];
?>

<main class="success">
    <div class="success_card">

        <div class="success_icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="32" height="32" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="success_title">Bedankt voor uw aanmelding</h1>

        <p class="success_text"><?= e($message) ?></p>

        <?php if ($mailSent) { ?>

            <p class="success_text">
                We hebben een bevestiging gestuurd naar
                <strong><?= e($mailTo) ?></strong>.
            </p>

        <?php } else { ?>

            <p class="success_text">
                Uw aanmelding is ontvangen, maar de bevestigingsmail kon
                niet worden verstuurd.
            </p>

        <?php } ?>

    </div>
</main>

<script>
setTimeout(function () {
    window.location.href = 'index.php?page=home';
}, 5000);
</script>