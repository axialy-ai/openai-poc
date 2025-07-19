<?php
/**
 * Lightweight SMTP helper for Axialy UI.
 *
 * Usage:
 *   $mail = \AxiaBA\Mailer::make();
 *   $mail->addAddress($to);
 *   $mail->Subject = 'Subject';
 *   $mail->Body    = $html;
 *   $mail->send();
 *
 * Reads SMTP configuration directly from environment variables.
 */

namespace AxiaBA;

// Ensure Composer autoloader is loaded
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class Mailer
{
    /** @throws Exception */
    public static function make(): PHPMailer
    {
        // Load environment variables from .env file
        self::loadEnvFile();
        
        $mail = new PHPMailer(true);            // Exceptions enabled
        $mail->isSMTP();                        // SMTP, not sendmail
        
        // Get SMTP settings from environment variables
        $mail->Host       = getenv('SMTP_HOST') ?: 'localhost';
        $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER') ?: '';
        $mail->Password   = getenv('SMTP_PASSWORD') ?: '';

        // Set encryption based on SMTP_SECURE setting
        $secure = strtolower(getenv('SMTP_SECURE') ?: 'tls');
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);
        
        // Set default from address
        $fromEmail = getenv('SMTP_USER') ?: 'support@axiaba.com';
        $fromName = 'AxiaBA';
        $mail->setFrom($fromEmail, $fromName);

        return $mail;
    }

    /**
     * Load environment variables from .env file
     */
    private static function loadEnvFile(): void
    {
        $envFile = __DIR__ . '/../.env';
        
        if (!file_exists($envFile)) {
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return;
        }
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            
            // Skip lines without = 
            if (strpos($line, '=') === false) {
                continue;
            }
            
            // Parse key=value
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            
            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$value");
            }
        }
    }

    // Static helper only – no instantiation
    private function __construct() {}
}
