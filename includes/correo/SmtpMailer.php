<?php
/**
 * Transporte SMTP (PHPMailer). Sirve con cualquier proveedor: Brevo, Mailgun,
 * SendGrid, Amazon SES, Google Workspace…
 * DigitalOcean bloquea la salida por los puertos 25/465/587 en cuentas nuevas:
 * usar el puerto alterno del proveedor (normalmente 2525) o pedir el desbloqueo.
 */

use PHPMailer\PHPMailer\PHPMailer;

class SmtpMailer implements MailerInterface
{
    public function nombre(): string
    {
        return 'smtp';
    }

    public function enviar(array $mensaje): array
    {
        if (!correo_cargar_phpmailer()) {
            return ['ok' => false, 'error' => 'PHPMailer no instalado (composer install)'];
        }
        $host = trim((string) teatro_env('MAIL_HOST', ''));
        if ($host === '') {
            return ['ok' => false, 'error' => 'MAIL_HOST no configurado'];
        }
        $port = (int) teatro_env('MAIL_PORT', '587');
        $enc = strtolower(trim((string) teatro_env('MAIL_ENCRYPTION', 'tls')));
        $user = (string) teatro_env('MAIL_USER', '');
        $pass = (string) teatro_env('MAIL_PASS', '');

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port > 0 ? $port : 587;
            $mail->Timeout = 15;
            $mail->SMTPDebug = 0;
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'none') {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            if ($user !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = $pass;
            }
            correo_phpmailer_armar($mensaje, $mail);
            $mail->send();
            return ['ok' => true, 'message_id' => $mail->getLastMessageID()];
        } catch (Throwable $e) {
            // ErrorInfo de PHPMailer no incluye la contraseña; se recorta por si acaso.
            $err = isset($mail) && $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
            if ($pass !== '') {
                $err = str_replace($pass, '***', $err);
            }
            return ['ok' => false, 'error' => mb_substr($err, 0, 480)];
        }
    }
}
