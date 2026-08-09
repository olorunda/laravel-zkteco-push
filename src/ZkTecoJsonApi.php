<?php

declare(strict_types=1);

namespace ZkTeco\Push;

use ZkTeco\Push\Storage\ZkTecoStorageInterface;
use Throwable;

class ZkTecoJsonApi
{
    private ZkTecoStorageInterface $storage;
    private ZkTecoCommandBuilder $commandBuilder;
    private ?ZkTecoConfigManager $configManager;

    public function __construct(
        ZkTecoStorageInterface $storage,
        ZkTecoCommandBuilder $commandBuilder,
        ?ZkTecoConfigManager $configManager = null
    ) {
        $this->storage = $storage;
        $this->commandBuilder = $commandBuilder;
        $this->configManager = $configManager;
    }

    /**
     * Handle incoming REST API request from External Server.
     */
    public function handleApiRequest(
        string $path,
        string $method,
        array $queryParams,
        string $body
    ): array {
        try {
            // Optional API Key Verification
            if ($this->configManager) {
                $configuredKey = $this->configManager->get('middleware_api_key');
                if (!empty($configuredKey)) {
                    $providedKey = $_SERVER['HTTP_X_API_KEY']
                        ?? $_SERVER['HTTP_AUTHORIZATION']
                        ?? $queryParams['api_key']
                        ?? (function_exists('request') ? (request()->header('X-API-Key') ?? request()->header('Authorization')) : null);

                    if ($providedKey) {
                        $providedKey = str_replace('Bearer ', '', $providedKey);
                    }

                    if ($providedKey !== $configuredKey) {
                        return $this->jsonResponse(false, ['error' => 'Unauthorized: Invalid or missing API Key'], 401);
                    }
                }
            }

            $input = json_decode($body, true) ?? [];

            // 1. GET /api/devices or /api/zkteco/devices - List devices
            if (preg_match('#^/api/(?:zkteco/)?devices/?$#', $path) && $method === 'GET') {
                $devices = $this->storage->getAllDevices();
                return $this->jsonResponse(true, ['devices' => $devices]);
            }

            // 2. GET /api/devices/{sn} or /api/zkteco/devices/{sn} - Single device
            if (preg_match('#^/api/(?:zkteco/)?devices/([^/]+)$#', $path, $matches) && $method === 'GET') {
                $device = $this->storage->getDevice($matches[1]);
                if (!$device) {
                    return $this->jsonResponse(false, ['error' => 'Device not found'], 404);
                }
                return $this->jsonResponse(true, ['device' => $device]);
            }

            // 3. GET /api/attendance or /api/zkteco/attendance - Query attendance logs
            if (preg_match('#^/api/(?:zkteco/)?attendance/?$#', $path) && $method === 'GET') {
                $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100;
                $offset = isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0;

                $logs = $this->storage->getAttendanceLogs($queryParams, $limit, $offset);
                return $this->jsonResponse(true, [
                    'count' => count($logs),
                    'logs' => $logs
                ]);
            }

            // 4. POST /api/users or /api/zkteco/users - Create / update user on device
            if (preg_match('#^/api/(?:zkteco/)?users/?$#', $path) && $method === 'POST') {
                $deviceSn = $input['device_sn'] ?? null;
                $pin = $input['pin'] ?? null;
                $name = $input['name'] ?? null;

                if (!$deviceSn || !$pin || !$name) {
                    return $this->jsonResponse(false, ['error' => 'Missing required fields: device_sn, pin, name'], 400);
                }

                $cmdStr = $this->commandBuilder->buildAddUser(
                    (string)$pin,
                    (string)$name,
                    $input['card_number'] ?? null,
                    $input['password'] ?? null,
                    (int)($input['privilege'] ?? 0),
                    (int)($input['group_id'] ?? 1)
                );

                $commandId = $this->storage->queueCommand($deviceSn, $cmdStr);
                $this->storage->saveUser($input);

                return $this->jsonResponse(true, [
                    'message' => 'User creation command queued successfully',
                    'command_id' => $commandId,
                    'device_sn' => $deviceSn,
                    'pin' => $pin
                ]);
            }

            // 5. DELETE /api/users/{pin} or DELETE /api/zkteco/users/{pin}
            if (preg_match('#^/api/(?:zkteco/)?users(?:/([^/]+))?$#', $path, $matches) && ($method === 'DELETE' || $method === 'POST')) {
                $pin = $matches[1] ?? $input['pin'] ?? null;
                $deviceSn = $input['device_sn'] ?? $queryParams['device_sn'] ?? null;

                if (!$pin || !$deviceSn) {
                    return $this->jsonResponse(false, ['error' => 'Missing required fields: pin, device_sn'], 400);
                }

                $delaySeconds = $this->parseDelaySeconds($input, $queryParams);
                $cmdStr = $this->commandBuilder->buildDeleteUser((string)$pin);
                $commandId = $this->storage->queueCommand($deviceSn, $cmdStr, $delaySeconds);
                $this->storage->deleteUser((string)$pin, $deviceSn);

                $scheduledAt = date('Y-m-d H:i:s', time() + $delaySeconds);
                $msg = ($delaySeconds > 0)
                    ? "User deletion command scheduled in {$delaySeconds} seconds (at {$scheduledAt})"
                    : "User deletion command queued for immediate dispatch";

                return $this->jsonResponse(true, [
                    'message' => $msg,
                    'command_id' => $commandId,
                    'pin' => $pin,
                    'delay_seconds' => $delaySeconds,
                    'scheduled_at' => $scheduledAt
                ]);
            }

            // 6. POST /api/commands/reboot
            if (preg_match('#^/api/(?:zkteco/)?commands/reboot$#', $path) && $method === 'POST') {
                $deviceSn = $input['device_sn'] ?? null;
                if (!$deviceSn) {
                    return $this->jsonResponse(false, ['error' => 'Missing device_sn'], 400);
                }

                $cmdId = $this->storage->queueCommand($deviceSn, $this->commandBuilder->buildReboot());
                return $this->jsonResponse(true, ['message' => 'Reboot command queued', 'command_id' => $cmdId]);
            }

            // 7. POST /api/commands/clear-logs
            if (preg_match('#^/api/(?:zkteco/)?commands/clear-logs$#', $path) && $method === 'POST') {
                $deviceSn = $input['device_sn'] ?? null;
                if (!$deviceSn) {
                    return $this->jsonResponse(false, ['error' => 'Missing device_sn'], 400);
                }

                $cmdId = $this->storage->queueCommand($deviceSn, $this->commandBuilder->buildClearLogs());
                return $this->jsonResponse(true, ['message' => 'Clear logs command queued', 'command_id' => $cmdId]);
            }

            // 8. POST /api/commands/sync-time
            if (preg_match('#^/api/(?:zkteco/)?commands/sync-time$#', $path) && $method === 'POST') {
                $deviceSn = $input['device_sn'] ?? null;
                if (!$deviceSn) {
                    return $this->jsonResponse(false, ['error' => 'Missing device_sn'], 400);
                }

                $cmdId = $this->storage->queueCommand($deviceSn, $this->commandBuilder->buildSyncTime());
                return $this->jsonResponse(true, ['message' => 'Sync time command queued', 'command_id' => $cmdId]);
            }

            // 9. GET /api/commands/status/{cmd_id}
            if (preg_match('#^/api/(?:zkteco/)?commands/status/([^/]+)$#', $path, $matches) && $method === 'GET') {
                $cmdStatus = $this->storage->getCommandStatus($matches[1]);
                if (!$cmdStatus) {
                    return $this->jsonResponse(false, ['error' => 'Command ID not found'], 404);
                }

                return $this->jsonResponse(true, ['command' => $cmdStatus]);
            }

            // 10. POST /api/commands/custom
            if (preg_match('#^/api/(?:zkteco/)?commands/custom$#', $path) && $method === 'POST') {
                $deviceSn = $input['device_sn'] ?? null;
                $commandText = $input['command'] ?? null;

                if (!$deviceSn || !$commandText) {
                    return $this->jsonResponse(false, ['error' => 'Missing device_sn or command'], 400);
                }

                $cmdId = $this->storage->queueCommand($deviceSn, $commandText);
                return $this->jsonResponse(true, ['message' => 'Custom command queued', 'command_id' => $cmdId]);
            }

            return $this->jsonResponse(false, ['error' => "API Endpoint not found: {$method} {$path}"], 404);

        } catch (Throwable $e) {
            return $this->jsonResponse(false, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse delay time parameters (delay_minutes, delay_seconds, or string like '2m', '90s').
     */
    private function parseDelaySeconds(array $input, array $queryParams): int
    {
        if (isset($input['delay_minutes'])) {
            return (int)$input['delay_minutes'] * 60;
        }

        if (isset($queryParams['delay_minutes'])) {
            return (int)$queryParams['delay_minutes'] * 60;
        }

        if (isset($input['delay_seconds'])) {
            return (int)$input['delay_seconds'];
        }

        if (isset($queryParams['delay_seconds'])) {
            return (int)$queryParams['delay_seconds'];
        }

        if (isset($input['delay_time'])) {
            $val = trim((string)$input['delay_time']);
            if (preg_match('#^(\d+)\s*(m|min|minute|minutes)$#i', $val, $m)) {
                return (int)$m[1] * 60;
            }
            if (preg_match('#^(\d+)\s*(s|sec|second|seconds)$#i', $val, $m)) {
                return (int)$m[1];
            }
            if (preg_match('#^(\d+)\s*(h|hr|hour|hours)$#i', $val, $m)) {
                return (int)$m[1] * 3600;
            }
        }

        return 0;
    }

    private function jsonResponse(bool $success, array $data = [], int $status = 200): array
    {
        $payload = array_merge(['success' => $success], $data);
        return [
            'status' => $status,
            'headers' => [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-API-Key'
            ],
            'body' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ];
    }
}
