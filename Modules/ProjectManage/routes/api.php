<?php

use Illuminate\Support\Facades\Route;
use Modules\ProjectManage\Http\Controllers\Api\ProjectManageApiController;

Route::middleware(['auth:sanctum'])->prefix('v1/pos')->name('pos.')->group(function () {

    $c = ProjectManageApiController::class;

    // Projects
    Route::get   ('pm/projects',             [$c, 'projectIndex'])  ->name('pm.projects.index');
    Route::post  ('pm/projects',             [$c, 'projectStore'])  ->name('pm.projects.store');
    Route::patch ('pm/projects/{id}',        [$c, 'projectUpdate']) ->where('id', '[0-9]+')->name('pm.projects.update');
    Route::delete('pm/projects/{id}',        [$c, 'projectDestroy'])->where('id', '[0-9]+')->name('pm.projects.destroy');

    // Board
    Route::get('pm/projects/{id}/board',     [$c, 'board'])         ->where('id', '[0-9]+')->name('pm.projects.board');

    // Task statuses (board columns — built-in + custom per project)
    Route::get   ('pm/projects/{id}/statuses', [$c, 'statusIndex'])  ->where('id', '[0-9]+')->name('pm.projects.statuses.index');
    Route::post  ('pm/projects/{id}/statuses', [$c, 'statusStore'])  ->where('id', '[0-9]+')->name('pm.projects.statuses.store');
    Route::patch ('pm/statuses/{id}',          [$c, 'statusUpdate']) ->where('id', '[0-9]+')->name('pm.statuses.update');
    Route::delete('pm/statuses/{id}',          [$c, 'statusDestroy'])->where('id', '[0-9]+')->name('pm.statuses.destroy');

    // Tasks (project-scoped)
    Route::get ('pm/projects/{id}/tasks',    [$c, 'taskIndex'])     ->where('id', '[0-9]+')->name('pm.projects.tasks.index');
    Route::post('pm/projects/{id}/tasks',    [$c, 'taskStore'])     ->where('id', '[0-9]+')->name('pm.projects.tasks.store');

    // Tasks (task-scoped)
    Route::patch ('pm/tasks/{id}/status',   [$c, 'taskStatus'])    ->where('id', '[0-9]+')->name('pm.tasks.status');
    Route::post  ('pm/tasks/{id}/complete', [$c, 'taskComplete'])  ->where('id', '[0-9]+')->name('pm.tasks.complete');
    Route::post  ('pm/tasks/{id}/reopen',   [$c, 'taskReopen'])    ->where('id', '[0-9]+')->name('pm.tasks.reopen');
    Route::post  ('pm/tasks/{id}/comments', [$c, 'taskComment'])   ->where('id', '[0-9]+')->name('pm.tasks.comment');
    Route::post  ('pm/tasks/{id}/time',     [$c, 'taskTime'])      ->where('id', '[0-9]+')->name('pm.tasks.time');
    Route::delete('pm/tasks/{id}',          [$c, 'taskDestroy'])   ->where('id', '[0-9]+')->name('pm.tasks.destroy');

    // My Tasks
    Route::get('pm/my-tasks', [$c, 'myTasks'])->name('pm.my-tasks');

    // Milestones
    Route::get   ('pm/projects/{id}/milestones',     [$c, 'milestoneIndex'])   ->where('id', '[0-9]+')->name('pm.projects.milestones.index');
    Route::post  ('pm/projects/{id}/milestones',     [$c, 'milestoneStore'])   ->where('id', '[0-9]+')->name('pm.projects.milestones.store');
    Route::post  ('pm/milestones/{id}/complete',     [$c, 'milestoneComplete'])->where('id', '[0-9]+')->name('pm.milestones.complete');
    Route::delete('pm/milestones/{id}',              [$c, 'milestoneDestroy']) ->where('id', '[0-9]+')->name('pm.milestones.destroy');
});
