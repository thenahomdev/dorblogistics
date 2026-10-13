<?php

if (!isset($customer, $recentOrders, $totalOrders, $openOrders)) {
 
    $klantLogicFile = is_file(__DIR__ . '/php/dashboard_klant.php')
        ? __DIR__ . '/php/dashboard_klant.php'     
        : __DIR__ . '/../php/dashboard_klant.php';  

    require_once $klantLogicFile;
}

$dashboardMessage     = $_SESSION['dashboard_message'] ?? null;
$dashboardMessageType = $_SESSION['dashboard_message_type'] ?? 'success';

unset($_SESSION['dashboard_message'], $_SESSION['dashboard_message_type']);

function klantEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<div class="klant-dashboard">

    <section class="klant-header">
        <div class="klant-header-content">

            <div>
                <p class="klant-eyebrow">Klantportaal</p>

                <h1 class="klant-title">
                    Welkom, <?= klantEsc($customer['first_name'] ?? '') ?>
                </h1>

                <p class="klant-subtitle">
                    Bekijk je bestellingen en beheer je account.
                </p>
            </div>

            <a href="index.php?page=offerte" class="klant-button klant-button-primary">
                Nieuwe bestelling
            </a>

        </div>
    </section>

    <?php if (!empty($dashboardMessage)): ?>
        <div class="klant-alert <?= $dashboardMessageType === 'error' ? 'klant-alert-error' : 'klant-alert-success' ?>">
            <?= klantEsc($dashboardMessage) ?>
        </div>
    <?php endif; ?>

    <section class="klant-stats">

        <div class="klant-stat-card">
            <span class="klant-stat-label">Totaal bestellingen</span>
            <strong class="klant-stat-number"><?= (int) $totalOrders ?></strong>
        </div>

        <div class="klant-stat-card">
            <span class="klant-stat-label">Openstaande bestellingen</span>
            <strong class="klant-stat-number"><?= (int) $openOrders ?></strong>
        </div>

        <div class="klant-stat-card">
            <span class="klant-stat-label">Accountstatus</span>
            <strong class="klant-stat-status">Actief</strong>
        </div>

    </section>

    <section class="klant-section">

        <div class="klant-section-header">

            <div>
                <h2>Recente bestellingen</h2>
                <p>Een overzicht van je meest recente transportorders.</p>
            </div>

            <a href="index.php?page=recent_orders_klant" class="klant-text-link">
                Alle bestellingen bekijken →
            </a>

        </div>

        <?php if (empty($recentOrders)): ?>

            <div class="klant-empty">

                <div class="klant-empty-icon">📦</div>

                <h3>Nog geen bestellingen</h3>

                <p>Je hebt nog geen transportbestellingen geplaatst.</p>

                <a href="index.php?page=offerte" class="klant-button klant-button-primary">
                    Nieuwe bestelling plaatsen
                </a>

            </div>

        <?php else: ?>

            <div class="klant-orders-wrapper">

                <table class="klant-orders-table">

                    <thead>
                        <tr>
                            <th>Ordernummer</th>
                            <th>Datum</th>
                            <th>Bestemming</th>
                            <th>Pallets</th>
                            <th>Prijs</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recentOrders as $recentOrder): ?>

                            <tr>

                                <td>
                                    <strong>#<?= (int) $recentOrder['order_id'] ?></strong>
                                </td>

                                <td>
                                    <?= klantEsc(date('d-m-Y', strtotime((string) $recentOrder['order_date']))) ?>
                                </td>

                                <td>
                                    <?= klantEsc($recentOrder['destination_city'] ?? '') ?>
                                    <br>
                                    <small><?= klantEsc($recentOrder['destination_country'] ?? '') ?></small>
                                </td>

                                <td>
                                    <?= (int) $recentOrder['pallet_count'] ?>
                                </td>

                                <td>
                                    € <?= number_format((float) $recentOrder['total_price'], 2, ',', '.') ?>
                                </td>

                                <td>
                                    <span class="klant-status <?= klantEsc($recentOrder['status_class']) ?>">
                                        <?= klantEsc($recentOrder['status_text']) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <section class="klant-section">

        <div class="klant-section-header">
            <div>
                <h2>Mijn account</h2>
                <p>Beheer hier je persoonlijke gegevens.</p>
            </div>
        </div>

        <div class="klant-account-grid">

            <div class="klant-card">

                <h3>Persoonsgegevens</h3>

                <form method="post" action="php/dashboard_klant.php" class="klant-form">

                    <input type="hidden" name="action" value="update_profile">

                    <div class="klant-form-row">

                        <div class="klant-field">
                            <label for="klant-first-name">Voornaam</label>
                            <input
                                type="text"
                                id="klant-first-name"
                                name="first_name"
                                value="<?= klantEsc($customer['first_name'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="klant-field">
                            <label for="klant-last-name">Achternaam</label>
                            <input
                                type="text"
                                id="klant-last-name"
                                name="last_name"
                                value="<?= klantEsc($customer['last_name'] ?? '') ?>"
                                required
                            >
                        </div>

                    </div>

                    <div class="klant-field">
                        <label for="klant-email">E-mailadres</label>
                        <input
                            type="email"
                            id="klant-email"
                            name="email"
                            value="<?= klantEsc($customer['email'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="klant-field">
                        <label for="klant-phone">Telefoonnummer</label>
                        <input
                            type="text"
                            id="klant-phone"
                            name="phonenumber"
                            value="<?= klantEsc($customer['phonenumber'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="klant-form-row">

                        <div class="klant-field">
                            <label for="klant-street">Straat</label>
                            <input
                                type="text"
                                id="klant-street"
                                name="street"
                                value="<?= klantEsc($customer['street'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="klant-field klant-field-small">
                            <label for="klant-house-number">Huisnummer</label>
                            <input
                                type="text"
                                id="klant-house-number"
                                name="housenumber"
                                value="<?= klantEsc($customer['housenumber'] ?? '') ?>"
                                required
                            >
                        </div>

                    </div>

                    <div class="klant-form-row">

                        <div class="klant-field">
                            <label for="klant-postalcode">Postcode</label>
                            <input
                                type="text"
                                id="klant-postalcode"
                                name="postalcode"
                                value="<?= klantEsc($customer['postalcode'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="klant-field">
                            <label for="klant-city">Plaats</label>
                            <input
                                type="text"
                                id="klant-city"
                                name="city_name"
                                value="<?= klantEsc($customer['city_name'] ?? '') ?>"
                                required
                            >
                        </div>

                    </div>

                    <button type="submit" class="klant-button klant-button-primary">
                        Gegevens opslaan
                    </button>

                </form>

            </div>

            <div class="klant-card">

                <h3>Wachtwoord wijzigen</h3>

                <form method="post" action="php/dashboard_klant.php" class="klant-form">

                    <input type="hidden" name="action" value="change_password">

                    <div class="klant-field">
                        <label for="klant-current-password">Huidig wachtwoord</label>
                        <input
                            type="password"
                            id="klant-current-password"
                            name="current_password"
                            required
                        >
                    </div>

                    <div class="klant-field">
                        <label for="klant-new-password">Nieuw wachtwoord</label>
                        <input
                            type="password"
                            id="klant-new-password"
                            name="new_password"
                            minlength="8"
                            required
                        >
                    </div>

                    <div class="klant-field">
                        <label for="klant-confirm-password">Nieuw wachtwoord opnieuw</label>
                        <input
                            type="password"
                            id="klant-confirm-password"
                            name="confirm_password"
                            minlength="8"
                            required
                        >
                    </div>

                    <button type="submit" class="klant-button klant-button-primary">
                        Wachtwoord wijzigen
                    </button>

                </form>

            </div>

        </div>

        <section class="klant-delete-section">

            <div>
                <h2>Account verwijderen</h2>
                <p>
                    Je account wordt op inactief gezet.
                    Je gegevens en eerdere bestellingen blijven in het systeem bewaard.
                </p>

                <?php if (!$canDeleteAccount): ?>
                    <p class="klant-delete-warning">
                        Als je een bestelling open hebt staan, kun je je account niet verwijderen.
                        Zodra al je bestellingen zijn afgerond of geannuleerd, kun je je account verwijderen.
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($canDeleteAccount): ?>

                <form
                    method="post"
                    action="php/delete_klant.php"
                    class="klant-delete-form"
                    onsubmit="return confirm('Weet je zeker dat je je account wilt verwijderen?');"
                >
                    <button type="submit" class="klant-button klant-button-danger">
                        Account verwijderen
                    </button>
                </form>

            <?php else: ?>

                <button
                    type="button"
                    class="klant-button klant-button-danger"
                    disabled
                    style="opacity: 0.5; cursor: not-allowed;"
                    title="Je hebt nog openstaande bestellingen"
                >
                    Account verwijderen
                </button>

            <?php endif; ?>
        </section>
    </section>
</div>