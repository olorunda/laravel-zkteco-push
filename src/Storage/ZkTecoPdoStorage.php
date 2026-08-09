<?php

declare(strict_types=1);

namespace ZkTeco\Push\Storage;

use PDO;
use Exception;

class ZkTecoPdoStorage implements ZkTecoStorageInterface
{
    private PDO $pdo;
    private string $tablePrefix;

    private string $tDevices;
    private string $tLogs;
    private string $tCommands;
    private string $tUsers;

    public function __construct(PDO $pdo, string $tablePrefix = 'zkteco_')
    {
        $this->pdo = $pdo;
        $this->tablePrefix = $tablePrefix;

        $this->tDevices = $this->tablePrefix . 'devices';
        $this->tLogs = $this->tablePrefix . 'attendance_logs';
        $this->tCommands = $this->tablePrefix . 'commands';
        $this->tUsers = $this->tablePrefix . 'users';

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initializeSchema();
    }

    /**
     * Create database tables automatically if they do not exist.
     */
    private function initializeSchema(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $autoIncrement = ($driver === 'sqlite') ? 'AUTOINCREMENT' : 'AUTO_INCREMENT';
        $textType = ($driver === 'sqlite') ? 'TEXT' : 'LONGTEXT';

        // 1. Devices Table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tDevices} (
                serial_number VARCHAR(100) PRIMARY KEY,
                ip_address VARCHAR(45) NULL,
                push_version VARCHAR(50) NULL,
                firmware VARCHAR(100) NULL,
                last_seen_at DATETIME NOT NULL,
                metadata {$textType} NULL
            )
        ");

        // 2. Attendance Logs Table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tLogs} (
                id INTEGER PRIMARY KEY {$autoIncrement},
                device_sn VARCHAR(100) NOT NULL,
                pin VARCHAR(50) NOT NULL,
                timestamp DATETIME NOT NULL,
                status_code INT NOT NULL,
                status_label VARCHAR(50) NOT NULL,
                verify_type_code INT NOT NULL,
                verify_type_label VARCHAR(50) NOT NULL,
                work_code VARCHAR(50) NULL,
                raw_line TEXT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT uq_att_log UNIQUE (device_sn, pin, timestamp, status_code)
            )
        ");

        // 3. Device Commands Queue Table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tCommands} (
                command_id VARCHAR(100) PRIMARY KEY,
                device_sn VARCHAR(100) NOT NULL,
                command_text TEXT NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
                return_code INT NULL,
                extra_info TEXT NULL,
                queued_at DATETIME NOT NULL,
                execute_after DATETIME NULL,
                executed_at DATETIME NULL
            )
        ");

        // Migration checks for existing databases created prior to schema updates
        $deviceColumns = [
            'last_seen_at' => 'DATETIME NULL',
            'ip_address' => 'VARCHAR(45) NULL',
            'push_version' => 'VARCHAR(50) NULL',
            'firmware' => 'VARCHAR(100) NULL',
            'metadata' => "{$textType} NULL"
        ];

        foreach ($deviceColumns as $colName => $colDef) {
            try {
                $this->pdo->exec("ALTER TABLE {$this->tDevices} ADD COLUMN {$colName} {$colDef}");
            } catch (Throwable $e) {
                // Column already exists or alter not required
            }
        }

        try {
            $this->pdo->exec("ALTER TABLE {$this->tCommands} ADD COLUMN execute_after DATETIME NULL");
        } catch (Throwable $e) {
            // Column already exists
        }

        // 4. User Profiles Table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tUsers} (
                pin VARCHAR(50) NOT NULL,
                device_sn VARCHAR(100) NOT NULL,
                name VARCHAR(100) NULL,
                card_number VARCHAR(50) NULL,
                password VARCHAR(50) NULL,
                privilege INT DEFAULT 0,
                group_id INT DEFAULT 1,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (pin, device_sn)
            )
        ");
    }

    public function updateDevice(string $serialNumber, array $info = []): void
    {
        $now = date('Y-m-d H:i:s');
        $ip = $info['ip'] ?? null;
        $pushVersion = $info['pushversion'] ?? null;
        $firmware = $info['firmware'] ?? null;
        $metadata = json_encode($info);

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->tDevices} (serial_number, ip_address, push_version, firmware, last_seen_at, metadata)
                VALUES (:sn, :ip, :push_ver, :fw, :now, :meta)
                ON CONFLICT(serial_number) DO UPDATE SET
                    ip_address = COALESCE(EXCLUDED.ip_address, {$this->tDevices}.ip_address),
                    push_version = COALESCE(EXCLUDED.push_version, {$this->tDevices}.push_version),
                    firmware = COALESCE(EXCLUDED.firmware, {$this->tDevices}.firmware),
                    last_seen_at = EXCLUDED.last_seen_at,
                    metadata = EXCLUDED.metadata
            ");
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->tDevices} (serial_number, ip_address, push_version, firmware, last_seen_at, metadata)
                VALUES (:sn, :ip, :push_ver, :fw, :now, :meta)
                ON DUPLICATE KEY UPDATE
                    ip_address = VALUES(ip_address),
                    push_version = VALUES(push_version),
                    firmware = VALUES(firmware),
                    last_seen_at = VALUES(last_seen_at),
                    metadata = VALUES(metadata)
            ");
        }

        $stmt->execute([
            ':sn' => $serialNumber,
            ':ip' => $ip,
            ':push_ver' => $pushVersion,
            ':fw' => $firmware,
            ':now' => $now,
            ':meta' => $metadata
        ]);
    }

    public function getDevice(string $serialNumber): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tDevices} WHERE serial_number = :sn");
        $stmt->execute([':sn' => $serialNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['metadata'] = isset($row['metadata']) && $row['metadata'] ? json_decode($row['metadata'], true) : [];
        $lastSeen = $row['last_seen_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? date('Y-m-d H:i:s');
        $row['last_seen_at'] = $lastSeen;
        $row['is_online'] = (strtotime($lastSeen) >= (time() - 120));

        return $row;
    }

    public function getAllDevices(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM {$this->tDevices} ORDER BY last_seen_at DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            try {
                $stmt = $this->pdo->query("SELECT * FROM {$this->tDevices}");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e2) {
                return [];
            }
        }

        foreach ($rows as &$row) {
            $row['metadata'] = isset($row['metadata']) && $row['metadata'] ? json_decode($row['metadata'], true) : [];
            $lastSeen = $row['last_seen_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? date('Y-m-d H:i:s');
            $row['last_seen_at'] = $lastSeen;
            $row['is_online'] = (strtotime($lastSeen) >= (time() - 120));
        }

        return $rows;
    }

    public function saveAttendanceLogs(string $serialNumber, array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $insertedCount = 0;

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $ignoreKeyword = ($driver === 'sqlite') ? 'OR IGNORE' : 'IGNORE';

        $stmt = $this->pdo->prepare("
            INSERT {$ignoreKeyword} INTO {$this->tLogs}
            (device_sn, pin, timestamp, status_code, status_label, verify_type_code, verify_type_label, work_code, raw_line, created_at)
            VALUES
            (:device_sn, :pin, :timestamp, :status_code, :status_label, :verify_type_code, :verify_type_label, :work_code, :raw_line, :created_at)
        ");

        foreach ($records as $record) {
            try {
                $stmt->execute([
                    ':device_sn' => $serialNumber,
                    ':pin' => (string)$record['pin'],
                    ':timestamp' => $record['timestamp'],
                    ':status_code' => $record['status_code'],
                    ':status_label' => $record['status_label'],
                    ':verify_type_code' => $record['verify_type_code'],
                    ':verify_type_label' => $record['verify_type_label'],
                    ':work_code' => $record['work_code'] ?? '0',
                    ':raw_line' => $record['raw_line'] ?? null,
                    ':created_at' => $now,
                ]);

                if ($stmt->rowCount() > 0) {
                    $insertedCount++;
                }
            } catch (Exception $e) {
                // Ignore duplicates or log
            }
        }

        return $insertedCount;
    }

    public function getAttendanceLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['device_sn'])) {
            $where[] = "device_sn = :device_sn";
            $params[':device_sn'] = $filters['device_sn'];
        }

        if (!empty($filters['pin'])) {
            $where[] = "pin = :pin";
            $params[':pin'] = $filters['pin'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "timestamp >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "timestamp <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $whereSql = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

        $sql = "SELECT * FROM {$this->tLogs} {$whereSql} ORDER BY timestamp DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function queueCommand(string $serialNumber, string $commandString, int $delaySeconds = 0): string
    {
        $commandId = (string) (time() . rand(100, 999));
        $now = date('Y-m-d H:i:s');
        $executeAfter = ($delaySeconds > 0) ? date('Y-m-d H:i:s', time() + $delaySeconds) : $now;

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tCommands} (command_id, device_sn, command_text, status, queued_at, execute_after)
            VALUES (:cmd_id, :sn, :cmd_text, 'PENDING', :now, :execute_after)
        ");

        $stmt->execute([
            ':cmd_id' => $commandId,
            ':sn' => $serialNumber,
            ':cmd_text' => $commandString,
            ':now' => $now,
            ':execute_after' => $executeAfter
        ]);

        return $commandId;
    }

    public function getPendingCommands(string $serialNumber): array
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            SELECT command_id AS id, command_text AS command
            FROM {$this->tCommands}
            WHERE device_sn = :sn AND status = 'PENDING' AND (execute_after IS NULL OR execute_after <= :now)
            ORDER BY queued_at ASC
        ");

        $stmt->execute([':sn' => $serialNumber, ':now' => $now]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $dispatchedIds = array_column($rows, 'id');
            $inClause = implode(',', array_fill(0, count($dispatchedIds), '?'));

            $updateStmt = $this->pdo->prepare("
                UPDATE {$this->tCommands} SET status = 'DISPATCHED' WHERE command_id IN ({$inClause})
            ");
            $updateStmt->execute($dispatchedIds);
        }

        return $rows;
    }

    public function updateCommandStatus(string $serialNumber, string $commandId, int $returnCode, ?string $extraInfo = null): void
    {
        $status = ($returnCode === 0) ? 'COMPLETED' : 'FAILED';
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            UPDATE {$this->tCommands}
            SET status = :status, return_code = :rc, extra_info = :extra, executed_at = :now
            WHERE command_id = :cmd_id AND device_sn = :sn
        ");

        $stmt->execute([
            ':status' => $status,
            ':rc' => $returnCode,
            ':extra' => $extraInfo,
            ':now' => $now,
            ':cmd_id' => $commandId,
            ':sn' => $serialNumber
        ]);
    }

    public function getCommandStatus(string $commandId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tCommands} WHERE command_id = :cmd_id");
        $stmt->execute([':cmd_id' => $commandId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function saveUser(array $userData): void
    {
        $now = date('Y-m-d H:i:s');
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->tUsers} (pin, device_sn, name, card_number, password, privilege, group_id, updated_at)
                VALUES (:pin, :sn, :name, :card, :pass, :priv, :grp, :now)
                ON CONFLICT(pin, device_sn) DO UPDATE SET
                    name = EXCLUDED.name,
                    card_number = EXCLUDED.card_number,
                    password = EXCLUDED.password,
                    privilege = EXCLUDED.privilege,
                    group_id = EXCLUDED.group_id,
                    updated_at = EXCLUDED.updated_at
            ");
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->tUsers} (pin, device_sn, name, card_number, password, privilege, group_id, updated_at)
                VALUES (:pin, :sn, :name, :card, :pass, :priv, :grp, :now)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    card_number = VALUES(card_number),
                    password = VALUES(password),
                    privilege = VALUES(privilege),
                    group_id = VALUES(group_id),
                    updated_at = VALUES(updated_at)
            ");
        }

        $stmt->execute([
            ':pin' => (string)$userData['pin'],
            ':sn' => $userData['device_sn'] ?? 'ALL',
            ':name' => $userData['name'] ?? null,
            ':card' => $userData['card_number'] ?? null,
            ':pass' => $userData['password'] ?? null,
            ':priv' => $userData['privilege'] ?? 0,
            ':grp' => $userData['group_id'] ?? 1,
            ':now' => $now
        ]);
    }

    public function getUsers(?string $deviceSn = null): array
    {
        if ($deviceSn) {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tUsers} WHERE device_sn = :sn OR device_sn = 'ALL'");
            $stmt->execute([':sn' => $deviceSn]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM {$this->tUsers}");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteUser(string $pin, ?string $deviceSn = null): void
    {
        if ($deviceSn) {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tUsers} WHERE pin = :pin AND (device_sn = :sn OR device_sn = 'ALL')");
            $stmt->execute([':pin' => $pin, ':sn' => $deviceSn]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tUsers} WHERE pin = :pin");
            $stmt->execute([':pin' => $pin]);
        }
    }
}
