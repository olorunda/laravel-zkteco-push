<?php

namespace ZkTeco\Push\Tests\Unit;

use ZkTeco\Push\Tests\TestCase;
use ZkTeco\Push\ZkTecoDataParser;

class ZkTecoDataParserTest extends TestCase
{
    private ZkTecoDataParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ZkTecoDataParser();
    }

    public function test_parses_raw_tab_separated_attendance_logs(): void
    {
        $rawContent = "101\t2026-08-09 08:30:15\t0\t1\t0\t0\t0\n102\t2026-08-09 17:15:00\t1\t0\t0\t0\t0";

        $records = $this->parser->parseAttendanceLogs($rawContent);

        $this->assertCount(2, $records);

        $this->assertEquals('101', $records[0]['pin']);
        $this->assertEquals('2026-08-09 08:30:15', $records[0]['timestamp']);
        $this->assertEquals(0, $records[0]['status_code']);
        $this->assertEquals('Check-In', $records[0]['status_label']);
        $this->assertEquals(1, $records[0]['verify_type_code']);
        $this->assertEquals('Fingerprint', $records[0]['verify_type_label']);

        $this->assertEquals('102', $records[1]['pin']);
        $this->assertEquals(1, $records[1]['status_code']);
        $this->assertEquals('Check-Out', $records[1]['status_label']);
        $this->assertEquals(0, $records[1]['verify_type_code']);
        $this->assertEquals('Password', $records[1]['verify_type_label']);
    }

    public function test_parses_device_cmd_return_body(): void
    {
        $rawContent = "ID=1001&Return=0&CMD=DATA";

        $result = $this->parser->parseDeviceCmdReturn($rawContent);

        $this->assertEquals('1001', $result['command_id']);
        $this->assertEquals(0, $result['return_code']);
        $this->assertEquals('DATA', $result['cmd_name']);
        $this->assertTrue($result['is_success']);
    }

    public function test_parses_user_info_payload(): void
    {
        $rawContent = "101\tName=John Doe\tCard=987654\tPassword=1234\tPri=0\tGrp=1";

        $users = $this->parser->parseUserInfo($rawContent);

        $this->assertCount(1, $users);
        $this->assertEquals('101', $users[0]['pin']);
        $this->assertEquals('John Doe', $users[0]['name']);
        $this->assertEquals('987654', $users[0]['card_number']);
        $this->assertEquals('1234', $users[0]['password']);
    }
}
