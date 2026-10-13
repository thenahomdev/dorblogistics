# DORB Logistics

Webapplicatie voor een (fictief) transportbedrijf dat pallets vervoert door Nederland, België en Duitsland. Klanten vragen offertes aan en volgen hun zendingen, planners stellen ritten samen, chauffeurs zien hun ritten en managers beheren klanten, medewerkers en statistieken.

Dit project is gemaakt als teamproject tijdens mijn opleiding Software Developer (Rijn IJssel).

## Screenshots

> _Plaats hier screenshots, bijvoorbeeld in een map `docs/screenshots/`:_
>
> `![Homepagina](docs/screenshots/home.png)`
> `![Dashboard planner](docs/screenshots/dashboard-planner.png)`

## Gebruikte technieken

- **PHP 8** (met `strict_types`, PDO en prepared statements)
- **MySQL / MariaDB**
- **HTML, CSS en JavaScript** (zonder framework)
- **Composer** met:
  - [PHPMailer](https://github.com/PHPMailer/PHPMailer) voor e-mails (orderbevestigingen)
  - [Dompdf](https://github.com/dompdf/dompdf) voor PDF-facturen
- **OpenRouteService API** voor het berekenen van afstanden en ritprijzen

## Functies

Het systeem heeft vier rollen, elk met een eigen dashboard:

**Klant**
- Account registreren (bedrijf of particulier) en inloggen
- Offerte aanvragen met automatische prijsberekening (afstand × aantal pallets × tarief, plus landtoeslag)
- Bestellingen bevestigen of annuleren
- Overzicht van recente zendingen en facturen
- Wachtwoord wijzigen

**Planner**
- Overzicht van openstaande orders met filters
- Orders claimen om ze in te plannen
- Orders koppelen aan een rit, vrachtwagen en chauffeur ("rit samenstellen")

**Chauffeur**
- Dashboard met de toegewezen ritten en de details per rit

**Manager**
- Klanten beheren (overzicht, status wijzigen, verwijderen)
- Medewerkers aanmaken en beheren (met CSRF-beveiliging)
- Statistieken en grafieken over orders en omzet

**Beveiliging**
- Wachtwoorden gehasht met `password_hash()`
- Rolgebaseerde toegang per pagina
- Prepared statements tegen SQL-injectie, output-escaping tegen XSS

## Lokaal installeren (XAMPP)

### Vereisten
- [XAMPP](https://www.apachefriends.org/) met PHP 8.1 of hoger
- [Composer](https://getcomposer.org/)

### Stappen

1. **Clone het project** in de `htdocs`-map van XAMPP:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/thenahomdev/dorblogistics.git
   cd dorblogistics
   ```

2. **Installeer de PHP-pakketten:**
   ```bash
   composer install
   ```

3. **Maak de database aan.** Start Apache en MySQL in het XAMPP Control Panel, open [phpMyAdmin](http://localhost/phpmyadmin) en importeer:
   1. `database/schema.sql`: maakt de database `dorb_logistics` met alle tabellen en basisgegevens (rollen, landen, tarieven)
   2. `database/seed.sql` (optioneel): vult de database met fictieve demo-gegevens

4. **Stel de configuratie in.** Kopieer het voorbeeldbestand:
   ```bash
   copy lib\config.example.php lib\config.php
   ```
   Vul in `lib/config.php` je databasegegevens in (bij een standaard XAMPP-installatie: gebruiker `root`, leeg wachtwoord).
   Voor de afstandsberekening kun je optioneel een gratis API-key aanvragen op [openrouteservice.org](https://openrouteservice.org).

5. **(Optioneel) E-mail testen:** de app verstuurt mail via een lokale SMTP-server op `127.0.0.1:1025`, bijvoorbeeld [Mailpit](https://mailpit.axllent.org/).

6. Open **http://localhost/dorblogistics** in je browser.

### Demo-accounts

Na het importeren van `seed.sql` kun je inloggen met onder andere:

| Rol | E-mail |
|---|---|
| Manager | `manager@dorb.test` |
| Planner | `planner@dorb.test` |
| Chauffeur | `chauffeur@dorb.test` |
| Klant | `bedrijf@dorb.test` |

Alle demo-accounts hebben hetzelfde wachtwoord: `<vul hier het demo-wachtwoord in>`

## Projectstructuur

```
dorblogistics/
├── index.php          # Router: kiest de pagina en controleert de rol
├── includes/          # Pagina's (views)
├── php/               # Verwerking van formulieren en acties
├── lib/               # Hulpfuncties: auth, mail, pdf, geo, statistieken
├── database/          # Databaseverbinding, schema en demo-data
├── css/  js/          # Opmaak en scripts
└── composer.json
```
