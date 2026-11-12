<?php

namespace Multicoin\TokenSweeper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Multicoin\TokenSweeper\Models\PendingSweep;

class SweepCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public PendingSweep $sweep)
    {
    }
}
