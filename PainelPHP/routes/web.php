<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CampaignPublicationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayProfileController;
use App\Http\Controllers\MediaApprovalController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaNormalizationController;
use App\Http\Controllers\ScreenCompositionPublicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::post('/display-profiles', [DisplayProfileController::class, 'store'])->name('display-profiles.store');
    Route::get('/media/{mediaAsset}/preview', [MediaController::class, 'preview'])->name('media.preview');
    Route::delete('/media/{mediaAsset}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('/media/{mediaAsset}/normalize', MediaNormalizationController::class)->name('media.normalize');
    Route::post('/media-variants/{mediaVariant}/approve', MediaApprovalController::class)->name('media.approve');
    Route::post('/media-variants/{mediaVariant}/publish', CampaignPublicationController::class)->name('campaigns.publish');
    Route::post('/campaigns/compose-and-publish', ScreenCompositionPublicationController::class)->name('campaigns.compose-publish');
});
