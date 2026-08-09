<?php

use Illuminate\Support\Facades\Broadcast;

// A user may only listen on their own private notification channel.
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
