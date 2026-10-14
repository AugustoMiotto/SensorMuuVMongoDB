<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeSensorData implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $pitch;
    public $roll;
    public $uuid;
    public $is_moving;
    public $is_ruminating;

    public function __construct($uuid, $pitch, $roll, $is_moving = false, $is_ruminating = false)
    {
        $this->uuid = $uuid;
        $this->pitch = $pitch;
        $this->roll = $roll;
        $this->is_moving = $is_moving;
        $this->is_ruminating = $is_ruminating;
    }

    public function broadcastOn()
    {
        return new Channel('cow.' . $this->uuid);
    }

    public function broadcastAs()
    {
        return 'sensor.update';
    }
}
