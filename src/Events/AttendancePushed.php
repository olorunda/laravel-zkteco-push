<?php

namespace ZkTeco\Push\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendancePushed
{
    use Dispatchable, SerializesModels;

    public array $records;
    public string $deviceSn;

    public function __construct(array $records, string $deviceSn)
    {
        $this->records = $records;
        $this->deviceSn = $deviceSn;
    }
}
