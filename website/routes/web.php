<?php

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ParkirController as P;
use App\Http\Controllers\WebproController as W;
use Illuminate\Support\Facades\Route;

Route::get('/area-parkir', [W::class, 'overview'])->name('areas.overview');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [P::class, 'login'])->middleware('throttle:6,1');
});
Route::middleware(['auth', 'enabled'])->group(function () {
    Route::get('/', [P::class, 'dashboard'])->name('dashboard');
    Route::get('/status', [P::class, 'stats']);
    Route::get('/riwayat', [P::class, 'history']);
    Route::post('/logout', [P::class, 'logout']);
    Route::post('/notifikasi/{id}/baca', [P::class, 'readNotice']);
    Route::get('/denda', [W::class, 'fines'])->name('fines.index');
    Route::middleware('admin')->group(function () {
        Route::get('/kelola-area', [W::class, 'manage'])->name('areas.manage');
        Route::post('/kelola-area/{area?}', [W::class, 'saveArea'])->whereNumber('area');
        Route::delete('/kelola-area/{area}', [W::class, 'deleteArea'])->whereNumber('area');
        Route::post('/kelola-area/{area}/slot/{slot?}', [W::class, 'saveSlot'])->whereNumber(['area', 'slot']);
        Route::delete('/kelola-area/{area}/slot/{slot}', [W::class, 'deleteSlot'])->whereNumber(['area', 'slot']);
        Route::post('/denda/{fine?}', [W::class, 'saveFine'])->whereNumber('fine');
        Route::delete('/denda/{fine}', [W::class, 'deleteFine'])->whereNumber('fine');
        Route::get('/mahasiswa', [P::class, 'students']);
        Route::post('/mahasiswa', [P::class, 'saveStudent']);
        Route::put('/mahasiswa/{id}', [P::class, 'saveStudent']);
        Route::post('/pengaturan', [P::class, 'settings']);
        Route::get('/perangkat', [DeviceController::class, 'index']);
        Route::post('/perangkat/{id}/resolve', [DeviceController::class, 'resolve']);
        Route::post('/sesi/{id}/keluar-manual', [DeviceController::class, 'manualExit']);
    });
});
