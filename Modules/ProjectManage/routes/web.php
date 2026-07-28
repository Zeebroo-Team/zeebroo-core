<?php

use Illuminate\Support\Facades\Route;
use Modules\ProjectManage\Http\Controllers\MilestoneController;
use Modules\ProjectManage\Http\Controllers\MyTasksController;
use Modules\ProjectManage\Http\Controllers\ProjectController;
use Modules\ProjectManage\Http\Controllers\TaskController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {

    // Projects
    Route::get('/pm/projects',               [ProjectController::class, 'index'])  ->name('pm.projects.index');
    Route::post('/pm/projects',              [ProjectController::class, 'store'])  ->name('pm.projects.store');
    Route::get('/pm/projects/{project}',     [ProjectController::class, 'show'])   ->name('pm.projects.show');
    Route::get('/pm/projects/{project}/edit',[ProjectController::class, 'edit'])   ->name('pm.projects.edit');
    Route::put('/pm/projects/{project}',     [ProjectController::class, 'update']) ->name('pm.projects.update');
    Route::delete('/pm/projects/{project}',  [ProjectController::class, 'destroy'])->name('pm.projects.destroy');

    // Milestones
    Route::post('/pm/projects/{project}/milestones',                           [MilestoneController::class, 'store'])   ->name('pm.projects.milestones.store');
    Route::put('/pm/projects/{project}/milestones/{milestone}',                [MilestoneController::class, 'update'])  ->name('pm.projects.milestones.update');
    Route::post('/pm/projects/{project}/milestones/{milestone}/complete',      [MilestoneController::class, 'complete'])->name('pm.projects.milestones.complete');
    Route::post('/pm/projects/{project}/milestones/{milestone}/reopen',        [MilestoneController::class, 'reopen'])  ->name('pm.projects.milestones.reopen');
    Route::delete('/pm/projects/{project}/milestones/{milestone}',             [MilestoneController::class, 'destroy']) ->name('pm.projects.milestones.destroy');

    // Tasks (project-scoped)
    Route::get('/pm/projects/{project}/tasks',  [TaskController::class, 'index']) ->name('pm.projects.tasks.index');
    Route::get('/pm/projects/{project}/board',  [TaskController::class, 'board']) ->name('pm.projects.tasks.board');
    Route::post('/pm/projects/{project}/tasks', [TaskController::class, 'store']) ->name('pm.projects.tasks.store');

    // Tasks (task-scoped)
    Route::get('/pm/tasks/{task}',             [TaskController::class, 'show'])    ->name('pm.tasks.show');
    Route::put('/pm/tasks/{task}',             [TaskController::class, 'update'])  ->name('pm.tasks.update');
    Route::patch('/pm/tasks/{task}/status',    [TaskController::class, 'status'])  ->name('pm.tasks.status');
    Route::post('/pm/tasks/{task}/complete',   [TaskController::class, 'complete'])->name('pm.tasks.complete');
    Route::post('/pm/tasks/{task}/reopen',     [TaskController::class, 'reopen'])  ->name('pm.tasks.reopen');
    Route::post('/pm/tasks/{task}/comments',   [TaskController::class, 'comment']) ->name('pm.tasks.comment');
    Route::post('/pm/tasks/{task}/time-logs',  [TaskController::class, 'logTime']) ->name('pm.tasks.time');
    Route::delete('/pm/tasks/{task}',          [TaskController::class, 'destroy']) ->name('pm.tasks.destroy');

    // My Tasks
    Route::get('/pm/my-tasks', [MyTasksController::class, 'index'])->name('pm.my-tasks');
});
