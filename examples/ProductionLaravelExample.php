<?php

/**
 * ==============================================================================
 * Production-Ready ZKTeco Push SDK Integration for Laravel Applications
 * ==============================================================================
 *
 * This file contains complete, copy-pasteable production-grade Laravel code
 * including Eloquent Models, Queued Event Listeners, Controller actions for
 * onboarding/offboarding employees, and error handling.
 */

namespace App\Models {
    use Illuminate\Database\Eloquent\Model;

    /**
     * Eloquent Model for Attendance Records
     */
    class EmployeeAttendance extends Model
    {
        protected $table = 'employee_attendances';

        protected $fillable = [
            'device_sn',
            'user_pin',
            'punched_at',
            'status',
            'verification_method',
            'work_code',
            'raw_payload',
        ];

        protected $casts = [
            'punched_at' => 'datetime',
        ];
    }

    /**
     * Eloquent Model for Biometric Devices
     */
    class BiometricDevice extends Model
    {
        protected $table = 'biometric_devices';

        protected $fillable = [
            'serial_number',
            'name',
            'location',
            'ip_address',
            'firmware_version',
            'is_online',
            'last_seen_at',
        ];

        protected $casts = [
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }
}

namespace App\Listeners {
    use ZkTeco\Push\Events\AttendancePushed;
    use ZkTeco\Push\Events\DeviceHeartbeat;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\DB;
    use App\Models\EmployeeAttendance;
    use App\Models\BiometricDevice;
    use Throwable;

    /**
     * Production Queued Listener for Real-Time Attendance Punches
     */
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
                Log::error("Failed to sync attendance batch from [{$deviceSn}]: " . $e->getMessage(), [
                    'exception' => $e
                ]);

                throw $e;
            }
        }
    }

    /**
     * Production Queued Listener for Device Heartbeat & Ping Monitoring
     */
    class MonitorBiometricDeviceHeartbeat implements ShouldQueue
    {
        use InteractsWithQueue;

        public function handle(DeviceHeartbeat $event): void
        {
            $sn = $event->deviceSn;
            $meta = $event->metadata;

            BiometricDevice::updateOrCreate(
                ['serial_number' => $sn],
                [
                    'ip_address' => $meta['ip'] ?? null,
                    'firmware_version' => $meta['firmware'] ?? $meta['pushversion'] ?? 'ADMS Push SDK',
                    'is_online' => true,
                    'last_seen_at' => now(),
                ]
            );
        }
    }
}

namespace App\Http\Controllers {
    use Illuminate\Http\Request;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Routing\Controller;
    use ZkTeco\Push\Facades\ZkTecoPush;
    use App\Models\BiometricDevice;
    use Illuminate\Support\Facades\Log;

    /**
     * Production API Controller for Managing Hardware & Employee Biometrics
     */
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
         * Offboard Employee with Delayed Deletion
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

        /**
         * Query Real-Time Hardware Status
         * GET /api/devices/status
         */
        public function listDevices(): JsonResponse
        {
            $devices = ZkTecoPush::getAllDevices();
            return response()->json([
                'status' => 'success',
                'devices' => $devices,
            ]);
        }
    }
}
