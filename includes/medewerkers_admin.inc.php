<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/medewerkers_admin.php';
?>

<main class="employee-create">
    <div class="employee-create-card">

        <header class="employee-create-header">
            <h1 class="employee-create-title">
                Medewerker aanmaken
            </h1>

            <p class="employee-create-subtitle">
                Maak een nieuw account aan voor een medewerker van DORB Logistics.
            </p>
        </header>

        <?php if ($success !== null): ?>
            <div class="employee-create-success">
                <?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="employee-create-errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li>
                            <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form
            class="employee-create-form"
            action="/dorblogistics/php/medewerkers_admin_verwerken.php"
            method="post"
            id="employee-create-form"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
            >

            <fieldset class="employee-create-fieldset">
                <legend class="employee-create-legend">
                    Functie
                </legend>

                <div class="employee-create-field">
                    <label
                        class="employee-create-label"
                        for="employee-role"
                    >
                        Functie *
                    </label>

                    <select
                        class="employee-create-select"
                        id="employee-role"
                        name="role_id"
                        required
                    >
                        <option value="" data-role="">
                            Kies een functie
                        </option>

                        <?php foreach ($employeeRoles as $role): ?>
                            <option
                                value="<?= (int) $role['role_id'] ?>"
                                data-role="<?= htmlspecialchars((string) $role['name'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= (string) ($old['role_id'] ?? '') === (string) $role['role_id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    ucfirst((string) $role['name']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>

            <fieldset class="employee-create-fieldset">
                <legend class="employee-create-legend">
                    Persoonsgegevens
                </legend>

                <div class="employee-create-row">

                    <div class="employee-create-field">
                        <label
                            class="employee-create-label"
                            for="first-name"
                        >
                            Voornaam *
                        </label>

                        <input
                            class="employee-create-input"
                            type="text"
                            id="first-name"
                            name="first_name"
                            maxlength="60"
                            required
                            autocomplete="given-name"
                            value="<?= employee_old('first_name', $old) ?>"
                        >
                    </div>

                    <div class="employee-create-field">
                        <label
                            class="employee-create-label"
                            for="last-name"
                        >
                            Achternaam *
                        </label>

                        <input
                            class="employee-create-input"
                            type="text"
                            id="last-name"
                            name="last_name"
                            maxlength="80"
                            required
                            autocomplete="family-name"
                            value="<?= employee_old('last_name', $old) ?>"
                        >
                    </div>

                </div>

                <div class="employee-create-row">

                    <div class="employee-create-field">
                        <label
                            class="employee-create-label"
                            for="email"
                        >
                            E-mailadres *
                        </label>

                        <input
                            class="employee-create-input"
                            type="email"
                            id="email"
                            name="email"
                            maxlength="255"
                            required
                            autocomplete="email"
                            value="<?= employee_old('email', $old) ?>"
                        >
                    </div>

                    <div class="employee-create-field">
                        <label
                            class="employee-create-label"
                            for="phone"
                        >
                            Telefoonnummer *
                        </label>

                        <input
                            class="employee-create-input"
                            type="tel"
                            id="phone"
                            name="phone"
                            maxlength="20"
                            required
                            autocomplete="tel"
                            value="<?= employee_old('phone', $old) ?>"
                        >
                    </div>

                </div>

                <div class="employee-create-field">
                    <label
                        class="employee-create-label"
                        for="hire-date"
                    >
                        Datum indiensttreding *
                    </label>

                    <input
                        class="employee-create-input"
                        type="date"
                        id="hire-date"
                        name="hire_date"
                        required
                        value="<?= employee_old('hire_date', $old) ?>"
                    >
                </div>
            </fieldset>

            <fieldset
                class="employee-create-fieldset employee-create-driver-fields"
                id="driver-fields"
                hidden
            >
                <legend class="employee-create-legend">
                    Chauffeursgegevens
                </legend>

                <div class="employee-create-row">

                    <div class="employee-create-field">
                        <label
                            class="employee-create-label"
                            for="license-type"
                        >
                            Rijbewijscategorie *
                        </label>

                        <select
                            class="employee-create-select"
                            id="license-type"
                            name="license_type"
                        >
                            <option value="">
                                Kies een categorie
                            </option>

                            <option
                                value="C"
                                <?= ($old['license_type'] ?? '') === 'C' ? 'selected' : '' ?>
                            >
                                C
                            </option>

                            <option
                                value="D"
                                <?= ($old['license_type'] ?? '') === 'D' ? 'selected' : '' ?>
                            >
                                D
                            </option>
                        </select>
                    </div>

                    <div class="employee-create-field">
                        <label
                            class="employee-create-label"
                            for="license-number"
                        >
                            Rijbewijsnummer *
                        </label>

                        <input
                            class="employee-create-input"
                            type="text"
                            id="license-number"
                            name="license_number"
                            maxlength="30"
                            value="<?= employee_old('license_number', $old) ?>"
                        >
                    </div>

                </div>

                <div class="employee-create-field">
                    <label
                        class="employee-create-label"
                        for="license-expiry"
                    >
                        Rijbewijs geldig tot *
                    </label>

                    <input
                        class="employee-create-input"
                        type="date"
                        id="license-expiry"
                        name="license_expiry"
                        value="<?= employee_old('license_expiry', $old) ?>"
                    >
                </div>

                <div class="employee-create-field">
                    <span class="employee-create-label">
                        Extra landen waarin de chauffeur mag rijden
                    </span>

                    <div class="employee-create-countries">

                        <?php
                        $selectedLandIds = array_map(
                            'strval',
                            is_array($old['driver_land_ids'] ?? null)
                                ? $old['driver_land_ids']
                                : []
                        );
                        ?>

                        <?php foreach ($employeeCountries as $country): ?>

                            <?php if ($country['country_code'] !== 'NL'): ?>

                                <label class="employee-create-checkbox">
                                    <input
                                        type="checkbox"
                                        name="driver_land_ids[]"
                                        value="<?= (int) $country['land_id'] ?>"
                                        <?= in_array(
                                            (string) $country['land_id'],
                                            $selectedLandIds,
                                            true
                                        ) ? 'checked' : '' ?>
                                    >

                                    <span>
                                        <?= htmlspecialchars(
                                            (string) $country['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </label>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>

                    <small class="employee-create-help">
                        Nederland is standaard toegestaan. Selecteer één of meerdere
                        landen als de chauffeur ook in het buitenland mag rijden.
                    </small>
                </div>
            </fieldset>

            <fieldset class="employee-create-fieldset">
                <legend class="employee-create-legend">
                    Inloggegevens
                </legend>

                <div class="employee-create-row">

                    <div class="employee-create-field">
                        <label
                            class="employee-create-label"
                            for="password"
                        >
                            Wachtwoord *
                        </label>

                        <input
                            class="employee-create-input"
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            required
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="employee-create-field">
                        <label
                            class="employee-create-label"
                            for="password-repeat"
                        >
                            Herhaal wachtwoord *
                        </label>

                        <input
                            class="employee-create-input"
                            type="password"
                            id="password-repeat"
                            name="password_repeat"
                            minlength="8"
                            required
                            autocomplete="new-password"
                        >
                    </div>

                </div>

                <div class="employee-create-rules">
                    <h2 class="employee-create-rules-title">
                        Het wachtwoord moet bevatten:
                    </h2>

                    <ul class="employee-create-rules-list">
                        <li>minimaal 8 tekens</li>
                        <li>minimaal 1 hoofdletter</li>
                        <li>minimaal 1 cijfer</li>
                        <li>minimaal 1 speciaal teken</li>
                    </ul>
                </div>
            </fieldset>

            <button
                class="employee-create-button"
                type="submit"
            >
                Medewerker aanmaken
            </button>

        </form>
    </div>
</main>

<script src="js/script.js"></script>