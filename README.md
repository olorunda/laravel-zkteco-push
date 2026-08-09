# ZKTeco ADMS Push SDK Laravel Package & REST API Interpreter Bridge

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zkteco/laravel-push-sdk.svg?style=flat-square)](https://packagist.org/packages/zkteco/laravel-push-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/zkteco/laravel-push-sdk.svg?style=flat-square)](https://packagist.org/packages/zkteco/laravel-push-sdk)
[![License](https://img.shields.io/packagist/l/zkteco/laravel-push-sdk.svg?style=flat-square)](LICENSE.md)

A clean, native **Laravel Package** (`zkteco/laravel-push-sdk`) that acts as an **interpreter bridge** between **ZKTeco Biometric Devices** (Fingerprint, Face, Palm, RFID Access Control) and any **External Application / API** (HR, ERP, Payroll System).

---

## 🚀 Key Package Features

- 🔌 **Native Laravel Auto-Discovery**: Automatically registers Service Provider, Facades, Routes, and Database Migrations.
- ⚡ **Fluent `ZkTecoPush` Facade**: Chain device actions using expressive array parameters (`deleteUser`, `addUser`, `queueCommand`, `reboot`, `syncTime`, `unlockDoor`).
- 🕒 **Delayed Action Scheduler**: Queue user deletions or command dispatches with time delays (`delay_minutes`, `delay_seconds`, or `delaySeconds: 120`).
- 🎧 **Native Laravel Events**: Automatically dispatches `AttendancePushed`, `DeviceHeartbeat`, and `CommandExecuted` events for queued listeners/jobs.
- 🗄️ **Configurable Table Prefix**: Custom prefix setting (`table_prefix => 'zk_'`) for all database tables.
- 📊 **Built-In Admin UI Dashboard**: Glassmorphism web dashboard at `/zkteco/admin` to monitor connected devices and logs.

---

## 📦 Installation

### Step 1: Install Package via Composer

```bash
composer require zkteco/laravel-push-sdk
```

Or for local development in your main Laravel `composer.json`:
```json
"repositories": [
    {
        "type": "path",
        "url": "./packages/laravel-zkteco-push"
    }
]
```

### Step 2: Publish Configuration & Migrations

```bash
php artisan vendor:publish --tag=zkteco-config
php artisan vendor:publish --tag=zkteco-migrations

php artisan migrate
```

---

## ⚙️ Environment Configuration (`.env`)

```ini
# Table Prefix
ZKTECO_TABLE_PREFIX=zkteco_

# Middleware REST API Secret Key
ZKTECO_API_KEY=zk_api_key_live_998877

# External Server Target (Where translated JSON webhooks are forwarded)
ZKTECO_EXTERNAL_API_URL=https://api.yourcompany.com/v1
ZKTECO_WEBHOOK_ENABLED=true
ZKTECO_WEBHOOK_SECRET=sk_live_zkteco_secret_9988
ZKTECO_ATTENDANCE_WEBHOOK_PATH=/webhooks/attendance
ZKTECO_HEARTBEAT_WEBHOOK_PATH=/webhooks/device-status
```

---

## ⚡ Using the `ZkTecoPush` Facade

### Array Payload Syntax:
```php
use ZkTeco\Push\Facades\ZkTecoPush;

// 1. Delete user with a 2-minute delay (120 seconds) using array
ZkTecoPush::deleteUser('ZK-SN-001', ['pin' => '1003'], delaySeconds: 120);

// 2. Queue command using array format
ZkTecoPush::queueCommand('ZK-SN-001', [
    'action' => 'delete_user',
    'pin' => '1003'
], delaySeconds: 120);

// 3. Add or update user using array payload
ZkTecoPush::addUser('ZK-SN-001', [
    'pin' => '1004',
    'name' => 'John Doe',
    'card_number' => '987654321',
    'password' => '1234'
]);
```

### Fluent Device Chaining Syntax:
```php
use ZkTeco\Push\Facades\ZkTecoPush;

// Chain methods directly on a target machine
ZkTecoPush::device('ZK-SN-001')
    ->deleteUser('1003', delaySeconds: 120);

ZkTecoPush::device('ZK-SN-001')
    ->reboot(delaySeconds: 10);

ZkTecoPush::device('ZK-SN-001')
    ->syncTime();

ZkTecoPush::device('ZK-SN-001')
    ->unlockDoor(unlockSeconds: 5);
```

---

## 🎧 Detailed Examples of Available Laravel Events

The package automatically fires native Laravel events whenever connected biometric machines push data or execute commands. You can attach queued listeners or event callbacks in your `AppServiceProvider` or `EventServiceProvider`.

---

### 1. `AttendancePushed` Event

Fired in real time whenever an employee scans a fingerprint, face, palm, RFID card, or enters a password on any ZKTeco terminal.

#### Listener Callback Example:
```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ZkTeco\Push\Events\AttendancePushed;

Event::listen(AttendancePushed::class, function (AttendancePushed $event) {
    // Machine Serial Number (e.g. "ZK-SN-001")
    $deviceSn = $event->deviceSn;
    
    // Array of parsed attendance punch records
    foreach ($event->records as $log) {
        $userPin = $log['pin'];                      // e.g. "1003"
        $punchTime = $log['timestamp'];              // e.g. "2026-08-09 08:30:00"
        $statusCode = $log['status_code'];           // e.g. 0 (Check-In) or 1 (Check-Out)
        $statusLabel = $log['status_label'];         // e.g. "Check-In"
        $verifyTypeCode = $log['verify_type_code'];   // e.g. 1 (Fingerprint) or 15 (Face)
        $verifyTypeLabel = $log['verify_type_label']; // e.g. "Fingerprint"
        
        Log::info("Attendance Punch recorded for User {$userPin} on Device {$deviceSn} via {$verifyTypeLabel}");
        
        // Save to your Eloquent Attendance model:
        // AttendanceRecord::create([
        //     'employee_id' => $userPin,
        //     'device_sn' => $deviceSn,
        //     'punched_at' => $punchTime,
        //     'punch_type' => $statusLabel,
        //     'verification_method' => $verifyTypeLabel,
        // ]);
    }
});
```

#### Dedicated Queued Listener Class Example (`app/Listeners/ProcessZkTecoPunch.php`):
```php
namespace App\Listeners;

use ZkTeco\Push\Events\AttendancePushed;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Attendance;

class ProcessZkTecoPunch implements ShouldQueue
{
    public function handle(AttendancePushed $event): void
    {
        foreach ($event->records as $log) {
            Attendance::updateOrCreate(
                [
                    'device_sn' => $event->deviceSn,
                    'user_pin' => $log['pin'],
                    'punched_at' => $log['timestamp'],
                ],
                [
                    'status' => $log['status_label'],
                    'method' => $log['verify_type_label'],
                ]
            );
        }
    }
}
```

---

### 2. `DeviceHeartbeat` Event

Fired whenever a ZKTeco biometric device connects to the server, completes an initial ADMS handshake, or sends a periodic ping.

#### Listener Callback Example:
```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ZkTeco\Push\Events\DeviceHeartbeat;

Event::listen(DeviceHeartbeat::class, function (DeviceHeartbeat $event) {
    $deviceSn = $event->deviceSn; // Device Serial Number (e.g. "ZK-SN-001")
    $metadata = $event->metadata; // Hardware metadata array
    
    $ipAddress = $metadata['ip'] ?? '127.0.0.1';
    $pushVersion = $metadata['pushversion'] ?? '3.0.1';
    $firmware = $metadata['firmware'] ?? 'Ver 8.0.0';
    
    Log::info("Device {$deviceSn} heartbeat received from IP {$ipAddress}");
    
    // Update machine status in your database:
    // BiometricDevice::where('serial_number', $deviceSn)->update([
    //     'last_seen_at' => now(),
    //     'ip_address' => $ipAddress,
    //     'is_online' => true,
    // ]);
});
```

---

### 3. `CommandExecuted` Event

Fired when a hardware machine finishes executing a queued command (such as user creation, user deletion, delayed deletion, device reboot, or log clearance).

#### Listener Callback Example:
```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use ZkTeco\Push\Events\CommandExecuted;

Event::listen(CommandExecuted::class, function (CommandExecuted $event) {
    $deviceSn = $event->deviceSn; // Machine Serial Number
    $result = $event->result;     // Command Execution Result Payload
    
    $commandId = $result['command_id'];  // Unique Command ID string
    $returnCode = $result['return_code']; // 0 = Success, non-zero = error
    $status = ($returnCode === 0) ? 'COMPLETED' : 'FAILED';
    
    if ($returnCode === 0) {
        Log::info("Command {$commandId} successfully executed on device {$deviceSn}");
    } else {
        Log::error("Command {$commandId} failed on device {$deviceSn} with code {$returnCode}");
    }
    
    // Update command status in your system:
    // DeviceCommandLog::where('command_id', $commandId)->update([
    //     'status' => $status,
    //     'executed_at' => now(),
    // ]);
});
```

---

## 🏭 Production-Ready Integration Guide

Below is a complete, production-grade implementation pattern for real-world Laravel applications. Reference file: **[ProductionLaravelExample.php](examples/ProductionLaravelExample.php)**.

### 1. Queued Event Listener (`app/Listeners/SyncBiometricAttendanceListener.php`)

```php
namespace App\Listeners;

use ZkTeco\Push\Events\AttendancePushed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\EmployeeAttendance;
use Throwable;

class SyncBiometricAttendanceListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $timeout = 60;

    public function handle(AttendancePushed $event): void
    {
        $deviceSn = $event->deviceSn;
        $records = $event->records;

        Log::channel('daily')->info("Processing " . count($records) . " attendance punches from device [{$deviceSn}]");

        DB::beginTransaction();
        try {
            foreach ($records as $log) {
                EmployeeAttendance::updateOrCreate(
                    [
                        'device_sn' => $deviceSn,
                        'user_pin' => (string)$log['pin'],
                        'punched_at' => $log['timestamp'],
                    ],
                    [
                        'status' => $log['status_label'],
                        'verification_method' => $log['verify_type_label'],
                        'work_code' => $log['work_code'] ?? '0',
                        'raw_payload' => json_encode($log),
                    ]
                );
            }

            DB::commit();
            Log::info("Successfully synced " . count($records) . " attendance records for device [{$deviceSn}]");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("Failed to sync attendance batch from [{$deviceSn}]: " . $e->getMessage());
            throw $e; // Trigger job retry
        }
    }
}
```

---

### 2. Employee Management Controller (`app/Http/Controllers/BiometricEmployeeManagementController.php`)

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ZkTeco\Push\Facades\ZkTecoPush;
use Illuminate\Support\Facades\Log;

class BiometricEmployeeManagementController extends Controller
{
    /**
     * Onboard Employee to Biometric Terminal
     * POST /api/employees/onboard
     */
    public function onboardEmployee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_sn' => 'required|string',
            'employee_pin' => 'required|string',
            'full_name' => 'required|string|max:100',
            'card_number' => 'nullable|string',
            'passcode' => 'nullable|string',
        ]);

        try {
            // Provision employee onto physical biometric machine via Facade
            $cmdId = ZkTecoPush::device($validated['device_sn'])
                ->addUser([
                    'pin' => $validated['employee_pin'],
                    'name' => $validated['full_name'],
                    'card_number' => $validated['card_number'] ?? null,
                    'password' => $validated['passcode'] ?? null,
                    'privilege' => 0, // 0 = Standard User, 14 = Admin
                ]);

            return response()->json([
                'status' => 'success',
                'message' => "User provisioning command queued for terminal {$validated['device_sn']}",
                'command_id' => $cmdId,
            ]);
        } catch (\Throwable $e) {
            Log::error("Onboarding error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Offboard Employee with Delayed Deletion (e.g., 2 minutes delay)
     * DELETE /api/employees/offboard
     */
    public function offboardEmployee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_sn' => 'required|string',
            'employee_pin' => 'required|string',
            'delay_minutes' => 'nullable|integer|min:0|max:1440',
        ]);

        $delaySeconds = ($validated['delay_minutes'] ?? 0) * 60;

        try {
            // Schedule user deletion with delay
            $cmdId = ZkTecoPush::device($validated['device_sn'])
                ->deleteUser($validated['employee_pin'], delaySeconds: $delaySeconds);

            return response()->json([
                'status' => 'success',
                'message' => "User deletion command queued for device {$validated['device_sn']}",
                'command_id' => $cmdId,
                'delay_seconds' => $delaySeconds,
                'scheduled_for' => now()->addSeconds($delaySeconds)->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Trigger Emergency Access Control Door Unlock
     * POST /api/devices/unlock-door
     */
    public function unlockDoor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_sn' => 'required|string',
            'seconds' => 'nullable|integer|min:1|max:60',
        ]);

        $seconds = $validated['seconds'] ?? 5;
        $cmdId = ZkTecoPush::device($validated['device_sn'])->unlockDoor($seconds);

        return response()->json([
            'status' => 'success',
            'message' => "Door unlock command issued for {$seconds} seconds",
            'command_id' => $cmdId,
        ]);
    }
}
```
