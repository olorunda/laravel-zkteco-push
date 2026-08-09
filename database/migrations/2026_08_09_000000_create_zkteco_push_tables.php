<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $prefix = config('zkteco-push.table_prefix', 'zkteco_');

        $tDevices = $prefix . 'devices';
        $tLogs = $prefix . 'attendance_logs';
        $tCommands = $prefix . 'commands';
        $tUsers = $prefix . 'users';

        // 1. Devices Table
        if (!Schema::hasTable($tDevices)) {
            Schema::create($tDevices, function (Blueprint $table) {
                $table->string('serial_number', 100)->primary();
                $table->string('ip_address', 45)->nullable();
                $table->string('push_version', 50)->nullable();
                $table->string('firmware', 100)->nullable();
                $table->dateTime('last_seen_at')->nullable();
                $table->text('metadata')->nullable();
            });
        } else {
            Schema::table($tDevices, function (Blueprint $table) use ($tDevices) {
                if (!Schema::hasColumn($tDevices, 'last_seen_at')) {
                    $table->dateTime('last_seen_at')->nullable();
                }
                if (!Schema::hasColumn($tDevices, 'ip_address')) {
                    $table->string('ip_address', 45)->nullable();
                }
                if (!Schema::hasColumn($tDevices, 'push_version')) {
                    $table->string('push_version', 50)->nullable();
                }
                if (!Schema::hasColumn($tDevices, 'firmware')) {
                    $table->string('firmware', 100)->nullable();
                }
                if (!Schema::hasColumn($tDevices, 'metadata')) {
                    $table->text('metadata')->nullable();
                }
            });
        }

        // 2. Attendance Logs Table
        if (!Schema::hasTable($tLogs)) {
            Schema::create($tLogs, function (Blueprint $table) {
                $table->id();
                $table->string('device_sn', 100)->index();
                $table->string('pin', 50)->index();
                $table->dateTime('timestamp');
                $table->integer('status_code');
                $table->string('status_label', 50);
                $table->integer('verify_type_code');
                $table->string('verify_type_label', 50);
                $table->string('work_code', 50)->default('0');
                $table->text('raw_line')->nullable();
                $table->dateTime('created_at');

                $table->unique(['device_sn', 'pin', 'timestamp', 'status_code'], 'uq_att_log');
            });
        }

        // 3. Commands Queue Table
        if (!Schema::hasTable($tCommands)) {
            Schema::create($tCommands, function (Blueprint $table) {
                $table->string('command_id', 100)->primary();
                $table->string('device_sn', 100)->index();
                $table->text('command_text');
                $table->string('status', 30)->default('PENDING')->index();
                $table->integer('return_code')->nullable();
                $table->text('extra_info')->nullable();
                $table->dateTime('queued_at');
                $table->dateTime('execute_after')->nullable();
                $table->dateTime('executed_at')->nullable();
            });
        }

        // 4. User Profiles Table
        if (!Schema::hasTable($tUsers)) {
            Schema::create($tUsers, function (Blueprint $table) {
                $table->string('pin', 50);
                $table->string('device_sn', 100);
                $table->string('name', 100)->nullable();
                $table->string('card_number', 50)->nullable();
                $table->string('password', 50)->nullable();
                $table->integer('privilege')->default(0);
                $table->integer('group_id')->default(1);
                $table->dateTime('updated_at');

                $table->primary(['pin', 'device_sn']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $prefix = config('zkteco-push.table_prefix', 'zkteco_');

        Schema::dropIfExists($prefix . 'users');
        Schema::dropIfExists($prefix . 'commands');
        Schema::dropIfExists($prefix . 'attendance_logs');
        Schema::dropIfExists($prefix . 'devices');
    }
};
