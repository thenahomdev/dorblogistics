<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const MAIL_HOST      = '127.0.0.1';
const MAIL_PORT      = 1025;
const MAIL_FROM      = 'noreply@dorblogistics.test';
const MAIL_FROM_NAME = 'DORB Logistics';
const MAIL_SITE_FALLBACK = 'http://localhost/DorbLogistics';

function mailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host        = MAIL_HOST;
    $mail->Port        = MAIL_PORT;
    $mail->SMTPAuth    = false;
    $mail->SMTPAutoTLS = false;
    $mail->CharSet     = 'UTF-8';
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

    return $mail;
}

function site_url(): string
{
    if (empty($_SERVER['HTTP_HOST']) || empty($_SERVER['SCRIPT_NAME'])) {
        return MAIL_SITE_FALLBACK;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/');

    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $base;
}

function mail_amount(float $value): string
{
    return '€ ' . number_format($value, 2, ',', '.');
}

function mail_row(string $label, string $value, bool $strong = false): string
{
    $weight = $strong ? '700' : '400';
    $border = $strong ? '2px solid #14181f' : '1px solid #e4e7ec';

    return '<tr>'
        . '<td style="padding:10px 0;border-top:' . $border . ';color:#5b6472;font-size:14px;">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td style="padding:10px 0;border-top:' . $border . ';text-align:right;'
        . 'font-size:14px;font-weight:' . $weight . ';color:#14181f;">'
        . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr>';
}

function confirmation_mail_body(string $name, array $order): string
{
    $km        = number_format((float) $order['distance_km'], 2, ',', '.');
    $transport = (float) $order['total_price'] - (float) $order['surcharge'];
    $address   = $order['dest_street'] . ' ' . $order['dest_housenumber'] . ', '
        . $order['dest_postalcode'] . ' ' . $order['dest_city'];

    $rows = mail_row('Bestemming', $address)
        . mail_row('Land', $order['land_name'])
        . mail_row('Aantal pallets', (string) (int) $order['pallet_count'])
        . mail_row('Afstand vanaf Arnhem', $km . ' km')
        . mail_row('Tarief', mail_amount((float) $order['unit_price']) . ' per km per pallet')
        . mail_row('Vervoer', mail_amount($transport))
        . mail_row('Toeslag ' . $order['land_name'], mail_amount((float) $order['surcharge']))
        . mail_row('Totaal', mail_amount((float) $order['total_price']), true);

    return '<div style="margin:0;padding:24px 12px;background:#f6f7f9;'
        . 'font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
        . ' style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e4e7ec;'
        . 'border-radius:14px;overflow:hidden;">'

        . '<tr><td style="background:#14181f;padding:24px 32px;">'
        . '<span style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.5px;">'
        . 'DORB <span style="color:#f0a500;">Logistics</span></span>'
        . '<div style="color:#8b93a1;font-size:12px;letter-spacing:2px;text-transform:uppercase;'
        . 'margin-top:6px;">A new Track of Success</div>'
        . '</td></tr>'

        . '<tr><td style="height:4px;background:#f0a500;font-size:0;line-height:0;">&nbsp;</td></tr>'

        . '<tr><td style="padding:32px;">'
        . '<h1 style="margin:0 0 4px;font-size:22px;color:#14181f;">Order '
        . (int) $order['order_id'] . ' bevestigd</h1>'
        . '<p style="margin:0 0 24px;color:#5b6472;font-size:14px;">Beste '
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        . ', bedankt voor uw order. De bevestiging zit als PDF bij deze mail.</p>'

        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">'
        . $rows
        . '</table>'

        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"'
        . ' style="margin:28px 0 8px;"><tr>'
        . '<td style="background:#f0a500;border-radius:10px;">'
        . '<a href="' . site_url() . '/index.php?page=recent_orders_klant"'
        . ' style="display:inline-block;padding:13px 26px;color:#14181f;font-size:15px;'
        . 'font-weight:700;text-decoration:none;">Mijn zendingen</a>'
        . '</td></tr></table>'

        . '<p style="margin:16px 0 0;color:#5b6472;font-size:13px;line-height:1.6;">'
        . 'Deze prijs is vastgelegd en verandert niet meer. Wij plannen de rit in en laten u '
        . 'weten wanneer wij leveren. Na levering ontvangt u de factuur en wordt het bedrag '
        . 'automatisch van uw rekening afgeschreven.</p>'
        . '</td></tr>'

        . '<tr><td style="background:#f6f7f9;border-top:1px solid #e4e7ec;padding:20px 32px;'
        . 'color:#8b93a1;font-size:12px;line-height:1.6;">'
        . 'DORB Logistics &middot; Zijpendaalseweg 167, 6814 CJ Arnhem<br>'
        . 'Dit bericht is automatisch verstuurd, u kunt er niet op antwoorden.'
        . '</td></tr>'

        . '</table></div>';
}

function send_order_confirmation_mail(string $email, string $name, array $order, string $pdf): bool
{
    try {
        $mail = mailer();
        $mail->addAddress($email, $name);
        $mail->Subject = 'Bevestiging van uw order ' . (int) $order['order_id'] . ' bij DORB Logistics';
        $mail->isHTML(true);
        $mail->msgHTML(confirmation_mail_body($name, $order));
        $mail->addStringAttachment(
            $pdf,
            'orderbevestiging-' . (int) $order['order_id'] . '.pdf',
            PHPMailer::ENCODING_BASE64,
            'application/pdf'
        );
        $mail->send();

        return true;
    } catch (Exception) {
        return false;
    }
}


//NK 28/09/2026

function nk_mail_layout(string $title, string $content): string
{
    return '<div style="margin:0;padding:24px 12px;background:#f6f7f9;'
        . 'font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
        . ' style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e4e7ec;'
        . 'border-radius:14px;overflow:hidden;">'

        . '<tr><td style="background:#14181f;padding:24px 32px;">'
        . '<span style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.5px;">'
        . 'DORB <span style="color:#f0a500;">Logistics</span></span>'
        . '<div style="color:#8b93a1;font-size:12px;letter-spacing:2px;text-transform:uppercase;'
        . 'margin-top:6px;">A new Track of Success</div>'
        . '</td></tr>'

        . '<tr><td style="height:4px;background:#f0a500;font-size:0;line-height:0;">&nbsp;</td></tr>'

        . '<tr><td style="padding:32px;">'
        . '<h1 style="margin:0 0 16px;font-size:22px;color:#14181f;">'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . $content
        . '</td></tr>'

        . '<tr><td style="background:#f6f7f9;border-top:1px solid #e4e7ec;padding:20px 32px;'
        . 'color:#8b93a1;font-size:12px;line-height:1.6;">'
        . 'DORB Logistics &middot; Zijpendaalseweg 167, 6814 CJ Arnhem<br>'
        . 'Dit bericht is automatisch verstuurd, u kunt er niet op antwoorden.'
        . '</td></tr>'

        . '</table></div>';
}

function nk_mail_text(string $text): string
{
    return '<p style="margin:0 0 16px;color:#5b6472;font-size:14px;line-height:1.6;">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
}

function nk_mail_small_text(string $text): string
{
    return '<p style="margin:16px 0 0;color:#8b93a1;font-size:13px;line-height:1.6;">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
}

function nk_mail_button(string $label, string $url): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"'
        . ' style="margin:8px 0 16px;"><tr>'
        . '<td style="background:#f0a500;border-radius:10px;">'
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
        . ' style="display:inline-block;padding:13px 26px;color:#14181f;font-size:15px;'
        . 'font-weight:700;text-decoration:none;">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>'
        . '</td></tr></table>';
}

function nk_mail_table(string $rows): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
        . ' style="margin:8px 0 24px;">' . $rows . '</table>';
}

function nk_send_mail(string $email, string $name, string $subject, string $html): bool
{
    try {
        $mail = mailer();
        $mail->addAddress($email, $name);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->msgHTML($html);
        $mail->send();

        return true;
    } catch (Exception) {
        return false;
    }
}

function send_registration_mail(string $email, string $name): bool
{
    $content = nk_mail_text('Beste ' . $name . ', bedankt voor uw aanmelding bij DORB Logistics.')
        . nk_mail_table(
            mail_row('Naam', $name)
            . mail_row('E-mailadres', $email)
            . mail_row('Status', 'Wacht op goedkeuring', true)
        )
        . nk_mail_text('Een medewerker beoordeelt uw aanmelding zo snel mogelijk. '
            . 'U ontvangt een e-mail zodra uw account is goedgekeurd.')
        . nk_mail_small_text('Heeft u zich niet aangemeld? Dan kunt u deze e-mail negeren.');

    $html = nk_mail_layout('Aanmelding ontvangen', $content);

    return nk_send_mail($email, $name, 'Uw aanmelding bij DORB Logistics is ontvangen', $html);
}

function send_approval_mail(string $email, string $name): bool
{
    $content = nk_mail_text('Beste ' . $name . ', goed nieuws: uw account bij DORB Logistics is goedgekeurd.')
        . nk_mail_text('U kunt nu inloggen met uw e-mailadres en wachtwoord en uw eerste order plaatsen.')
        . nk_mail_button('Inloggen', site_url() . '/index.php?page=login');

    $html = nk_mail_layout('Uw account is goedgekeurd', $content);

    return nk_send_mail($email, $name, 'Uw account bij DORB Logistics is goedgekeurd', $html);
}

function send_blocked_mail(string $email, string $name): bool
{
    $content = nk_mail_text('Beste ' . $name . ', uw account bij DORB Logistics is geblokkeerd.')
        . nk_mail_text('U kunt niet meer inloggen en geen nieuwe orders plaatsen. '
            . 'Orders die al zijn ingepland, worden gewoon afgeleverd.')
        . nk_mail_small_text('Denkt u dat dit een vergissing is? Neem dan contact met ons op.');

    $html = nk_mail_layout('Uw account is geblokkeerd', $content);

    return nk_send_mail($email, $name, 'Uw account bij DORB Logistics is geblokkeerd', $html);
}

function send_unblocked_mail(string $email, string $name): bool
{
    $content = nk_mail_text('Beste ' . $name . ', uw account bij DORB Logistics is weer actief.')
        . nk_mail_text('U kunt weer inloggen en orders plaatsen.')
        . nk_mail_button('Inloggen', site_url() . '/index.php?page=login');

    $html = nk_mail_layout('Uw account is weer actief', $content);

    return nk_send_mail($email, $name, 'Uw account bij DORB Logistics is weer actief', $html);
}

function send_deactivated_mail(string $email, string $name): bool
{
    $content = nk_mail_text('Beste ' . $name . ', u heeft uw account bij DORB Logistics opgezegd.')
        . nk_mail_text('U kunt niet meer inloggen. Uw gegevens en facturen bewaren wij zolang de wet dat verplicht.')
        . nk_mail_small_text('Heeft u dit niet zelf gedaan? Neem dan direct contact met ons op.');

    $html = nk_mail_layout('Uw account is opgezegd', $content);

    return nk_send_mail($email, $name, 'Uw account bij DORB Logistics is opgezegd', $html);
}

//NK 28/09/2026