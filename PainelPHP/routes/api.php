<?php

use App\Http\Controllers\Api\V1\Agent\CommandStatusController;
use App\Http\Controllers\Api\V1\Agent\DisplayStateController;
use App\Http\Controllers\Api\V1\Agent\HeartbeatController;
use App\Http\Controllers\Api\V1\Agent\MediaDownloadController;
use App\Http\Controllers\Api\V1\Agent\NextCommandController;
use App\Http\Controllers\Api\V1\Agent\RegistrationController;
use App\Http\Controllers\Api\V1\DeviceCommandController;
use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware(['web', 'auth:web'])->group(function (): void {
        Route::get('/devices', [DeviceController::class, 'index']);
        Route::get('/devices/{device}', [DeviceController::class, 'show']);
        Route::get('/devices/{device}/commands', [DeviceCommandController::class, 'index']);
        Route::post('/devices/{device}/commands', [DeviceCommandController::class, 'store']);
        Route::get('/devices/{device}/commands/{deviceCommand}', [DeviceCommandController::class, 'show']);
    });

    Route::post('/agent/register', RegistrationController::class)->middleware('throttle:10,1');

    Route::prefix('agent')->middleware(['device.auth', 'throttle:120,1'])->group(function (): void {
        Route::post('/heartbeat', HeartbeatController::class);
        Route::post('/display-state', DisplayStateController::class);
        Route::get('/commands/next', NextCommandController::class);
        Route::post('/commands/{deviceCommand}/started', [CommandStatusController::class, 'started']);
        Route::post('/commands/{deviceCommand}/completed', [CommandStatusController::class, 'completed']);
        Route::post('/commands/{deviceCommand}/failed', [CommandStatusController::class, 'failed']);
        Route::get('/media/{mediaVariant}/download', MediaDownloadController::class)->name('agent.media.download');
    });
});
