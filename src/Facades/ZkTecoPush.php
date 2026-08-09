<?php

namespace ZkTeco\Push\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ZkTeco\Push\ZkTecoDevice device(string $serialNumber)
 * @method static string deleteUser(string $serialNumber, string|array $userPinOrArray, int $delaySeconds = 0)
 * @method static string addUser(string $serialNumber, array $userData, int $delaySeconds = 0)
 * @method static string addFingerprint(string $serialNumber, string $pin, int $fingerId, string $templateData, int $flag = 1, int $delaySeconds = 0)
 * @method static string addFace(string $serialNumber, string $pin, string $faceData, int $delaySeconds = 0)
 * @method static string addPalm(string $serialNumber, string $pin, string $palmData, int $delaySeconds = 0)
 * @method static string rebootDevice(string $serialNumber, int $delaySeconds = 0)
 * @method static string clearAttendance(string $serialNumber, int $delaySeconds = 0)
 * @method static string clearData(string $serialNumber, int $delaySeconds = 0)
 * @method static string queueCommand(string $serialNumber, array|string $command, int $delaySeconds = 0)
 * @method static array getAttendanceLogs(array $filters = [], int $limit = 100, int $offset = 0)
 * @method static array getAllDevices()
 * @method static array|null getDevice(string $serialNumber)
 * @method static \ZkTeco\Push\ZkTecoPushMiddleware on(string $eventName, callable $callback)
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
