<?php

namespace ZkTeco\Push\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceHeartbeat
{
    use Dispatchable, SerializesModels;

    public string $deviceSn;
    public array $metadata;

    public function __construct(string $deviceSn, array $metadata)
    {
        $this->deviceSn = $deviceSn;
        $this->metadata = $metadata;
    }
}
