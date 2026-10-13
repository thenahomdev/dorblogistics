<?php
$sqlCountries = 'SELECT land_id, name
                 FROM land
                 ORDER BY (country_code = "NL") DESC, name';
$stmtCountries = $conn->prepare($sqlCountries);
$stmtCountries->execute();
$countries = $stmtCountries->fetchAll();

$old = $_SESSION['old'] ?? [];


unset($_SESSION['old'], $_SESSION['errors']);

function old(string $field, array $values): string
{
    if (isset($values[$field])) {
        return e($values[$field]);
    } else {
        return '';
    }
}

if (isset($_SESSION['user_id'])) 
  {
    header('Location: index.php');
    exit;
}
?>

<main class="register">
  <div class="register_card">

    <header class="register_header">
      <h1 class="register_title">Account aanmaken</h1>
    </header>


    <form class="register_form" action="php/klant/register.php" method="post">

      <fieldset class="register_fieldset">
        <legend class="register_legend">Ik ben een *</legend>

        <div class="register_radio">
          <input class="register_radio_input" type="radio" id="type-bedrijf"
                 name="customer_type" value="bedrijf" required
                 <?= ($old['customer_type'] ?? '') === 'bedrijf' ? 'checked' : '' ?>>
          <label class="register_radio_label" for="type-bedrijf">Bedrijf</label>
        </div>

        <div class="register_radio">
          <input class="register_radio_input" type="radio" id="type-particulier"
                 name="customer_type" value="particulier" required
                 <?= ($old['customer_type'] ?? '') === 'particulier' ? 'checked' : '' ?>>
          <label class="register_radio_label" for="type-particulier">Particulier</label>
        </div>
      </fieldset>

      <div class="register_field register_field_hidden" id="company-name-row">
        <label class="register_label" for="bedrijfsnaam">Bedrijfsnaam *</label>
        <input class="register_input" type="text" id="bedrijfsnaam"
               name="company_name" placeholder="Bijv. Van Dijk Transport BV"
               value="<?= old('company_name', $old) ?>">
      </div>

      <div class="register_row">
        <div class="register_field">
          <label class="register_label" for="voornaam">Voornaam *</label>
          <input class="register_input" type="text" id="voornaam"
                 name="first_name" required
                 value="<?= old('first_name', $old) ?>">
        </div>

        <div class="register_field">
          <label class="register_label" for="achternaam">Achternaam *</label>
          <input class="register_input" type="text" id="achternaam"
                 name="last_name" required
                 value="<?= old('last_name', $old) ?>">
        </div>
      </div>

      <div class="register_row">
        <div class="register_field">
          <label class="register_label" for="email">E-mailadres *</label>
          <input class="register_input" type="email" id="email"
                 name="email" placeholder="naam@bedrijf.nl" required
                 value="<?= old('email', $old) ?>">
          <p class="register_hint">Hiermee logt u straks in.</p>
        </div>

        <div class="register_field">
          <label class="register_label" for="telefoon">Telefoonnummer *</label>
          <input class="register_input" type="tel" id="telefoon"
                 name="phone" placeholder="06 12345678" required
                 value="<?= old('phone', $old) ?>">
        </div>
      </div>

      <div class="register_row">
        <div class="register_field">
          <label class="register_label" for="straat">Straat *</label>
          <input class="register_input" type="text" id="straat"
                 name="street" placeholder="Straatnaam" maxlength="120" required
                 value="<?= old('street', $old) ?>">
        </div>

        <div class="register_field register_field_small">
          <label class="register_label" for="huisnummer">Huisnummer *</label>
          <input class="register_input" type="text" id="huisnummer"
                 name="housenumber" placeholder="12a" maxlength="10" required
                 value="<?= old('housenumber', $old) ?>">
        </div>
      </div>

      <div class="register_row">
        <div class="register_field register_field_small">
          <label class="register_label" for="postcode">Postcode *</label>
          <input class="register_input" type="text" id="postcode"
                 name="postal_code" placeholder="1234 AB" required
                 value="<?= old('postal_code', $old) ?>">
        </div>

        <div class="register_field">
          <label class="register_label" for="plaats">Plaats *</label>
          <input class="register_input" type="text" id="plaats"
                 name="city" required
                 value="<?= old('city', $old) ?>">
        </div>

        <div class="register_field">
          <label class="register_label" for="land">Land *</label>
          <select class="register_select" id="land" name="country_id" required>
            <option value="">Kies een land</option>
            <?php foreach ($countries as $country): ?>
              <option value="<?= (int) $country['land_id'] ?>"
                <?= (string) ($old['country_id'] ?? '') === (string) $country['land_id'] ? 'selected' : '' ?>>
                <?= e($country['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

            <div class="register_field">
        <label class="register_label" for="iban">IBAN *</label>
        <input class="register_input" type="text" id="iban"
               name="iban" placeholder="NL91ABNA0417164300" maxlength="34" required
               value="<?= old('iban', $old) ?>">
        <p class="register_hint">
          Hiermee wordt na levering het factuurbedrag geïncasseerd.
        </p>
      </div>

      <div class="register_row">
        <div class="register_field">
          <label class="register_label" for="wachtwoord">Wachtwoord *</label>
          <input class="register_input" type="password" id="wachtwoord"
                 name="password" required>
        </div>

        <div class="register_field">
          <label class="register_label" for="wachtwoord-herhaal">
            Herhaal wachtwoord *
          </label>
          <input class="register_input" type="password" id="wachtwoord-herhaal"
                 name="password_repeat" required>
        </div>
      </div>

      <div class="register_rules">
        <h2 class="register_rules_title">Uw wachtwoord moet bevatten:</h2>
        <ul class="register_rules_list">
          <li class="register_rules_item">minimaal 8 tekens</li>
          <li class="register_rules_item">minimaal 1 hoofdletter</li>
          <li class="register_rules_item">minimaal 1 cijfer</li>
          <li class="register_rules_item">minimaal 1 speciaal teken</li>
        </ul>
      </div>

      <button class="register_button" type="submit">
        Account aanmaken
      </button>

    </form>

    <footer class="register_footer">
      <p class="register_footer_text">
        Heeft u al een account?
        <a class="register_link" href="index.php?page=login">Log hier in</a>
      </p>
    </footer>

  </div>
</main>