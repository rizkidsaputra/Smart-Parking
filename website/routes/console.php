<?php

use App\Models\User;
use App\Services\Parking;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('parking:notify', function (Parking $p) {
    $p->notices();
    $this->info('Notifikasi diperbarui.');
});
Schedule::command('parking:notify')->everyMinute()->withoutOverlapping();
Artisan::command('parking:admin', function () {
    $email = $this->ask('Email admin');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
        $this->error('Email tidak valid atau sudah dipakai.');

        return 1;
    }
    $name = $this->ask('Nama admin');
    $password = $this->secret('Kata sandi minimal 8 karakter');
    if (! $name || strlen($password ?? '') < 8) {
        $this->error('Data tidak valid.');

        return 1;
    }
    $u = new User;
    $u->forceFill(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'admin'])->save();
    $this->info('Admin berhasil dibuat.');
});

Artisan::command('parking:thingspeak', function (Parking $parking) {
    $key = trim((string) config('parking.thingspeak_write_key'));
    if ($key === '') {
        $this->warn('ThingSpeak belum dikonfigurasi.');

        return 1;
    }

    if (! Cache::add('parking:thingspeak:write-window', true, 20)) {
        $this->warn('Tunggu 20 detik antar percobaan pengiriman.');

        return 1;
    }

    $stats = $parking->stats();

    try {
        $response = Http::connectTimeout(3)->timeout(5)
            ->withHeaders(['THINGSPEAKAPIKEY' => $key])
            ->post('https://api.thingspeak.com/update.json', [
                'field1' => $stats['occupied'],
                'field2' => $stats['available'],
                'field3' => $stats['entries'],
                'field4' => $stats['exits'],
            ]);
    } catch (ConnectionException) {
        $this->error('Koneksi ke ThingSpeak gagal. Pengiriman berikutnya akan memakai ringkasan terbaru.');

        return 1;
    }

    if (! $response->successful() || (int) $response->json('entry_id') < 1) {
        $this->error('ThingSpeak menolak pembaruan. Periksa Write API Key dan konfigurasi channel.');

        return 1;
    }

    $this->info('Ringkasan parkir TULT dikirim ke ThingSpeak.');

    return 0;
})->purpose('Kirim ringkasan parkir TULT ke ThingSpeak');

Schedule::command('parking:thingspeak')->everyMinute()->withoutOverlapping()
    ->when(fn () => filled(config('parking.thingspeak_write_key')));
