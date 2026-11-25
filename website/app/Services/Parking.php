<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Parking
{
    public function stats(): array
    {
        $setting = DB::table('parking_settings')->find(1);
        $occupied = DB::table('parking_sessions')->whereNull('exited_at')->count();

        return ['capacity' => $setting->capacity, 'occupied' => $occupied, 'available' => max(0, $setting->capacity - $occupied), 'online' => $setting->last_seen_at && now()->diffInSeconds(Carbon::parse($setting->last_seen_at), true) < 45, 'entries' => DB::table('parking_sessions')->whereDate('entered_at', today())->count(), 'exits' => DB::table('parking_sessions')->whereDate('exited_at', today())->count()];
    }

    public function authorize(string $uid, string $direction): array
    {
        return DB::transaction(function () use ($uid, $direction) {
            $settings = DB::table('parking_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('gate_transactions')->where('state', 'pending')->where('expires_at', '<=', now())->update(['state' => 'expired']);
            $user = DB::table('users')->where('uid', strtoupper($uid))->where('role', 'student')->first();
            $this->require($user && $user->enabled && $user->plate, 'KTM tidak terdaftar atau dinonaktifkan.');
            $this->require(! DB::table('gate_transactions')->where('state', 'pending')->exists(), 'Palang sedang melayani kendaraan.');
            $active = DB::table('parking_sessions')->where('user_id', $user->id)->whereNull('exited_at')->exists();
            if ($direction === 'entry') {
                $this->require(! $active, 'Kendaraan masih tercatat parkir.');
                $this->require(DB::table('parking_sessions')->whereNull('exited_at')->count() < $settings->capacity, 'Parkir penuh.');
            } else {
                $this->require($active, 'Tidak ada sesi parkir aktif.');
            }
            $id = (string) Str::uuid();
            DB::table('gate_transactions')->insert(['id' => $id, 'user_id' => $user->id, 'direction' => $direction, 'state' => 'pending', 'expires_at' => now()->addSeconds(15), 'created_at' => now(), 'updated_at' => now()]);

            return ['allowed' => true, 'transaction_id' => $id, 'expires_in' => 15];
        });
    }

    public function finish(string $id, string $outcome): array
    {
        return DB::transaction(function () use ($id, $outcome) {
            DB::table('parking_settings')->where('id', 1)->lockForUpdate()->first();
            $tx = DB::table('gate_transactions')->where('id', $id)->lockForUpdate()->first();
            $this->require((bool) $tx, 'Transaksi tidak ditemukan.');
            if ($tx->state === 'confirmed') {
                return ['ok' => true, 'duplicate' => true];
            }
            if ($tx->state === 'cancelled' && $outcome === 'cancelled') {
                return ['ok' => true, 'duplicate' => true];
            }
            $this->require($tx->state === 'pending', 'Transaksi sudah berakhir; perlu rekonsiliasi petugas.');
            if ($outcome === 'cancelled') {
                DB::table('gate_transactions')->where('id', $id)->update(['state' => 'cancelled', 'updated_at' => now()]);

                return ['ok' => true];
            }
            $this->require(now()->lte($tx->expires_at), 'Konfirmasi terlambat; perlu rekonsiliasi petugas.');
            $user = DB::table('users')->find($tx->user_id);
            if ($tx->direction === 'entry') {
                DB::table('parking_sessions')->insert(['user_id' => $user->id, 'plate' => $user->plate, 'uid' => $user->uid, 'entered_at' => now(), 'entry_transaction' => $id]);
            } else {
                DB::table('parking_sessions')->where('user_id', $user->id)->whereNull('exited_at')->update(['exited_at' => now(), 'exit_transaction' => $id]);
            }
            DB::table('gate_transactions')->where('id', $id)->update(['state' => 'confirmed', 'updated_at' => now()]);
            $this->notices();

            return ['ok' => true];
        });
    }

    public function notices(): void
    {
        // Backfill each crossed 22:00 boundary, including visits between scheduler runs.
        foreach (DB::table('parking_sessions')->cursor() as $s) {
            $end = $s->exited_at ? Carbon::parse($s->exited_at) : now();
            $boundary = Carbon::parse($s->entered_at)->startOfDay()->setHour(22);
            while ($boundary->lte($end)) {
                DB::table('parking_notices')->insertOrIgnore(['user_id' => $s->user_id, 'parking_session_id' => $s->id, 'night' => $boundary->toDateString(), 'created_at' => $boundary, 'updated_at' => now()]);
                $boundary->addDay();
            }
        }
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['parking' => $message]);
        }
    }
}
