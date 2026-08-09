<?php

declare(strict_types=1);

namespace ZkTeco\Push;

use ZkTeco\Push\Storage\ZkTecoStorageInterface;

class ZkTecoDevice
{
    private string $serialNumber;
    private ZkTecoStorageInterface $storage;
    private ZkTecoCommandBuilder $commandBuilder;

    public function __construct(
        string $serialNumber,
        ZkTecoStorageInterface $storage,
        ZkTecoCommandBuilder $commandBuilder
    ) {
        $this->serialNumber = $serialNumber;
        $this->storage = $storage;
        $this->commandBuilder = $commandBuilder;
    }

    /**
     * Get target device serial number.
     */
    public function getSerialNumber(): string
    {
        return $this->serialNumber;
    }

    /**
     * Queue a command using an associative array or raw string, with optional time delay in seconds.
     *
     * Example array usage:
     * ZkTecoPush::device('ZK-SN-001')->queueCommand(['action' => 'delete_user', 'pin' => '1003'], delaySeconds: 120);
     * ZkTecoPush::device('ZK-SN-001')->queueCommand(['action' => 'add_user', 'pin' => '1003', 'name' => 'Alice']);
     * ZkTecoPush::device('ZK-SN-001')->queueCommand(['action' => 'reboot'], delaySeconds: 10);
     *
     * @param array|string $command Array payload definition or raw ADMS command string
     * @param int $delaySeconds Time delay in seconds before command is dispatched to machine
     * @return string Unique Command ID
     */
    public function queueCommand(array|string $command, int $delaySeconds = 0): string
    {
        $cmdStr = is_array($command)
            ? $this->commandBuilder->buildFromArray($command)
            : (string)$command;

        return $this->storage->queueCommand($this->serialNumber, $cmdStr, $delaySeconds);
    }

    /**
     * Delete a user profile from the hardware machine with optional time delay.
     *
     * Example:
     * ZkTecoPush::device('ZK-SN-001')->deleteUser('1003', delaySeconds: 120);
     * ZkTecoPush::device('ZK-SN-001')->deleteUser(['pin' => '1003'], delaySeconds: 120);
     *
     * @param string|array $userPinOrArray User PIN string or array containing ['pin' => '1003']
     * @param int $delaySeconds Delay in seconds before deletion takes effect on device
     * @return string Unique Command ID
     */
    public function deleteUser(string|array $userPinOrArray, int $delaySeconds = 0): string
    {
        $pin = is_array($userPinOrArray)
            ? (string)($userPinOrArray['pin'] ?? $userPinOrArray['id'] ?? '')
            : (string)$userPinOrArray;

        $cmdStr = $this->commandBuilder->buildDeleteUser($pin);
        $cmdId = $this->storage->queueCommand($this->serialNumber, $cmdStr, $delaySeconds);
        $this->storage->deleteUser($pin, $this->serialNumber);

        return $cmdId;
    }

    /**
     * Add or update a user profile on the hardware machine with optional delay.
     *
     * Example:
     * ZkTecoPush::device('ZK-SN-001')->addUser([
     *     'pin' => '1003',
     *     'name' => 'Alice Smith',
     *     'card_number' => '987654321',
     *     'password' => '4321',
     *     'privilege' => 0
     * ], delaySeconds: 0);
     */
    public function addUser(array $userData, int $delaySeconds = 0): string
    {
        $pin = (string)($userData['pin'] ?? '');
        $name = (string)($userData['name'] ?? '');
        $card = $userData['card_number'] ?? $userData['card'] ?? null;
        $pass = $userData['password'] ?? $userData['passwd'] ?? null;
        $priv = (int)($userData['privilege'] ?? 0);
        $group = (int)($userData['group_id'] ?? 1);

        $cmdStr = $this->commandBuilder->buildAddUser($pin, $name, $card, $pass, $priv, $group);
        $cmdId = $this->storage->queueCommand($this->serialNumber, $cmdStr, $delaySeconds);

        $userData['device_sn'] = $this->serialNumber;
        $this->storage->saveUser($userData);

        return $cmdId;
    }

    /**
     * Reboot the hardware machine.
     */
    public function reboot(int $delaySeconds = 0): string
    {
        return $this->storage->queueCommand(
            $this->serialNumber,
            $this->commandBuilder->buildReboot(),
            $delaySeconds
        );
    }

    /**
     * Sync system clock of the hardware machine with current server time.
     */
    public function syncTime(?string $dateTime = null, int $delaySeconds = 0): string
    {
        return $this->storage->queueCommand(
            $this->serialNumber,
            $this->commandBuilder->buildSyncTime($dateTime),
            $delaySeconds
        );
    }

    /**
     * Clear all attendance log records stored on the machine.
     */
    public function clearLogs(int $delaySeconds = 0): string
    {
        return $this->storage->queueCommand(
            $this->serialNumber,
            $this->commandBuilder->buildClearLogs(),
            $delaySeconds
        );
    }

    /**
     * Trigger door unlock relay for access control machines.
     */
    public function unlockDoor(int $unlockSeconds = 5, int $delaySeconds = 0): string
    {
        return $this->storage->queueCommand(
            $this->serialNumber,
            $this->commandBuilder->buildUnlockDoor($unlockSeconds),
            $delaySeconds
        );
    }

    /**
     * Get device metadata & status information.
     */
    public function getDetails(): ?array
    {
        return $this->storage->getDevice($this->serialNumber);
    }
}
