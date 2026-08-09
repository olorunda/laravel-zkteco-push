<?php

namespace ZkTeco\Push\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommandExecuted
{
    use Dispatchable, SerializesModels;

    public array $result;
    public string $deviceSn;

    public function __construct(array $result, string $deviceSn)
    {
        $this->result = $result;
        $this->deviceSn = $deviceSn;
    }
}
