<?php
/**
 * Transporte simulado: no se conecta a ningún servidor. Guarda el correo completo
 * como .eml (se abre con Outlook / Thunderbird) en MAIL_MOCK_DIR o en el temporal
 * del sistema, fuera de la carpeta pública.
 */

use PHPMailer\PHPMailer\PHPMailer;

class MockMailer implements MailerInterface
{
    public function nombre(): string
    {
        return 'mock';
    }

    public static function directorio(): string
    {
        $dir = trim((string) teatro_env('MAIL_MOCK_DIR', ''));
        if ($dir === '') {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatro_correos';
        }
        return rtrim($dir, '/\\');
    }

    public function enviar(array $mensaje): array
    {
        if (!correo_cargar_phpmailer()) {
            return ['ok' => false, 'error' => 'PHPMailer no instalado (composer install)'];
        }
        try {
            $mail = new PHPMailer(true);
            correo_phpmailer_armar($mensaje, $mail);
            $mail->preSend();
            $mime = $mail->getSentMIMEMessage();

            $dir = self::directorio();
            if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
                return ['ok' => false, 'error' => 'No se pudo crear el directorio de correos simulados'];
            }
            $archivo = $dir . DIRECTORY_SEPARATOR . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.eml';
            if (file_put_contents($archivo, $mime) === false) {
                return ['ok' => false, 'error' => 'No se pudo escribir el correo simulado'];
            }
            return ['ok' => true, 'message_id' => $mail->getLastMessageID(), 'archivo' => $archivo];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
