<?php

declare(strict_types=1);

namespace ZkTeco\Push;

class ZkTecoCommandBuilder
{
    public function buildAddUser(
        string $pin,
        string $name,
        ?string $cardNumber = null,
        ?string $password = null,
        int $privilege = 0,
        int $group = 1
    ): string {
        $parts = [
            "DATA USERINFO PIN={$pin}",
            "Name={$name}",
            "Pri={$privilege}",
            "Grp={$group}",
        ];

        if ($password !== null && $password !== '') {
            $parts[] = "Passwd={$password}";
        }

        if ($cardNumber !== null && $cardNumber !== '') {
            $parts[] = "Card={$cardNumber}";
        }

        return implode("\t", $parts);
    }

    public function buildDeleteUser(string $pin): string
    {
        return "DATA DELETE USERINFO PIN={$pin}";
    }

    public function buildReboot(): string
    {
        return "REBOOT";
    }

    public function buildClearLogs(): string
    {
        return "CLEAR LOG";
    }

    public function buildSyncTime(?string $dateTimeStr = null): string
    {
        $timeStr = $dateTimeStr ?? date('Y-m-d H:i:s');
        return "SET TIME {$timeStr}";
    }

    public function buildGetInfo(): string
    {
        return "INFO";
    }

    public function buildUnlockDoor(int $delaySeconds = 5): string
    {
        return "AC_UNLOCK {$delaySeconds}";
    }

    public function buildCheck(): string
    {
        return "CHECK";
    }

    public function buildFromArray(array $commandData): string
    {
        $action = strtolower((string)($commandData['action'] ?? $commandData['cmd'] ?? ''));

        if ($action === 'delete_user' || isset($commandData['delete_user'])) {
            $pin = (string)($commandData['pin'] ?? $commandData['delete_user'] ?? '');
            return $this->buildDeleteUser($pin);
        }

        if ($action === 'add_user' || isset($commandData['add_user'])) {
            $pin = (string)($commandData['pin'] ?? '');
            $name = (string)($commandData['name'] ?? '');
            $card = $commandData['card_number'] ?? $commandData['card'] ?? null;
            $password = $commandData['password'] ?? $commandData['passwd'] ?? null;
            $privilege = (int)($commandData['privilege'] ?? $commandData['pri'] ?? 0);
            $group = (int)($commandData['group_id'] ?? $commandData['grp'] ?? 1);

            return $this->buildAddUser($pin, $name, $card, $password, $privilege, $group);
        }

        if ($action === 'reboot') {
            return $this->buildReboot();
        }

        if ($action === 'clear_logs') {
            return $this->buildClearLogs();
        }

        if ($action === 'sync_time') {
            return $this->buildSyncTime($commandData['time'] ?? null);
        }

        if ($action === 'unlock_door') {
            return $this->buildUnlockDoor((int)($commandData['seconds'] ?? 5));
        }

        if ($action === 'info') {
            return $this->buildGetInfo();
        }

        if ($action === 'check') {
            return $this->buildCheck();
        }

        if (isset($commandData['command']) || isset($commandData['raw'])) {
            return (string)($commandData['command'] ?? $commandData['raw']);
        }

        return "CHECK";
    }
}
