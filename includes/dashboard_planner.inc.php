<?php

require_once __DIR__ . '/../php/dashboard_planner.php';

?>

<title>Orderoverzicht</title>

<body>

<div class="page">

    <div class="titlebar">

        <div class="title-content">

            <div class="title-icon">
                ▦
            </div>

            <div>

                <h1>
                    Orderoverzicht
                </h1>

                <p class="subtitle">
                    Beheer en plan openstaande transportorders
                </p>

            </div>

        </div>

        <div class="role-badge">
            Planner
        </div>

    </div>

    <div class="toolbar">

        <div class="badge">

            <span class="badge-dot"></span>

            <span id="order-count-text">
                <?= $orderCount ?> orders
            </span>

        </div>

        <div class="new-trip-form">

            <button
                type="button"
                class="btn btn-primary"
                onclick="createNewTrip()"
            >

                <span class="button-icon">
                    +
                </span>

                Nieuwe rit maken

            </button>

        </div>

    </div>

    <div class="filterbar">

        <span class="filter-label">
            Status filter:
        </span>

        <button
            type="button"
            id="filter-alles"
            class="btn btn-filter"
            onclick="filterOrders('alles')"
        >
            Alles
        </button>

        <button
            type="button"
            id="filter-openstaand"
            class="btn btn-filter active"
            onclick="filterOrders('openstaand')"
        >
            Openstaand
        </button>

        <button
            type="button"
            id="filter-afgehandeld"
            class="btn btn-filter"
            onclick="filterOrders('afgehandeld')"
        >
            Afgehandeld
        </button>

        <label class="btn btn-checkbox">

            <input
                type="checkbox"
                id="mijn-orders-checkbox"
                onchange="filterMineOrders(this.checked)"
            >

            <span class="custom-checkbox"></span>

            Alleen mijn orders tonen

        </label>

    </div>

    <div class="table-container">

        <table class="orders-table">

            <thead>

                <tr>

                    <th class="col-select"></th>
                    <th>Klantnaam</th>
                    <th>Bestemming</th>
                    <th>Land</th>
                    <th>Pallets</th>
                    <th>Leverdatum</th>
                    <th>Status</th>
                    <th>Toegewezen</th>
                    <th>Afstand</th>
                    <th class="col-plan">Inplannen</th>

                </tr>

            </thead>

            <tbody>

                <?= $orderRows ?>

            </tbody>

        </table>

    </div>

    <div class="footnote">

        <span class="info-icon">
            i
        </span>

        <span>
            Gele rij = order toegewezen aan deze planner.
            Standaardfilter = 'Openstaand'.
            Selecteer een order en maak vervolgens een nieuwe rit.
        </span>

    </div>

</div>

</body>