<?php

declare(strict_types=1);

namespace ZkTeco\Push\Storage;

class ZkTecoArrayStorage implements ZkTecoStorageInterface
{
    private array $devices = [];
    private array $attendanceLogs = [];
    private array $commands = [];
    private array $users = [];
    private ?string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath;
        $this->loadFromFile();
    }

    private function loadFromFile(): void
    {
        if ($this->filePath && file_exists($this->filePath)) {
            $data = json_decode(file_get_contents($this->filePath), true);
            if (is_array($data)) {
                $this->devices = $data['devices'] ?? [];
                $this->attendanceLogs = $data['attendanceLogs'] ?? [];
                $this->commands = $data['commands'] ?? [];
                $this->users = $data['users'] ?? [];
            }
        }
    }

    private function saveToFile(): void
    {
        if ($this->filePath) {
            $dir = dirname($this->filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($this->filePath, json_encode([
                'devices' => $this->devices,
                'attendanceLogs' => $this->attendanceLogs,
                'commands' => $this->commands,
                'users' => $this->users,
            ], JSON_PRETTY_PRINT));
        }
    }

    public function updateDevice(string $serialNumber, array $info = []): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->devices[$serialNumber] ?? [];

        $this->devices[$serialNumber] = array_merge($existing, [
            'serial_number' => $serialNumber,
            'ip_address' => $info['ip'] ?? $existing['ip_address'] ?? null,
            'push_version' => $info['pushversion'] ?? $existing['push_version'] ?? null,
            'firmware' => $info['firmware'] ?? $existing['firmware'] ?? null,
            'last_seen_at' => $now,
            'metadata' => $info
        ]);

        $this->saveToFile();
    }

    public function getDevice(string $serialNumber): ?array
    {
        if (!isset($this->devices[$serialNumber])) {
            return null;
        }

        $device = $this->devices[$serialNumber];
        $device['is_online'] = (strtotime($device['last_seen_at']) >= (time() - 120));
        return $device;
    }

    public function getAllDevices(): array
    {
        $list = array_values($this->devices);
        foreach ($list as &$device) {
            $device['is_online'] = (strtotime($device['last_seen_at']) >= (time() - 120));
        }

        usort($list, fn($a, $b) => strcmp($b['last_seen_at'], $a['last_seen_at']));
        return $list;
    }

    public function saveAttendanceLogs(string $serialNumber, array $records): int
    {
        $inserted = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($records as $rec) {
            $key = "{$serialNumber}_{$rec['pin']}_{$rec['timestamp']}_{$rec['status_code']}";

            if (!isset($this->attendanceLogs[$key])) {
                $this->attendanceLogs[$key] = array_merge($rec, [
                    'device_sn' => $serialNumber,
                    'created_at' => $now
                ]);
                $inserted++;
            }
        }

        $this->saveToFile();
        return $inserted;
    }

    public function getAttendanceLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $logs = array_values($this->attendanceLogs);

        if (!empty($filters['device_sn'])) {
            $logs = array_filter($logs, fn($l) => $l['device_sn'] === $filters['device_sn']);
        }

        if (!empty($filters['pin'])) {
            $logs = array_filter($logs, fn($l) => (string)$l['pin'] === (string)$filters['pin']);
        }

        if (!empty($filters['start_date'])) {
            $logs = array_filter($logs, fn($l) => $l['timestamp'] >= $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $logs = array_filter($logs, fn($l) => $l['timestamp'] <= $filters['end_date']);
        }

        usort($logs, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return array_slice($logs, $offset, $limit);
    }

    public function queueCommand(string $serialNumber, string $commandString, int $delaySeconds = 0): string
    {
        $commandId = (string)(time() . rand(100, 999));
        $now = date('Y-m-d H:i:s');
        $executeAfter = ($delaySeconds > 0) ? date('Y-m-d H:i:s', time() + $delaySeconds) : $now;

        $this->commands[$commandId] = [
            'command_id' => $commandId,
            'device_sn' => $serialNumber,
            'command_text' => $commandString,
            'status' => 'PENDING',
            'return_code' => null,
            'extra_info' => null,
            'queued_at' => $now,
            'execute_after' => $executeAfter,
            'executed_at' => null
        ];

        $this->saveToFile();
        return $commandId;
    }

    public function getPendingCommands(string $serialNumber): array
    {
        $pending = [];
        $now = date('Y-m-d H:i:s');

        foreach ($this->commands as $cmdId => &$cmd) {
            if ($cmd['device_sn'] === $serialNumber && $cmd['status'] === 'PENDING') {
                if (empty($cmd['execute_after']) || $cmd['execute_after'] <= $now) {
                    $cmd['status'] = 'DISPATCHED';
                    $pending[] = [
                        'id' => $cmd['command_id'],
                        'command' => $cmd['command_text']
                    ];
                }
            }
        }

        if (!empty($pending)) {
            $this->saveToFile();
        }

        return $pending;
    }

    public function updateCommandStatus(string $serialNumber, string $commandId, int $returnCode, ?string $extraInfo = null): void
    {
        if (isset($this->commands[$commandId])) {
            $this->commands[$commandId]['status'] = ($returnCode === 0) ? 'COMPLETED' : 'FAILED';
            $this->commands[$commandId]['return_code'] = $returnCode;
            $this->commands[$commandId]['extra_info'] = $extraInfo;
            $this->commands[$commandId]['executed_at'] = date('Y-m-d H:i:s');

            $this->saveToFile();
        }
    }

    public function getCommandStatus(string $commandId): ?array
    {
        return $this->commands[$commandId] ?? null;
    }

    public function saveUser(array $userData): void
    {
        $pin = (string)$userData['pin'];
        $sn = $userData['device_sn'] ?? 'ALL';
        $key = "{$pin}_{$sn}";

        $this->users[$key] = array_merge($userData, [
            'pin' => $pin,
            'device_sn' => $sn,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $this->saveToFile();
    }

    public function getUsers(?string $deviceSn = null): array
    {
        $list = array_values($this->users);
        if ($deviceSn) {
            $list = array_filter($list, fn($u) => $u['device_sn'] === $deviceSn || $u['device_sn'] === 'ALL');
        }
        return array_values($list);
    }

    public function deleteUser(string $pin, ?string $deviceSn = null): void
    {
        foreach ($this->users as $key => $user) {
            if ((string)$user['pin'] === (string)$pin) {
                if (!$deviceSn || $user['device_sn'] === $deviceSn || $user['device_sn'] === 'ALL') {
                    unset($this->users[$key]);
                }
            }
        }
        $this->saveToFile();
    }
}
