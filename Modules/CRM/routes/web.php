<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\ActivityController;
use Modules\CRM\Http\Controllers\ContactController;
use Modules\CRM\Http\Controllers\CrmImageController;
use Modules\CRM\Http\Controllers\LeadController;
use Modules\CRM\Http\Controllers\LeadCustomFieldController;
use Modules\CRM\Http\Controllers\LeadFormController;
use Modules\CRM\Http\Controllers\LeadStageAutomationController;
use Modules\CRM\Http\Controllers\LeadStageController;
use Modules\CRM\Http\Controllers\ProjectController;
use Modules\CRM\Http\Controllers\PublicLeadFormController;
use Modules\CRM\Http\Controllers\TaskController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/crm/projects',                  [ProjectController::class, 'index'])     ->name('crm.projects.index');
    Route::post('/crm/projects',                 [ProjectController::class, 'store'])     ->name('crm.projects.store');
    Route::get('/crm/projects/{project}',        [ProjectController::class, 'show'])       ->name('crm.projects.show');
    Route::get('/crm/projects/{project}/edit',   [ProjectController::class, 'edit'])       ->name('crm.projects.edit');
    Route::put('/crm/projects/{project}',        [ProjectController::class, 'update'])    ->name('crm.projects.update');
    Route::post('/crm/projects/{project}/archive',    [ProjectController::class, 'archive'])    ->name('crm.projects.archive');
    Route::post('/crm/projects/{project}/reactivate', [ProjectController::class, 'reactivate'])->name('crm.projects.reactivate');
    Route::delete('/crm/projects/{project}',     [ProjectController::class, 'destroy'])   ->name('crm.projects.destroy');

    Route::get('/crm/projects/{project}/leads',          [LeadController::class, 'index']) ->name('crm.projects.leads.index');
    Route::get('/crm/projects/{project}/leads/board',    [LeadController::class, 'board']) ->name('crm.projects.leads.board');
    Route::post('/crm/projects/{project}/leads',         [LeadController::class, 'store']) ->name('crm.projects.leads.store');

    Route::get('/crm/projects/{project}/stages',              [LeadStageController::class, 'index']  )->name('crm.projects.stages.index');
    Route::post('/crm/projects/{project}/stages',             [LeadStageController::class, 'store']  )->name('crm.projects.stages.store');
    Route::post('/crm/projects/{project}/stages/reorder',     [LeadStageController::class, 'reorder'])->name('crm.projects.stages.reorder');
    Route::put('/crm/projects/{project}/stages/{stage}',      [LeadStageController::class, 'update'] )->name('crm.projects.stages.update');
    Route::delete('/crm/projects/{project}/stages/{stage}',   [LeadStageController::class, 'destroy'])->name('crm.projects.stages.destroy');

    Route::get('/crm/projects/{project}/stages/{stage}/automations',                  [LeadStageAutomationController::class, 'index']  )->name('crm.projects.stages.automations.index');
    Route::post('/crm/projects/{project}/stages/{stage}/automations',                 [LeadStageAutomationController::class, 'store']  )->name('crm.projects.stages.automations.store');
    Route::put('/crm/projects/{project}/stages/{stage}/automations/{automation}',     [LeadStageAutomationController::class, 'update'] )->name('crm.projects.stages.automations.update');
    Route::delete('/crm/projects/{project}/stages/{stage}/automations/{automation}',  [LeadStageAutomationController::class, 'destroy'])->name('crm.projects.stages.automations.destroy');

    Route::get('/crm/projects/{project}/custom-fields',                 [LeadCustomFieldController::class, 'index']  )->name('crm.projects.custom-fields.index');
    Route::post('/crm/projects/{project}/custom-fields',                [LeadCustomFieldController::class, 'store']  )->name('crm.projects.custom-fields.store');
    Route::post('/crm/projects/{project}/custom-fields/reorder',        [LeadCustomFieldController::class, 'reorder'])->name('crm.projects.custom-fields.reorder');
    Route::put('/crm/projects/{project}/custom-fields/{customField}',   [LeadCustomFieldController::class, 'update'] )->name('crm.projects.custom-fields.update');
    Route::delete('/crm/projects/{project}/custom-fields/{customField}', [LeadCustomFieldController::class, 'destroy'])->name('crm.projects.custom-fields.destroy');

    Route::get('/crm/projects/{project}/forms',                [LeadFormController::class, 'index']    )->name('crm.projects.forms.index');
    Route::post('/crm/projects/{project}/forms',               [LeadFormController::class, 'store']    )->name('crm.projects.forms.store');
    Route::get('/crm/projects/{project}/forms/{form}/builder', [LeadFormController::class, 'builder']  )->name('crm.projects.forms.builder');
    Route::put('/crm/projects/{project}/forms/{form}',         [LeadFormController::class, 'update']   )->name('crm.projects.forms.update');
    Route::post('/crm/projects/{project}/forms/{form}/publish',   [LeadFormController::class, 'publish']  )->name('crm.projects.forms.publish');
    Route::post('/crm/projects/{project}/forms/{form}/unpublish', [LeadFormController::class, 'unpublish'])->name('crm.projects.forms.unpublish');
    Route::delete('/crm/projects/{project}/forms/{form}',      [LeadFormController::class, 'destroy']   )->name('crm.projects.forms.destroy');

    Route::get('/crm/images/picker',    [CrmImageController::class, 'picker']  )->name('crm.images.picker');
    Route::post('/crm/images/upload',   [CrmImageController::class, 'upload']  )->name('crm.images.upload');
    Route::post('/crm/images/generate', [CrmImageController::class, 'generate'])->name('crm.images.generate');

    Route::get('/crm/leads/{lead}',             [LeadController::class, 'show'])        ->name('crm.leads.show');
    Route::get('/crm/leads/{lead}/edit',        [LeadController::class, 'edit'])        ->name('crm.leads.edit');
    Route::put('/crm/leads/{lead}',             [LeadController::class, 'update'])      ->name('crm.leads.update');
    Route::post('/crm/leads/{lead}/move-stage', [LeadController::class, 'moveStage'])   ->name('crm.leads.move-stage');
    Route::post('/crm/leads/{lead}/convert',    [LeadController::class, 'convert'])     ->name('crm.leads.convert');
    Route::post('/crm/leads/{lead}/mark-lost',  [LeadController::class, 'markLost'])    ->name('crm.leads.mark-lost');
    Route::post('/crm/leads/{lead}/reopen',     [LeadController::class, 'reopen'])      ->name('crm.leads.reopen');
    Route::delete('/crm/leads/{lead}',          [LeadController::class, 'destroy'])     ->name('crm.leads.destroy');

    Route::get('/crm/contacts',                 [ContactController::class, 'index'])    ->name('crm.contacts.index');
    Route::get('/crm/contacts/{customer}',      [ContactController::class, 'show'])     ->name('crm.contacts.show');

    Route::post('/crm/activities',              [ActivityController::class, 'store'])   ->name('crm.activities.store');
    Route::delete('/crm/activities/{activity}', [ActivityController::class, 'destroy']) ->name('crm.activities.destroy');

    Route::get('/crm/tasks',                    [TaskController::class, 'index'])       ->name('crm.tasks.index');
    Route::post('/crm/tasks',                   [TaskController::class, 'store'])       ->name('crm.tasks.store');
    Route::put('/crm/tasks/{task}',             [TaskController::class, 'update'])      ->name('crm.tasks.update');
    Route::post('/crm/tasks/{task}/complete',   [TaskController::class, 'complete'])    ->name('crm.tasks.complete');
    Route::post('/crm/tasks/{task}/reopen',     [TaskController::class, 'reopen'])      ->name('crm.tasks.reopen');
    Route::delete('/crm/tasks/{task}',          [TaskController::class, 'destroy'])     ->name('crm.tasks.destroy');
});

// Public lead-capture form pages — no auth, reachable by anyone with the link.
Route::middleware(['web'])->group(function () {
    Route::get('/f/{token}',  [PublicLeadFormController::class, 'show'])  ->name('crm.public-forms.show');
    Route::post('/f/{token}', [PublicLeadFormController::class, 'submit'])->name('crm.public-forms.submit');
});
