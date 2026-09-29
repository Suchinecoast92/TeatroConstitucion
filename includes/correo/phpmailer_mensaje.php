<?php
/**
 * Arma un PHPMailer a partir del mensaje neutral (ver MailerInterface).
 * Lo comparten el transporte SMTP y el simulado, así el .eml simulado es idéntico al real.
 */

use PHPMailer\PHPMailer\PHPMailer;

function correo_cargar_phpmailer(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $autoload = dirname(__DIR__, 2) . '/vnt_interfaz/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    $ok = class_exists(PHPMailer::class);
    return $ok;
}

function correo_remitente(): array
{
    $from = trim((string) teatro_env('MAIL_FROM', ''));
    if ($from === '' && function_exists('correo_modo') && correo_modo() === 'mock') {
        $from = 'boletos@example.com';
    }
    $nombre = trim((string) teatro_env('MAIL_FROM_NAME', 'Teatro Constitución'));
    return [$from, $nombre !== '' ? $nombre : 'Teatro Constitución'];
}

/**
 * @param array<string,mixed> $m
 */
function correo_phpmailer_armar(array $m, PHPMailer $mail): void
{
    [$from, $fromName] = correo_remitente();
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('MAIL_FROM no configurado o inválido');
    }
    $to = (string) ($m['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Destinatario inválido');
    }

    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = PHPMailer::ENCODING_BASE64;
    $mail->setFrom($from, $fromName, false);
    $mail->addAddress($to, (string) ($m['to_name'] ?? ''));

    $replyTo = trim((string) ($m['reply_to'] ?? teatro_env('MAIL_REPLY_TO', '')));
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($replyTo);
    }

    $mail->Subject = str_replace(["\r", "\n"], ' ', (string) ($m['subject'] ?? ''));
    $mail->isHTML(true);
    $mail->Body = (string) ($m['html'] ?? '');
    $mail->AltBody = (string) ($m['text'] ?? '');

    foreach ((array) ($m['inline'] ?? []) as $img) {
        $path = (string) ($img['path'] ?? '');
        if ($path !== '' && is_file($path)) {
            $mail->addEmbeddedImage($path, (string) $img['cid'], (string) ($img['name'] ?? basename($path)), PHPMailer::ENCODING_BASE64, 'image/png');
        }
    }
}
