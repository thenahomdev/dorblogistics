DROP DATABASE IF EXISTS dorb_logistics;
CREATE DATABASE dorb_logistics
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE dorb_logistics;

CREATE TABLE land (
    land_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(60)  NOT NULL,
    country_code CHAR(2)      NOT NULL,
    surcharge    DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (land_id),
    UNIQUE KEY uq_land_code (country_code),
    UNIQUE KEY uq_land_name (name),
    CONSTRAINT chk_land_surcharge CHECK (surcharge >= 0)
) ENGINE = InnoDB;

CREATE TABLE city (
    city_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name    VARCHAR(80)  NOT NULL,
    land_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (city_id),
    UNIQUE KEY uq_city_name_land (name, land_id),
    KEY idx_city_land (land_id),
    CONSTRAINT fk_city_land
        FOREIGN KEY (land_id) REFERENCES land (land_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE role (
    role_id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name    VARCHAR(30)      NOT NULL,
    PRIMARY KEY (role_id),
    UNIQUE KEY uq_role_name (name)
) ENGINE = InnoDB;

CREATE TABLE users (
    user_id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    role_id         TINYINT UNSIGNED NOT NULL,
    email           VARCHAR(255)     NOT NULL,
    password_hash   VARCHAR(255)     NOT NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME         NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login      DATETIME         NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES role (role_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE customer (
    user_id     INT UNSIGNED NOT NULL,
    type        ENUM('particulier','bedrijf') NOT NULL
                COMMENT 'particulier = privepersoon, geen bedrijfsnaam; bedrijf = zakelijke klant, bedrijfsnaam verplicht',
    status      ENUM('aangemeld','actief','inactief','geblokkeerd') NOT NULL DEFAULT 'aangemeld'
                COMMENT 'aangemeld = nieuw account, wacht op goedkeuring door DORB; actief = mag inloggen en orders plaatsen; inactief = klant is gestopt en kan niet inloggen; geblokkeerd = door DORB geblokkeerd en kan niet inloggen',
    companyname VARCHAR(120) NULL,
    first_name  VARCHAR(60)  NOT NULL,
    last_name   VARCHAR(80)  NOT NULL,
    phonenumber VARCHAR(20)  NOT NULL,
    iban        VARCHAR(34)  NOT NULL,
    street      VARCHAR(120) NOT NULL,
    housenumber VARCHAR(10)  NOT NULL,
    postalcode  VARCHAR(10)  NOT NULL,
    city_id     INT UNSIGNED NOT NULL,
    signed_up   DATE         NOT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_customer_phonenumber (phonenumber),
    KEY idx_customer_status (status),
    KEY idx_customer_city (city_id),
    CONSTRAINT fk_customer_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_customer_city
        FOREIGN KEY (city_id) REFERENCES city (city_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_customer_companyname CHECK (
        (type = 'bedrijf'     AND companyname IS NOT NULL) OR
        (type = 'particulier' AND companyname IS NULL)
    )
) ENGINE = InnoDB;

CREATE TABLE employee (
    employee_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    first_name  VARCHAR(60)  NOT NULL,
    last_name   VARCHAR(80)  NOT NULL,
    phonenumber VARCHAR(20)  NOT NULL,
    hire_date   DATE         NOT NULL,
    in_service  BOOLEAN      NOT NULL DEFAULT TRUE,
    PRIMARY KEY (employee_id),
    UNIQUE KEY uq_employee_user (user_id),
    CONSTRAINT fk_employee_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE driver (
    employee_id    INT UNSIGNED  NOT NULL,
    license_type   ENUM('C','D') NOT NULL
                   COMMENT 'C = vrachtwagen zonder aanhanger (CF 300 en CF 410); D = mag ook de XF 430 met aanhanger rijden',
    license_number VARCHAR(30)   NOT NULL,
    license_expiry DATE          NOT NULL,
    PRIMARY KEY (employee_id),
    UNIQUE KEY uq_driver_license (license_number),
    CONSTRAINT fk_driver_employee
        FOREIGN KEY (employee_id) REFERENCES employee (employee_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE driver_land (
    employee_id INT UNSIGNED NOT NULL,
    land_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (employee_id, land_id),
    KEY idx_driver_land_land (land_id),
    CONSTRAINT fk_driverland_driver
        FOREIGN KEY (employee_id) REFERENCES driver (employee_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_driverland_land
        FOREIGN KEY (land_id) REFERENCES land (land_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE trucktype (
    trucktype_id     INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    model_name       VARCHAR(40)      NOT NULL,
    pallet_capacity  TINYINT UNSIGNED NOT NULL,
    has_trailer      BOOLEAN          NOT NULL DEFAULT FALSE,
    required_license ENUM('C','D')    NOT NULL
                     COMMENT 'minimaal benodigd rijbewijs: C = zonder aanhanger; D = met aanhanger',
    PRIMARY KEY (trucktype_id),
    UNIQUE KEY uq_trucktype_model (model_name),
    CONSTRAINT chk_trucktype_capacity CHECK (pallet_capacity > 0)
) ENGINE = InnoDB;

CREATE TABLE truck (
    truck_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    trucktype_id  INT UNSIGNED NOT NULL,
    license_plate VARCHAR(15)  NOT NULL,
    in_service    BOOLEAN      NOT NULL DEFAULT TRUE,
    PRIMARY KEY (truck_id),
    UNIQUE KEY uq_truck_plate (license_plate),
    KEY idx_truck_type (trucktype_id),
    CONSTRAINT fk_truck_type
        FOREIGN KEY (trucktype_id) REFERENCES trucktype (trucktype_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE tariff (
    tariff_id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    min_pallets             TINYINT UNSIGNED NOT NULL,
    max_pallets             TINYINT UNSIGNED NULL,
    price_per_km_per_pallet DECIMAL(5,2)     NOT NULL,
    valid_from              DATE             NOT NULL,
    valid_until             DATE             NULL,
    PRIMARY KEY (tariff_id),
    CONSTRAINT chk_tariff_range CHECK (max_pallets IS NULL OR max_pallets >= min_pallets),
    CONSTRAINT chk_tariff_price CHECK (price_per_km_per_pallet > 0)
) ENGINE = InnoDB;

CREATE TABLE orders (
    order_id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    customer_id      INT UNSIGNED     NOT NULL,
    tariff_id        INT UNSIGNED     NOT NULL,
    planner_id       INT UNSIGNED     NULL,
    pallet_count     TINYINT UNSIGNED NOT NULL,
    dest_street      VARCHAR(120)     NOT NULL,
    dest_housenumber VARCHAR(10)      NOT NULL,
    dest_postalcode  VARCHAR(10)      NOT NULL,
    dest_city_id     INT UNSIGNED     NOT NULL,
    distance_km      DECIMAL(7,2)     NOT NULL,
    unit_price       DECIMAL(5,2)     NOT NULL,
    surcharge        DECIMAL(6,2)     NOT NULL,
    order_date       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivery_date    DATE             NULL,
    status           ENUM('offerte','bevestigd','ingepland','onderweg','geleverd','gefactureerd','geannuleerd')
                     NOT NULL DEFAULT 'offerte'
                     COMMENT 'offerte = prijs berekend, klant heeft nog niet bevestigd; bevestigd = klant heeft bevestigd, wacht op planning; ingepland = gekoppeld aan een rit; onderweg = rit is vertrokken; geleverd = afgeleverd op de bestemming; gefactureerd = factuur is verstuurd; geannuleerd = order gaat niet door',
    PRIMARY KEY (order_id),
    KEY idx_orders_customer (customer_id),
    KEY idx_orders_tariff (tariff_id),
    KEY idx_orders_planner (planner_id),
    KEY idx_orders_status (status),
    KEY idx_orders_city (dest_city_id),
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customer (user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_orders_tariff
        FOREIGN KEY (tariff_id) REFERENCES tariff (tariff_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_orders_planner
        FOREIGN KEY (planner_id) REFERENCES employee (employee_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_orders_city
        FOREIGN KEY (dest_city_id) REFERENCES city (city_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_orders_pallets CHECK (pallet_count BETWEEN 1 AND 30),
    CONSTRAINT chk_orders_distance CHECK (distance_km > 0)
) ENGINE = InnoDB;

CREATE TABLE ride (
    ride_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    truck_id          INT UNSIGNED NOT NULL,
    driver_id         INT UNSIGNED NOT NULL,
    planner_id        INT UNSIGNED NOT NULL,
    ride_date         DATE         NOT NULL,
    departure_time    TIME         NULL,
    total_distance_km DECIMAL(7,2) NULL,
    status            ENUM('gepland','onderweg','afgerond','geannuleerd') NOT NULL DEFAULT 'gepland'
                      COMMENT 'gepland = rit staat klaar, nog niet vertrokken; onderweg = chauffeur is vertrokken; afgerond = alle stops zijn afgeleverd; geannuleerd = rit gaat niet door',
    PRIMARY KEY (ride_id),
    KEY idx_ride_truck (truck_id),
    KEY idx_ride_driver (driver_id),
    KEY idx_ride_planner (planner_id),
    KEY idx_ride_date (ride_date),
    CONSTRAINT fk_ride_truck
        FOREIGN KEY (truck_id) REFERENCES truck (truck_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ride_driver
        FOREIGN KEY (driver_id) REFERENCES driver (employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ride_planner
        FOREIGN KEY (planner_id) REFERENCES employee (employee_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE ride_order (
    ride_id       INT UNSIGNED     NOT NULL,
    order_id      INT UNSIGNED     NOT NULL,
    stop_sequence TINYINT UNSIGNED NOT NULL,
    delivered_at  DATETIME         NULL,
    PRIMARY KEY (ride_id, order_id),
    UNIQUE KEY uq_rideorder_sequence (ride_id, stop_sequence),
    UNIQUE KEY uq_rideorder_order (order_id),
    CONSTRAINT fk_rideorder_ride
        FOREIGN KEY (ride_id) REFERENCES ride (ride_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rideorder_order
        FOREIGN KEY (order_id) REFERENCES orders (order_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_rideorder_sequence CHECK (stop_sequence > 0)
) ENGINE = InnoDB;

CREATE TABLE invoice (
    invoice_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED  NOT NULL,
    invoice_number  VARCHAR(20)   NOT NULL,
    invoice_date    DATE          NOT NULL,
    due_date        DATE          NOT NULL,
    total_amount    DECIMAL(10,2) NOT NULL,
    iban            VARCHAR(34)   NOT NULL,
    collection_date DATE          NULL,
    status          ENUM('open','geincasseerd','mislukt','gestorneerd') NOT NULL DEFAULT 'open'
                    COMMENT 'open = factuur verstuurd, incasso nog niet uitgevoerd; geincasseerd = bedrag is afgeschreven; mislukt = incasso geweigerd door de bank; gestorneerd = klant heeft het bedrag teruggeboekt',
    PRIMARY KEY (invoice_id),
    UNIQUE KEY uq_invoice_number (invoice_number),
    UNIQUE KEY uq_invoice_order (order_id),
    KEY idx_invoice_status (status),
    CONSTRAINT fk_invoice_order
        FOREIGN KEY (order_id) REFERENCES orders (order_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_invoice_amount CHECK (total_amount >= 0),
    CONSTRAINT chk_invoice_dates CHECK (due_date >= invoice_date)
) ENGINE = InnoDB;

INSERT INTO role (name) VALUES
    ('klant'), ('chauffeur'), ('planner'), ('manager');

INSERT INTO land (name, country_code, surcharge) VALUES
    ('Nederland', 'NL',  0.00),
    ('Duitsland', 'DE', 25.00),
    ('Belgie',    'BE', 15.00),
    ('Luxemburg', 'LU', 20.00);

INSERT INTO trucktype (model_name, pallet_capacity, has_trailer, required_license) VALUES
    ('CF 300 FA',  10, FALSE, 'C'),
    ('CF 410 FTN', 20, FALSE, 'C'),
    ('XF 430 FTM', 30, TRUE,  'D');

INSERT INTO tariff (min_pallets, max_pallets, price_per_km_per_pallet, valid_from) VALUES
    (1, 1,    0.50, '2024-01-01'),
    (2, 3,    0.45, '2024-01-01'),
    (4, 5,    0.40, '2024-01-01'),
    (6, NULL, 0.35, '2024-01-01');
