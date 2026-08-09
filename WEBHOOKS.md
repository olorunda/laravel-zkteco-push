# ZKTeco Push SDK Middleware - Webhooks & Event Integration Guide

This guide provides comprehensive technical documentation for the **JSON Webhook Forwarder** included in the ZKTeco Push SDK Middleware.

The middleware translates raw tab-separated ZKTeco ADMS device data into standardized JSON webhooks and POSTs them in real time to your external application (HR System, ERP, Payroll Engine, Node.js, Python, or Laravel backend).

---

## 📲 How to Configure Your ZKTeco Physical Device

ZKTeco biometric hardware machines use the **ADMS (Automatic Data Master Server)** HTTP Push protocol.

> ⚠️ **IMPORTANT NOTE ON ENDPOINTS**: 
> You **DO NOT** type `/iclock/cdata` into the ZKTeco device screen menu. ZKTeco firmware automatically appends `/iclock/cdata`, `/iclock/getrequest`, and `/iclock/devicecmd` to the Server IP / Domain you configure.

```text
Configured Server Address: http://192.168.1.100:8080
       │
       ├── Hardware automatically sends Handshake & Logs to:  http://192.168.1.100:8080/iclock/cdata
       ├── Hardware automatically polls for Commands at:     http://192.168.1.100:8080/iclock/getrequest
       └── Hardware automatically reports Execution at:      http://192.168.1.100:8080/iclock/devicecmd
```

### On-Screen Hardware Menu Setup Guide (TFT Screen Machines)

1. Press **[M/OK]** button on keypad to open Menu.
2. Select **Comm.** (Communication Settings) ➔ **Cloud Server Options** (or **ADMS**, **WDMS**, **Webserver Settings**).
3. Set **Server Mode**: `ADMS` or `Cloud Server`.
4. Configure Server Target:
   - **Using IP Address**: Set **Enable Domain Name** = `OFF` ➔ Enter **Server IP Address** (e.g. `192.168.1.100`).
   - **Using Domain Name**: Set **Enable Domain Name** = `ON` ➔ Enter **Server Domain Name** (e.g. `zkteco.yourcompany.com`).
5. **Server Port**: Enter your PHP Middleware server port (e.g. `8080`, `80`, or `443`).
6. **Enable Proxy Server**: `OFF`.
7. **HTTPS / SSL**: Set to `ON` if using `https://` on Port `443`.
8. Press **[ESC]** and confirm **Save**.

---

## 🔒 Webhook Security & Signature Verification

Every HTTP POST request sent by the middleware to your external server includes security headers:

| Header | Description | Example |
|---|---|---|
| `Content-Type` | Always `application/json` | `application/json` |
| `User-Agent` | Middleware signature | `ZkTeco-Push-Middleware-Bridge/2.0` |
| `Authorization` | Configured secret bearer token | `Bearer sk_live_zkteco_secret_9988` |
| `X-ZkTeco-Signature` | HMAC-SHA256 signature of request body | `a3b8c9...` |

### Validating HMAC SHA256 Signature (Backend Examples)

#### 🐘 PHP (Laravel / Vanilla PHP)

```php
$secretToken = 'sk_live_zkteco_secret_9988';
$receivedSignature = $_SERVER['HTTP_X_ZKTECO_SIGNATURE'] ?? '';
$requestBody = file_get_contents('php://input');

$calculatedSignature = hash_hmac('sha256', $requestBody, $secretToken);

if (!hash_equals($calculatedSignature, $receivedSignature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid webhook signature']);
    exit;
}
```

#### 🟢 Node.js (Express.js)

```javascript
const crypto = require('crypto');

app.post('/webhooks/attendance', (req, res) => {
    const secretToken = 'sk_live_zkteco_secret_9988';
    const receivedSignature = req.headers['x-zkteco-signature'];
    const rawBody = JSON.stringify(req.body);

    const calculatedSignature = crypto
        .createHmac('sha256', secretToken)
        .update(rawBody)
        .digest('hex');

    if (receivedSignature !== calculatedSignature) {
        return res.status(401).json({ error: 'Invalid webhook signature' });
    }

    // Process event
    console.log('Attendance Event Received:', req.body.data);
    res.status(200).json({ status: 'success' });
});
```

#### 🐍 Python (FastAPI / Flask)

```python
import hmac
import hashlib
from fastapi import FastAPI, Request, HTTPException

app = FastAPI()

SECRET_TOKEN = "sk_live_zkteco_secret_9988"

@app.post("/webhooks/attendance")
async def handle_attendance_webhook(request: Request):
    signature = request.headers.get("X-ZkTeco-Signature", "")
    body = await request.body()

    expected_signature = hmac.new(
        SECRET_TOKEN.encode(),
        body,
        hashlib.sha256
    ).hexdigest()

    if not hmac.compare_digest(signature, expected_signature):
        raise HTTPException(status_code=401, detail="Invalid signature")

    data = await request.json()
    print("Received attendance data:", data)
    return {"status": "success"}
```

---

## 📡 Webhook Events & JSON Payload Specifications

---

### Event 1: `zkteco.attendance`

Fired immediately whenever an employee punches attendance on any connected ZKTeco machine (Fingerprint, Face Scan, RFID Card, Palm, or Password).

- **HTTP Method**: `POST`
- **Default Path**: `/webhooks/attendance`

#### JSON Payload Sample

```json
{
  "event": "zkteco.attendance",
  "timestamp": "2026-08-09T08:30:15+00:00",
  "data": {
    "device_sn": "ZK-TEST-SN-9988",
    "count": 2,
    "records": [
      {
        "pin": "1001",
        "timestamp": "2026-08-09 08:30:00",
        "status_code": 0,
        "status_label": "Check-In",
        "verify_type_code": 1,
        "verify_type_label": "Fingerprint",
        "work_code": "0",
        "raw_line": "1001\t2026-08-09 08:30:00\t0\t1\t0\t0\t0"
      },
      {
        "pin": "1002",
        "timestamp": "2026-08-09 08:31:12",
        "status_code": 0,
        "status_label": "Check-In",
        "verify_type_code": 15,
        "verify_type_label": "Face",
        "work_code": "0",
        "raw_line": "1002\t2026-08-09 08:31:12\t0\t15\t0\t0\t0"
      }
    ]
  }
}
```

#### Field Glossary

| Field | Type | Description |
|---|---|---|
| `device_sn` | `string` | Serial Number of the ZKTeco hardware machine. |
| `count` | `integer` | Number of attendance logs pushed in this batch. |
| `records[].pin` | `string` | User / Employee ID registered on the biometric device. |
| `records[].timestamp` | `string` | Exact punch timestamp (`YYYY-MM-DD HH:MM:SS`). |
| `records[].status_code` | `integer` | Raw state code (`0`=Check-In, `1`=Check-Out, `2`=Break-Out, `3`=Break-In, `4`=OT-In, `5`=OT-Out). |
| `records[].status_label` | `string` | Human-readable punch label. |
| `records[].verify_type_code` | `integer` | Verification mode (`0`=Password, `1`=Fingerprint, `2`=Card, `15`=Face, `25`=Palm). |
| `records[].verify_type_label` | `string` | Human-readable verification method label. |

---

### Event 2: `zkteco.heartbeat`

Fired when a biometric hardware machine connects, performs an initial ADMS handshake, or polls the middleware.

- **HTTP Method**: `POST`
- **Default Path**: `/webhooks/device-status`

#### JSON Payload Sample

```json
{
  "event": "zkteco.heartbeat",
  "timestamp": "2026-08-09T08:30:00+00:00",
  "data": {
    "device_sn": "ZK-TEST-SN-9988",
    "status": "online",
    "ip": "192.168.1.150",
    "metadata": {
      "SN": "ZK-TEST-SN-9988",
      "options": "all",
      "pushversion": "3.0.1",
      "language": "83",
      "ip": "192.168.1.150"
    }
  }
}
```

#### Field Glossary

| Field | Type | Description |
|---|---|---|
| `device_sn` | `string` | Serial Number of the device. |
| `status` | `string` | Always `"online"`. |
| `ip` | `string` | Client IPv4 or IPv6 address of the machine. |
| `metadata` | `object` | Firmware, language, and push SDK parameters sent by device. |

---

### Event 3: `zkteco.command_result`

Fired when a ZKTeco machine finishes executing a queued command (e.g., user registration, user deletion, delayed deletion, reboot, clear logs) sent by your external server.

- **HTTP Method**: `POST`
- **Default Path**: `/webhooks/command-result`

#### JSON Payload Sample

```json
{
  "event": "zkteco.command_result",
  "timestamp": "2026-08-09T08:32:05+00:00",
  "data": {
    "device_sn": "ZK-TEST-SN-9988",
    "command_id": "1786256706801",
    "return_code": 0,
    "status": "COMPLETED",
    "raw": "ID=1786256706801&Return=0&CMD=DATA"
  }
}
```

#### Field Glossary

| Field | Type | Description |
|---|---|---|
| `device_sn` | `string` | Device Serial Number that executed the command. |
| `command_id` | `string` | Unique command identifier generated when queued via REST API. |
| `return_code` | `integer` | Return execution code from machine (`0` = Success, non-zero = error). |
| `status` | `string` | `"COMPLETED"` or `"FAILED"`. |

---

## 🛠️ Complete Laravel Webhook Receiver Example

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Log;

class ZkTecoWebhookController extends Controller
{
    public function handleAttendance(Request $request): JsonResponse
    {
        // 1. Verify HMAC Signature
        $secretToken = config('services.zkteco.webhook_secret');
        $receivedSignature = $request->header('X-ZkTeco-Signature');
        $calculatedSignature = hash_hmac('sha256', $request->getContent(), $secretToken);

        if (!hash_equals($calculatedSignature, (string)$receivedSignature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 2. Extract Event Data
        $payload = $request->json()->all();
        $deviceSn = $payload['data']['device_sn'];
        $records = $payload['data']['records'];

        foreach ($records as $log) {
            Log::info("Attendance Punch", [
                'device' => $deviceSn,
                'user_id' => $log['pin'],
                'timestamp' => $log['timestamp'],
                'type' => $log['status_label'],
                'method' => $log['verify_type_label']
            ]);

            // Save to your database (e.g. EmployeeAttendance::create(...))
        }

        return response()->json(['success' => true]);
    }
}
```
