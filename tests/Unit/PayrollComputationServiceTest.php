<?php

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\HRManagement\Services\PayrollComponentBuilderService;
use Modules\HRManagement\Services\PayrollComputationService;
use Modules\HRManagement\Services\PayrollRuleEvaluationService;

test('finalize cycle safeguard blocks errored or empty cycles', function () {
    $svc = new PayrollComputationService(new PayrollComponentBuilderService(new PayrollRuleEvaluationService));

    // Case 1: has errored items
    $whereRelation = Mockery::mock(HasMany::class);
    $whereRelation->shouldReceive('exists')->once()->andReturn(true);

    $itemsRelation1 = Mockery::mock(HasMany::class);
    $itemsRelation1->shouldReceive('where')->once()->with('status', 'error')->andReturn($whereRelation);

    $cycle1 = Mockery::mock(PayrollCycle::class);
    $cycle1->shouldReceive('items')->once()->andReturn($itemsRelation1);

    expect(fn () => $svc->finalizeCycle($cycle1, 10))
        ->toThrow(RuntimeException::class, 'Cannot finalize payroll cycle with errored items.');

    // Case 2: no errored items, but empty cycle
    $whereRelation2 = Mockery::mock(HasMany::class);
    $whereRelation2->shouldReceive('exists')->once()->andReturn(false);

    $itemsRelation2 = Mockery::mock(HasMany::class);
    $itemsRelation2->shouldReceive('where')->once()->with('status', 'error')->andReturn($whereRelation2);
    $itemsRelation2->shouldReceive('exists')->once()->andReturn(false);

    $cycle2 = Mockery::mock(PayrollCycle::class);
    $cycle2->shouldReceive('items')->twice()->andReturn($itemsRelation2);

    expect(fn () => $svc->finalizeCycle($cycle2, 10))
        ->toThrow(RuntimeException::class, 'Cannot finalize payroll cycle without computed items.');
});
