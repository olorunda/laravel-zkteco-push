<?php

namespace ZkTeco\Push\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ZkTeco\Push\ZkTecoPushMiddleware;
use ZkTeco\Push\Events\AttendancePushed;
use ZkTeco\Push\Events\DeviceHeartbeat;
use ZkTeco\Push\Events\CommandExecuted;

class ZkTecoPushController extends Controller
{
    private ZkTecoPushMiddleware $pushMiddleware;

    public function __construct(ZkTecoPushMiddleware $pushMiddleware)
    {
        $this->pushMiddleware = $pushMiddleware;

        // Dispatch Laravel Events automatically
        $this->pushMiddleware->on('attendance', function (array $records, string $deviceSn) {
            event(new AttendancePushed($records, $deviceSn));
        });

        $this->pushMiddleware->on('heartbeat', function (string $deviceSn, array $meta) {
            event(new DeviceHeartbeat($deviceSn, $meta));
        });

        $this->pushMiddleware->on('command_result', function (array $result, string $deviceSn) {
            event(new CommandExecuted($result, $deviceSn));
        });
    }

    /**
     * Handle incoming ZKTeco hardware ADMS requests (/iclock/cdata, /iclock/getrequest, etc.)
     */
    public function handleDevice(Request $request): Response
    {
        $res = $this->pushMiddleware->handleRequest(
            $request->getRequestUri(),
            $request->getMethod(),
            $request->query->all(),
            $request->getContent()
        );

        $response = response($res['body'], $res['status']);
        foreach ($res['headers'] as $k => $v) {
            $response->header($k, $v);
        }

        return $response;
    }

    /**
     * Handle REST JSON API requests from external client apps (/api/zkteco/*)
     */
    public function handleApi(Request $request): JsonResponse
    {
        $res = $this->pushMiddleware->handleRequest(
            $request->getRequestUri(),
            $request->getMethod(),
            $request->query->all(),
            $request->getContent()
        );

        $data = json_decode($res['body'], true) ?? [];
        return response()->json($data, $res['status']);
    }

    /**
     * Handle Admin Dashboard UI requests (/zkteco/admin)
     */
    public function handleAdmin(Request $request): Response
    {
        $res = $this->pushMiddleware->handleRequest(
            $request->getRequestUri(),
            $request->getMethod(),
            $request->query->all(),
            $request->getContent()
        );

        return response($res['body'], $res['status'])
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}
