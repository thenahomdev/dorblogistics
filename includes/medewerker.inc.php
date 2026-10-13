<?php
require_once __DIR__ . '/../lib/manager.php';

$employeeId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$employee   = $employeeId === false ? null : manager_employee($conn, $employeeId);
?>

<section class="section">
    <div class="container">

        <?php if ($employee === null): ?>
            <h1 class="section-title">Medewerker niet gevonden</h1>
            <p class="section-intro">Deze medewerker bestaat niet of is verwijderd.</p>
            <a class="btn btn-accent" href="index.php?page=dashboard_manager">Terug naar het overzicht</a>
        <?php else: ?>
            <h1 class="section-title">
                <?= e($employee['first_name'] . ' ' . $employee['last_name']) ?>
            </h1>
            <p class="section-intro">
                <?= e(ucfirst($employee['role'])) ?> bij DORB Logistics sinds
                <?= e(date('d-m-Y', strtotime((string) $employee['hire_date']))) ?>.
            </p>

            <div class="manager-detail">

                <div class="panel">
                    <h2 class="panel-title">Gegevens</h2>

                    <div class="manager-row">
                        <span>Functie</span>
                        <span><?= e(ucfirst($employee['role'])) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>Status</span>
                        <span class="manager-status <?= (int) $employee['in_service'] === 1 ? 'manager-status-geleverd' : 'manager-status-geannuleerd' ?>">
                            <?= (int) $employee['in_service'] === 1 ? 'In dienst' : 'Uit dienst' ?>
                        </span>
                    </div>
                    <div class="manager-row">
                        <span>E-mailadres</span>
                        <span><?= e($employee['email']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>Telefoonnummer</span>
                        <span><?= e($employee['phonenumber']) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>In dienst sinds</span>
                        <span><?= e(date('d-m-Y', strtotime((string) $employee['hire_date']))) ?></span>
                    </div>
                    <div class="manager-row">
                        <span>Laatst ingelogd</span>
                        <span>
                            <?= $employee['last_login'] === null
                                ? 'nog nooit'
                                : e(date('d-m-Y H:i', strtotime((string) $employee['last_login']))) ?>
                        </span>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title">
                        <?= $employee['role'] === 'chauffeur' ? 'Rijbewijs en ritten' : 'Werk' ?>
                    </h2>

                    <?php if ($employee['role'] === 'chauffeur'): ?>
                        <div class="manager-row">
                            <span>Rijbewijs</span>
                            <span><?= e((string) $employee['license_type']) ?></span>
                        </div>
                        <div class="manager-row">
                            <span>Rijbewijsnummer</span>
                            <span><?= e((string) $employee['license_number']) ?></span>
                        </div>
                        <div class="manager-row">
                            <span>Geldig tot</span>
                            <span><?= e(date('d-m-Y', strtotime((string) $employee['license_expiry']))) ?></span>
                        </div>
                        <div class="manager-row">
                            <span>Mag rijden in</span>
                            <span><?= e((string) ($employee['countries'] ?? '-')) ?></span>
                        </div>
                        <div class="manager-row">
                            <span>Aantal ritten</span>
                            <span><?= (int) $employee['ride_count'] ?></span>
                        </div>
                    <?php else: ?>
                        <div class="manager-row">
                            <span>Orders toegewezen</span>
                            <span><?= (int) $employee['claimed_orders'] ?></span>
                        </div>
                        <p class="manager-empty">
                            Deze medewerker rijdt niet, dus er is geen rijbewijs geregistreerd.
                        </p>
                    <?php endif; ?>
                </div>

            </div>

            <p class="note">
                <a href="index.php?page=dashboard_manager">Terug naar het overzicht</a>
            </p>
        <?php endif; ?>

    </div>
</section>
