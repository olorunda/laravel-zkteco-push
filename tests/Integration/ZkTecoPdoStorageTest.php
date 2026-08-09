<?php

namespace ZkTeco\Push\Tests\Integration;

use ZkTeco\Push\Tests\TestCase;
use ZkTeco\Push\Storage\ZkTecoPdoStorage;
use Illuminate\Support\Facades\DB;

class ZkTecoPdoStorageTest extends TestCase
{
    private ZkTecoPdoStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = DB::connection()->getPdo();
        $this->storage = new ZkTecoPdoStorage($pdo, 'zkteco_');
    }

    public function test_device_crud_operations(): void
    {
        $this->storage->updateDevice('ZK-PDO-001', [
            'ip' => '192.168.1.50',
            'pushversion' => '3.1.1',
            'firmware' => 'Ver 8.0.4'
        ]);

        $device = $this->storage->getDevice('ZK-PDO-001');
        $this->assertNotNull($device);
        $this->assertEquals('192.168.1.50', $device['ip_address']);
        $this->assertEquals('3.1.1', $device['push_version']);
        $this->assertTrue($device['is_online']);

        $allDevices = $this->storage->getAllDevices();
        $this->assertCount(1, $allDevices);
    }

    public function test_attendance_logs_persistence_and_unique_constraint(): void
    {
        $records = [
            [
                'pin' => '201',
                'timestamp' => '2026-08-09 09:00:00',
                'status_code' => 0,
                'status_label' => 'Check-In',
                'verify_type_code' => 1,
                'verify_type_label' => 'Fingerprint',
                'work_code' => '0',
                'raw_line' => "201\t2026-08-09 09:00:00\t0\t1\t0\t0\t0"
            ]
        ];

        $inserted = $this->storage->saveAttendanceLogs('ZK-PDO-001', $records);
        $this->assertEquals(1, $inserted);

        // Attempt inserting duplicate record
        $insertedDup = $this->storage->saveAttendanceLogs('ZK-PDO-001', $records);
        $this->assertEquals(0, $insertedDup);

        $logs = $this->storage->getAttendanceLogs();
        $this->assertCount(1, $logs);
        $this->assertEquals('201', $logs[0]['pin']);
    }

    public function test_command_queueing_and_fetching(): void
    {
        $cmdId = $this->storage->queueCommand('ZK-PDO-001', 'DATA DELETE USERINFO PIN=201');

        $this->assertNotEmpty($cmdId);

        $pending = $this->storage->getPendingCommands('ZK-PDO-001');
        $this->assertCount(1, $pending);
        $this->assertEquals('DATA DELETE USERINFO PIN=201', $pending[0]['command_text']);

        $this->storage->updateCommandStatus('ZK-PDO-001', $cmdId, 0, 'Return=0');

        $pendingAfter = $this->storage->getPendingCommands('ZK-PDO-001');
        $this->assertCount(0, $pendingAfter);
    }
}
