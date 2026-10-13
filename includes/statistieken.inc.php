<?php
require_once __DIR__ . '/../lib/stats.php';

$period   = stats_period($_GET);
$from     = $period['from'];
$to       = $period['to'];
$fromSql  = $from->format('Y-m-d');
$toSql    = $to->format('Y-m-d');
$grouping = stats_grouping($from, $to);

$rides     = stats_rides($conn, $fromSql, $toSql);
$totals    = stats_totals($conn, $fromSql, $toSql);
$buckets   = stats_buckets($from, $to, $grouping);
$series    = stats_series($conn, $fromSql, $toSql, $buckets, $grouping);
$byLand    = stats_by_land($conn, $fromSql, $toSql);
$customers = stats_top_customers($conn, $fromSql, $toSql);

$hasOrders = $totals['orders'] > 0;
$hasRides  = $rides['rides'] > 0;
$hasData   = $hasOrders || $hasRides;

$km  = static fn (?float $value): string => $value === null ? '-' : number_format($value, 1, ',', '.') . ' km';
$pct = static fn (?float $value): string => $value === null ? '-' : number_format($value, 1, ',', '.') . '%';

$chartData = [
    'labels'  => $series['labels'],
    'revenue' => $series['revenue'],
    'pallets' => $series['pallets'],
    'lands'   => array_column($byLand, 'land'),
    'landRevenue' => array_map(static fn (array $row): float => (float) $row['revenue'], $byLand),
];
?>

<section class="section">
    <div class="container">

        <h1 class="section-title">Statistieken</h1>
        <p class="section-intro">
            Hoe efficient rijden we en waar valt winst te halen? Kies hieronder een periode.
        </p>

        <div class="manager-filters">
            <?php foreach (STATS_QUICK as $key => $label): ?>
                <a class="manager-filter <?= $period['quick'] === $key ? 'is-active' : '' ?>"
                   href="index.php?page=statistieken&amp;snel=<?= e($key) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form class="manager-toolbar" method="get" action="index.php">
            <input type="hidden" name="page" value="statistieken">

            <div class="manager-field">
                <label for="van">Van</label>
                <input type="date" id="van" name="van" value="<?= e($fromSql) ?>">
            </div>

            <div class="manager-field">
                <label for="tot">Tot en met</label>
                <input type="date" id="tot" name="tot" value="<?= e($toSql) ?>">
            </div>

            <div class="manager-field">
                <button class="btn btn-accent" type="submit">Toon periode</button>
            </div>

            <span class="manager-reset">
                <?= e($from->format('d-m-Y')) ?> t/m <?= e($to->format('d-m-Y')) ?>
            </span>
        </form>

        <?php if (!$hasData): ?>

            <div class="panel">
                <p class="manager-empty">
                    Er zijn geen gegevens in deze periode. Er zijn geen ritten afgerond en er zijn
                    geen bestellingen geleverd tussen <?= e($from->format('d-m-Y')) ?>
                    en <?= e($to->format('d-m-Y')) ?>. Kies een andere periode.
                </p>
            </div>

        <?php else: ?>

            <div class="stats-grid">
                <div class="manager-stat">
                    <span class="manager-stat-number"><?= e($km($rides['billed_km'])) ?></span>
                    <span class="manager-stat-label">Gefactureerde kilometers</span>
                </div>
                <div class="manager-stat">
                    <span class="manager-stat-number"><?= e($km($rides['driven_km'])) ?></span>
                    <span class="manager-stat-label">Werkelijk gereden kilometers</span>
                </div>
                <div class="manager-stat">
                    <span class="manager-stat-number <?= $rides['difference_km'] > 0 ? 'stats-good' : '' ?>">
                        <?= e($km($rides['difference_km'])) ?>
                    </span>
                    <span class="manager-stat-label">
                        Verschil (<?= e($pct($rides['difference_pct'])) ?> minder gereden dan gefactureerd)
                    </span>
                </div>
                <div class="manager-stat">
                    <span class="manager-stat-number">
                        <?= $rides['orders_per_ride'] === null
                            ? '-'
                            : e(number_format($rides['orders_per_ride'], 1, ',', '.')) ?>
                    </span>
                    <span class="manager-stat-label">Gemiddeld aantal orders per rit</span>
                </div>
                <div class="manager-stat">
                    <span class="manager-stat-number"><?= e($pct($rides['occupancy_pct'])) ?></span>
                    <span class="manager-stat-label">Gemiddelde bezetting vrachtwagens</span>
                </div>
                <div class="manager-stat">
                    <span class="manager-stat-number"><?= money($totals['revenue']) ?></span>
                    <span class="manager-stat-label">Omzet over <?= (int) $totals['orders'] ?> geleverde bestellingen</span>
                </div>
            </div>

            <?php if (!$hasRides): ?>
                <p class="note">
                    Er zijn in deze periode geen ritten afgerond, dus de kilometers en de bezetting
                    kunnen niet worden berekend.
                </p>
            <?php endif; ?>

            <h2 class="section-title">Omzet per <?= e($grouping) ?></h2>

            <div class="panel">
                <?php if (!$hasOrders): ?>
                    <p class="manager-empty">Er zijn in deze periode geen bestellingen geleverd.</p>
                <?php else: ?>
                    <div class="stats-chart">
                        <canvas id="revenueChart" aria-label="Omzet per <?= e($grouping) ?>" role="img"></canvas>
                    </div>
                <?php endif; ?>
            </div>

            <h2 class="section-title">Geleverde pallets per <?= e($grouping) ?></h2>

            <div class="panel">
                <?php if (!$hasOrders): ?>
                    <p class="manager-empty">Er zijn in deze periode geen pallets geleverd.</p>
                <?php else: ?>
                    <div class="stats-chart">
                        <canvas id="palletChart" aria-label="Geleverde pallets per <?= e($grouping) ?>" role="img"></canvas>
                    </div>
                <?php endif; ?>
            </div>

            <div class="stats-split">

                <div>
                    <h2 class="section-title">Omzet per land</h2>

                    <div class="panel">
                        <?php if (!$hasOrders): ?>
                            <p class="manager-empty">Er is in deze periode geen omzet gemaakt.</p>
                        <?php else: ?>
                            <div class="stats-chart stats-chart-small">
                                <canvas id="landChart" aria-label="Omzet per land" role="img"></canvas>
                            </div>
                            <table class="manager-table">
                                <thead>
                                    <tr>
                                        <th>Land</th>
                                        <th class="manager-right">Bestellingen</th>
                                        <th class="manager-right">Omzet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($byLand as $land): ?>
                                        <tr>
                                            <td><?= e($land['land']) ?></td>
                                            <td class="manager-right"><?= (int) $land['orders'] ?></td>
                                            <td class="manager-right"><?= money($land['revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <h2 class="section-title">Top 5 klanten</h2>

                    <div class="panel table-scroll">
                        <?php if ($customers === []): ?>
                            <p class="manager-empty">Er zijn in deze periode geen klanten met omzet.</p>
                        <?php else: ?>
                            <table class="manager-table">
                                <thead>
                                    <tr>
                                        <th>Klant</th>
                                        <th class="manager-right">Bestellingen</th>
                                        <th class="manager-right">Pallets</th>
                                        <th class="manager-right">Omzet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $customer): ?>
                                        <tr>
                                            <td><?= e($customer['customer_name']) ?></td>
                                            <td class="manager-right"><?= (int) $customer['orders'] ?></td>
                                            <td class="manager-right"><?= (int) $customer['pallets'] ?></td>
                                            <td class="manager-right"><?= money($customer['revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <p class="note">
                <a href="index.php?page=dashboard_manager">Terug naar het overzicht</a>
            </p>

            <?php if ($hasOrders): ?>
                <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
                <script>
                    window.dorbStats = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                </script>
                <script src="js/stats.js"></script>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</section>
