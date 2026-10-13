USE dorb_logistics;

DELETE FROM invoice;
DELETE FROM ride_order;
DELETE FROM ride;
DELETE FROM orders;
DELETE FROM driver_land;
DELETE FROM driver;
DELETE FROM employee;
DELETE FROM customer;
DELETE FROM users;
DELETE FROM truck;
DELETE FROM city;

ALTER TABLE invoice  AUTO_INCREMENT = 1;
ALTER TABLE ride     AUTO_INCREMENT = 1;
ALTER TABLE orders   AUTO_INCREMENT = 1;
ALTER TABLE employee AUTO_INCREMENT = 1;
ALTER TABLE users    AUTO_INCREMENT = 1;
ALTER TABLE truck    AUTO_INCREMENT = 1;
ALTER TABLE city     AUTO_INCREMENT = 1;

SET @hash = '$2y$10$f8GMdC2f19t8EIpBxYOIsuEbPBZy9Hf9/TPTm/6RzRh9EBkjS5CmW';

SET @role_klant     = (SELECT role_id FROM role WHERE name = 'klant');
SET @role_chauffeur = (SELECT role_id FROM role WHERE name = 'chauffeur');
SET @role_planner   = (SELECT role_id FROM role WHERE name = 'planner');
SET @role_manager   = (SELECT role_id FROM role WHERE name = 'manager');

INSERT INTO users (role_id, email, password_hash) VALUES
    (@role_manager,   'manager@dorb.test',     @hash),
    (@role_planner,   'planner@dorb.test',     @hash),
    (@role_planner,   'k.dewit@dorb.test',     @hash),
    (@role_chauffeur, 'chauffeur@dorb.test',   @hash),
    (@role_chauffeur, 's.vermeulen@dorb.test', @hash),
    (@role_chauffeur, 'p.nowak@dorb.test',     @hash),
    (@role_chauffeur, 'j.hendriks@dorb.test',  @hash),
    (@role_chauffeur, 'f.elamrani@dorb.test',  @hash),
    (@role_klant,     'bedrijf@dorb.test',     @hash),
    (@role_klant,     'particulier@dorb.test', @hash),
    (@role_klant,     'nieuw@dorb.test',       @hash),
    (@role_klant,     'geblokkeerd@dorb.test', @hash);

SET @u_manager     = (SELECT user_id FROM users WHERE email = 'manager@dorb.test');
SET @u_planner     = (SELECT user_id FROM users WHERE email = 'planner@dorb.test');
SET @u_kevin       = (SELECT user_id FROM users WHERE email = 'k.dewit@dorb.test');
SET @u_ahmet       = (SELECT user_id FROM users WHERE email = 'chauffeur@dorb.test');
SET @u_sanne       = (SELECT user_id FROM users WHERE email = 's.vermeulen@dorb.test');
SET @u_piotr       = (SELECT user_id FROM users WHERE email = 'p.nowak@dorb.test');
SET @u_jeroen      = (SELECT user_id FROM users WHERE email = 'j.hendriks@dorb.test');
SET @u_fatima      = (SELECT user_id FROM users WHERE email = 'f.elamrani@dorb.test');
SET @u_bedrijf     = (SELECT user_id FROM users WHERE email = 'bedrijf@dorb.test');
SET @u_particulier = (SELECT user_id FROM users WHERE email = 'particulier@dorb.test');
SET @u_nieuw       = (SELECT user_id FROM users WHERE email = 'nieuw@dorb.test');
SET @u_geblokkeerd = (SELECT user_id FROM users WHERE email = 'geblokkeerd@dorb.test');

SET @nl = (SELECT land_id FROM land WHERE country_code = 'NL');
SET @de = (SELECT land_id FROM land WHERE country_code = 'DE');
SET @be = (SELECT land_id FROM land WHERE country_code = 'BE');
SET @lu = (SELECT land_id FROM land WHERE country_code = 'LU');

INSERT INTO city (name, land_id) VALUES
    ('Arnhem', @nl), ('Nijmegen', @nl), ('Oosterbeek', @nl), ('Assen', @nl),
    ('Groningen', @nl), ('Utrecht', @nl), ('Antwerpen', @be), ('Dortmund', @de),
    ('Luxemburg', @lu);

SET @c_arnhem     = (SELECT city_id FROM city WHERE name = 'Arnhem'     AND land_id = @nl);
SET @c_nijmegen   = (SELECT city_id FROM city WHERE name = 'Nijmegen'   AND land_id = @nl);
SET @c_oosterbeek = (SELECT city_id FROM city WHERE name = 'Oosterbeek' AND land_id = @nl);
SET @c_assen      = (SELECT city_id FROM city WHERE name = 'Assen'      AND land_id = @nl);
SET @c_groningen  = (SELECT city_id FROM city WHERE name = 'Groningen'  AND land_id = @nl);
SET @c_utrecht    = (SELECT city_id FROM city WHERE name = 'Utrecht'    AND land_id = @nl);
SET @c_antwerpen  = (SELECT city_id FROM city WHERE name = 'Antwerpen'  AND land_id = @be);
SET @c_dortmund   = (SELECT city_id FROM city WHERE name = 'Dortmund'   AND land_id = @de);
SET @c_luxemburg  = (SELECT city_id FROM city WHERE name = 'Luxemburg'  AND land_id = @lu);

INSERT INTO customer
    (user_id, type, status, companyname, first_name, last_name, phonenumber, iban,
     street, housenumber, postalcode, city_id, signed_up)
VALUES
    (@u_bedrijf, 'bedrijf', 'actief', 'Van Dalen Groothandel BV', 'Marieke', 'van Dalen', '024-3456789',
     'NL91ABNA0417164300', 'Handelsweg', '12', '6541 CA', @c_nijmegen, '2025-01-15'),
    (@u_particulier, 'particulier', 'actief', NULL, 'Tom', 'Jansen', '06-51234567',
     'NL02RABO0123456789', 'Dorpsstraat', '4', '6862 AB', @c_oosterbeek, '2025-03-02'),
    (@u_nieuw, 'bedrijf', 'aangemeld', 'Beekman Bouwmaterialen', 'Sander', 'Beekman', '026-4455661',
     'NL18INGB0002445588', 'Nijverheidsweg', '88', '6827 AT', @c_arnhem, '2026-08-28'),
    (@u_geblokkeerd, 'particulier', 'geblokkeerd', NULL, 'Ilse', 'de Groot', '06-19998877',
     'NL30SNSB0009988776', 'Kerkstraat', '21', '6811 DN', @c_arnhem, '2025-06-11');

INSERT INTO employee (user_id, first_name, last_name, phonenumber, hire_date) VALUES
    (@u_manager, 'Dirk',   'Overmars',  '026-1234501', '2015-03-01'),
    (@u_planner, 'Rianne', 'Bakker',    '026-1234502', '2018-09-17'),
    (@u_kevin,   'Kevin',  'de Wit',    '026-1234503', '2021-01-11'),
    (@u_ahmet,   'Ahmet',  'Yilmaz',    '06-11223301', '2019-05-06'),
    (@u_sanne,   'Sanne',  'Vermeulen', '06-11223302', '2020-02-24'),
    (@u_piotr,   'Piotr',  'Nowak',     '06-11223303', '2017-11-13'),
    (@u_jeroen,  'Jeroen', 'Hendriks',  '06-11223304', '2022-06-20'),
    (@u_fatima,  'Fatima', 'El Amrani', '06-11223305', '2023-04-03');

SET @e_planner = (SELECT employee_id FROM employee WHERE user_id = @u_planner);
SET @e_ahmet   = (SELECT employee_id FROM employee WHERE user_id = @u_ahmet);
SET @e_sanne   = (SELECT employee_id FROM employee WHERE user_id = @u_sanne);
SET @e_piotr   = (SELECT employee_id FROM employee WHERE user_id = @u_piotr);
SET @e_jeroen  = (SELECT employee_id FROM employee WHERE user_id = @u_jeroen);
SET @e_fatima  = (SELECT employee_id FROM employee WHERE user_id = @u_fatima);

INSERT INTO driver (employee_id, license_type, license_number, license_expiry) VALUES
    (@e_ahmet,  'D', 'NL-RIJB-40001', '2029-05-31'),
    (@e_sanne,  'C', 'NL-RIJB-40002', '2028-02-28'),
    (@e_piotr,  'D', 'NL-RIJB-40003', '2027-12-31'),
    (@e_jeroen, 'C', 'NL-RIJB-40004', '2030-06-30'),
    (@e_fatima, 'C', 'NL-RIJB-40005', '2029-09-30');

INSERT INTO driver_land (employee_id, land_id) VALUES
    (@e_ahmet, @nl), (@e_ahmet, @de), (@e_ahmet, @be), (@e_ahmet, @lu),
    (@e_sanne, @nl),
    (@e_piotr, @nl), (@e_piotr, @de),
    (@e_jeroen, @nl), (@e_jeroen, @be),
    (@e_fatima, @nl);

SET @tt_cf300 = (SELECT trucktype_id FROM trucktype WHERE model_name = 'CF 300 FA');
SET @tt_cf410 = (SELECT trucktype_id FROM trucktype WHERE model_name = 'CF 410 FTN');
SET @tt_xf430 = (SELECT trucktype_id FROM trucktype WHERE model_name = 'XF 430 FTM');

INSERT INTO truck (trucktype_id, license_plate) VALUES
    (@tt_cf300, 'BX-01-DR'), (@tt_cf300, 'BX-02-DR'), (@tt_cf300, 'BX-03-DR'), (@tt_cf300, 'BX-04-DR'),
    (@tt_cf300, 'BX-05-DR'), (@tt_cf300, 'BX-06-DR'), (@tt_cf300, 'BX-07-DR'), (@tt_cf300, 'BX-08-DR'),
    (@tt_cf300, 'BX-09-DR'), (@tt_cf300, 'BX-10-DR'), (@tt_cf300, 'BX-11-DR'),
    (@tt_cf410, 'CF-01-DR'), (@tt_cf410, 'CF-02-DR'), (@tt_cf410, 'CF-03-DR'),
    (@tt_cf410, 'CF-04-DR'), (@tt_cf410, 'CF-05-DR'), (@tt_cf410, 'CF-06-DR'),
    (@tt_xf430, 'XF-01-DR'), (@tt_xf430, 'XF-02-DR'), (@tt_xf430, 'XF-03-DR');

SET @tariff_1 = (SELECT tariff_id FROM tariff WHERE min_pallets = 1);
SET @tariff_2 = (SELECT tariff_id FROM tariff WHERE min_pallets = 2);
SET @tariff_4 = (SELECT tariff_id FROM tariff WHERE min_pallets = 4);
SET @tariff_6 = (SELECT tariff_id FROM tariff WHERE min_pallets = 6);

INSERT INTO orders
    (customer_id, tariff_id, planner_id, pallet_count,
     dest_street, dest_housenumber, dest_postalcode, dest_city_id,
     distance_km, unit_price, surcharge, order_date, delivery_date, status)
VALUES
    (@u_bedrijf, @tariff_2, NULL, 3,
     'Industrieweg', '5', '9403 AB', @c_assen,
     150.00, 0.45, 0.00, '2026-08-24 09:12:00', '2026-08-26', 'geleverd'),
    (@u_particulier, @tariff_2, @e_planner, 2,
     'Peizerweg', '40', '9727 AL', @c_groningen,
     175.00, 0.45, 0.00, '2026-08-24 10:41:00', '2026-08-26', 'geleverd'),
    (@u_bedrijf, @tariff_4, NULL, 4,
     'Atoomweg', '12', '3542 AA', @c_utrecht,
     120.00, 0.40, 0.00, '2026-08-18 14:03:00', '2026-08-20', 'gefactureerd'),
    (@u_particulier, @tariff_1, NULL, 1,
     'Grote Markt', '3', '2000', @c_antwerpen,
     95.00, 0.50, 15.00, '2026-08-12 11:27:00', '2026-08-14', 'gefactureerd'),
    (@u_bedrijf, @tariff_6, @e_planner, 8,
     'Ruhrallee', '15', '44139', @c_dortmund,
     210.00, 0.35, 25.00, '2026-09-02 08:55:00', NULL, 'onderweg'),
    (@u_bedrijf, @tariff_6, @e_planner, 6,
     'Route de Thionville', '22', '2610', @c_luxemburg,
     305.00, 0.35, 20.00, '2026-09-05 16:20:00', NULL, 'offerte');

SET @o_assen     = (SELECT order_id FROM orders WHERE dest_city_id = @c_assen);
SET @o_groningen = (SELECT order_id FROM orders WHERE dest_city_id = @c_groningen);
SET @o_utrecht   = (SELECT order_id FROM orders WHERE dest_city_id = @c_utrecht);
SET @o_antwerpen = (SELECT order_id FROM orders WHERE dest_city_id = @c_antwerpen);
SET @o_dortmund  = (SELECT order_id FROM orders WHERE dest_city_id = @c_dortmund);

SET @t_cf410  = (SELECT truck_id FROM truck WHERE license_plate = 'CF-01-DR');
SET @t_cf300  = (SELECT truck_id FROM truck WHERE license_plate = 'BX-01-DR');
SET @t_cf300b = (SELECT truck_id FROM truck WHERE license_plate = 'BX-02-DR');
SET @t_xf430  = (SELECT truck_id FROM truck WHERE license_plate = 'XF-01-DR');

INSERT INTO ride (truck_id, driver_id, planner_id, ride_date, departure_time, total_distance_km, status) VALUES
    (@t_cf410,  @e_piotr,  @e_planner, '2026-08-26', '06:30:00', 180.00, 'afgerond'),
    (@t_cf300,  @e_sanne,  @e_planner, '2026-08-20', '07:15:00', 120.00, 'afgerond'),
    (@t_cf300b, @e_jeroen, @e_planner, '2026-08-14', '07:00:00',  95.00, 'afgerond'),
    (@t_xf430,  @e_ahmet,  @e_planner, '2026-09-07', '05:45:00', NULL,   'onderweg');

SET @r_noord     = (SELECT ride_id FROM ride WHERE ride_date = '2026-08-26');
SET @r_utrecht   = (SELECT ride_id FROM ride WHERE ride_date = '2026-08-20');
SET @r_belgie    = (SELECT ride_id FROM ride WHERE ride_date = '2026-08-14');
SET @r_duitsland = (SELECT ride_id FROM ride WHERE ride_date = '2026-09-07');

INSERT INTO ride_order (ride_id, order_id, stop_sequence, delivered_at) VALUES
    (@r_noord,     @o_assen,     1, '2026-08-26 10:05:00'),
    (@r_noord,     @o_groningen, 2, '2026-08-26 11:20:00'),
    (@r_utrecht,   @o_utrecht,   1, '2026-08-20 09:40:00'),
    (@r_belgie,    @o_antwerpen, 1, '2026-08-14 09:55:00'),
    (@r_duitsland, @o_dortmund,  1, NULL);

INSERT INTO invoice (order_id, invoice_number, invoice_date, due_date, total_amount, iban, collection_date, status) VALUES
    (@o_utrecht,   'DORB-2026-0001', '2026-08-21', '2026-09-04', 192.00, 'NL91ABNA0417164300', NULL,         'open'),
    (@o_antwerpen, 'DORB-2026-0002', '2026-08-15', '2026-08-29',  62.50, 'NL02RABO0123456789', '2026-08-29', 'geincasseerd');
