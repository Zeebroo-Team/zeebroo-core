<?php

require_once __DIR__ . '/Support/CrmWorkflowFixture.php';

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Modules\CRM\Events\FollowUpDue;
use Modules\CRM\Events\FollowUpOverdue;
use Modules\CRM\Models\FollowUp;
use Modules\CRM\Services\CrmNotifier;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\FollowUpService;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Models\PosNotification;

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 09:00:00');
    crmwfSetUp();
});

afterEach(fn () => Carbon::setTestNow());

// ── Scheduling on send ───────────────────────────────────────────────────

test('sending a quotation plans follow-ups at the default 2 and 5 days for the primary assignee', function () {
    $q = crmwfSentQuotation();

    $rows = FollowUp::query()->orderBy('due_at')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('due_at')->map->toDateTimeString()->all())->toBe(['2026-11-12 09:00:00', '2026-11-15 09:00:00'])
        ->and($rows->pluck('user_id')->unique()->all())->toBe([3])
        ->and($rows->pluck('quotation_id')->unique()->all())->toBe([$q->id])
        ->and($rows->pluck('status')->unique()->all())->toBe(['pending']);
});

test('the department\'s own follow-up days are used', function () {
    app(CrmOrgService::class)->saveSettings($this->business, Department::find(10), ['followup_days' => [1, 3, 7]]);

    crmwfSentQuotation();

    expect(FollowUp::query()->orderBy('due_at')->pluck('due_at')->map->toDateString()->all())
        ->toBe(['2026-11-11', '2026-11-13', '2026-11-17']);
});

test('re-sending the same version adds nothing; a new version sent replaces the pending ones', function () {
    $q = crmwfSentQuotation();
    crmwf(CrmwfQuoteApi::class, 'send', 3, [$this->lead->id, $q->id], 'POST', ['to' => 'kasun@example.com', 'subject' => 'Reminder', 'message' => 'Hi']);
    expect(FollowUp::count())->toBe(2);

    Carbon::setTestNow('2026-11-11 10:00:00');
    $v2 = Modules\Sales\Models\Quotation::findOrFail(crmwf(CrmwfQuoteApi::class, 'revise', 3, [$this->lead->id, $q->id], 'POST')->getData(true)['data']['id']);
    crmwf(CrmwfQuoteApi::class, 'submit', 3, [$this->lead->id, $v2->id], 'POST');
    crmwf(CrmwfQuoteApi::class, 'approve', 2, [$this->lead->id, $v2->id], 'POST');
    crmwf(CrmwfQuoteApi::class, 'send', 3, [$this->lead->id, $v2->id], 'POST', ['to' => 'kasun@example.com', 'subject' => 'v2', 'message' => 'Hi']);

    expect(FollowUp::query()->where('quotation_id', $q->id)->pluck('status')->unique()->all())->toBe(['cancelled'])
        ->and(FollowUp::query()->where('quotation_id', $v2->id)->pending()->orderBy('due_at')->pluck('due_at')->map->toDateTimeString()->all())
        ->toBe(['2026-11-13 10:00:00', '2026-11-16 10:00:00']);
});

// ── Logging ──────────────────────────────────────────────────────────────

test('logging a follow-up records the outcome and plans the next one', function () {
    crmwfSentQuotation();
    $first = FollowUp::query()->orderBy('due_at')->firstOrFail();

    expect(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'log', 3, [$first->id], 'POST', ['notes' => 'x'])))->toBe(422)
        ->and(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'log', 3, [$first->id], 'POST', ['outcome' => 'call_back', 'next_follow_up_at' => '2026-11-01 10:00'])))->toBe(422);

    $res = crmwf(CrmwfFollowUpApi::class, 'log', 3, [$first->id], 'POST', [
        'outcome' => 'call_back', 'channel' => 'call', 'notes' => 'Busy, call Friday', 'next_follow_up_at' => '2026-11-14 11:00',
    ]);

    $first->refresh();
    $next = FollowUp::query()->whereKey($res->getData(true)['data']['next']['id'])->firstOrFail();
    expect($res->getStatusCode())->toBe(200)
        ->and($first->status)->toBe('done')
        ->and($first->done_at->toDateTimeString())->toBe('2026-11-10 09:00:00')
        ->and($first->outcome)->toBe('call_back')
        ->and($first->channel)->toBe('call')
        ->and($first->notes)->toBe('Busy, call Friday')
        ->and($first->next_follow_up_at->toDateTimeString())->toBe('2026-11-14 11:00:00')
        ->and($next->status)->toBe('pending')
        ->and($next->user_id)->toBe(3)
        ->and($next->quotation_id)->toBe($first->quotation_id)
        ->and(crmwfLead()->first_response_at)->not->toBeNull();

    // Done is done
    expect(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'log', 3, [$first->id], 'POST', ['outcome' => 'reached'])))->toBe(422);
});

test('the assignee or whoever edits the lead works a follow-up; others may not', function () {
    crmwfSentQuotation();
    $f = FollowUp::query()->orderBy('due_at')->firstOrFail();

    expect(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'log', 6, [$f->id], 'POST', ['outcome' => 'reached'])))->toBe(403)
        ->and(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'log', 5, [$f->id], 'POST', ['outcome' => 'reached'])))->toBe(403)
        ->and(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'log', 8, [$f->id], 'POST', ['outcome' => 'reached'])))->toBe(404)
        ->and(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'log', 2, [$f->id], 'POST', ['outcome' => 'reached'])))->toBe(200);
});

test('a follow-up can be planned by hand from the lead window', function () {
    $res = crmwf(CrmwfFollowUpApi::class, 'store', 3, [$this->lead->id], 'POST', ['due_at' => '2026-11-11 15:00', 'channel' => 'whatsapp', 'notes' => 'Send brochure']);

    expect($res->getStatusCode())->toBe(201)
        ->and(FollowUp::sole()->user_id)->toBe(3)
        ->and(FollowUp::sole()->channel)->toBe('whatsapp')
        ->and(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'store', 3, [$this->lead->id], 'POST', ['due_at' => '2026-11-11 15:00', 'user_id' => 8])))->toBe(422)
        ->and(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'store', 6, [$this->lead->id], 'POST', ['due_at' => '2026-11-11 15:00'])))->toBe(403);
});

// ── Due / overdue ────────────────────────────────────────────────────────

test('overdue means still pending after the day it was due', function () {
    crmwfSentQuotation();
    $f = FollowUp::query()->orderBy('due_at')->firstOrFail(); // due 2026-11-12 09:00

    Carbon::setTestNow('2026-11-12 08:00:00');
    expect($f->fresh()->isOverdue())->toBeFalse()->and($f->fresh()->isDueToday())->toBeTrue();

    Carbon::setTestNow('2026-11-12 23:59:00');
    expect($f->fresh()->isOverdue())->toBeFalse()->and(FollowUp::query()->overdue($this->business->id)->count())->toBe(0);

    Carbon::setTestNow('2026-11-13 00:01:00');
    expect($f->fresh()->isOverdue())->toBeTrue()->and(FollowUp::query()->overdue($this->business->id)->count())->toBe(1);
});

test('today and overdue follow the business\'s own timezone, not the server\'s', function () {
    $this->business->setSetting('business.timezone', 'Asia/Colombo'); // UTC+5:30
    crmwfSentQuotation();
    $f = FollowUp::query()->orderBy('due_at')->firstOrFail(); // due 2026-11-12 09:00 UTC = 14:30 in Colombo
    Event::fake([FollowUpDue::class, FollowUpOverdue::class]);
    $service = app(FollowUpService::class);

    // 00:30 on the 12th in Colombo, still the 11th in UTC
    Carbon::setTestNow('2026-11-11 19:00:00');
    expect($f->fresh()->isDueToday())->toBeTrue();

    // 23:30 on the 12th in Colombo
    Carbon::setTestNow('2026-11-12 18:00:00');
    expect($f->fresh()->isOverdue())->toBeFalse()->and($service->process()['overdue'])->toBe(0);

    // 00:30 on the 13th in Colombo, still the 12th in UTC
    Carbon::setTestNow('2026-11-12 19:00:00');
    expect($f->fresh()->isOverdue())->toBeTrue()
        ->and(FollowUp::query()->overdue($this->business->id)->count())->toBe(1)
        ->and($service->process()['overdue'])->toBe(1);
});

test('the scheduler fires "due" once when the time comes and "overdue" once the next day', function () {
    crmwfSentQuotation();
    Event::fake([FollowUpDue::class, FollowUpOverdue::class]);
    $service = app(FollowUpService::class);

    Carbon::setTestNow('2026-11-12 08:45:00');
    expect($service->process())->toBe(['due' => 0, 'overdue' => 0]);

    Carbon::setTestNow('2026-11-12 09:00:00');
    expect($service->process())->toBe(['due' => 1, 'overdue' => 0])
        ->and($service->process())->toBe(['due' => 0, 'overdue' => 0]);

    Carbon::setTestNow('2026-11-13 00:15:00');
    expect($service->process())->toBe(['due' => 0, 'overdue' => 1])
        ->and($service->process())->toBe(['due' => 0, 'overdue' => 0]);

    // Found already overdue (the scheduler was down): only "overdue", no late "due"
    Carbon::setTestNow('2026-11-17 09:00:00');
    expect($service->process())->toBe(['due' => 0, 'overdue' => 1]);

    Event::assertDispatchedTimes(FollowUpDue::class, 1);
    Event::assertDispatchedTimes(FollowUpOverdue::class, 2);
    expect(FollowUp::query()->whereNotNull('overdue_at')->count())->toBe(2);
});

test('due goes to the assignee; overdue to the assignee and the head', function () {
    crmwfSentQuotation();

    Carbon::setTestNow('2026-11-12 09:05:00');
    $this->artisan('crm:process-follow-ups')->assertSuccessful();
    expect(PosNotification::query()->where('type', CrmNotifier::TYPE_FOLLOW_UP_DUE)->pluck('user_id')->all())->toBe([3]);

    Carbon::setTestNow('2026-11-13 09:05:00');
    $this->artisan('crm:process-follow-ups')->assertSuccessful();
    expect(PosNotification::query()->where('type', CrmNotifier::TYPE_FOLLOW_UP_OVERDUE)->orderBy('user_id')->pluck('user_id')->all())->toBe([2, 3]);
});

test('logged or cancelled follow-ups are never reminded', function () {
    crmwfSentQuotation();
    FollowUp::query()->orderBy('due_at')->firstOrFail()->update(['status' => 'done', 'done_at' => now()]);
    FollowUp::query()->orderByDesc('due_at')->firstOrFail()->update(['status' => 'cancelled', 'cancelled_at' => now()]);

    Carbon::setTestNow('2026-11-20 09:00:00');
    expect(app(FollowUpService::class)->process())->toBe(['due' => 0, 'overdue' => 0]);
});

test('the command runs every 15 minutes from the CRM module', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'crm:process-follow-ups'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/15 * * * *');
});

// ── Lists ────────────────────────────────────────────────────────────────

test('my follow-ups for staff, team overdue for heads only', function () {
    crmwfSentQuotation();
    Carbon::setTestNow('2026-11-14 09:00:00'); // the first one is overdue

    $mine = crmwf(CrmwfFollowUpApi::class, 'index', 3, [], 'GET', ['scope' => 'mine', 'status' => 'pending'])->getData(true);
    expect(count($mine['data']))->toBe(2)
        ->and($mine['data'][0]['is_overdue'])->toBeTrue()
        ->and($mine['data'][1]['is_overdue'])->toBeFalse()
        ->and($mine['data'][0]['lead']['name'])->toBe('Kasun')
        ->and($mine['data'][0]['can_log'])->toBeTrue()
        ->and($mine['meta']['counts'])->toBe(['mine_pending' => 2, 'mine_overdue' => 1, 'team_overdue' => null])
        ->and($mine['meta']['can_team'])->toBeFalse();

    expect(crmwfStatus(fn () => crmwf(CrmwfFollowUpApi::class, 'index', 3, [], 'GET', ['scope' => 'team'])))->toBe(403);

    $team = crmwf(CrmwfFollowUpApi::class, 'index', 2, [], 'GET', ['scope' => 'team', 'status' => 'overdue'])->getData(true);
    expect(count($team['data']))->toBe(1)->and($team['data'][0]['user']['name'])->toBe('Sunil')
        ->and($team['meta']['counts']['team_overdue'])->toBe(1);

    // Another department's head sees none of it
    $other = crmwf(CrmwfFollowUpApi::class, 'index', 8, [], 'GET', ['scope' => 'team', 'status' => 'all'])->getData(true);
    expect($other['data'])->toBe([]);
});

test('the board card carries the next follow-up and its overdue count', function () {
    crmwfSentQuotation();
    Carbon::setTestNow('2026-11-13 09:00:00');

    $request = \Illuminate\Http\Request::create('/api', 'GET');
    $request->setUserResolver(fn () => \App\Models\User::find(3));
    $data = app(CrmwfFollowUpBoardApi::class)->pipeline($request, $this->sales->id)->getData(true)['data'];
    $card = collect($data['columns'])->flatMap(fn ($c) => $c['leads'])->firstWhere('id', $this->lead->id);

    expect($card['follow_up']['pending'])->toBe(2)
        ->and($card['follow_up']['overdue'])->toBe(1)
        ->and($card['follow_up']['next_due_at'])->toStartWith('2026-11-12')
        ->and($card['suggested_score']['score'])->toBeIn(['hot', 'warm', 'cold']);
});

test('the lead tab lists pending first, then the history', function () {
    crmwfSentQuotation();
    $first = FollowUp::query()->orderBy('due_at')->firstOrFail();
    crmwf(CrmwfFollowUpApi::class, 'log', 3, [$first->id], 'POST', ['outcome' => 'reached']);

    $tab = crmwf(CrmwfFollowUpApi::class, 'forLead', 3, [$this->lead->id])->getData(true);
    expect(collect($tab['data'])->pluck('status')->all())->toBe(['pending', 'done'])
        ->and($tab['meta']['can_add'])->toBeTrue()
        ->and(collect($tab['meta']['outcomes'])->pluck('key'))->toContain('no_answer');
});

class CrmwfFollowUpBoardApi extends \Modules\Pos\Http\Controllers\Api\PosCrmApiController
{
    protected function businessOrAbort(\Illuminate\Http\Request $request): \Modules\Business\Models\Business
    {
        return \Modules\Business\Models\Business::find(7);
    }
}
