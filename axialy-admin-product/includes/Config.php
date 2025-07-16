<?php
// Lightweight, env-variable-driven replacement for the legacy Config
// Keeps the public API (getInstance()->get()) so older code continues to work.

namespace Axialy\AdminConfig;

final class Config
{
    private static ?self $instance = null;
    private array $cache = [];

    private function __construct()
    {
        // Map legacy keys ➜ environment variables
        $map = [
            // Database (UI)
            'db_host'               => 'UI_DB_HOST',
            'db_name'               => 'UI_DB_NAME',
            'db_user'               => 'UI_DB_USER',
            'db_password'           => 'UI_DB_PASSWORD',

            // Misc services – extend as needed
            'api_base_url'          => 'API_BASE_URL',
            'app_base_url'          => 'APP_BASE_URL',
            'internal_api_key'      => 'INTERNAL_API_KEY',
            'openai_api_key'        => 'OPENAI_API_KEY',
            'stripe_api_key'        => 'STRIPE_API_KEY',
            'stripe_publishable_key'=> 'STRIPE_PUBLISHABLE_KEY',
            'stripe_webhook_secret' => 'STRIPE_WEBHOOK_SECRET',
        ];

        foreach ($map as $key => $env) {
            $this->cache[$key] = getenv($env) !== false ? getenv($env) : null;
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function get(string $key): ?string
    {
        return $this->cache[$key] ?? null;
    }

    private function __clone() {}
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialise singleton');
    }
}
