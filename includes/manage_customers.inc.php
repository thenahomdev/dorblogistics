<?php

$perPage = 15;

$currentPage = (int) ($_GET['p'] ?? 1);

if ($currentPage < 1) {
  $currentPage = 1;
}

$status = trim((string) ($_GET['status'] ?? ''));

$allowedStatuses = ['aangemeld', 'actief', 'inactief'];

if (!in_array($status, $allowedStatuses, true)) {
  $status = '';
}

//
$search = trim((string) ($_GET['search'] ?? ''));

$conditions = [];
$params = [];

if ($status !== '') {
  $conditions[] = "c.`status` = :status";
  $params[':status'] = $status;
}


if ($search !== '') {
  $conditions[] = "(c.companyname LIKE :search1
                   OR CONCAT(c.first_name, ' ', c.last_name) LIKE :search2)";

  $params[':search1'] = '%' . $search . '%';
  $params[':search2'] = '%' . $search . '%';
}

$whereSql = '';

if ($conditions !== []) {
  $whereSql = 'WHERE ' . implode(' AND ', $conditions);
}

$urlExtra = '';

if ($status !== '') {
  $urlExtra .= '&status=' . urlencode($status);
}

if ($search !== '') {
  $urlExtra .= '&search=' . urlencode($search);
}
//

$classAlle = '';
$classAangemeld = '';
$classActief = '';
$classInactief = '';

if ($status === 'aangemeld') {
  $classAangemeld = 'active';
} elseif ($status === 'actief') {
  $classActief = 'active';
} elseif ($status === 'inactief') {
  $classInactief = 'active';
} else {
  $classAlle = 'active';
}

$sqlTotal = "SELECT COUNT(*)
             FROM customer c
             $whereSql";

$stmtTotal = $conn->prepare($sqlTotal);
$stmtTotal->execute($params);

$totalCustomers = (int) $stmtTotal->fetchColumn();

$totalPages = (int) ceil($totalCustomers / $perPage);

if ($totalPages < 1) {
  $totalPages = 1;
}

if ($currentPage > $totalPages) {
  $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $perPage;

$sqlCustomers = "SELECT c.user_id, c.type, c.status, c.companyname,
                        c.first_name, c.last_name, c.signed_up,
                        ci.name AS city_name
                 FROM customer c
                 JOIN city ci ON ci.city_id = c.city_id
                 $whereSql
                 ORDER BY c.`status` = 'aangemeld' DESC, c.signed_up DESC
                 LIMIT $perPage OFFSET $offset";

$stmtCustomers = $conn->prepare($sqlCustomers);
$stmtCustomers->execute($params);

$customers = $stmtCustomers->fetchAll();

$sqlPending = "SELECT COUNT(*)
               FROM customer
               WHERE `status` = 'aangemeld'";

$stmtPending = $conn->prepare($sqlPending);
$stmtPending->execute();

$pendingCount = (int) $stmtPending->fetchColumn();

if ($totalCustomers === 0) {
  $fromNumber = 0;
  $toNumber = 0;
} else {
  $fromNumber = $offset + 1;
  $toNumber = $offset + count($customers);
}

?>

<div class="page">

  <div class="titlebar">

    <div class="title-content">

      <div>
        <h1>Klanten beheren</h1>
      </div>

    </div>

    <?php if ($pendingCount > 0) { ?>

      <div class="badge">
        <span class="badge-dot"></span>
        <?= $pendingCount ?> nieuwe aanmeldingen wachten op goedkeuring
      </div>

    <?php } ?>

  </div>

  <div class="filterbar manage_customers">

    <form class="klant-form-row" method="get" action="index.php">

      <input type="hidden" name="page" value="manage_customers" >

      <?php if ($status !== '') { ?>

        <input type="hidden" name="status" value="<?= e($status) ?>" >

      <?php } ?>

      <div class="klant-field">

        <label for="search">
          Zoek op naam
        </label>

        <input
          type="text"
          id="search"
          name="search"
          placeholder="Bedrijfsnaam of naam"
          value="<?= e($search) ?>"
        >

      </div>

      <div class="klant-field">

        <label>&nbsp;</label>

        <button type="submit" class="btn btn-ghost">
          Zoeken
        </button>

      </div>

    </form>

    <div class="manage_customers_filters">

      <span class="filter-label">
        Status filter:
      </span>

      <a class="btn btn-filter <?= $classAlle ?>" href="index.php?page=manage_customers<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" >
        Alle
      </a>

      <a class="btn btn-filter <?= $classAangemeld ?>" href="index.php?page=manage_customers&status=aangemeld<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" >
        Aangemeld (<?= $pendingCount ?>)
      </a>

      <a class="btn btn-filter <?= $classActief ?>" href="index.php?page=manage_customers&status=actief<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" >
        Actief
      </a>

      <a class="btn btn-filter <?= $classInactief ?>" href="index.php?page=manage_customers&status=inactief<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" >
        Inactief
      </a>

    </div>

  </div>

  <?php if ($totalCustomers > 0) { ?>

    <div class="filterbar">

      <span class="filter-label">
        <?= $fromNumber ?> - <?= $toNumber ?> van <?= $totalCustomers ?> klanten
      </span>

    </div>

  <?php } ?>

  <div class="table-container">

    <table class="orders-table">

      <thead>

        <tr>
          <th>Naam</th>
          <th>Type</th>
          <th>Plaats</th>
          <th>Status</th>
          <th>Aangemeld op</th>
          <th>Acties</th>
        </tr>

      </thead>

      <tbody>

        <?php if ($customers === []) { ?>

          <tr>

            <td colspan="6">

              <?php if ($search !== '') { ?>

                Geen klanten gevonden voor:
                <strong><?= e($search) ?></strong>

              <?php } else { ?>

                Er zijn geen klanten gevonden.

              <?php } ?>

            </td>

          </tr>

        <?php } ?>

        <?php foreach ($customers as $customer) { ?>

          <?php

          if (!empty($customer['companyname'])) {
            $customerName = $customer['companyname'];
          } else {
            $customerName = trim($customer['first_name'] . ' ' . $customer['last_name']);
          }

          if ($customer['status'] === 'aangemeld') {
            $statusClass = 'klant-status-open';
          } elseif ($customer['status'] === 'actief') {
            $statusClass = 'klant-status-completed';
          } else {
            $statusClass = 'klant-status-cancelled';
          }

          $signedUp = date('d-m-Y', strtotime($customer['signed_up']));

          ?>

          <tr>

            <td>
              <?= e($customerName) ?>
            </td>

            <td>
              <?= e(ucfirst($customer['type'])) ?>
            </td>

            <td>
              <?= e($customer['city_name']) ?>
            </td>

            <td>

              <span class="klant-status <?= e($statusClass) ?>">
                <?= e(ucfirst($customer['status'])) ?>
              </span>

            </td>

            <td>
              <?= e($signedUp) ?>
            </td>

            <td>

              <form method="post" action="php/customer_status.php">

                <input type="hidden" name="user_id" value="<?= (int) $customer['user_id'] ?>" >

                <?php if ($customer['status'] === 'aangemeld') { ?>

                  <input type="hidden" name="action" value="approve" >

                  <button type="submit" class="btn btn-primary">
                    Goedkeuren
                  </button>

                <?php } elseif ($customer['status'] === 'actief') { ?>

                  <input type="hidden" name="action" value="block" >

                  <button type="submit" class="btn btn-ghost">
                    Blokkeren
                  </button>

                <?php } else { ?>

                  <input type="hidden" name="action" value="unblock" >

                  <button type="submit" class="btn btn-filter">
                    Deblokkeren
                  </button>

                <?php } ?>

              </form>

            </td>

          </tr>

        <?php } ?>

      </tbody>

    </table>

  </div>

  <?php if ($totalPages > 1) { ?>

    <div class="filterbar">

      <?php if ($currentPage > 1) { ?>

        <a class="btn btn-filter" href="index.php?page=manage_customers&p=<?= $currentPage - 1 ?><?= $urlExtra ?>" >
          &larr; Vorige
        </a>

      <?php } ?>

      <span class="filter-label">
        Pagina <?= $currentPage ?> van <?= $totalPages ?>
      </span>

      <?php if ($currentPage < $totalPages) { ?>

        <a class="btn btn-filter" href="index.php?page=manage_customers&p=<?= $currentPage + 1 ?><?= $urlExtra ?>" >
          Volgende &rarr;
        </a>

      <?php } ?>

    </div>

  <?php } ?>

</div>