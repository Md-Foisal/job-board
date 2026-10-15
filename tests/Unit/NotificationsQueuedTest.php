<?php

use Illuminate\Contracts\Queue\ShouldQueue;

arch('every notification goes through the queue, so no page waits on the mail provider')
    ->expect('App\Notifications')
    ->toImplement(ShouldQueue::class);
