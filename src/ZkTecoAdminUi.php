<?php

declare(strict_types=1);

namespace ZkTeco\Push;

use ZkTeco\Push\Storage\ZkTecoStorageInterface;

class ZkTecoAdminUi
{
    private ZkTecoConfigManager $configManager;
    private ZkTecoStorageInterface $storage;

    public function __construct(ZkTecoConfigManager $configManager, ZkTecoStorageInterface $storage)
    {
        $this->configManager = $configManager;
        $this->storage = $storage;
    }

    /**
     * Render complete Admin Configuration & Live Dashboard HTML Page.
     */
    public function renderPage(?string $message = null, ?string $messageType = 'success'): string
    {
        $config = $this->configManager->getConfig();
        $devices = $this->storage->getAllDevices();
        $recentLogs = $this->storage->getAttendanceLogs([], 25, 0);

        $onlineCount = count(array_filter($devices, fn($d) => !empty($d['is_online'])));
        $totalDevices = count($devices);
        $totalLogs = count($recentLogs);

        $alertHtml = '';
        if ($message) {
            $alertClass = ($messageType === 'error') ? 'alert-danger' : 'alert-success';
            $alertHtml = "<div class='alert {$alertClass}'>{$message}</div>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZKTeco Push SDK Middleware & External API Interpreter</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --card-border: rgba(255, 255, 255, 0.1);
            --accent-primary: #06b6d4;
            --accent-glow: rgba(6, 182, 212, 0.3);
            --accent-indigo: #6366f1;
            --accent-emerald: #10b981;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.15) 0px, transparent 50%);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 40px;
        }

        .header {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--card-border);
            padding: 18px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-indigo));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
            box-shadow: 0 0 20px var(--accent-glow);
        }

        .brand-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 12px;
            color: var(--text-muted);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: #34d399;
            border-radius: 50%;
            box-shadow: 0 0 8px #34d399;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }

        .container {
            max-width: 1280px;
            margin: 32px auto;
            padding: 0 24px;
        }

        /* Metrics Row */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .metric-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 20px 24px;
        }

        .metric-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .metric-val {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -1px;
        }

        /* Tab Navigation */
        .tabs {
            display: flex;
            gap: 12px;
            border-bottom: 1px solid var(--card-border);
            margin-bottom: 24px;
        }

        .tab-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .tab-btn:hover {
            color: var(--text-main);
        }

        .tab-btn.active {
            color: var(--accent-primary);
            border-bottom-color: var(--accent-primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Card Section */
        .section-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .section-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        /* Form Layout */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #cbd5e1;
        }

        .form-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 12px 16px;
            color: #ffffff;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 12px var(--accent-glow);
        }

        .btn {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-indigo));
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.1s, opacity 0.2s;
            text-decoration: none;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-main);
        }

        /* Table Design */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: rgba(15, 23, 42, 0.8);
            color: var(--text-muted);
            padding: 14px 18px;
            font-weight: 600;
            border-bottom: 1px solid var(--card-border);
        }

        td {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-online { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-offline { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-checkin { background: rgba(6, 182, 212, 0.2); color: #38bdf8; }
        .badge-checkout { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }

        .alert {
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 24px;
        }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
        .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }

        .code-box {
            background: #090d16;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: #38bdf8;
            overflow-x: auto;
            white-space: pre-wrap;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .toggle-switch input {
            display: none;
        }

        .toggle-slider {
            width: 44px;
            height: 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            position: relative;
            transition: 0.3s;
        }

        .toggle-slider::before {
            content: "";
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: white;
            top: 3px;
            left: 3px;
            transition: 0.3s;
        }

        input:checked + .toggle-slider {
            background: var(--accent-primary);
        }

        input:checked + .toggle-slider::before {
            transform: translateX(20px);
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="brand">
            <div class="brand-logo">ZK</div>
            <div>
                <div class="brand-title">ZKTeco Push SDK Middleware</div>
                <div class="brand-subtitle">Biometric Hardware to External REST API Interpreter</div>
            </div>
        </div>
        <div class="status-pill">
            <span class="status-dot"></span>
            Interpreter Active
        </div>
    </header>

    <div class="container">

        {$alertHtml}

        <!-- Metrics Grid -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-label">Connected ZKTeco Devices</div>
                <div class="metric-val">{$onlineCount} <span style="font-size: 14px; color: var(--text-muted); font-weight: 400;">/ {$totalDevices} Total</span></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Logs Processed</div>
                <div class="metric-val">{$totalLogs}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">External Webhook Dispatch</div>
                <div class="metric-val" style="color: var(--accent-emerald);">
                    HTML_WEBHOOK_STATUS
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Target External API</div>
                <div class="metric-val" style="font-size: 15px; font-family: 'JetBrains Mono', monospace; word-break: break-all; color: var(--accent-primary);">
                    {$config['external_api_url']}
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('configTab')">⚙️ External API Config</button>
            <button class="tab-btn" onclick="showTab('devicesTab')">📱 Connected Devices ({$totalDevices})</button>
            <button class="tab-btn" onclick="showTab('attendanceTab')">📋 Real-Time Attendance Stream</button>
            <button class="tab-btn" onclick="showTab('testerTab')">🧪 Interactive Hardware Command Tester</button>
        </div>

        <!-- TAB 1: External API Configuration Form -->
        <div id="configTab" class="tab-content active">
            <div class="section-card">
                <div class="section-title">External API & Webhook Forwarder Settings</div>
                <div class="section-desc">
                    Configure the External Backend API URL (e.g. HR / ERP / Payroll System). When a ZKTeco machine pushes attendance logs or heartbeats to this middleware, it translates them into JSON and forwards them to these endpoints.
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_config">

                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" name="webhook_enabled" value="1" HTML_WEBHOOK_CHECKED>
                            <span class="toggle-slider"></span>
                            <span class="form-label" style="margin-bottom: 0;">Enable Automatic JSON Webhook Forwarding to External API</span>
                        </label>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">External Server Base URL</label>
                            <input type="url" class="form-input" name="external_api_url" value="{$config['external_api_url']}" placeholder="https://api.yourcompany.com/v1" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Webhook Secret / Bearer Token</label>
                            <input type="text" class="form-input" name="webhook_secret_token" value="{$config['webhook_secret_token']}" placeholder="sk_live_secret_token_123">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Attendance Punch Webhook Path</label>
                            <input type="text" class="form-input" name="attendance_webhook_path" value="{$config['attendance_webhook_path']}" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Device Heartbeat Webhook Path</label>
                            <input type="text" class="form-input" name="heartbeat_webhook_path" value="{$config['heartbeat_webhook_path']}" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Middleware REST API Access Key</label>
                            <input type="text" class="form-input" name="middleware_api_key" value="{$config['middleware_api_key']}" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; margin-top: 16px;">
                        <button type="submit" class="btn">💾 Save Configuration</button>
                    </div>
                </form>
            </div>

            <!-- Test Webhook Section -->
            <div class="section-card">
                <div class="section-title">🧪 Test External Webhook Dispatch</div>
                <div class="section-desc">Send a sample attendance punch JSON payload to your configured External API URL to verify connectivity.</div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="test_webhook">
                    <button type="submit" class="btn btn-secondary">🚀 Send Test Webhook Payload</button>
                </form>
            </div>
        </div>

        <!-- TAB 2: Connected Devices -->
        <div id="devicesTab" class="tab-content">
            <div class="section-card">
                <div class="section-title">Registered ZKTeco Biometric Devices</div>
                <div class="section-desc">Hardware machines connecting to <code>http://YOUR_MIDDLEWARE_IP/iclock/cdata</code></div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Device Serial Number (SN)</th>
                                <th>IP Address</th>
                                <th>Push SDK Version</th>
                                <th>Last Ping</th>
                            </tr>
                        </thead>
                        <tbody>
                            HTML_DEVICE_ROWS
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: Attendance Log Stream -->
        <div id="attendanceTab" class="tab-content">
            <div class="section-card">
                <div class="section-title">Parsed Real-Time Attendance Stream</div>
                <div class="section-desc">Translated attendance logs parsed from raw ZKTeco ADMS tab-separated format into structured records.</div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Device SN</th>
                                <th>User PIN</th>
                                <th>Timestamp</th>
                                <th>Punch State</th>
                                <th>Verification Mode</th>
                            </tr>
                        </thead>
                        <tbody>
                            HTML_ATTENDANCE_ROWS
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 4: Command Tester -->
        <div id="testerTab" class="tab-content">
            <div class="section-card">
                <div class="section-title">Send Action Command to Device</div>
                <div class="section-desc">Test translating external REST commands into ZKTeco ADMS push syntax (e.g. <code>DATA USERINFO PIN=101...</code> or <code>REBOOT</code>).</div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_test_command">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Target Device Serial Number</label>
                            <input type="text" class="form-input" name="device_sn" placeholder="e.g. ZK-TEST-SN-9988" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Action Command</label>
                            <select name="command_type" class="form-input">
                                <option value="reboot">Reboot Device</option>
                                <option value="sync_time">Sync Clock with Server</option>
                                <option value="clear_logs">Clear Attendance Logs</option>
                                <option value="unlock_door">Unlock Door (Access Control)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn">⚡ Queue Command to Hardware</button>
                </form>
            </div>
        </div>

    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
HTML;
    }

    /**
     * Render dynamic HTML rows for devices, logs, and status.
     */
    public function renderPageWithData(?string $message = null, ?string $messageType = 'success'): string
    {
        $config = $this->configManager->getConfig();
        $devices = $this->storage->getAllDevices();
        $recentLogs = $this->storage->getAttendanceLogs([], 30, 0);

        // Webhook status
        $webhookStatus = $config['webhook_enabled'] ? 'Enabled' : 'Disabled';

        // Checkbox status
        $webhookChecked = $config['webhook_enabled'] ? 'checked' : '';

        // Device Rows HTML
        $deviceRows = '';
        if (empty($devices)) {
            $deviceRows = "<tr><td colspan='5' style='text-align: center; color: var(--text-muted);'>No ZKTeco devices connected yet. Set device ADMS Server IP to this host.</td></tr>";
        } else {
            foreach ($devices as $d) {
                $statusBadge = $d['is_online']
                    ? "<span class='badge badge-online'>ONLINE</span>"
                    : "<span class='badge badge-offline'>OFFLINE</span>";
                $ip = $d['ip_address'] ?? 'N/A';
                $ver = $d['push_version'] ?? 'Standard ADMS';

                $deviceRows .= "<tr>
                    <td>{$statusBadge}</td>
                    <td><strong style='font-family: monospace;'>{$d['serial_number']}</strong></td>
                    <td>{$ip}</td>
                    <td>{$ver}</td>
                    <td>{$d['last_seen_at']}</td>
                </tr>";
            }
        }

        // Attendance Rows HTML
        $attRows = '';
        if (empty($recentLogs)) {
            $attRows = "<tr><td colspan='5' style='text-align: center; color: var(--text-muted);'>No attendance logs recorded yet.</td></tr>";
        } else {
            foreach ($recentLogs as $l) {
                $badgeClass = ($l['status_code'] === 1) ? 'badge-checkout' : 'badge-checkin';
                $attRows .= "<tr>
                    <td><code>{$l['device_sn']}</code></td>
                    <td><strong>{$l['pin']}</strong></td>
                    <td>{$l['timestamp']}</td>
                    <td><span class='badge {$badgeClass}'>{$l['status_label']}</span></td>
                    <td>{$l['verify_type_label']}</td>
                </tr>";
            }
        }

        $html = $this->renderPage($message, $messageType);
        $html = str_replace('HTML_WEBHOOK_STATUS', $webhookStatus, $html);
        $html = str_replace('HTML_WEBHOOK_CHECKED', $webhookChecked, $html);
        $html = str_replace('HTML_DEVICE_ROWS', $deviceRows, $html);
        $html = str_replace('HTML_ATTENDANCE_ROWS', $attRows, $html);

        return $html;
    }
}
