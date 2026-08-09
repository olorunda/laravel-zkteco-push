<?php

namespace ZkTeco\Push\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ZkTeco\Push\ZkTecoDevice device(string $serialNumber)
 * @method static string deleteUser(string $serialNumber, string|array $userPinOrArray, int $delaySeconds = 0)
 * @method static string addUser(string $serialNumber, array $userData, int $delaySeconds = 0)
 * @method static string queueCommand(string $serialNumber, array|string $command, int $delaySeconds = 0)
 * @method static array getAttendanceLogs(array $filters = [], int $limit = 100, int $offset = 0)
 * @method static array getAllDevices()
 * @method static array|null getDevice(string $serialNumber)
 * 
 * @see \ZkTeco\Push\ZkTecoPushMiddleware
 */
class ZkTecoPush extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'zkteco-push';
    }
}
