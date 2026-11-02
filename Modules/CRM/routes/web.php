<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\ActivityController;
use Modules\CRM\Http\Controllers\ContactController;
use Modules\CRM\Http\Controllers\CrmAuditLogController;
use Modules\CRM\Http\Controllers\CrmImageController;
use Modules\CRM\Http\Controllers\CrmOrgController;
use Modules\CRM\Http\Controllers\CustomerImportController;
use Modules\CRM\Http\Controllers\LeadController;
use Modules\CRM\Http\Controllers\LeadCustomFieldController;
use Modules\CRM\Http\Controllers\LeadFormController;
use Modules\CRM\Http\Controllers\LeadImportController;
use Modules\CRM\Http\Controllers\LeadStageController;
use Modules\CRM\Http\Controllers\LeadWorkspaceController;
use Modules\CRM\Http\Controllers\MarketingInboxController;
use Modules\CRM\Http\Controllers\OverviewController;
use Modules\CRM\Http\Controllers\ProjectController;
use Modules\CRM\Http\Controllers\PublicLeadFormController;
use Modules\CRM\Http\Controllers\TaskController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/crm',                            [OverviewController::class, 'index'])   ->name('crm.overview');

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
    Route::get('/crm/projects/{project}/custom-data',    [LeadController::class, 'customData'])->name('crm.projects.custom-data.index');
    Route::post('/crm/projects/{project}/leads',         [LeadController::class, 'store']) ->name('crm.projects.leads.store');

    Route::get('/crm/projects/{project}/stages',              [LeadStageController::class, 'index']  )->name('crm.projects.stages.index');
    Route::post('/crm/projects/{project}/stages',             [LeadStageController::class, 'store']  )->name('crm.projects.stages.store');
    Route::post('/crm/projects/{project}/stages/reorder',     [LeadStageController::class, 'reorder'])->name('crm.projects.stages.reorder');
    Route::put('/crm/projects/{project}/stages/{stage}',      [LeadStageController::class, 'update'] )->name('crm.projects.stages.update');
    Route::delete('/crm/projects/{project}/stages/{stage}',   [LeadStageController::class, 'destroy'])->name('crm.projects.stages.destroy');
    Route::post('/crm/projects/{project}/stages/automation',  [LeadStageController::class, 'createAutomation'])->name('crm.projects.stages.automation');

    Route::get('/crm/projects/{project}/custom-fields',                 [LeadCustomFieldController::class, 'index']  )->name('crm.projects.custom-fields.index');
    Route::post('/crm/projects/{project}/custom-fields',                [LeadCustomFieldController::class, 'store']  )->name('crm.projects.custom-fields.store');
    Route::post('/crm/projects/{project}/custom-fields/reorder',        [LeadCustomFieldController::class, 'reorder'])->name('crm.projects.custom-fields.reorder');
    Route::put('/crm/projects/{project}/custom-fields/{customField}',   [LeadCustomFieldController::class, 'update'] )->name('crm.projects.custom-fields.update');
    Route::delete('/crm/projects/{project}/custom-fields/{customField}', [LeadCustomFieldController::class, 'destroy'])->name('crm.projects.custom-fields.destroy');

    Route::get('/crm/projects/{project}/forms',                [LeadFormController::class, 'index']    )->name('crm.projects.forms.index');
    Route::post('/crm/projects/{project}/forms',               [LeadFormController::class, 'store']    )->name('crm.projects.forms.store');
    Route::get('/crm/projects/{project}/forms/{form}/builder', [LeadFormController::class, 'builder']  )->name('crm.projects.forms.builder');
    Route::put('/crm/projects/{project}/forms/{form}',         [LeadFormController::class, 'update']   )->name('crm.projects.forms.update');
    Route::post('/crm/projects/{project}/forms/{form}/default',   [LeadFormController::class, 'toggleDefault'])->name('crm.projects.forms.default');
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
    Route::post('/crm/leads/{lead}/hold',       [LeadController::class, 'hold'])        ->name('crm.leads.hold');
    Route::post('/crm/leads/{lead}/resume',     [LeadController::class, 'resume'])      ->name('crm.leads.resume');
    Route::delete('/crm/leads/{lead}',          [LeadController::class, 'destroy'])     ->name('crm.leads.destroy');

    // Lead workspace: assignees, internal messages, files
    Route::post('/crm/leads/{lead}/assignees',                  [LeadWorkspaceController::class, 'syncAssignees'])     ->name('crm.leads.assignees.sync');
    Route::delete('/crm/leads/{lead}/assignees/{user}',         [LeadWorkspaceController::class, 'removeAssignee'])    ->whereNumber('user')->name('crm.leads.assignees.destroy');
    Route::post('/crm/leads/{lead}/assignees/{user}/primary',   [LeadWorkspaceController::class, 'primaryAssignee'])   ->whereNumber('user')->name('crm.leads.assignees.primary');
    Route::post('/crm/leads/{lead}/messages',                   [LeadWorkspaceController::class, 'storeMessage'])      ->name('crm.leads.messages.store');
    Route::put('/crm/leads/{lead}/messages/{message}',          [LeadWorkspaceController::class, 'updateMessage'])     ->whereNumber('message')->name('crm.leads.messages.update');
    Route::delete('/crm/leads/{lead}/messages/{message}',       [LeadWorkspaceController::class, 'destroyMessage'])    ->whereNumber('message')->name('crm.leads.messages.destroy');
    Route::post('/crm/leads/{lead}/attachments',                [LeadWorkspaceController::class, 'storeAttachments'])  ->name('crm.leads.attachments.store');
    Route::get('/crm/leads/{lead}/attachments/{attachment}',    [LeadWorkspaceController::class, 'downloadAttachment'])->whereNumber('attachment')->name('crm.leads.attachments.download');
    Route::delete('/crm/leads/{lead}/attachments/{attachment}', [LeadWorkspaceController::class, 'destroyAttachment']) ->whereNumber('attachment')->name('crm.leads.attachments.destroy');

    Route::get('/crm/audit',                    [CrmAuditLogController::class, 'index']) ->name('crm.audit.index');

    Route::get('/crm/contacts',                 [ContactController::class, 'index'])    ->name('crm.contacts.index');

    // Customer import: upload → preview (skip / update duplicates) → run → rows not imported
    Route::get('/crm/contacts/import',                  [CustomerImportController::class, 'index'])   ->name('crm.contacts.import.index');
    Route::get('/crm/contacts/import/template',         [CustomerImportController::class, 'template'])->name('crm.contacts.import.template');
    Route::post('/crm/contacts/import',                 [CustomerImportController::class, 'store'])   ->name('crm.contacts.import.store');
    Route::get('/crm/contacts/import/{token}',          [CustomerImportController::class, 'preview']) ->name('crm.contacts.import.preview');
    Route::post('/crm/contacts/import/{token}/run',     [CustomerImportController::class, 'run'])     ->name('crm.contacts.import.run');
    Route::get('/crm/contacts/import/{token}/result',   [CustomerImportController::class, 'result'])  ->name('crm.contacts.import.result');
    Route::get('/crm/contacts/import/{token}/errors',   [CustomerImportController::class, 'errors'])  ->name('crm.contacts.import.errors');
    Route::delete('/crm/contacts/import/{token}',       [CustomerImportController::class, 'destroy']) ->name('crm.contacts.import.destroy');

    Route::get('/crm/contacts/{customer}',      [ContactController::class, 'show'])     ->name('crm.contacts.show');

    Route::post('/crm/activities',              [ActivityController::class, 'store'])   ->name('crm.activities.store');
    Route::delete('/crm/activities/{activity}', [ActivityController::class, 'destroy']) ->name('crm.activities.destroy');

    Route::get('/crm/tasks',                    [TaskController::class, 'index'])       ->name('crm.tasks.index');
    Route::post('/crm/tasks',                   [TaskController::class, 'store'])       ->name('crm.tasks.store');
    Route::put('/crm/tasks/{task}',             [TaskController::class, 'update'])      ->name('crm.tasks.update');
    Route::post('/crm/tasks/{task}/complete',   [TaskController::class, 'complete'])    ->name('crm.tasks.complete');
    Route::post('/crm/tasks/{task}/reopen',     [TaskController::class, 'reopen'])      ->name('crm.tasks.reopen');
    Route::delete('/crm/tasks/{task}',          [TaskController::class, 'destroy'])     ->name('crm.tasks.destroy');

    Route::get('/crm/inbox',                     [MarketingInboxController::class, 'index'])  ->name('crm.inbox.index');
    Route::put('/crm/inbox/{lead}',              [MarketingInboxController::class, 'update']) ->name('crm.inbox.update');
    Route::post('/crm/inbox/{lead}/verify',      [MarketingInboxController::class, 'verify']) ->name('crm.inbox.verify');
    Route::post('/crm/inbox/{lead}/review',      [MarketingInboxController::class, 'review']) ->name('crm.inbox.review');
    Route::post('/crm/inbox/{lead}/forward',     [MarketingInboxController::class, 'forward'])->name('crm.inbox.forward');
    Route::post('/crm/inbox/{lead}/merge',       [MarketingInboxController::class, 'merge'])  ->name('crm.inbox.merge');

    Route::get('/crm/imports',                      [LeadImportController::class, 'index'])   ->name('crm.imports.index');
    Route::get('/crm/imports/template',             [LeadImportController::class, 'template'])->name('crm.imports.template');
    Route::post('/crm/imports',                     [LeadImportController::class, 'store'])   ->name('crm.imports.store');
    Route::get('/crm/imports/{import}',             [LeadImportController::class, 'show'])    ->whereNumber('import')->name('crm.imports.show');
    Route::post('/crm/imports/{import}/preview',    [LeadImportController::class, 'preview']) ->whereNumber('import')->name('crm.imports.preview');
    Route::post('/crm/imports/{import}/run',        [LeadImportController::class, 'run'])     ->whereNumber('import')->name('crm.imports.run');
    Route::get('/crm/imports/{import}/errors',      [LeadImportController::class, 'errors'])  ->whereNumber('import')->name('crm.imports.errors');
    Route::delete('/crm/imports/{import}',          [LeadImportController::class, 'destroy']) ->whereNumber('import')->name('crm.imports.destroy');

    Route::put('/crm/settings/intake',                                  [CrmOrgController::class, 'updateCrmSettings'])->name('crm.settings.update');
    Route::get('/crm/settings/org',                                      [CrmOrgController::class, 'index'])          ->name('crm.org.index');
    Route::post('/crm/settings/org/departments/{department}/divisions',  [CrmOrgController::class, 'storeDivision'])  ->whereNumber('department')->name('crm.org.divisions.store');
    Route::put('/crm/settings/org/divisions/{division}',                 [CrmOrgController::class, 'updateDivision']) ->whereNumber('division')->name('crm.org.divisions.update');
    Route::delete('/crm/settings/org/divisions/{division}',              [CrmOrgController::class, 'destroyDivision'])->whereNumber('division')->name('crm.org.divisions.destroy');
    Route::put('/crm/settings/org/departments/{department}/settings',    [CrmOrgController::class, 'updateSettings']) ->whereNumber('department')->name('crm.org.settings.update');
    Route::post('/crm/settings/org/departments/{department}/pipeline',   [CrmOrgController::class, 'linkPipeline'])   ->whereNumber('department')->name('crm.org.pipeline.link');
    Route::delete('/crm/settings/org/departments/{department}/pipeline', [CrmOrgController::class, 'unlinkPipeline']) ->whereNumber('department')->name('crm.org.pipeline.unlink');
});

// Public lead-capture form pages — no auth, reachable by anyone with the link.
Route::middleware(['web'])->group(function () {
    Route::get('/f/{token}',  [PublicLeadFormController::class, 'show'])  ->name('crm.public-forms.show');
    Route::post('/f/{token}', [PublicLeadFormController::class, 'submit'])->name('crm.public-forms.submit');
});
