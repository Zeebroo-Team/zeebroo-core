<?php

use Illuminate\Support\Facades\Route;
use Modules\Mail\Http\Controllers\Admin\AdminEmailMarketingController;
use Modules\Mail\Http\Controllers\InboxController;
use Modules\Mail\Http\Controllers\MarketingUnsubscribeController;
use Modules\Mail\Http\Controllers\MailFilterController;
use Modules\Mail\Http\Controllers\MailScheduledController;
use Modules\Mail\Http\Controllers\MailSettingsController;
use Modules\Mail\Http\Controllers\MailTemplateController;

// Admin — platform email marketing: reusable templates + bulk sends to Zeebroo users.
Route::middleware(['web', 'auth', 'role:admin'])->prefix('admin/email-marketing')->name('admin.email-marketing.')->group(function (): void {
    Route::get('/', [AdminEmailMarketingController::class, 'index'])->name('index');

    Route::get('/templates/create', [AdminEmailMarketingController::class, 'createTemplate'])->name('templates.create');
    Route::post('/templates', [AdminEmailMarketingController::class, 'storeTemplate'])->name('templates.store');
    Route::get('/templates/{template}/edit', [AdminEmailMarketingController::class, 'editTemplate'])->name('templates.edit');
    Route::put('/templates/{template}', [AdminEmailMarketingController::class, 'updateTemplate'])->name('templates.update');
    Route::delete('/templates/{template}', [AdminEmailMarketingController::class, 'destroyTemplate'])->name('templates.destroy');

    Route::get('/send', [AdminEmailMarketingController::class, 'compose'])->name('compose');
    Route::post('/send', [AdminEmailMarketingController::class, 'send'])->name('send');
    Route::post('/send-test', [AdminEmailMarketingController::class, 'sendTest'])->name('send-test');

    Route::get('/campaigns/{campaign}', [AdminEmailMarketingController::class, 'showCampaign'])->name('campaigns.show');
    Route::post('/campaigns/{campaign}/retry', [AdminEmailMarketingController::class, 'retryCampaign'])->name('campaigns.retry');
});

// Public, signed unsubscribe link included in every marketing email.
Route::middleware(['web', 'signed', 'throttle:30,1'])->group(function (): void {
    Route::get('/marketing/unsubscribe/{user}', [MarketingUnsubscribeController::class, 'show'])->name('marketing.unsubscribe');
    Route::post('/marketing/unsubscribe/{user}', [MarketingUnsubscribeController::class, 'store'])->name('marketing.unsubscribe.store');
});

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/settings/mail', [MailSettingsController::class, 'edit'])->name('mail.settings.edit');
    Route::put('/settings/mail', [MailSettingsController::class, 'update'])->name('mail.settings.update');
    Route::post('/settings/mail/test', [MailSettingsController::class, 'sendTest'])->name('mail.settings.test');
    Route::post('/settings/mail/verify', [MailSettingsController::class, 'verifyCredentials'])->name('mail.settings.verify');
    Route::post('/settings/mail/mailbox', [MailSettingsController::class, 'connectMailbox'])->name('mail.settings.mailbox.connect');
    Route::delete('/settings/mail/mailbox', [MailSettingsController::class, 'disconnectMailbox'])->name('mail.settings.mailbox.disconnect');
    Route::post('/settings/mail/mailbox/sync', [MailSettingsController::class, 'syncMailboxNow'])->name('mail.settings.mailbox.sync');
    Route::post('/settings/mail/domain', [MailSettingsController::class, 'autoConfigureDomain'])->name('mail.settings.domain.configure');
    Route::post('/settings/mail/domain/status', [MailSettingsController::class, 'checkDomainStatus'])->name('mail.settings.domain.status');

    // Static /mail/* routes must be registered before the /mail/{message} catch-one
    // below, otherwise Laravel's route-model-binding would swallow them.
    Route::get('/mail/compose', [InboxController::class, 'compose'])->name('mail.inbox.compose');
    Route::post('/mail/send', [InboxController::class, 'send'])->name('mail.inbox.send');

    Route::get('/mail/templates', [MailTemplateController::class, 'index'])->name('mail.templates.index');
    Route::post('/mail/templates', [MailTemplateController::class, 'store'])->name('mail.templates.store');
    Route::put('/mail/templates/{template}', [MailTemplateController::class, 'update'])->name('mail.templates.update');
    Route::delete('/mail/templates/{template}', [MailTemplateController::class, 'destroy'])->name('mail.templates.destroy');

    Route::get('/mail/filters', [MailFilterController::class, 'index'])->name('mail.filters.index');
    Route::post('/mail/filters', [MailFilterController::class, 'store'])->name('mail.filters.store');
    Route::post('/mail/filters/reorder', [MailFilterController::class, 'reorder'])->name('mail.filters.reorder');
    Route::put('/mail/filters/{filter}', [MailFilterController::class, 'update'])->name('mail.filters.update');
    Route::delete('/mail/filters/{filter}', [MailFilterController::class, 'destroy'])->name('mail.filters.destroy');

    Route::get('/mail/scheduled', [MailScheduledController::class, 'index'])->name('mail.scheduled.index');
    Route::delete('/mail/scheduled/{scheduled}', [MailScheduledController::class, 'cancel'])->name('mail.scheduled.cancel');

    Route::get('/mail', [InboxController::class, 'index'])->name('mail.inbox.index');
    Route::get('/mail/{message}', [InboxController::class, 'show'])->name('mail.inbox.show');
    Route::post('/mail/{message}/convert-to-customer', [InboxController::class, 'convertToCustomer'])->name('mail.inbox.convertToCustomer');
    Route::post('/mail/{message}/ai-summary', [InboxController::class, 'aiSummary'])->name('mail.inbox.aiSummary');
    Route::post('/mail/{message}/ai-suggest-reply', [InboxController::class, 'aiSuggestReply'])->name('mail.inbox.aiSuggestReply');
    Route::post('/mail/{message}/assign', [InboxController::class, 'assignConversation'])->name('mail.inbox.assign');
});
