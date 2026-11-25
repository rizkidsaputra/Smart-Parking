<?php

use App\Services\Parking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['device', 'throttle:120,1'])->prefix('device')->group(function () {
    Route::post('/heartbeat', fn () => ['ok' => true, 'server_time' => now()->toIso8601String()]);
    Route::post('/authorize', function (Request $r, Parking $p) {
        $v = $r->validate(['uid' => 'required|string|max:40', 'direction' => 'required|in:entry,exit']);

        return $p->authorize($v['uid'], $v['direction']);
    });
    Route::post('/confirm', function (Request $r, Parking $p) {
        $v = $r->validate(['transaction_id' => 'required|uuid', 'outcome' => 'required|in:passed,cancelled']);

        return $p->finish($v['transaction_id'], $v['outcome']);
    });
});
