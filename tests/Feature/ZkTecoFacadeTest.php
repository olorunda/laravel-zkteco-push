<?php

namespace ZkTeco\Push\Tests\Feature;

use ZkTeco\Push\Tests\TestCase;
use ZkTeco\Push\Facades\ZkTecoPush;

class ZkTecoFacadeTest extends TestCase
{
    public function test_facade_resolves_and_queues_commands(): void
    {
        $cmdId = ZkTecoPush::deleteUser('ZK-FACADE-01', ['pin' => '901'], delaySeconds: 30);

        $this->assertNotEmpty($cmdId);

        $device = ZkTecoPush::device('ZK-FACADE-01');
        $this->assertEquals('ZK-FACADE-01', $device->getSerialNumber());
    }

    public function test_facade_retrieves_all_devices(): void
    {
        ZkTecoPush::queueCommand('ZK-FACADE-02', 'REBOOT');

        $devices = ZkTecoPush::getAllDevices();
        $this->assertIsArray($devices);
    }
}
