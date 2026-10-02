<?php

use Illuminate\Support\Facades\Route;
use Modules\ProjectManage\Http\Controllers\MilestoneController;
use Modules\ProjectManage\Http\Controllers\MyTasksController;
use Modules\ProjectManage\Http\Controllers\OverviewController;
use Modules\ProjectManage\Http\Controllers\ProjectController;
use Modules\ProjectManage\Http\Controllers\TaskController;
use Modules\ProjectManage\Http\Controllers\TaskStatusController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {

    Route::get('/pm', [OverviewController::class, 'index'])->name('pm.overview');

    // Projects
    Route::get('/pm/projects',               [ProjectController::class, 'index'])  ->name('pm.projects.index');
    Route::post('/pm/projects',              [ProjectController::class, 'store'])  ->name('pm.projects.store');
    Route::get('/pm/projects/{project}',     [ProjectController::class, 'show'])   ->name('pm.projects.show');
    Route::get('/pm/projects/{project}/edit',[ProjectController::class, 'edit'])   ->name('pm.projects.edit');
    Route::put('/pm/projects/{project}',     [ProjectController::class, 'update']) ->name('pm.projects.update');
    Route::delete('/pm/projects/{project}',  [ProjectController::class, 'destroy'])->name('pm.projects.destroy');

    // Project members (team)
    Route::post('/pm/projects/{project}/members',          [ProjectController::class, 'addMembers'])  ->name('pm.projects.members.store');
    Route::delete('/pm/projects/{project}/members/{user}', [ProjectController::class, 'removeMember'])->whereNumber('user')->name('pm.projects.members.destroy');

    // Milestones
    Route::post('/pm/projects/{project}/milestones',                          [MilestoneController::class, 'store'])   ->name('pm.projects.milestones.store');
    Route::post('/pm/projects/{project}/milestones/reorder',                   [MilestoneController::class, 'reorder']) ->name('pm.projects.milestones.reorder');
    Route::put('/pm/projects/{project}/milestones/{milestone}',                [MilestoneController::class, 'update'])  ->name('pm.projects.milestones.update');
    Route::post('/pm/projects/{project}/milestones/{milestone}/complete',      [MilestoneController::class, 'complete'])->name('pm.projects.milestones.complete');
    Route::post('/pm/projects/{project}/milestones/{milestone}/reopen',        [MilestoneController::class, 'reopen'])  ->name('pm.projects.milestones.reopen');
    Route::delete('/pm/projects/{project}/milestones/{milestone}',             [MilestoneController::class, 'destroy']) ->name('pm.projects.milestones.destroy');

    // Tasks (project-scoped)
    Route::get('/pm/projects/{project}/tasks',    [TaskController::class, 'index']) ->name('pm.projects.tasks.index');
    Route::get('/pm/projects/{project}/board',    [TaskController::class, 'board']) ->name('pm.projects.tasks.board');
    Route::get('/pm/projects/{project}/my-tasks', [TaskController::class, 'mine'])  ->name('pm.projects.tasks.mine');
    Route::post('/pm/projects/{project}/tasks',   [TaskController::class, 'store']) ->name('pm.projects.tasks.store');

    // Task statuses (custom board columns)
    Route::post('/pm/projects/{project}/statuses', [TaskStatusController::class, 'store'])  ->name('pm.projects.statuses.store');
    Route::put('/pm/statuses/{taskStatus}',        [TaskStatusController::class, 'update']) ->name('pm.statuses.update');
    Route::delete('/pm/statuses/{taskStatus}',     [TaskStatusController::class, 'destroy'])->name('pm.statuses.destroy');

    // Tasks (task-scoped)
    Route::get('/pm/tasks/{task}',             [TaskController::class, 'show'])    ->name('pm.tasks.show');
    Route::put('/pm/tasks/{task}',             [TaskController::class, 'update'])  ->name('pm.tasks.update');
    Route::patch('/pm/tasks/{task}/status',    [TaskController::class, 'status'])  ->name('pm.tasks.status');
    Route::patch('/pm/tasks/{task}/milestone', [TaskController::class, 'milestone'])->name('pm.tasks.milestone');
    Route::patch('/pm/tasks/{task}/assignees', [TaskController::class, 'assignees'])->name('pm.tasks.assignees');
    Route::post('/pm/tasks/{task}/complete',   [TaskController::class, 'complete'])->name('pm.tasks.complete');
    Route::post('/pm/tasks/{task}/reopen',     [TaskController::class, 'reopen'])  ->name('pm.tasks.reopen');
    Route::post('/pm/tasks/{task}/comments',   [TaskController::class, 'comment']) ->name('pm.tasks.comment');
    Route::post('/pm/tasks/{task}/time-logs',  [TaskController::class, 'logTime']) ->name('pm.tasks.time');
    Route::delete('/pm/tasks/{task}',          [TaskController::class, 'destroy']) ->name('pm.tasks.destroy');

    // My Tasks
    Route::get('/pm/my-tasks', [MyTasksController::class, 'index'])->name('pm.my-tasks');
});
