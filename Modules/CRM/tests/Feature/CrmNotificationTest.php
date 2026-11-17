<?php

require_once __DIR__ . '/Support/CrmWorkflowFixture.php';

use Illuminate\Support\Facades\Mail;
use Modules\Business\Models\Business;
use Modules\CRM\Mail\CrmNotificationMail;
use Modules\CRM\Models\LostReason;
use Modules\CRM\Services\CrmNotifier;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\LostReasonService;
use Modules\CRM\Services\MarketingInboxService;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Models\PosNotification;
use Modules\Pos\Services\PosNotificationService;

beforeEach(fn () => crmwfSetUp());

/** user ids notified with this type, sorted */
function crmNotified(string $type, int $businessId = 7): array
{
    return PosNotification::query()->where('business_id', $businessId)->where('type', $type)->orderBy('user_id')->pluck('user_id')->all();
}

test('a new lead in the inbox goes to marketing, not to whoever entered it', function () {
    $lead = app(LeadService::class)->create($this->marketing, ['name' => 'Web enquiry', 'source' => 'website'], 1);

    $row = PosNotification::query()->where('type', CrmNotifier::TYPE_LEAD_NEW)->sole();
    expect(crmNotified(CrmNotifier::TYPE_LEAD_NEW))->toBe([4])
        ->and($row->payload)->toMatchArray(['lead_id' => $lead->id, 'project_id' => $this->marketing->id])
        ->and($row->action_url)->toBe('/crm/leads/' . $lead->id)
        ->and($row->title)->toBe('New lead in the inbox');

    // Kamala entering one herself is not told about it
    app(LeadService::class)->create($this->marketing, ['name' => 'Walk in'], 4);
    expect(PosNotification::query()->where('type', CrmNotifier::TYPE_LEAD_NEW)->count())->toBe(1);
});

test('forwarding notifies the receiving department head', function () {
    $lead = app(LeadService::class)->create($this->marketing, ['name' => 'Web enquiry'], 4);
    app(MarketingInboxService::class)->forward($lead, 10, null, 'Wants 10 panels', 4);

    $row = PosNotification::query()->where('type', CrmNotifier::TYPE_LEAD_FORWARDED)->sole();
    expect($row->user_id)->toBe(2)
        ->and($row->title)->toBe('Lead forwarded to Sales')
        ->and($row->message)->toContain('Wants 10 panels');
});

test('assigning notifies the staff added, and the new primary', function () {
    // The fixture assigned Sunil (by Nimal)
    expect(crmNotified(CrmNotifier::TYPE_LEAD_ASSIGNED))->toBe([3]);

    app(LeadAssignmentService::class)->add($this->lead->fresh(), [6], 2);
    expect(crmNotified(CrmNotifier::TYPE_LEAD_ASSIGNED))->toBe([3, 6]);

    // A head assigning themselves is not told
    PosNotification::query()->delete();
    app(LeadAssignmentService::class)->add($this->lead->fresh(), [2], 2);
    expect(crmNotified(CrmNotifier::TYPE_LEAD_ASSIGNED))->toBe([]);

    // Priya becomes primary
    app(LeadAssignmentService::class)->setPrimary($this->lead->fresh(), 6, 2);
    expect(PosNotification::query()->where('type', CrmNotifier::TYPE_LEAD_ASSIGNED)->sole()->title)->toBe('You are now the primary on a lead');
});

test('submitting asks the head; a discount over the limit says so', function () {
    app(CrmOrgService::class)->saveSettings($this->business, Department::find(10), ['discount_approval_limit_percent' => 5]);
    Mail::fake();

    $res = crmwf(CrmwfQuoteApi::class, 'store', 3, [$this->lead->id], 'POST', [
        'quote_date' => now()->toDateString(),
        'items'      => [['item_type' => 'custom', 'description' => 'Install', 'quantity' => 1, 'unit_price' => 100, 'discount_amount' => 20]],
    ]);
    $id = $res->getData(true)['data']['id'];
    crmwf(CrmwfQuoteApi::class, 'submit', 3, [$this->lead->id, $id], 'POST');

    $row = PosNotification::query()->where('type', CrmNotifier::TYPE_QUOTATION_SUBMITTED)->sole();
    expect($row->user_id)->toBe(2)
        ->and($row->title)->toBe('Discount approval needed')
        ->and($row->message)->toContain('20% discount, over the 5% limit')
        ->and($row->reference_type)->toBe('crm_quotation')
        ->and($row->payload['quotation_id'])->toBe($id);
});

test('approve / return / reject go back to whoever submitted', function (string $action, string $title) {
    Mail::fake();
    $res = crmwf(CrmwfQuoteApi::class, 'store', 3, [$this->lead->id], 'POST', [
        'quote_date' => now()->toDateString(),
        'items'      => [['item_type' => 'custom', 'description' => 'Install', 'quantity' => 1, 'unit_price' => 100]],
    ]);
    $id = $res->getData(true)['data']['id'];
    crmwf(CrmwfQuoteApi::class, 'submit', 3, [$this->lead->id, $id], 'POST');
    crmwf(CrmwfQuoteApi::class, $action, 2, [$this->lead->id, $id], 'POST', ['comment' => 'Check the price']);

    $row = PosNotification::query()->where('type', CrmNotifier::TYPE_QUOTATION_DECIDED)->sole();
    expect($row->user_id)->toBe(3)->and($row->title)->toBe($title);
})->with([
    'approved' => ['approve', 'Quotation approved'],
    'returned' => ['returnForChanges', 'Quotation returned for changes'],
    'rejected' => ['reject', 'Quotation rejected'],
]);

test('won and lost go to the head, unless the head closed it', function () {
    crmwf(CrmwfCloseApi::class, 'won', 3, [$this->lead->id], 'POST', ['create' => 'none']);
    $row = PosNotification::query()->where('type', CrmNotifier::TYPE_LEAD_CLOSED)->sole();
    expect($row->user_id)->toBe(2)->and($row->title)->toBe('Lead won')->and($row->payload['outcome'])->toBe('won');

    crmwf(CrmwfCloseApi::class, 'reopen', 2, [$this->lead->id], 'POST', ['reason' => 'Order cancelled']);
    PosNotification::query()->delete();
    app(LostReasonService::class)->ensureDefaults(7);
    crmwf(CrmwfCloseApi::class, 'lost', 2, [$this->lead->id], 'POST', ['lost_reason_id' => LostReason::query()->value('id')]);
    expect(crmNotified(CrmNotifier::TYPE_LEAD_CLOSED))->toBe([]);
});

test('no notification crosses into another business', function () {
    // Business 8's intake gets a lead: only its own marketing (Ravi) hears of it
    app(LeadService::class)->create($this->other, ['name' => 'Their lead'], 9);
    expect(crmNotified(CrmNotifier::TYPE_LEAD_NEW, 8))->toBe([11])
        ->and(crmNotified(CrmNotifier::TYPE_LEAD_NEW, 7))->toBe([]);

    // Asked to notify an outsider about business 7's lead, the notifier drops them
    $sent = app(CrmNotifier::class)->notify($this->lead, [11, 9, 3], CrmNotifier::TYPE_LEAD_ASSIGNED, 'Test', 'Test');
    expect($sent)->toBe([3])
        ->and(PosNotification::query()->whereIn('user_id', [9, 11])->where('business_id', 7)->count())->toBe(0);

    // And business 7's bell never lists business 8's rows
    $list = app(PosNotificationService::class)->list(Business::find(7), null, 50, 11);
    expect(collect($list['data'] ?? $list)->pluck('type')->all())->not->toContain(CrmNotifier::TYPE_LEAD_NEW);
});

test('notifying about the same lead again re-surfaces one row instead of stacking', function () {
    $n = app(CrmNotifier::class);
    $n->notify($this->lead, [3], CrmNotifier::TYPE_LEAD_ASSIGNED, 'One', 'First');
    PosNotification::query()->update(['read_at' => now()]);
    $n->notify($this->lead, [3], CrmNotifier::TYPE_LEAD_ASSIGNED, 'Two', 'Second');

    $row = PosNotification::query()->where('type', CrmNotifier::TYPE_LEAD_ASSIGNED)->sole();
    expect($row->title)->toBe('Two')->and($row->read_at)->toBeNull();
});

test('each recipient gets a queued email unless they turned CRM emails off', function () {
    Mail::fake();
    $n = app(CrmNotifier::class);

    $n->notify($this->lead, [3, 6], CrmNotifier::TYPE_LEAD_ASSIGNED, 'Lead assigned to you', 'Hi');
    Mail::assertQueued(CrmNotificationMail::class, 2);
    Mail::assertQueued(CrmNotificationMail::class, fn ($m) => $m->hasTo('sunil@example.com') && $m->title === 'Lead assigned to you');

    // Sunil turns them off through the API
    $res = crmwf(CrmwfFollowUpApi::class, 'updatePreferences', 3, [], 'PUT', ['email_notifications' => false]);
    expect($res->getData(true)['data']['email_notifications'])->toBeFalse()
        ->and(crmwf(CrmwfFollowUpApi::class, 'preferences', 3)->getData(true)['data']['email_notifications'])->toBeFalse();

    Mail::fake();
    $n->notify($this->lead, [3, 6], CrmNotifier::TYPE_LEAD_CLOSED, 'Lead won', 'Hi');
    Mail::assertQueued(CrmNotificationMail::class, 1);
    Mail::assertNotQueued(CrmNotificationMail::class, fn ($m) => $m->hasTo('sunil@example.com'));
    // …but the in-app one still arrives
    expect(crmNotified(CrmNotifier::TYPE_LEAD_CLOSED))->toBe([3, 6]);
});

test('the desktop bell lists CRM notifications with their deep link', function () {
    app(CrmNotifier::class)->notify($this->lead, [3], CrmNotifier::TYPE_LEAD_ASSIGNED, 'Lead assigned to you', 'Hi');

    $list = app(PosNotificationService::class)->list(Business::find(7), null, 50, 3);
    $row  = collect($list['data'] ?? $list)->firstWhere('type', CrmNotifier::TYPE_LEAD_ASSIGNED);
    expect($row['action_url'])->toBe('/crm/leads/' . $this->lead->id)
        ->and($row['payload']['lead_id'])->toBe($this->lead->id)
        ->and($row['payload']['project_id'])->toBe($this->sales->id);
});
