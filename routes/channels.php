<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('dgu-chatroom', function () {
    return auth()->check();
});
