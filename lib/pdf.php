<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function pdf_amount(float $value): string
{
    return '€ ' . number_format($value, 2, ',', '.');
}

function pdf_row(string $label, string $value, bool $strong = false): string
{
    $weight = $strong ? '700' : '400';
    $border = $strong ? '2px solid #14181f' : '1px solid #e4e7ec';

    return '<tr>'
        . '<td style="padding:8px 0;border-top:' . $border . ';color:#5b6472;">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td style="padding:8px 0;border-top:' . $border . ';text-align:right;'
        . 'font-weight:' . $weight . ';color:#14181f;">'
        . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr>';
}

function order_confirmation_html(array $order): string
{
    $km        = number_format((float) $order['distance_km'], 2, ',', '.');
    $transport = (float) $order['total_price'] - (float) $order['surcharge'];
    $address   = $order['dest_street'] . ' ' . $order['dest_housenumber'] . ', '
        . $order['dest_postalcode'] . ' ' . $order['dest_city'];
    $name      = $order['companyname'] ?: $order['first_name'] . ' ' . $order['last_name'];

    $rows = pdf_row('Bestemming', $address)
        . pdf_row('Land', $order['land_name'])
        . pdf_row('Aantal pallets', (string) (int) $order['pallet_count'])
        . pdf_row('Afstand vanaf Arnhem', $km . ' km')
        . pdf_row('Tarief', pdf_amount((float) $order['unit_price']) . ' per km per pallet')
        . pdf_row('Vervoer', pdf_amount($transport))
        . pdf_row('Toeslag ' . $order['land_name'], pdf_amount((float) $order['surcharge']))
        . pdf_row('Totaal', pdf_amount((float) $order['total_price']), true);

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
        . 'body{font-family:Helvetica,sans-serif;font-size:11px;color:#14181f;background:#ffffff;margin:0;}'
        . '.header{background:#14181f;color:#ffffff;padding:24px 32px;}'
        . '.brand{font-size:18px;font-weight:700;}'
        . '.accent{color:#f0a500;}'
        . '.tagline{color:#8b93a1;font-size:9px;letter-spacing:2px;text-transform:uppercase;}'
        . '.rule{height:4px;background:#f0a500;font-size:0;line-height:0;}'
        . '.body{padding:32px;}'
        . 'h1{font-size:17px;margin:0 0 4px;}'
        . '.meta{color:#5b6472;margin:0 0 24px;}'
        . 'table{width:100%;border-collapse:collapse;}'
        . '.footer{color:#8b93a1;font-size:9px;padding:0 32px;line-height:1.6;}'
        . '.note{color:#5b6472;line-height:1.6;margin-top:24px;}'
        . '</style></head><body>'

        . '<div class="header">'
        . '<div class="brand">DORB <span class="accent">Logistics</span></div>'
        . '<div class="tagline">A new Track of Success</div>'
        . '</div><div class="rule">&nbsp;</div>'

        . '<div class="body">'
        . '<h1>Orderbevestiging ' . (int) $order['order_id'] . '</h1>'
        . '<p class="meta">Datum ' . date('d-m-Y') . '<br>'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '<br>'
        . htmlspecialchars($order['street'] . ' ' . $order['housenumber'], ENT_QUOTES, 'UTF-8') . '<br>'
        . htmlspecialchars($order['postalcode'] . ' ' . $order['city_name'], ENT_QUOTES, 'UTF-8')
        . '</p>'

        . '<table>' . $rows . '</table>'

        . '<p class="note">Deze prijs is vastgelegd bij het bevestigen van uw order en '
        . 'verandert niet meer. Wij plannen de rit in en laten u weten wanneer wij leveren. '
        . 'Na levering ontvangt u de factuur en wordt het bedrag automatisch van uw '
        . 'rekening afgeschreven.</p>'
        . '</div>'

        . '<div class="footer">DORB Logistics &middot; Zijpendaalseweg 167, 6814 CJ Arnhem<br>'
        . 'Dit document is automatisch aangemaakt.</div>'

        . '</body></html>';
}

function order_confirmation_pdf(array $order): string
{
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml(order_confirmation_html($order), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return (string) $dompdf->output();
}