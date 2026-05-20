<?php

use Illuminate\Support\Facades\Route;
use Modules\AIBot\Http\Controllers\AIBotChatController;
use Modules\AIBot\Http\Controllers\AIBotController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('ai-bot', [AIBotController::class, 'index'])->name('aibot.index');

    Route::post('ai-bot/chat', AIBotChatController::class)
        ->middleware('throttle:30,1')
        ->name('aibot.chat');
});
