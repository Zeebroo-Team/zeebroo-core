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

    // Project members (team) — tasks can only be assigned to members
    Route::get   ('pm/projects/{id}/members',          [$c, 'memberIndex'])  ->where('id', '[0-9]+')->name('pm.projects.members.index');
    Route::post  ('pm/projects/{id}/members',          [$c, 'memberStore'])  ->where('id', '[0-9]+')->name('pm.projects.members.store');
    Route::delete('pm/projects/{id}/members/{userId}', [$c, 'memberDestroy'])->where(['id' => '[0-9]+', 'userId' => '[0-9]+'])->name('pm.projects.members.destroy');

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
    Route::patch ('pm/tasks/{id}/milestone',[$c, 'taskMilestone']) ->where('id', '[0-9]+')->name('pm.tasks.milestone');
    Route::patch ('pm/tasks/{id}/assignees',[$c, 'taskAssign'])    ->where('id', '[0-9]+')->name('pm.tasks.assignees');
    Route::post  ('pm/tasks/{id}/complete', [$c, 'taskComplete'])  ->where('id', '[0-9]+')->name('pm.tasks.complete');
    Route::post  ('pm/tasks/{id}/reopen',   [$c, 'taskReopen'])    ->where('id', '[0-9]+')->name('pm.tasks.reopen');
    Route::post  ('pm/tasks/{id}/comments', [$c, 'taskComment'])   ->where('id', '[0-9]+')->name('pm.tasks.comment');
    Route::post  ('pm/tasks/{id}/time',     [$c, 'taskTime'])      ->where('id', '[0-9]+')->name('pm.tasks.time');
    Route::delete('pm/tasks/{id}',          [$c, 'taskDestroy'])   ->where('id', '[0-9]+')->name('pm.tasks.destroy');

    // My Tasks
    Route::get('pm/my-tasks', [$c, 'myTasks'])->name('pm.my-tasks');

    // My Projects panel (Assigned Project Access) — only tasks assigned to the caller
    Route::get  ('pm/my-work',                  [$c, 'myWork'])          ->name('pm.my-work');
    Route::post ('pm/my-work/tasks',            [$c, 'myWorkTaskStore']) ->name('pm.my-work.tasks.store');
    Route::get  ('pm/my-work/tasks/{id}',       [$c, 'myWorkTaskShow'])  ->where('id', '[0-9]+')->name('pm.my-work.tasks.show');
    Route::patch('pm/my-work/tasks/{id}/status', [$c, 'myWorkTaskStatus'])->where('id', '[0-9]+')->name('pm.my-work.tasks.status');

    // Milestones
    Route::get   ('pm/projects/{id}/milestones',     [$c, 'milestoneIndex'])   ->where('id', '[0-9]+')->name('pm.projects.milestones.index');
    Route::post  ('pm/projects/{id}/milestones',     [$c, 'milestoneStore'])   ->where('id', '[0-9]+')->name('pm.projects.milestones.store');
    Route::post  ('pm/projects/{id}/milestones/reorder', [$c, 'milestoneReorder'])->where('id', '[0-9]+')->name('pm.projects.milestones.reorder');
    Route::patch ('pm/milestones/{id}',              [$c, 'milestoneUpdate'])  ->where('id', '[0-9]+')->name('pm.milestones.update');
    Route::post  ('pm/milestones/{id}/complete',     [$c, 'milestoneComplete'])->where('id', '[0-9]+')->name('pm.milestones.complete');
    Route::post  ('pm/milestones/{id}/reopen',       [$c, 'milestoneReopen'])  ->where('id', '[0-9]+')->name('pm.milestones.reopen');
    Route::delete('pm/milestones/{id}',              [$c, 'milestoneDestroy']) ->where('id', '[0-9]+')->name('pm.milestones.destroy');
});
