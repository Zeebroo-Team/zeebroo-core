<?php

use Illuminate\Support\Facades\Route;
use Modules\AIBot\Http\Controllers\AIBotChatController;
use Modules\AIBot\Http\Controllers\AIBotController;
use Modules\AIBot\Http\Controllers\AIBotConversationController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('ai-bot', [AIBotController::class, 'index'])->name('aibot.index');

    Route::post('ai-bot/chat', AIBotChatController::class)
        ->middleware('throttle:30,1')
        ->name('aibot.chat');

    Route::get('ai-bot/conversations', [AIBotConversationController::class, 'index'])->name('aibot.conversations.index');
    Route::get('ai-bot/conversations/{conversation}', [AIBotConversationController::class, 'show'])
        ->whereNumber('conversation')
        ->name('aibot.conversations.show');
});
