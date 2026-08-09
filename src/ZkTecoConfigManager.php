<?php

declare(strict_types=1);

namespace ZkTeco\Push;

class ZkTecoConfigManager
{
    private string $configFilePath;
    private array $defaultConfig = [
        'table_prefix' => 'zkteco_',
        'external_api_url' => 'https://api.example.com/v1',
        'attendance_webhook_path' => '/webhooks/attendance',
        'heartbeat_webhook_path' => '/webhooks/device-status',
        'command_result_webhook_path' => '/webhooks/command-result',
        'webhook_secret_token' => 'sk_live_zkteco_secret_9988',
        'webhook_enabled' => true,
        'middleware_api_key' => 'zk_api_key_default_12345',
        'device_timeout_seconds' => 120,
    ];

    public function __construct(?string $configFilePath = null)
    {
        $this->configFilePath = $configFilePath ?? __DIR__ . '/../config.json';
    }

    public function getConfig(): array
    {
        if (function_exists('config') && config('zkteco-push')) {
            return [
                'table_prefix' => config('zkteco-push.table_prefix', 'zkteco_'),
                'external_api_url' => config('zkteco-push.external_api_url', 'https://api.example.com/v1'),
                'attendance_webhook_path' => config('zkteco-push.webhook.attendance_path', '/webhooks/attendance'),
                'heartbeat_webhook_path' => config('zkteco-push.webhook.heartbeat_path', '/webhooks/device-status'),
                'command_result_webhook_path' => config('zkteco-push.webhook.command_result_path', '/webhooks/command-result'),
                'webhook_secret_token' => config('zkteco-push.webhook.secret_token', 'sk_live_zkteco_secret_9988'),
                'webhook_enabled' => config('zkteco-push.webhook.enabled', true),
                'middleware_api_key' => config('zkteco-push.api_key', 'zk_api_key_default_12345'),
                'device_timeout_seconds' => 120,
            ];
        }

        if (file_exists($this->configFilePath)) {
            $saved = json_decode(file_get_contents($this->configFilePath), true) ?? [];
            return array_merge($this->defaultConfig, $saved);
        }

        return $this->defaultConfig;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->getConfig();
        return $config[$key] ?? $default;
    }

    public function updateConfig(array $newSettings): array
    {
        $current = $this->getConfig();

        if (isset($newSettings['external_api_url'])) {
            $newSettings['external_api_url'] = rtrim(trim($newSettings['external_api_url']), '/');
        }

        if (isset($newSettings['attendance_webhook_path'])) {
            $newSettings['attendance_webhook_path'] = '/' . ltrim(trim($newSettings['attendance_webhook_path']), '/');
        }

        if (isset($newSettings['heartbeat_webhook_path'])) {
            $newSettings['heartbeat_webhook_path'] = '/' . ltrim(trim($newSettings['heartbeat_webhook_path']), '/');
        }

        if (isset($newSettings['webhook_enabled'])) {
            $newSettings['webhook_enabled'] = (bool)$newSettings['webhook_enabled'];
        }

        $updated = array_merge($current, $newSettings);
        file_put_contents($this->configFilePath, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $updated;
    }

    public function getWebhookUrl(string $eventType): ?string
    {
        $config = $this->getConfig();
        if (empty($config['webhook_enabled']) || empty($config['external_api_url'])) {
            return null;
        }

        $pathKey = match ($eventType) {
            'attendance' => 'attendance_webhook_path',
            'heartbeat' => 'heartbeat_webhook_path',
            'command_result' => 'command_result_webhook_path',
            default => null
        };

        if (!$pathKey || empty($config[$pathKey])) {
            return null;
        }

        return $config['external_api_url'] . $config[$pathKey];
    }
}
