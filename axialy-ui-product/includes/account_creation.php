<?php
// /includes/account_creation.php

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Mailer.php';

use AxiaBA\Config\Config;
use AxiaBA\Mailer;

class AccountCreation
{
    private \PDO   $pdo;
    private Config $config;

    public function __construct(\PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->config = Config::getInstance();
    }

    /* ──────────────────────────────────── €
     *  Helpers
     * ──────────────────────────────────── */

    public function checkEmailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ui_users WHERE user_email = ?'
        );
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    public function createVerificationToken(string $email): string
    {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->pdo->prepare(
            'INSERT INTO email_verifications (email, token, expires_at)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$email, $token, $expires]);

        return $token;
    }

    /* ──────────────────────────────────── €
     *  Outbound e‑mail (PHPMailer via SMTP)
     * ──────────────────────────────────── */

    public function sendVerificationEmail(string $email, string $token): bool
    {
        $verificationLink =
            rtrim($this->config->get('app_base_url') ?: '', '/')
            . '/verify_email.php?token=' . urlencode($token);

        $subject = 'Verify your email for AxiaBA';
        $message = <<<HTML
        <html><head><title>Email Verification</title></head><body>
        <h2>Welcome to AxiaBA</h2>
        <p>Please click the link below to verify your email address:</p>
        <p><a href="{$verificationLink}">Verify Email Address</a></p>
        <p>If the link is not clickable, copy &amp; paste this URL:</p>
        <p>{$verificationLink}</p>
        <p>This link expires in 24&nbsp;hours.</p>
        </body></html>
        HTML;

        try {
            $mail = Mailer::make();
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            return $mail->send();
        } catch (\Throwable $e) {
            error_log('Verification‑e‑mail error: '.$e->getMessage());
            return false;
        }
    }

    private function sendWelcomeEmail(string $email): void
    {
        $loginUrl = rtrim($this->config->get('app_base_url') ?: '', '/')
                  . '/login.php';

        $subject = 'Your AxiaBA account is ready!';
        $message = <<<HTML
        <html><head><title>Welcome to AxiaBA</title></head><body>
        <h2>Welcome aboard!</h2>
        <p>Your account has been created successfully. You can now log in at:</p>
        <p><a href="{$loginUrl}">AxiaBA&nbsp;Login</a></p>
        <p>Thank you for choosing AxiaBA!</p>
        </body></html>
        HTML;

        try {
            $mail = Mailer::make();
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->send();        // ignore result – best effort
        } catch (\Throwable $e) {
            error_log('Welcome‑e‑mail error: '.$e->getMessage());
        }
    }

    /* ──────────────────────────────────── €
     *  Token verification & account setup
     * ──────────────────────────────────── */

    public function verifyToken(string $token): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT email FROM email_verifications
             WHERE token = ? AND expires_at > NOW() AND used = 0'
        );
        $stmt->execute([$token]);
        return $stmt->fetchColumn() ?: null;
    }

    public function createAccount(string $email, string $username, string $password): bool
    {
        try {
            $this->pdo->beginTransaction();

            /* 1) create org */
            $stmt = $this->pdo->prepare(
                'INSERT INTO default_organizations (default_organization_name)
                 VALUES (?)'
            );
            $stmt->execute([$email]);
            $orgId = (int)$this->pdo->lastInsertId();

            /* 2) create user */
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->pdo->prepare(
                'INSERT INTO ui_users
                 (username, password, user_email, default_organization_id)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$username, $hashed, $email, $orgId]);

            /* 3) mark token used */
            $stmt = $this->pdo->prepare(
                'UPDATE email_verifications SET used = 1
                 WHERE email = ? AND used = 0'
            );
            $stmt->execute([$email]);

            $this->pdo->commit();

            $this->sendWelcomeEmail($email);
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('Account creation error: '.$e->getMessage());
            return false;
        }
    }
}
