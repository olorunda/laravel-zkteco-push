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

    public function getSerialNumber(): string
    {
        return $this->serialNumber;
    }

    public function queueCommand(array|string $command, int $delaySeconds = 0): string
    {
        $cmdStr = is_array($command)
            ? $this->commandBuilder->buildFromArray($command)
            : (string)$command;

        return $this->storage->queueCommand($this->serialNumber, $cmdStr, $delaySeconds);
    }

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

    public function reboot(int $delaySeconds = 0): string
    {
        return $this->storage->queueCommand(
            $this->serialNumber,
            $this->commandBuilder->buildReboot(),
            $delaySeconds
        );
    }

    public function syncTime(?string $dateTime = null, int $delaySeconds = 0): string
    {
        return $this->storage->queueCommand(
            $this->serialNumber,
            $this->commandBuilder->buildSyncTime($dateTime),
            $delaySeconds
        );
    }

    public function clearLogs(int $delaySeconds = 0): string
    {
        return $this->storage->queueCommand(
            $this->serialNumber,
            $this->commandBuilder->buildClearLogs(),
            $delaySeconds
        );
    }

    public function unlockDoor(int $unlockSeconds = 5, int $delaySeconds = 0): string
    {
        return $this->storage->queueCommand(
            $this->serialNumber,
            $this->commandBuilder->buildUnlockDoor($unlockSeconds),
            $delaySeconds
        );
    }

    public function getDetails(): ?array
    {
        return $this->storage->getDevice($this->serialNumber);
    }
}
