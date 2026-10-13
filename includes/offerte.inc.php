<?php
$lands = $conn->query("SELECT land_id, name, surcharge FROM land ORDER BY name")->fetchAll();

$tariffs = $conn->query("
    SELECT min_pallets, max_pallets, price_per_km_per_pallet
    FROM tariff
    WHERE valid_from <= CURDATE()
      AND (valid_until IS NULL OR valid_until >= CURDATE())
    ORDER BY min_pallets
")->fetchAll();

$errors    = $_SESSION['order_errors'] ?? [];
$old       = $_SESSION['order_old'] ?? [];
$success   = $_SESSION['order_success'] ?? null;
$confirmed = $_SESSION['order_confirmed'] ?? null;
$cancelled = $_SESSION['order_cancelled'] ?? null;
unset(
    $_SESSION['order_errors'],
    $_SESSION['order_old'],
    $_SESSION['order_success'],
    $_SESSION['order_confirmed'],
    $_SESSION['order_cancelled']
);
?>

<section class="section">
    <div class="container">
        <h1 class="section-title">Offerte aanvragen</h1>
        <p class="section-intro">
            Vul de bestemming en het aantal pallets in. U ziet meteen wat de rit kost;
            pas na uw bevestiging plannen wij hem in.
        </p>

        <?php if ($success !== null): ?>
            <div class="form-success">
                <h2 class="panel-title">Offerte <?= (int) $success['order_id'] ?> berekend</h2>
                <p>
                    <?= (int) $success['pallet_count'] ?> pallets naar
                    <?= e($success['dest_city']) ?> (<?= e($success['land_name']) ?>),
                    <?= e(number_format((float) $success['distance_km'], 2, ',', '.')) ?> km.
                </p>
                <table class="price-table">
                    <tbody>
                        <tr>
                            <td>
                                <?= e(number_format((float) $success['distance_km'], 2, ',', '.')) ?> km
                                &times; <?= (int) $success['pallet_count'] ?> pallets
                                &times; <?= money($success['unit_price']) ?>
                            </td>
                            <td><?= money((float) $success['total_price'] - (float) $success['surcharge']) ?></td>
                        </tr>
                        <tr>
                            <td>Toeslag <?= e($success['land_name']) ?></td>
                            <td><?= money($success['surcharge']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Totaal</strong></td>
                            <td><strong><?= money($success['total_price']) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                <p class="note">
                    Deze prijs geldt zodra u bevestigt. Daarna plannen wij de rit in en
                    ontvangt u de orderbevestiging als PDF per e-mail.
                </p>
                <div class="btn-row">
                    <form action="php/order_confirm.php" method="post">
                        <input type="hidden" name="order_id" value="<?= (int) $success['order_id'] ?>">
                        <button type="submit" class="btn btn-accent">Offerte bevestigen</button>
                    </form>

                    <form action="php/order_cancel.php" method="post">
                        <input type="hidden" name="order_id" value="<?= (int) $success['order_id'] ?>">
                        <button type="submit" class="btn btn-ghost">Offerte afwijzen</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($confirmed !== null): ?>
            <div class="form-success">
                <h2 class="panel-title">Order <?= (int) $confirmed['order_id'] ?> bevestigd</h2>
                <p>
                    Uw zending naar <?= e($confirmed['dest_city']) ?> staat klaar om ingepland
                    te worden voor <?= money($confirmed['total_price']) ?>.
                </p>
                <p class="note">
                    <?php if (!empty($confirmed['mail_sent'])): ?>
                        De orderbevestiging is als PDF verstuurd naar <?= e($confirmed['mail_to']) ?>.
                    <?php else: ?>
                        De order is bevestigd, maar de e-mail kon niet worden verstuurd.
                        Neem contact op met DORB als u hem niet ontvangt.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($cancelled !== null): ?>
            <div class="form-success">
                <h2 class="panel-title">Offerte <?= (int) $cancelled['order_id'] ?> afgewezen</h2>
                <p>
                    Deze offerte is geannuleerd en wordt niet ingepland.
                    U kunt hieronder een nieuwe aanvraag doen.
                </p>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="form-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-2">
            <form class="panel" action="php/order_create.php" method="post">
                <h2 class="panel-title">Bestemming</h2>

                <div class="form-row">
                    <div class="field">
                        <label for="dest_street">Straat</label>
                        <input type="text" id="dest_street" name="dest_street" maxlength="120"
                               value="<?= e($old['dest_street'] ?? '') ?>" required>
                    </div>

                    <div class="field">
                        <label for="dest_housenumber">Huisnummer</label>
                        <input type="text" id="dest_housenumber" name="dest_housenumber" maxlength="10"
                               value="<?= e($old['dest_housenumber'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="dest_postalcode">Postcode</label>
                        <input type="text" id="dest_postalcode" name="dest_postalcode" maxlength="10"
                               value="<?= e($old['dest_postalcode'] ?? '') ?>" required>
                    </div>

                    <div class="field">
                        <label for="dest_city">Plaats</label>
                        <input type="text" id="dest_city" name="dest_city" maxlength="80"
                               value="<?= e($old['dest_city'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="field">
                    <label for="dest_land_id">Land</label>
                    <select id="dest_land_id" name="dest_land_id" required>
                        <?php foreach ($lands as $land): ?>
                            <option value="<?= (int) $land['land_id'] ?>"
                                <?= (string) ($old['dest_land_id'] ?? '1') === (string) $land['land_id'] ? 'selected' : '' ?>>
                                <?= e($land['name']) ?>
                                <?= (float) $land['surcharge'] > 0
                                    ? '(+ ' . number_format((float) $land['surcharge'], 2, ',', '.') . ' toeslag)'
                                    : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h2 class="panel-title">Zending</h2>

                <div class="field">
                    <label for="pallet_count">Aantal pallets</label>
                    <input type="number" id="pallet_count" name="pallet_count" min="1" max="30"
                           value="<?= e($old['pallet_count'] ?? '1') ?>" required>
                </div>

                <p class="note">
                    De afstand berekenen wij zelf: de kortste route van ons
                    distributiecentrum in Arnhem naar het adres hierboven.
                </p>

                <button type="submit" class="btn btn-accent">Offerte berekenen</button>
            </form>

            <div class="panel">
                <h2 class="panel-title">Wat u betaalt</h2>
                <table class="price-table">
                    <tbody>
                        <?php foreach ($tariffs as $tariff): ?>
                            <tr>
                                <td>
                                    <?php if ($tariff['max_pallets'] === null): ?>
                                        <?= (int) $tariff['min_pallets'] ?> of meer pallets
                                    <?php elseif ((int) $tariff['max_pallets'] === (int) $tariff['min_pallets']): ?>
                                        <?= (int) $tariff['min_pallets'] ?> pallet
                                    <?php else: ?>
                                        <?= (int) $tariff['min_pallets'] ?> t/m <?= (int) $tariff['max_pallets'] ?> pallets
                                    <?php endif; ?>
                                </td>
                                <td><?= money($tariff['price_per_km_per_pallet']) ?> per km</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="note">
                    De prijs is de kortste route vanaf ons distributiecentrum in Arnhem,
                    maal het aantal pallets, maal het tarief. Levert u in Belgi&euml;,
                    Duitsland of Luxemburg, dan komt daar de toeslag van dat land bij.
                </p>
                <p class="note">
                    Het tarief en de toeslag worden vastgelegd op het moment dat u de
                    offerte aanvraagt en veranderen daarna niet meer.
                </p>
            </div>
        </div>
    </div>
</section>
