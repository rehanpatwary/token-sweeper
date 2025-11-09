<?php

namespace Multicoin\TokenSweeper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Multicoin\TokenSweeper\Models\PendingSweep;

class SweepStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(public PendingSweep $sweep) {}
}
