<?php

namespace ZkTeco\Push\Tests\Unit;

use ZkTeco\Push\Tests\TestCase;
use ZkTeco\Push\ZkTecoPushMiddleware;
use ZkTeco\Push\Storage\ZkTecoArrayStorage;
use ZkTeco\Push\ZkTecoConfigManager;

class ZkTecoPushMiddlewareTest extends TestCase
{
    private ZkTecoPushMiddleware $middleware;
    private ZkTecoArrayStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new ZkTecoArrayStorage();
        $this->middleware = new ZkTecoPushMiddleware($this->storage, new ZkTecoConfigManager());
    }

    public function test_handles_device_handshake_cdata_request(): void
    {
        $res = $this->middleware->handleRequest(
            '/iclock/cdata?SN=ZK-TEST-001&options=all&pushver=3.1.1',
            'GET',
            ['SN' => 'ZK-TEST-001', 'options' => 'all', 'pushver' => '3.1.1'],
            ''
        );

        $this->assertEquals(200, $res['status']);
        $this->assertStringContainsString('GET OPTION FROM:', $res['body']);

        $device = $this->middleware->getDevice('ZK-TEST-001');
        $this->assertNotNull($device);
        $this->assertEquals('ZK-TEST-001', $device['serial_number']);
    }

    public function test_handles_attendance_push_cdata(): void
    {
        $rawLogs = "101\t2026-08-09 08:30:15\t0\t1\t0\t0\t0";

        $res = $this->middleware->handleRequest(
            '/iclock/cdata?SN=ZK-TEST-001&table=ATTLOG',
            'POST',
            ['SN' => 'ZK-TEST-001', 'table' => 'ATTLOG'],
            $rawLogs
        );

        $this->assertEquals(200, $res['status']);
        $this->assertStringContainsString('OK:', $res['body']);

        $logs = $this->middleware->getAttendanceLogs();
        $this->assertCount(1, $logs);
        $this->assertEquals('101', $logs[0]['pin']);
    }

    public function test_queues_commands_via_fluent_device_interface(): void
    {
        $cmdId = $this->middleware->device('ZK-TEST-001')->deleteUser('105', 60);

        $this->assertNotEmpty($cmdId);

        $res = $this->middleware->handleRequest(
            '/iclock/getrequest?SN=ZK-TEST-001',
            'GET',
            ['SN' => 'ZK-TEST-001'],
            ''
        );

        $this->assertEquals(200, $res['status']);
    }
}
