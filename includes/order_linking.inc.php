<?php

$old = $_SESSION['ride_old'] ?? [];

unset($_SESSION['ride_old']);

function swapLink(array $ids, int $first, int $second, string $date): string
{
  $temp         = $ids[$first];
  $ids[$first]  = $ids[$second];
  $ids[$second] = $temp;

  return 'index.php?page=order_linking&orders=' . implode(',', $ids)
       . '&date=' . urlencode($date);
}

$idsString = (string) ($_GET['orders'] ?? '');

$orderIds = array_map('intval', explode(',', $idsString));
$orderIds = array_values(array_filter($orderIds));

if ($orderIds === []) {
  header('Location: index.php?page=dashboard_planner');
  exit;
} 

if (isset($_GET['date'])) {
    $rideDate = trim((string) $_GET['date']);
} else {
    $rideDate = '';
}

if ($rideDate === '') {

  if (isset($old['departure_date']) && $old['departure_date'] !== '') {
    $rideDate = $old['departure_date'];
  } else {
    $rideDate = date('Y-m-d', strtotime('+1 day'));
  }
}

if ($rideDate < date('Y-m-d')) {
  $rideDate = date('Y-m-d', strtotime('+1 day'));
}

if (isset($old['departure_time']) && $old['departure_time'] !== '') {
  $rideTime = $old['departure_time'];
} else {
  $rideTime = '07:00';
}

$today = date('Y-m-d');
$minTime = '';

if ($rideDate === $today) {

  $oneHourLater = strtotime('+1 hour');

  if (date('Y-m-d', $oneHourLater) !== $today) {
    $rideDate = date('Y-m-d', strtotime('+1 day'));
  } else {
    $minTime = date('H:i', $oneHourLater);
  }
}

$timeOptions = [];

$start = strtotime($rideDate . ' 05:00');
$end   = strtotime($rideDate . ' 22:00');

for ($currentTime = $start; $currentTime <= $end; $currentTime = $currentTime + 30 * 60) {

    $time = date('H:i', $currentTime);

    if ($minTime !== '' && $time < $minTime) {
        continue;
    }

    $timeOptions[] = $time;
}

if ($timeOptions === []) {

  $rideDate = date('Y-m-d', strtotime('+1 day'));
  $minTime  = '';

  $start = strtotime($rideDate . ' 05:00');
  $end   = strtotime($rideDate . ' 22:00');

  for ($t = $start; $t <= $end; $t = $t + 30 * 60) {
    $timeOptions[] = date('H:i', $t);
  }
}

if (isset($old['truck_id'])) {
  $selectedTruckId = (int) $old['truck_id'];
} else {
  $selectedTruckId = 0;
}

if (isset($old['driver_id'])) {
  $selectedDriverId = (int) $old['driver_id'];
} else {
  $selectedDriverId = 0;
}


$placeholders = implode(',', array_fill(0, count($orderIds), '?'));

$sqlOrders = "SELECT o.order_id, o.pallet_count, o.dest_street, o.dest_housenumber,
                     o.dest_postalcode, ci.name AS dest_city, ci.land_id, o.distance_km,
                     CONCAT(c.first_name, ' ', c.last_name) AS contact_name, c.companyname,
                     l.name AS land_name
              FROM orders o
              JOIN customer c ON c.user_id = o.customer_id
              JOIN city ci ON ci.city_id = o.dest_city_id
              JOIN land l ON l.land_id = ci.land_id
              WHERE o.order_id IN ($placeholders)
              ORDER BY FIELD(o.order_id, $placeholders)";

$stmtOrders = $conn->prepare($sqlOrders);

$stmtOrders->execute(array_merge($orderIds, $orderIds));
$stops = $stmtOrders->fetchAll();

$orderIds = [];

foreach ($stops as $stop) {
  $orderIds[] = (int) $stop['order_id'];
}

$totalPallets = 0;
$landIds = [];

foreach ($stops as $stop) {
  $totalPallets = $totalPallets + (int) $stop['pallet_count'];
  $landIds[] = (int) $stop['land_id'];
}

$landIds = array_values(array_unique($landIds));

$drivenKm = 0;

foreach ($stops as $index => $stop) {

  $currentKm = (float) $stop['distance_km'];

  if ($index === 0) {
    $stops[$index]['leg_km'] = $currentKm;
  } else {
    $previousKm = (float) $stops[$index - 1]['distance_km'];
    $stops[$index]['leg_km'] = abs($currentKm - $previousKm);
  }

  $drivenKm = $drivenKm + $stops[$index]['leg_km'];
}

$sqlTrucks = "SELECT t.truck_id, t.license_plate, tt.model_name, tt.pallet_capacity, tt.required_license
              FROM truck t
              JOIN trucktype tt ON tt.trucktype_id = t.trucktype_id
              WHERE t.in_service = 1
                AND tt.pallet_capacity = (
                    SELECT MIN(tt2.pallet_capacity)
                    FROM trucktype tt2
                    JOIN truck t2 ON t2.trucktype_id = tt2.trucktype_id
                    WHERE t2.in_service = 1
                      AND tt2.pallet_capacity >= :pallets
                )
                AND t.truck_id NOT IN (
                    SELECT truck_id
                    FROM ride
                    WHERE ride_date = :ride_date
                      AND status != 'geannuleerd'
                )
              ORDER BY t.license_plate";

$stmtTrucks = $conn->prepare($sqlTrucks);
$stmtTrucks->execute([
  ':pallets'   => $totalPallets,
  ':ride_date' => $rideDate
]);
$trucks = $stmtTrucks->fetchAll();

$drivers = [];

if ($landIds !== []) {

  $landPlaceholders = implode(',', array_fill(0, count($landIds), '?'));

  $sqlDrivers = "SELECT d.employee_id, d.license_type, e.first_name, e.last_name, GROUP_CONCAT(l.country_code ORDER BY l.country_code) AS countries
                 FROM driver d
                 JOIN employee e ON e.employee_id = d.employee_id
                 JOIN driver_land dl ON dl.employee_id = d.employee_id
                 JOIN land l ON l.land_id = dl.land_id
                 WHERE e.in_service = 1
                   AND d.license_expiry >= CURDATE()
                   AND d.employee_id IN (
                       SELECT employee_id
                       FROM driver_land
                       WHERE land_id IN ($landPlaceholders)
                       GROUP BY employee_id
                       HAVING COUNT(DISTINCT land_id) = ?
                   )
                   AND d.employee_id NOT IN (
                       SELECT driver_id
                       FROM ride
                       WHERE ride_date = ?
                         AND status <> 'geannuleerd'
                   )
                 GROUP BY d.employee_id, d.license_type, e.first_name, e.last_name
                 ORDER BY e.first_name, e.last_name";

  $params = array_merge($landIds, [count($landIds), $rideDate]);

  $stmtDrivers = $conn->prepare($sqlDrivers);
  $stmtDrivers->execute($params);
  $drivers = $stmtDrivers->fetchAll();
}

?>
<main class="ritplan">

  <header class="ritplan-header">
    <h1 class="ritplan-title">Nieuwe rit samenstellen</h1>
  </header>

  <form class="ritplan-form" action="php/order_linking.php" method="post">

    <div class="ritplan-layout">

      <section class="ritplan-stops">

        <h2 class="ritplan-subtitle">Stops in deze rit</h2>

        <table class="ritplan-table">

          <thead>
            <tr>
              <th class="ritplan-th">Nr.</th>
              <th class="ritplan-th">Volgorde</th>
              <th class="ritplan-th">Inladen als</th>
              <th class="ritplan-th">Klant en bestemming</th>
              <th class="ritplan-th">Land</th>
              <th class="ritplan-th ritplan-th-right">Pallets</th>
              <th class="ritplan-th ritplan-th-right">Afstand vanaf vorige</th>
            </tr>
          </thead>

          <tbody>

            <tr class="ritplan-row ritplan-row-start">
              <td class="ritplan-td">Start</td>
              <td class="ritplan-td"></td>
              <td class="ritplan-td"></td>
              <td class="ritplan-td">Distributiecentrum Arnhem</td>
              <td class="ritplan-td">Nederland</td>
              <td class="ritplan-td ritplan-td-right"></td>
              <td class="ritplan-td ritplan-td-right">-</td>
            </tr>

            <?php $number = 1; ?>

          
            <?php foreach ($stops as $index => $stop) { ?>

              <tr class="ritplan-row">

                <td class="ritplan-td">
                  <?= $number ?>
                  <input type="hidden"
                    name="sequence[<?= (int) $stop['order_id'] ?>]"
                    value="<?= $number ?>">
                </td>

               
                <td class="ritplan-td">

                  <?php if ($index > 0) { ?>
                    <a class="ritplan-move"
                       href="<?= e(swapLink($orderIds, $index, $index - 1, $rideDate)) ?>"
                       title="Eerder afleveren">&#9650;</a>
                  <?php } ?>

                  <?php if ($index < count($stops) - 1) { ?>
                    <a class="ritplan-move"
                       href="<?= e(swapLink($orderIds, $index, $index + 1, $rideDate)) ?>"
                       title="Later afleveren">&#9660;</a>
                  <?php } ?>

                </td>

                <td class="ritplan-td">
                  <?= count($stops) - $index ?>
                </td>


                <td class="ritplan-td">
                  <?php if (!empty($stop['companyname'])) { ?>
                    <?= e($stop['companyname']) ?>
                  <?php } else { ?>
                    <?= e($stop['contact_name']) ?>
                  <?php } ?>
                  - <?= e($stop['dest_city']) ?>
                </td>

                <td class="ritplan-td">
                  <?= e($stop['land_name']) ?>
                </td>

                <td class="ritplan-td ritplan-td-right">
                  <?= (int) $stop['pallet_count'] ?>
                </td>

                <td class="ritplan-td ritplan-td-right">
                  <?= number_format($stop['leg_km'], 0, ',', '.') ?> km
                </td>

              </tr>

              <?php $number = $number + 1; ?>

            <?php } ?>

          </tbody>

        </table>

        <p class="ritplan-total">
          Totaal <?= $totalPallets ?> pallets -
          gereden afstand <?= number_format($drivenKm, 0, ',', '.') ?> km
        </p>

        <p class="ritplan-hint">
          Pas eerst de volgorde aan met de pijltjes, en kies daarna de
          vrachtwagen en chauffeur.
        </p>

        <button class="ritplan-back" type="button"
          onclick="window.location.href='index.php?page=dashboard_planner'">
          <span>&#8617;</span> Terug
        </button>

      </section>

      <aside class="ritplan-side">

        <h2 class="ritplan-subtitle">Vrachtwagen en chauffeur</h2>

        <div class="ritplan-field">
          <label class="ritplan-label" for="departure-date">Vertrekdatum</label>
          <input class="ritplan-input" type="date" id="departure-date"
            name="departure_date" required
            value="<?= e($rideDate) ?>"
            min="<?= date('Y-m-d') ?>">
        </div>

        <div class="ritplan-field">
          <label class="ritplan-label" for="departure-time">Vertrektijd</label>

          <select class="ritplan-select" id="departure-time" name="departure_time" required>
            <?php foreach ($timeOptions as $time) { ?>

              <?php
                if ($time === $rideTime) {
                    $timeSelected = 'selected';
                } else {
                    $timeSelected = '';
                }
              ?>

              <option value="<?= e($time) ?>" <?= $timeSelected ?>>
                <?= e($time) ?> uur
              </option>

            <?php } ?>
          </select>

          <?php if ($minTime !== '') { ?>
            <p class="ritplan-hint">
              Vandaag kunt u vertrekken vanaf <?= e($minTime) ?> uur.
            </p>
          <?php } ?>
        </div>

        <div class="ritplan-field">
          <label class="ritplan-label" for="truck">Vrachtwagen</label>

          <?php if ($trucks === []) { ?>

            <p class="ritplan-warning">
              Geen vrachtwagen beschikbaar voor <?= $totalPallets ?> pallets
              op <?= e($rideDate) ?>. Kies een andere datum of pas de selectie aan.
            </p>

          <?php } else { ?>

            <select class="ritplan-select" id="truck" name="truck_id" required>
              <option value="">Kies een vrachtwagen</option>
              <?php foreach ($trucks as $truck) { ?>

                <?php
                  if ((int) $truck['truck_id'] === $selectedTruckId) {
                      $truckSelected = 'selected';
                  } else {
                      $truckSelected = '';
                  }
                ?>

                <option value="<?= (int) $truck['truck_id'] ?>"
                  data-capacity="<?= (int) $truck['pallet_capacity'] ?>"
                  data-license="<?= e($truck['required_license']) ?>"
                  <?= $truckSelected ?>>
                  <?= e($truck['model_name']) ?> -
                  <?= e($truck['license_plate']) ?> -
                  max <?= (int) $truck['pallet_capacity'] ?> pallets
                </option>
              <?php } ?>
            </select>

          <?php } ?>

        </div>

        <div class="ritplan-field">
          <label class="ritplan-label" for="driver">Chauffeur</label>

          <?php if ($drivers === []) { ?>

            <p class="ritplan-warning">
              Geen chauffeur beschikbaar op <?= e($rideDate) ?> die in alle
              landen van deze rit mag rijden.
            </p>

          <?php } else { ?>

            <select class="ritplan-select" id="driver" name="driver_id" required>
              <option value="">Kies een chauffeur</option>
              <?php foreach ($drivers as $driver) { ?>

                <?php
                  if ((int) $driver['employee_id'] === $selectedDriverId) {
                      $driverSelected = 'selected';
                  } else {
                      $driverSelected = '';
                  }

                  if (!empty($driver['countries'])) {
                      $driverCountries = $driver['countries'];
                  } else {
                      $driverCountries = 'geen landen';
                  }
                ?>

                <option value="<?= (int) $driver['employee_id'] ?>"
                  data-license="<?= e($driver['license_type']) ?>"
                  <?= $driverSelected ?>>
                  <?= e($driver['first_name']) ?>
                  <?= e($driver['last_name']) ?>
                  - rijbewijs <?= e($driver['license_type']) ?>
                  - <?= e($driverCountries) ?>
                </option>
              <?php } ?>
            </select>

          <?php } ?>

        </div>

        <?php if ($trucks !== [] && $drivers !== []) { ?>

          <button class="ritplan-submit" type="submit">
            Rit opslaan
          </button>

        <?php } ?>

      </aside>

    </div>

  </form>

</main>

<script>
  const truckSelect = document.getElementById('truck');
  const driverSelect = document.getElementById('driver');
  const dateInput = document.getElementById('departure-date');

  if (dateInput !== null) {

    dateInput.addEventListener('change', function() {
      const url = new URL(window.location.href);
      url.searchParams.set('date', dateInput.value);
      window.location.href = url.toString();
    });
  }

  if (truckSelect !== null && driverSelect !== null) {

    function filterDrivers() {

      const truckOption = truckSelect.options[truckSelect.selectedIndex];

      let needed;

      if (truckOption.dataset.license) {
        needed = truckOption.dataset.license;
      } else {
        needed = '';
      }

      for (const option of driverSelect.options) {

        if (option.value === '') {
          continue;
        }

        const driverLicense = option.dataset.license;

        let allowed;

        if (needed === '') {
          // Nog geen wagen gekozen: iedereen tonen.
          allowed = true;
        } else if (needed === 'C') {
          // A normal truck: every driver can handle it.
          allowed = true;
        } else if (driverLicense === 'D') {
          // Truck needs D and this driver has D.
          allowed = true;
        } else {
          // Truck needs D but this driver only has C.
          allowed = false;
        }

        option.disabled = !allowed;
        option.hidden = !allowed;
      }

      const current = driverSelect.options[driverSelect.selectedIndex];

      if (current && current.disabled) {
        driverSelect.value = '';
      }
    }

    truckSelect.addEventListener('change', filterDrivers);

    filterDrivers();
  }
</script>