<?php

use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;

test('an unconfigured user keeps full access', function () {
    $access = new CrmAccess(false);

    expect($access->can('leads_delete'))->toBeTrue()
        ->and($access->canSeeRelation(5))->toBeTrue()
        ->and($access->canSeeStage(5, 99))->toBeTrue()
        ->and($access->visibleStageIds(5))->toBeNull();
});

test('function toggles block only the keys set to false', function () {
    $access = new CrmAccess(false, ['leads_delete' => false, 'tasks_create' => true]);

    expect($access->can('leads_delete'))->toBeFalse()
        ->and($access->can('tasks_create'))->toBeTrue()
        ->and($access->can('stages_manage'))->toBeTrue();
});

test('stage access limits one relation and leaves the others open', function () {
    // JSON object keys arrive as strings
    $access = new CrmAccess(false, [], ['5' => ['1', 3]]);

    expect($access->visibleStageIds(5))->toBe([1, 3])
        ->and($access->canSeeStage(5, 1))->toBeTrue()
        ->and($access->canSeeStage(5, 2))->toBeFalse()
        ->and($access->canSeeStage(5, null))->toBeFalse()
        ->and($access->canSeeStage(6, 2))->toBeTrue()
        ->and($access->canSeeRelation(5))->toBeTrue();

    $stages = collect([['id' => 1], ['id' => 2], ['id' => 3]]);
    expect($access->filterStages(5, $stages)->pluck('id')->all())->toBe([1, 3])
        ->and($access->filterStages(6, $stages)->count())->toBe(3);
});

test('an empty stage list hides the relation', function () {
    $access = new CrmAccess(false, [], [5 => []]);

    expect($access->canSeeRelation(5))->toBeFalse()
        ->and($access->canSeeStage(5, 1))->toBeFalse();
});

test('admins ignore saved restrictions', function () {
    $access = CrmAccess::full(isAdmin: true);

    expect($access->isAdmin)->toBeTrue()
        ->and($access->can('relations_delete'))->toBeTrue()
        ->and($access->visibleStageIds(5))->toBeNull();
});

test('toArray lists every function key for the desktop client', function () {
    $data = (new CrmAccess(false, ['forms_manage' => false], [5 => [1]]))->toArray();

    expect(array_keys($data['permissions']))->toBe(CrmPermissionService::functionKeys())
        ->and($data['permissions']['forms_manage'])->toBeFalse()
        ->and($data['permissions']['leads_view'])->toBeTrue()
        ->and((array) $data['stage_access'])->toBe([5 => [1]])
        ->and($data['is_admin'])->toBeFalse();
});
