<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Device
{
    public function handle(Request $r, Closure $next)
    {
        $token = config('parking.device_token');
        abort_unless($token && hash_equals($token, $r->bearerToken() ?? ''), 401);
        DB::table('parking_settings')->where('id', 1)->update(['last_seen_at' => now()]);

        return $next($r);
    }
}
