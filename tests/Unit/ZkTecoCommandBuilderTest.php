<?php

namespace ZkTeco\Push\Tests\Unit;

use ZkTeco\Push\Tests\TestCase;
use ZkTeco\Push\ZkTecoCommandBuilder;

class ZkTecoCommandBuilderTest extends TestCase
{
    private ZkTecoCommandBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ZkTecoCommandBuilder();
    }

    public function test_builds_delete_user_command(): void
    {
        $cmd = $this->builder->buildDeleteUser('101');
        $this->assertEquals('DATA DELETE USERINFO PIN=101', $cmd);

        $cmdArray = $this->builder->buildDeleteUser(['pin' => '102']);
        $this->assertEquals('DATA DELETE USERINFO PIN=102', $cmdArray);
    }

    public function test_builds_add_user_command(): void
    {
        $cmd = $this->builder->buildAddUser([
            'pin' => '103',
            'name' => 'Jane Doe',
            'card_number' => '554433',
            'password' => '4321',
            'privilege' => 0,
            'group_id' => 1,
        ]);

        $this->assertStringContainsString('DATA USERINFO PIN=103', $cmd);
        $this->assertStringContainsString('Name=Jane Doe', $cmd);
        $this->assertStringContainsString('Card=554433', $cmd);
        $this->assertStringContainsString('Passwd=4321', $cmd);
    }

    public function test_builds_fingerprint_template_command(): void
    {
        $cmd = $this->builder->buildAddFingerprint('101', 0, 'BASE64_TEMPLATE_DATA', 1);

        $this->assertStringContainsString('DATA FP PIN=101', $cmd);
        $this->assertStringContainsString('FID=0', $cmd);
        $this->assertStringContainsString('TMP=BASE64_TEMPLATE_DATA', $cmd);
    }

    public function test_builds_face_template_command(): void
    {
        $cmd = $this->builder->buildAddFace('101', 'BASE64_FACE_DATA');

        $this->assertStringContainsString('DATA FACE PIN=101', $cmd);
        $this->assertStringContainsString('TMP=BASE64_FACE_DATA', $cmd);
    }

    public function test_builds_reboot_and_system_commands(): void
    {
        $this->assertEquals('REBOOT', $this->builder->buildReboot());
        $this->assertEquals('CLEAR LOG', $this->builder->buildClearLogs());
        $this->assertEquals('CLEAR DATA', $this->builder->buildClearData());
        $this->assertStringContainsString('AC_UNLOCK', $this->builder->buildUnlockDoor(5));
    }
}
