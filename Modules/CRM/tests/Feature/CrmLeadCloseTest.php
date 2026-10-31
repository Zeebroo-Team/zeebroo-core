<?php

require_once __DIR__ . '/Support/CrmWorkflowFixture.php';

use Modules\CRM\Models\FollowUp;
use Modules\CRM\Models\LeadAuditLog;
use Modules\CRM\Models\LeadStageLog;
use Modules\CRM\Models\LostReason;
use Modules\CRM\Models\Task;
use Modules\CRM\Services\LostReasonService;
use Modules\CRM\Services\TaskService;
use Modules\Pos\Models\Customer;
use Modules\ProjectManage\Models\Project as PmProject;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\SalesOrder;

beforeEach(fn () => crmwfSetUp());

function crmCloseLostReason(): LostReason
{
    app(LostReasonService::class)->ensureDefaults(7);

    return LostReason::query()->where('business_id', 7)->orderBy('id')->firstOrFail();
}

// ── Won ──────────────────────────────────────────────────────────────────

test('won with an invoice: the lead is won, linked to a customer and the invoice copies the quotation', function () {
    $q = crmwfSentQuotation();

    $res = crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['quotation_id' => $q->id, 'create' => 'invoice']);
    $body = $res->getData(true);

    $lead    = crmwfLead();
    $invoice = Invoice::sole();
    expect($res->getStatusCode())->toBe(200)
        ->and($lead->statusKey())->toBe('won')
        ->and($lead->closed_at)->not->toBeNull()
        ->and($lead->converted_at)->not->toBeNull()
        ->and($lead->customer_id)->toBe(Customer::sole()->id)
        ->and($q->fresh()->status)->toBe('accepted')
        ->and($invoice->quotation_id)->toBe($q->id)
        ->and($invoice->lead_id)->toBe($lead->id)
        ->and($invoice->customer_id)->toBe($lead->customer_id)
        ->and($invoice->reference)->toBe($q->quote_number)
        ->and($invoice->items()->count())->toBe(2)
        ->and((float) $invoice->total)->toBe((float) $q->total)
        ->and($body['data']['document']['type'])->toBe('invoice')
        ->and($body['data']['document_created'])->toBeTrue();
});

test('won is idempotent: asking twice never makes a second invoice', function () {
    $q = crmwfSentQuotation();

    crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['quotation_id' => $q->id, 'create' => 'invoice']);
    $again = crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['quotation_id' => $q->id, 'create' => 'invoice']);

    expect(Invoice::count())->toBe(1)
        ->and(Customer::count())->toBe(1)
        ->and($again->getData(true)['data']['document_created'])->toBeFalse()
        ->and($again->getData(true)['data']['document']['id'])->toBe(Invoice::sole()->id)
        // Only one "won" move in the history
        ->and(LeadStageLog::query()->where('lead_id', $this->lead->id)->whereHas('toStage', fn ($q) => $q->where('is_won', true))->count())->toBe(1);
});

test('won with a sales order, then a project, each once', function () {
    $q = crmwfSentQuotation();

    crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['quotation_id' => $q->id, 'create' => 'sales_order']);
    crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['quotation_id' => $q->id, 'create' => 'sales_order']);
    $res = crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['quotation_id' => $q->id, 'create' => 'project', 'project_name' => 'Kasun rooftop']);

    $order   = SalesOrder::sole();
    $project = PmProject::sole();
    expect(SalesOrder::count())->toBe(1)
        ->and($order->quotation_id)->toBe($q->id)
        ->and($order->lead_id)->toBe($this->lead->id)
        ->and((float) $order->total)->toBe((float) $q->total)
        ->and($project->name)->toBe('Kasun rooftop')
        ->and($project->lead_id)->toBe($this->lead->id)
        ->and($project->quotation_id)->toBe($q->id)
        ->and($project->project_type)->toBe(PmProject::TYPE_CUSTOMER)
        ->and($project->customer_id)->toBe(crmwfLead()->customer_id)
        ->and((float) $project->budget)->toBe((float) $q->total)
        ->and(collect($res->getData(true)['data']['documents'])->pluck('type')->all())->toBe(['sales_order', 'project']);
});

test('won with nothing to create still converts; a project needs no quotation', function () {
    $res = crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['create' => 'none']);
    expect($res->getStatusCode())->toBe(200)
        ->and(crmwfLead()->statusKey())->toBe('won')
        ->and(Invoice::count() + SalesOrder::count() + PmProject::count())->toBe(0);

    // Already won — the project can follow
    crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['create' => 'project']);
    expect(PmProject::sole()->name)->toBe('Kasun Stores');
});

test('an invoice or sales order needs a sent / accepted quotation of the lead', function () {
    expect(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['create' => 'invoice'])))->toBe(422);

    $q = crmwfSentQuotation();
    $q->update(['status' => 'draft']);
    expect(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['quotation_id' => $q->id, 'create' => 'invoice'])))->toBe(422)
        ->and(crmwfLead()->statusKey())->not->toBe('won');
});

test('the customer accepting the quotation wins the lead; the invoice can be made after', function () {
    $q = crmwfSentQuotation();
    crmwf(CrmwfQuoteApi::class, 'respond', 3, [$this->lead->id, $q->id], 'POST', ['response' => 'accept']);
    expect(crmwfLead()->statusKey())->toBe('won');

    $res = crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['quotation_id' => $q->id, 'create' => 'invoice']);
    expect($res->getStatusCode())->toBe(200)
        ->and(Invoice::sole()->quotation_id)->toBe($q->id)
        ->and(crmwfLead()->customer_id)->not->toBeNull();
});

test('who may mark a lead won', function (int $userId, int $status) {
    expect(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'won', $userId, [$this->lead->id], 'POST', ['create' => 'none'])))->toBe($status);
})->with([
    'the assigned staff'     => [3, 200],
    'the head'               => [2, 200],
    'staff not on the lead'  => [6, 403],
    'the CEO (view only)'    => [5, 403],
    'another department'     => [8, 404],
]);

// ── Lost ─────────────────────────────────────────────────────────────────

test('lost needs a lost reason, and closes the open follow-ups and tasks', function () {
    $q = crmwfSentQuotation();
    expect(FollowUp::query()->pending()->count())->toBe(2);
    $task = app(TaskService::class)->create($this->business, ['title' => 'Call back', 'assigned_to' => 3], $this->lead, 3);

    expect(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'lost', 3, [$this->lead->id], 'POST', [])))->toBe(422);

    $reason = crmCloseLostReason();
    $res = crmwf(CrmwfCloseApi::class, 'lost', 3, [$this->lead->id], 'POST', ['lost_reason_id' => $reason->id, 'note' => 'Went with a competitor']);

    $lead = crmwfLead();
    expect($res->getStatusCode())->toBe(200)
        ->and($lead->statusKey())->toBe('lost')
        ->and($lead->lost_reason_id)->toBe($reason->id)
        ->and($lead->lost_reason)->toBe('Went with a competitor')
        ->and($lead->closed_at)->not->toBeNull()
        ->and(FollowUp::query()->pending()->count())->toBe(0)
        ->and(FollowUp::query()->where('status', 'cancelled')->count())->toBe(2)
        ->and($task->fresh()->status)->toBe(Task::STATUS_COMPLETED);
});

test('a lost lead cannot be won until it is reopened', function () {
    crmwf(CrmwfCloseApi::class, 'lost', 3, [$this->lead->id], 'POST', ['lost_reason_id' => crmCloseLostReason()->id]);

    expect(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['create' => 'none'])))->toBe(422)
        ->and(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'lost', 3, [$this->lead->id], 'POST', ['lost_reason_id' => crmCloseLostReason()->id])))->toBe(422);
});

// ── Reopen ───────────────────────────────────────────────────────────────

test('only the head or an admin reopens, with a reason kept in the history', function () {
    crmwf(CrmwfCloseApi::class, 'lost', 3, [$this->lead->id], 'POST', ['lost_reason_id' => crmCloseLostReason()->id]);

    expect(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'reopen', 3, [$this->lead->id], 'POST', ['reason' => 'Changed mind'])))->toBe(403)
        ->and(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'reopen', 5, [$this->lead->id], 'POST', ['reason' => 'x'])))->toBe(403)
        ->and(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'reopen', 2, [$this->lead->id], 'POST', [])))->toBe(422);

    $res = crmwf(CrmwfCloseApi::class, 'reopen', 2, [$this->lead->id], 'POST', ['reason' => 'Customer called back']);

    $lead = crmwfLead();
    $log  = LeadStageLog::query()->where('lead_id', $lead->id)->latest('id')->first();
    $audit = LeadAuditLog::query()->where('lead_id', $lead->id)->where('event', LeadAuditLog::EVENT_REOPENED)->sole();
    expect($res->getStatusCode())->toBe(200)
        ->and($lead->isOpen())->toBeTrue()
        ->and($lead->closed_at)->toBeNull()
        ->and($lead->lost_reason_id)->toBeNull()
        ->and($log->reason)->toBe('Customer called back')
        ->and($log->changed_by)->toBe(2)
        ->and($audit->meta['reason'] ?? null)->toBe('Customer called back');

    // The owner (admin) may too
    crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['create' => 'none']);
    expect(crmwfStatus(fn () => crmwf(CrmwfCloseApi::class, 'reopen', 1, [$this->lead->id], 'POST', ['reason' => 'Order cancelled'])))->toBe(200);
});

test('dragging a closed lead back onto the board is the same rule as reopen', function () {
    crmwf(CrmwfCloseApi::class, 'lost', 3, [$this->lead->id], 'POST', ['lost_reason_id' => crmCloseLostReason()->id]);
    $open = $this->sales->stages()->where('system_key', 'contacted')->firstOrFail();

    $move = fn (int $userId) => crmwfStatus(function () use ($userId, $open) {
        $request = \Illuminate\Http\Request::create('/api', 'POST', ['stage_id' => $open->id]);
        $request->setUserResolver(fn () => \App\Models\User::find($userId));

        return app(CrmwfPipelineApi::class)->moveLead($request, $this->lead->id);
    });

    expect($move(3))->toBe(403)->and($move(2))->toBe(200);
});

test('the close options show the eligible quotations, documents and what the user may do', function () {
    $q = crmwfSentQuotation();

    $staff = crmwf(CrmwfCloseApi::class, 'options', 3, [$this->lead->id])->getData(true)['data'];
    expect($staff['status'])->toBe('open')
        ->and(collect($staff['quotations'])->pluck('id')->all())->toBe([$q->id])
        ->and($staff['can'])->toBe(['win' => true, 'lose' => true, 'reopen' => false]);

    crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['quotation_id' => $q->id, 'create' => 'invoice']);
    $head = crmwf(CrmwfCloseApi::class, 'options', 2, [$this->lead->id])->getData(true)['data'];
    expect($head['status'])->toBe('won')
        ->and($head['documents'][0]['type'])->toBe('invoice')
        ->and($head['can']['reopen'])->toBeTrue();
});

class CrmwfPipelineApi extends \Modules\Pos\Http\Controllers\Api\PosCrmApiController
{
    protected function businessOrAbort(\Illuminate\Http\Request $request): \Modules\Business\Models\Business
    {
        return \Modules\Business\Models\Business::find(7);
    }
}
