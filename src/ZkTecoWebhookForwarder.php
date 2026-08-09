<?php

declare(strict_types=1);

namespace ZkTeco\Push;

use Throwable;

class ZkTecoWebhookForwarder
{
    private ZkTecoConfigManager $configManager;

    public function __construct(ZkTecoConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    public function forward(string $eventType, array $payload): array
    {
        $webhookUrl = $this->configManager->getWebhookUrl($eventType);
        $secretToken = $this->configManager->get('webhook_secret_token', '');

        if (!$webhookUrl) {
            return [
                'success' => false,
                'error' => 'Webhook disabled or missing URL configuration',
                'http_code' => 0
            ];
        }

        $jsonPayload = json_encode([
            'event' => "zkteco.{$eventType}",
            'timestamp' => date('c'),
            'data' => $payload
        ], JSON_UNESCAPED_SLASHES);

        return $this->sendHttpRequest($webhookUrl, $jsonPayload, $secretToken);
    }

    public function sendHttpRequest(string $url, string $jsonPayload, string $bearerToken): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ZkTeco-Push-Middleware-Bridge/2.0',
        ];

        if (!empty($bearerToken)) {
            $headers[] = "Authorization: Bearer {$bearerToken}";
            $headers[] = "X-ZkTeco-Signature: " . hash_hmac('sha256', $jsonPayload, $bearerToken);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            return [
                'success' => ($httpCode >= 200 && $httpCode < 300),
                'http_code' => $httpCode,
                'url' => $url,
                'response' => $response ?: $error
            ];
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $jsonPayload,
                    'timeout' => 10,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500 Error';
            preg_match('#HTTP/\d\.\d (\d+)#', $statusLine, $matches);
            $httpCode = isset($matches[1]) ? (int)$matches[1] : 500;

            return [
                'success' => ($httpCode >= 200 && $httpCode < 300),
                'http_code' => $httpCode,
                'url' => $url,
                'response' => $response ?: ''
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'http_code' => 500
            ];
        }
    }
}
