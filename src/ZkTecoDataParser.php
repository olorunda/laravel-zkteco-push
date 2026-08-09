<?php

declare(strict_types=1);

namespace ZkTeco\Push;

class ZkTecoDataParser
{
    public static array $statusMap = [
        0 => 'Check-In',
        1 => 'Check-Out',
        2 => 'Break-Out',
        3 => 'Break-In',
        4 => 'Overtime-In',
        5 => 'Overtime-Out',
        255 => 'Other',
    ];

    public static array $verifyTypeMap = [
        0 => 'Password',
        1 => 'Fingerprint',
        2 => 'Card',
        3 => 'Password + Card',
        4 => 'Fingerprint + Password',
        5 => 'Fingerprint + Card',
        15 => 'Face',
        25 => 'Palm',
    ];

    public function parseAttendanceLogs(string $content): array
    {
        $records = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = explode("\t", $line);
            if (count($parts) < 2) {
                $parts = preg_split('/\s+/', $line);
            }

            if (count($parts) >= 2) {
                $pin = trim($parts[0]);
                $timestamp = trim($parts[1]);

                if (strtotime($timestamp) === false) {
                    continue;
                }

                $statusCode = isset($parts[2]) ? (int)trim($parts[2]) : 0;
                $verifyTypeCode = isset($parts[3]) ? (int)trim($parts[3]) : 1;
                $workCode = isset($parts[4]) ? trim($parts[4]) : '0';

                $records[] = [
                    'pin' => $pin,
                    'timestamp' => date('Y-m-d H:i:s', strtotime($timestamp)),
                    'status_code' => $statusCode,
                    'status_label' => self::$statusMap[$statusCode] ?? 'Check-In',
                    'verify_type_code' => $verifyTypeCode,
                    'verify_type_label' => self::$verifyTypeMap[$verifyTypeCode] ?? 'Unknown',
                    'work_code' => $workCode,
                    'raw_line' => $line
                ];
            }
        }

        return $records;
    }

    public function parseDeviceCmdReturn(string $content): array
    {
        $result = [
            'command_id' => '',
            'return_code' => -1,
            'cmd_name' => '',
            'raw' => $content
        ];

        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            parse_str(str_replace("\t", "&", $line), $queryData);

            if (isset($queryData['ID'])) {
                $result['command_id'] = (string)$queryData['ID'];
            }
            if (isset($queryData['Return'])) {
                $result['return_code'] = (int)$queryData['Return'];
            }
            if (isset($queryData['CMD'])) {
                $result['cmd_name'] = (string)$queryData['CMD'];
            }
        }

        return $result;
    }

    public function parseUserInfo(string $content): array
    {
        $users = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            parse_str(str_replace("\t", "&", $line), $data);

            if (isset($data['PIN']) || isset($data['pin'])) {
                $pin = $data['PIN'] ?? $data['pin'];
                $users[] = [
                    'pin' => (string)$pin,
                    'name' => $data['Name'] ?? $data['name'] ?? null,
                    'password' => $data['Passwd'] ?? $data['password'] ?? null,
                    'card_number' => $data['Card'] ?? $data['card'] ?? null,
                    'privilege' => isset($data['Pri']) ? (int)$data['Pri'] : 0,
                    'group_id' => isset($data['Grp']) ? (int)$data['Grp'] : 1,
                ];
            }
        }

        return $users;
    }
}
