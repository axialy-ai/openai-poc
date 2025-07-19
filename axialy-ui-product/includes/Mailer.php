<?php
/**
 * Lightweight SMTP helper for Axialy.
 *
 * Usage:
 *   $mail = \AxiaBA\Mailer::make();
 *   $mail->addAddress($to);
 *   $mail->Subject = 'Subject';
 *   $mail->Body    = $html;
 *   $mail->send();
 *
 * Reads SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASSWORD / SMTP_SECURE
 * from the environment (already populated via .env).
 */

namespace AxiaBA;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use AxiaBA\Config\Config;

final class Mailer
{
    /** @throws Exception */
    public static function make(): PHPMailer
    {
        $cfg  = Config::getInstance();

        $mail = new PHPMailer(true);            // Exceptions enabled
        $mail->isSMTP();                        // SMTP, not sendmail
        $mail->Host       = getenv('SMTP_HOST') ?: 'localhost';
        $mail->Port       = getenv('SMTP_PORT') ?: 587;
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER');
        $mail->Password   = getenv('SMTP_PASSWORD');

        $secure = strtolower(getenv('SMTP_SECURE') ?: 'tls');
        $mail->SMTPSecure = ($secure === 'ssl')
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);
        $mail->setFrom('support@axiaba.com', 'AxiaBA');

        // a single call sites can tweak further (addReplyTo, addAttachment, …)
        return $mail;
    }

    // static helper only – no instantiation
    private function __construct() {}
}
