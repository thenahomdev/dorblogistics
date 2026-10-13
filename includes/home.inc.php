<?php
$tariffs = $conn->query("
    SELECT min_pallets, max_pallets, price_per_km_per_pallet
    FROM tariff
    WHERE valid_from <= CURDATE()
      AND (valid_until IS NULL OR valid_until >= CURDATE())
    ORDER BY min_pallets
")->fetchAll();

$lands = $conn->query("
    SELECT name, surcharge
    FROM land
    ORDER BY surcharge, name
")->fetchAll();

$trucktypes = $conn->query("
    SELECT model_name, pallet_capacity, required_license
    FROM trucktype
    ORDER BY pallet_capacity
")->fetchAll();

function pallet_label(array $tariff): string
{
    $min = (int) $tariff['min_pallets'];
    $max = $tariff['max_pallets'] === null ? null : (int) $tariff['max_pallets'];

    if ($max === null) {
        return $min . ' of meer pallets';
    }
    if ($max === $min) {
        return $min . ($min === 1 ? ' pallet' : ' pallets');
    }
    if ($max === $min + 1) {
        return $min . ' of ' . $max . ' pallets';
    }

    return $min . ' t/m ' . $max . ' pallets';
}
?>

<section class="hero">
    <div class="container">
        <p class="eyebrow">A new Track of Success</p>
        <h1 class="hero-title">Uw pallets door de Benelux en Duitsland</h1>
        <p class="hero-text">
            Vanuit ons distributiecentrum in Arnhem bezorgen wij pallets bij bedrijven
            en particulieren. U betaalt per kilometer per pallet en achteraf.
        </p>
        <a href="#tarieven" class="btn btn-accent">Bekijk de tarieven</a>
    </div>
</section>

<section class="section section-alt" id="tarieven">
    <div class="container">
        <h2 class="section-title">Tarieven</h2>
        <p class="section-intro">
            De prijs is de kortste route vanaf Arnhem tot aan uw bestemming,
            maal het aantal pallets, maal het tarief hieronder.
        </p>

        <div class="grid grid-2">
            <div class="panel">
                <h3 class="panel-title">Prijs per kilometer per pallet</h3>
                <table class="price-table">
                    <tbody>
                        <?php foreach ($tariffs as $tariff): ?>
                            <tr>
                                <td><?= e(pallet_label($tariff)) ?></td>
                                <td><?= money($tariff['price_per_km_per_pallet']) ?> per km</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel">
                <h3 class="panel-title">Toeslag per levering</h3>
                <table class="price-table">
                    <tbody>
                        <?php foreach ($lands as $land): ?>
                            <tr>
                                <td><?= e($land['name']) ?></td>
                                <td>
                                    <?= (float) $land['surcharge'] > 0
                                        ? money($land['surcharge'], 0)
                                        : 'geen toeslag' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="section" id="wagenpark">
    <div class="container">
        <h2 class="section-title">Ons wagenpark</h2>

        <div class="grid grid-3">
            <?php foreach ($trucktypes as $truck): ?>
                <article class="truck-card">
                    <h3 class="truck-name"><?= e($truck['model_name']) ?></h3>
                    <p class="truck-capacity">
                        <?= (int) $truck['pallet_capacity'] ?> <span>pallets</span>
                    </p>
                    <p class="truck-note">Rijbewijs <?= e($truck['required_license']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
