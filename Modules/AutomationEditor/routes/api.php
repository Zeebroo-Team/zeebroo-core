<?php

use Illuminate\Support\Facades\Route;
use Modules\AutomationEditor\Http\Controllers\Api\AutomationFlowApiController;

Route::middleware(['auth:sanctum'])->prefix('v1/pos')->name('pos.')->group(function () {
    Route::get   ('automations',              [AutomationFlowApiController::class, 'index'])->name('automations.index');
    Route::post  ('automations',              [AutomationFlowApiController::class, 'store'])->name('automations.store');
    Route::get   ('automations/{id}',         [AutomationFlowApiController::class, 'show'])->where('id', '[0-9]+')->name('automations.show');
    Route::patch ('automations/{id}',         [AutomationFlowApiController::class, 'update'])->where('id', '[0-9]+')->name('automations.update');
    Route::delete('automations/{id}',         [AutomationFlowApiController::class, 'destroy'])->where('id', '[0-9]+')->name('automations.destroy');
    Route::get   ('automations/{id}/runs',    [AutomationFlowApiController::class, 'runs'])->where('id', '[0-9]+')->name('automations.runs');
    Route::post  ('automations/{id}/trigger', [AutomationFlowApiController::class, 'triggerManual'])->where('id', '[0-9]+')->name('automations.trigger');
    Route::get   ('automation-notifications',           [AutomationFlowApiController::class, 'notifications'])->name('automation-notifications.index');
    Route::post  ('automation-notifications/read-all',  [AutomationFlowApiController::class, 'markNotificationsRead'])->name('automation-notifications.read-all');
});
