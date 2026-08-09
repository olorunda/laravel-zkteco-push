<?php

declare(strict_types=1);

namespace ZkTeco\Push\Storage;

class ZkTecoArrayStorage implements ZkTecoStorageInterface
{
    private array $devices = [];
    private array $attendanceLogs = [];
    private array $commands = [];
    private array $users = [];
    private ?string $filePath = null;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath;
        if ($this->filePath && file_exists($this->filePath)) {
            $data = json_decode(file_get_contents($this->filePath), true) ?? [];
            $this->devices = $data['devices'] ?? [];
            $this->attendanceLogs = $data['attendanceLogs'] ?? [];
            $this->commands = $data['commands'] ?? [];
            $this->users = $data['users'] ?? [];
        }
    }

    private function persist(): void
    {
        if ($this->filePath) {
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
            'last_seen_at' => $now,
            'metadata' => $info,
            'is_online' => true
        ]);

        $this->persist();
    }

    public function getDevice(string $serialNumber): ?array
    {
        if (!isset($this->devices[$serialNumber])) {
            return null;
        }

        $dev = $this->devices[$serialNumber];
        $dev['is_online'] = (strtotime($dev['last_seen_at']) >= (time() - 120));
        return $dev;
    }

    public function getAllDevices(): array
    {
        $list = array_values($this->devices);
        foreach ($list as &$dev) {
            $dev['is_online'] = (strtotime($dev['last_seen_at']) >= (time() - 120));
        }
        return $list;
    }

    public function saveAttendanceLogs(string $serialNumber, array $records): int
    {
        $now = date('Y-m-d H:i:s');
        $added = 0;

        foreach ($records as $rec) {
            $key = "{$serialNumber}_{$rec['pin']}_{$rec['timestamp']}_{$rec['status_code']}";
            if (!isset($this->attendanceLogs[$key])) {
                $rec['device_sn'] = $serialNumber;
                $rec['created_at'] = $now;
                $this->attendanceLogs[$key] = $rec;
                $added++;
            }
        }

        if ($added > 0) {
            $this->persist();
        }

        return $added;
    }

    public function getAttendanceLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $logs = array_values($this->attendanceLogs);

        // Filter
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

        // Sort descending by timestamp
        usort($logs, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return array_slice($logs, $offset, $limit);
    }

    public function queueCommand(string $serialNumber, string $commandString, int $delaySeconds = 0): string
    {
        $cmdId = (string) (time() . rand(100, 999));
        $now = date('Y-m-d H:i:s');
        $executeAfter = ($delaySeconds > 0) ? date('Y-m-d H:i:s', time() + $delaySeconds) : $now;

        $this->commands[$cmdId] = [
            'command_id' => $cmdId,
            'device_sn' => $serialNumber,
            'command_text' => $commandString,
            'status' => 'PENDING',
            'return_code' => null,
            'queued_at' => $now,
            'execute_after' => $executeAfter,
            'executed_at' => null
        ];

        $this->persist();
        return $cmdId;
    }

    public function getPendingCommands(string $serialNumber): array
    {
        $pending = [];
        $now = date('Y-m-d H:i:s');

        foreach ($this->commands as $cmdId => &$cmd) {
            $executeAfter = $cmd['execute_after'] ?? $cmd['queued_at'];
            if ($cmd['device_sn'] === $serialNumber && $cmd['status'] === 'PENDING' && $executeAfter <= $now) {
                $pending[] = [
                    'id' => $cmd['command_id'],
                    'command' => $cmd['command_text']
                ];
                $cmd['status'] = 'DISPATCHED';
            }
        }

        if (!empty($pending)) {
            $this->persist();
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

            $this->persist();
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
        $key = "{$sn}_{$pin}";

        $this->users[$key] = array_merge($userData, [
            'pin' => $pin,
            'device_sn' => $sn,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $this->persist();
    }

    public function getUsers(?string $deviceSn = null): array
    {
        if (!$deviceSn) {
            return array_values($this->users);
        }

        return array_values(array_filter($this->users, function($u) use ($deviceSn) {
            return $u['device_sn'] === $deviceSn || $u['device_sn'] === 'ALL';
        }));
    }

    public function deleteUser(string $pin, ?string $deviceSn = null): void
    {
        foreach ($this->users as $key => $u) {
            if ((string)$u['pin'] === (string)$pin) {
                if (!$deviceSn || $u['device_sn'] === $deviceSn || $u['device_sn'] === 'ALL') {
                    unset($this->users[$key]);
                }
            }
        }

        $this->persist();
    }
}
