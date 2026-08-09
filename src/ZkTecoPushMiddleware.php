<?php

declare(strict_types=1);

namespace ZkTeco\Push;

use ZkTeco\Push\Storage\ZkTecoStorageInterface;
use Throwable;

class ZkTecoPushMiddleware
{
    private ZkTecoStorageInterface $storage;
    private ZkTecoDataParser $parser;
    private ZkTecoCommandBuilder $commandBuilder;
    private ZkTecoConfigManager $configManager;
    private ZkTecoWebhookForwarder $webhookForwarder;
    private array $callbacks = [];

    public function __construct(
        ZkTecoStorageInterface $storage,
        ?ZkTecoConfigManager $configManager = null,
        ?ZkTecoDataParser $parser = null,
        ?ZkTecoCommandBuilder $commandBuilder = null,
        ?ZkTecoWebhookForwarder $webhookForwarder = null
    ) {
        $this->storage = $storage;
        $this->configManager = $configManager ?? new ZkTecoConfigManager();
        $this->parser = $parser ?? new ZkTecoDataParser();
        $this->commandBuilder = $commandBuilder ?? new ZkTecoCommandBuilder();
        $this->webhookForwarder = $webhookForwarder ?? new ZkTecoWebhookForwarder($this->configManager);

        $this->setupDefaultWebhookForwarders();
    }

    /**
     * Setup default event forwarders that translate ZKTeco hardware data into JSON webhooks for the External API.
     */
    private function setupDefaultWebhookForwarders(): void
    {
        // 1. Forward translated attendance logs to External API
        $this->on('attendance', function (array $records, string $deviceSn) {
            $this->webhookForwarder->forward('attendance', [
                'device_sn' => $deviceSn,
                'count' => count($records),
                'records' => $records
            ]);
        });

        // 2. Forward device heartbeats to External API
        $this->on('heartbeat', function (string $deviceSn, array $meta) {
            $this->webhookForwarder->forward('heartbeat', [
                'device_sn' => $deviceSn,
                'status' => 'online',
                'ip' => $meta['ip'] ?? null,
                'metadata' => $meta
            ]);
        });

        // 3. Forward command execution results to External API
        $this->on('command_result', function (array $result, string $deviceSn) {
            $this->webhookForwarder->forward('command_result', [
                'device_sn' => $deviceSn,
                'command_id' => $result['command_id'],
                'return_code' => $result['return_code'],
                'status' => ($result['return_code'] === 0) ? 'COMPLETED' : 'FAILED',
                'raw' => $result['raw'] ?? ''
            ]);
        });
    }

    /**
     * Get a fluent device command manager for a target serial number.
     *
     * Example: ZkTecoPush::device('ZK-SN-001')->deleteUser('1003', delaySeconds: 120);
     * Example: ZkTecoPush::device('ZK-SN-001')->addUser(['pin' => '1003', 'name' => 'Alice']);
     */
    public function device(string $serialNumber): ZkTecoDevice
    {
        return new ZkTecoDevice($serialNumber, $this->storage, $this->commandBuilder);
    }

    /**
     * Delete a user profile from hardware using PIN string or array payload.
     *
     * Example: ZkTecoPush::deleteUser('ZK-SN-001', ['pin' => '1003'], delaySeconds: 120);
     * Example: ZkTecoPush::deleteUser('ZK-SN-001', '1003', delaySeconds: 120);
     */
    public function deleteUser(string $serialNumber, string|array $userPinOrArray, int $delaySeconds = 0): string
    {
        return $this->device($serialNumber)->deleteUser($userPinOrArray, $delaySeconds);
    }

    /**
     * Add or update a user on hardware using array payload.
     *
     * Example: ZkTecoPush::addUser('ZK-SN-001', ['pin' => '1003', 'name' => 'Alice Smith']);
     */
    public function addUser(string $serialNumber, array $userData, int $delaySeconds = 0): string
    {
        return $this->device($serialNumber)->addUser($userData, $delaySeconds);
    }

    /**
     * Queue array-defined command or raw string command.
     *
     * Example: ZkTecoPush::queueCommand('ZK-SN-001', ['action' => 'delete_user', 'pin' => '1003'], delaySeconds: 120);
     */
    public function queueCommand(string $serialNumber, array|string $command, int $delaySeconds = 0): string
    {
        return $this->device($serialNumber)->queueCommand($command, $delaySeconds);
    }

    /**
     * Get all connected devices.
     */
    public function getAllDevices(): array
    {
        return $this->storage->getAllDevices();
    }

    /**
     * Get single device details.
     */
    public function getDevice(string $serialNumber): ?array
    {
        return $this->storage->getDevice($serialNumber);
    }

    /**
     * Fetch attendance logs.
     */
    public function getAttendanceLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->storage->getAttendanceLogs($filters, $limit, $offset);
    }

    /**
     * Register event callback listener.
     */
    public function on(string $event, callable $callback): self
    {
        $this->callbacks[$event][] = $callback;
        return $this;
    }

    /**
     * Trigger registered callbacks.
     */
    private function trigger(string $event, ...$args): void
    {
        if (isset($this->callbacks[$event])) {
            foreach ($this->callbacks[$event] as $cb) {
                try {
                    call_user_func_array($cb, $args);
                } catch (Throwable $e) {
                    error_log("ZKTeco Callback Error [{$event}]: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Main HTTP request entry point for standalone or routed PHP scripts.
     */
    public function handleRequest(
        ?string $uri = null,
        ?string $method = null,
        ?array $queryParams = null,
        ?string $body = null
    ): array {
        $uri = $uri ?? $_SERVER['REQUEST_URI'] ?? '/';
        $method = strtoupper($method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        $queryParams = $queryParams ?? $_GET;
        $body = $body ?? file_get_contents('php://input');

        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // 1. ZKTeco Device ADMS Push Protocol Routes
        if (str_contains($path, '/iclock/cdata')) {
            return $this->handleCData($method, $queryParams, $body);
        }

        if (str_contains($path, '/iclock/getrequest')) {
            return $this->handleGetRequest($method, $queryParams);
        }

        if (str_contains($path, '/iclock/devicecmd')) {
            return $this->handleDeviceCmd($method, $queryParams, $body);
        }

        if (str_contains($path, '/iclock/fdata')) {
            return $this->handleFData($method, $queryParams, $body);
        }

        // 2. Admin UI Config Page (/admin, /config, /zkteco/admin, or any path containing /admin)
        $isAdminPath = str_contains($path, '/admin')
            || str_contains($path, '/config')
            || $path === '/admin'
            || $path === '/config'
            || ($path === '/' && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html'));

        if ($isAdminPath) {
            if ($method === 'POST') {
                return $this->handleAdminFormPost($body);
            }
            return $this->handleAdminUi($path, $method, $body);
        }

        // 3. Easy REST JSON API Routes for External API Server
        if (str_contains($path, '/api/zkteco') || str_starts_with($path, '/api/')) {
            $jsonApi = new ZkTecoJsonApi($this->storage, $this->commandBuilder, $this->configManager);
            return $jsonApi->handleApiRequest($path, $method, $queryParams, $body);
        }

        return [
            'status' => 404,
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => '404 Not Found - Invalid ZKTeco Endpoint'
        ];
    }

    /**
     * Handle Admin UI Dashboard & Config page.
     */
    private function handleAdminUi(string $path, string $method, string $body): array
    {
        $adminUi = new ZkTecoAdminUi($this->configManager, $this->storage);
        $html = $adminUi->renderPageWithData();

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html
        ];
    }

    /**
     * Handle Config Form submission.
     */
    private function handleAdminFormPost(?string $body = null): array
    {
        $adminUi = new ZkTecoAdminUi($this->configManager, $this->storage);
        $postData = $_POST;

        if (empty($postData) && function_exists('request')) {
            $postData = request()->all();
        }

        if (empty($postData) && !empty($body)) {
            parse_str($body, $postData);
        }

        if (empty($postData)) {
            $rawInput = file_get_contents('php://input');
            if (!empty($rawInput)) {
                parse_str($rawInput, $postData);
            }
        }

        $action = $postData['action'] ?? 'save_config';
        $message = null;
        $messageType = 'success';

        if ($action === 'save_config') {
            $this->configManager->updateConfig([
                'external_api_url' => $postData['external_api_url'] ?? '',
                'attendance_webhook_path' => $postData['attendance_webhook_path'] ?? '',
                'heartbeat_webhook_path' => $postData['heartbeat_webhook_path'] ?? '',
                'webhook_secret_token' => $postData['webhook_secret_token'] ?? '',
                'webhook_enabled' => isset($postData['webhook_enabled']),
                'middleware_api_key' => $postData['middleware_api_key'] ?? '',
            ]);
            $message = 'Configuration updated successfully! Middleware is ready to bridge device events to External API.';
        } elseif ($action === 'test_webhook') {
            $dummyLog = [
                'device_sn' => 'ZK-TEST-DEVICE-01',
                'count' => 1,
                'records' => [
                    [
                        'pin' => '9999',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'status_code' => 0,
                        'status_label' => 'Check-In (TEST)',
                        'verify_type_code' => 1,
                        'verify_type_label' => 'Fingerprint'
                    ]
                ]
            ];

            $res = $this->webhookForwarder->forward('attendance', $dummyLog);
            if ($res['success']) {
                $message = "Test Webhook successfully sent to {$res['url']} (HTTP {$res['http_code']})";
            } else {
                $messageType = 'error';
                $errDetail = $res['error'] ?? $res['response'] ?? 'Connection refused';
                $message = "Failed to dispatch test webhook: {$errDetail}";
            }
        } elseif ($action === 'send_test_command') {
            $sn = $postData['device_sn'] ?? '';
            $cmdType = $postData['command_type'] ?? '';

            if (!empty($sn)) {
                $cmdStr = match ($cmdType) {
                    'reboot' => $this->commandBuilder->buildReboot(),
                    'sync_time' => $this->commandBuilder->buildSyncTime(),
                    'clear_logs' => $this->commandBuilder->buildClearLogs(),
                    'unlock_door' => $this->commandBuilder->buildUnlockDoor(5),
                    default => 'CHECK'
                };

                $cmdId = $this->storage->queueCommand($sn, $cmdStr);
                $message = "Command [{$cmdType}] queued for device {$sn} (Command ID: {$cmdId})";
            }
        }

        $html = $adminUi->renderPageWithData($message, $messageType);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html
        ];
    }

    /**
     * Handle ZKTeco /iclock/cdata (Handshake & Log Ingestion)
     */
    private function handleCData(string $method, array $query, string $body): array
    {
        $sn = $query['SN'] ?? $query['sn'] ?? null;

        if (!$sn) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => 'ERROR: Missing Device Serial Number (SN)'
            ];
        }

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $this->storage->updateDevice($sn, array_merge($query, ['ip' => $clientIp]));
        $this->trigger('heartbeat', $sn, array_merge($query, ['ip' => $clientIp]));

        if ($method === 'GET') {
            $stamp = time();
            $responseBody = implode("\n", [
                "GET OPTION FROM: {$sn}",
                "Stamp={$stamp}",
                "OpStamp={$stamp}",
                "PhotoStamp={$stamp}",
                "ErrorDelay=60",
                "Delay=10",
                "TransTimes=00:00;14:05",
                "TransInterval=1",
                "TransFlag=1111111111",
                "Realtime=1",
                "Encrypt=0",
                "ServerVer=3.4.1 " . date('Y-m-d'),
                "SupportPING=1"
            ]);

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => $responseBody
            ];
        }

        $table = strtoupper($query['table'] ?? 'ATTLOG');

        if ($table === 'ATTLOG') {
            $parsedRecords = $this->parser->parseAttendanceLogs($body);
            $insertedCount = $this->storage->saveAttendanceLogs($sn, $parsedRecords);

            if (!empty($parsedRecords)) {
                $this->trigger('attendance', $parsedRecords, $sn);
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => "OK: {$insertedCount}"
            ];
        }

        if ($table === 'USERINFO') {
            $users = $this->parser->parseUserInfo($body);
            foreach ($users as $user) {
                $user['device_sn'] = $sn;
                $this->storage->saveUser($user);
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => 'OK'
            ];
        }

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => 'OK'
        ];
    }

    /**
     * Handle ZKTeco /iclock/getrequest (Command Polling)
     */
    private function handleGetRequest(string $method, array $query): array
    {
        $sn = $query['SN'] ?? $query['sn'] ?? null;

        if (!$sn) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => 'ERROR: Missing Device Serial Number (SN)'
            ];
        }

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $this->storage->updateDevice($sn, ['ip' => $clientIp]);

        $pendingCommands = $this->storage->getPendingCommands($sn);

        if (empty($pendingCommands)) {
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => 'OK'
            ];
        }

        $formattedLines = [];
        foreach ($pendingCommands as $cmd) {
            $formattedLines[] = "C:{$cmd['id']}:{$cmd['command']}";
        }

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => implode("\n", $formattedLines)
        ];
    }

    /**
     * Handle ZKTeco /iclock/devicecmd (Command Execution Result)
     */
    private function handleDeviceCmd(string $method, array $query, string $body): array
    {
        $sn = $query['SN'] ?? $query['sn'] ?? null;

        if (!$sn) {
            return [
                'status' => 400,
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => 'ERROR: Missing Device Serial Number (SN)'
            ];
        }

        $cmdResult = $this->parser->parseDeviceCmdReturn($body);

        if (!empty($cmdResult['command_id'])) {
            $this->storage->updateCommandStatus(
                $sn,
                $cmdResult['command_id'],
                $cmdResult['return_code'],
                $body
            );

            $this->trigger('command_result', $cmdResult, $sn);
        }

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => 'OK'
        ];
    }

    /**
     * Handle ZKTeco /iclock/fdata (Binary Uploads)
     */
    private function handleFData(string $method, array $query, string $body): array
    {
        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => 'OK'
        ];
    }

    /**
     * Output HTTP response directly.
     */
    public function dispatchResponse(array $response): void
    {
        http_response_code($response['status']);

        foreach ($response['headers'] as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $response['body'];
    }
}
