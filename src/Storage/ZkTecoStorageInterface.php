<?php

declare(strict_types=1);

namespace ZkTeco\Push\Storage;

interface ZkTecoStorageInterface
{
    /**
     * Register or update a ZKTeco device status.
     *
     * @param string $serialNumber Device Serial Number (SN)
     * @param array $info Additional device metadata (ip, push_version, oem, etc.)
     */
    public function updateDevice(string $serialNumber, array $info = []): void;

    /**
     * Get device information by Serial Number.
     */
    public function getDevice(string $serialNumber): ?array;

    /**
     * Get all connected/registered devices.
     */
    public function getAllDevices(): array;

    /**
     * Save raw or parsed attendance log records.
     *
     * @param string $serialNumber
     * @param array $records List of parsed attendance log associative arrays
     * @return int Number of newly inserted records
     */
    public function saveAttendanceLogs(string $serialNumber, array $records): int;

    /**
     * Retrieve attendance logs with optional filter.
     *
     * @param array $filters ['device_sn' => '...', 'pin' => '...', 'start_date' => '...', 'end_date' => '...']
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAttendanceLogs(array $filters = [], int $limit = 100, int $offset = 0): array;

    /**
     * Queue a command to be fetched by the device during its next polling cycle.
     *
     * @param string $serialNumber Target device SN
     * @param string $commandString Formatted ZKTeco command (e.g. 'DATA USERINFO PIN=101...')
     * @param int $delaySeconds Optional time delay in seconds before command becomes available for device polling
     * @return string Unique Command ID generated
     */
    public function queueCommand(string $serialNumber, string $commandString, int $delaySeconds = 0): string;

    /**
     * Fetch pending commands for a device.
     *
     * @param string $serialNumber
     * @return array List of ['id' => string, 'command' => string]
     */
    public function getPendingCommands(string $serialNumber): array;

    /**
     * Mark command status after execution response from device.
     *
     * @param string $serialNumber
     * @param string $commandId
     * @param int $returnCode 0 = Success, negative/positive non-zero = error
     * @param string|null $extraInfo
     */
    public function updateCommandStatus(string $serialNumber, string $commandId, int $returnCode, ?string $extraInfo = null): void;

    /**
     * Check the status of a queued command.
     */
    public function getCommandStatus(string $commandId): ?array;

    /**
     * Save user profile record.
     */
    public function saveUser(array $userData): void;

    /**
     * Get stored users.
     */
    public function getUsers(?string $deviceSn = null): array;

    /**
     * Delete user profile record.
     */
    public function deleteUser(string $pin, ?string $deviceSn = null): void;
}
