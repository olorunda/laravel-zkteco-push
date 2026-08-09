<?php

declare(strict_types=1);

namespace ZkTeco\Push\Storage;

interface ZkTecoStorageInterface
{
    public function updateDevice(string $serialNumber, array $info = []): void;
    public function getDevice(string $serialNumber): ?array;
    public function getAllDevices(): array;
    public function saveAttendanceLogs(string $serialNumber, array $records): int;
    public function getAttendanceLogs(array $filters = [], int $limit = 100, int $offset = 0): array;
    public function queueCommand(string $serialNumber, string $commandString, int $delaySeconds = 0): string;
    public function getPendingCommands(string $serialNumber): array;
    public function updateCommandStatus(string $serialNumber, string $commandId, int $returnCode, ?string $extraInfo = null): void;
    public function getCommandStatus(string $commandId): ?array;
    public function saveUser(array $userData): void;
    public function getUsers(?string $deviceSn = null): array;
    public function deleteUser(string $pin, ?string $deviceSn = null): void;
}
