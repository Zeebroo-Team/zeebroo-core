<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadCustomFieldValue;
use Modules\HRManagement\Models\Employee;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;

/**
 * What a head sees to decide a quotation — in the approval email (QuotationApprovalRequestMail)
 * and on the page its link opens (QuotationApprovalLinkController): the business letterhead, who
 * prepared it, the department / division and its heads, the lead in full, the discount against
 * the limit and the earlier versions' history.
 */
class QuotationApprovalBrief
{
    public function __construct(private readonly QuotationApprovalService $approvals) {}

    /**
     * @param  int[]|null  $emailedIds  heads this submission was emailed to; null when not known (the page)
     * @return array<string, mixed>
     */
    public function build(Quotation $q, Lead $lead, int $approverId, ?QuotationApproval $submission, ?array $emailedIds = null): array
    {
        $q->loadMissing(['items.product:id,name,sku', 'items.serviceItem', 'submittedBy:id,name,email', 'business', 'parent:id,parent_quotation_id,quote_number,version,total']);
        $lead->loadMissing([
            'stage:id,name,color', 'department.headEmployee.user', 'department.headEmployee.jobTitle',
            'department.coHeadEmployee.user', 'department.coHeadEmployee.jobTitle', 'division.headUser',
            'assignees.user:id,name', 'project:id,name', 'createdBy:id,name', 'verifiedBy:id,name',
            'products:id,name,sku', 'customFieldValues.customField', 'leadSource', 'form',
        ]);
        $business = $q->business;
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $money    = fn ($v) => trim(number_format((float) $v, 2) . ' ' . $currency);

        return [
            'q'          => $q,
            'lead'       => $lead,
            'leadName'   => $lead->company ? $lead->name . ' · ' . $lead->company : $lead->name,
            'business'   => $business,
            'branch'     => $business?->branches()->first(),
            'logoUrl'    => $this->logoUrl($q),
            'submission' => $submission,
            'preparedBy' => $this->person($q, $q->submittedBy, null),
            'chain'      => $this->chain($q, $lead, $approverId, $emailedIds),
            'leadInfo'   => $this->leadInfo($lead, $money),
            'discount'   => $this->approvals->discountSummary($lead, $q),
            'history'    => $this->history($q, $submission),
            'money'      => $money,
            'num'        => fn ($v, int $dp = 2) => rtrim(rtrim(number_format((float) $v, $dp, '.', ''), '0'), '.'),
        ];
    }

    private function logoUrl(Quotation $q): ?string
    {
        $path = $q->business?->logo_path;
        try {
            return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A staff member from their Zeebroo user and/or HR employee record in this business.
     *
     * @return array{id: ?int, name: string, title: ?string, employee_no: ?string, email: ?string, phone: ?string}|null
     */
    private function person(Quotation $q, ?User $user, ?Employee $employee): ?array
    {
        if ($user === null && $employee === null) {
            return null;
        }
        $employee ??= Employee::query()->with('jobTitle:id,name')
            ->where('business_id', $q->business_id)
            ->where('user_id', $user->id)
            ->first();
        $user ??= $employee->user;

        return [
            'id'          => $user?->id,
            'name'        => $employee?->full_name ?: $user?->name ?: '—',
            'title'       => $employee?->jobTitle?->name,
            'employee_no' => $employee?->employee_id,
            'email'       => $user?->email ?: $employee?->personal_email,
            'phone'       => $employee?->phone_number,
        ];
    }

    /**
     * The lead's department and division with their heads.
     *
     * @return array{department: ?string, division: ?string, heads: list<array{role: string, person: ?array, emailed: ?bool, you: bool}>}
     */
    private function chain(Quotation $q, Lead $lead, int $approverId, ?array $emailedIds): array
    {
        $division = $lead->division !== null && (int) $lead->division->department_id === (int) $lead->department_id ? $lead->division : null;

        $rows = [];
        if ($division) {
            $rows[] = ['Division head', $this->person($q, $division->headUser, null)];
        }
        if ($lead->department) {
            $rows[] = ['Department head', $this->person($q, null, $lead->department->headEmployee)];
            $rows[] = ['Department co-head', $this->person($q, null, $lead->department->coHeadEmployee)];
        }

        $heads = array_map(fn (array $r) => [
            'role'    => $r[0],
            'person'  => $r[1],
            'emailed' => $emailedIds === null ? null : $r[1] !== null && in_array($r[1]['id'], $emailedIds, true),
            'you'     => $r[1] !== null && $r[1]['id'] === $approverId,
        ], $rows);

        return ['department' => $lead->department?->name, 'division' => $division?->name, 'heads' => $heads];
    }

    /**
     * Every lead detail on file, grouped; empty values are left out.
     *
     * @return array<string, array<string, string>>
     */
    private function leadInfo(Lead $lead, \Closure $money): array
    {
        $date  = fn ($d, string $f = 'M j, Y') => $d?->format($f);
        $users = $lead->assignees->map(fn ($a) => $a->user?->name . ($a->is_primary && $lead->assignees->count() > 1 ? ' (primary)' : ''))->filter()->implode(', ');

        $groups = [
            'Contact' => [
                'Name'    => $lead->name,
                'Company' => $lead->company,
                'Email'   => $lead->email,
                'Phone'   => $lead->phone,
            ],
            'Pipeline' => [
                'Pipeline'        => $lead->project?->name,
                'Stage'           => $lead->stage?->name,
                'Rating'          => Lead::scoreLabel($lead->score),
                'Source'          => $lead->leadSource?->label ?? Lead::sourceLabel($lead->source),
                'Web form'        => $lead->form?->name,
                'Assigned to'     => $users ?: $lead->assignedTo?->name,
                'Estimated value' => (float) $lead->estimated_value > 0 ? $money($lead->estimated_value) : null,
                'Expected close'  => $date($lead->expected_close_date),
                'Customer'        => $lead->customer_id ? 'Existing customer' . ($lead->converted_at ? ' since ' . $date($lead->converted_at) : '') : null,
            ],
            'Record' => [
                'Lead ID'     => '#' . $lead->id,
                'Created'     => trim($date($lead->created_at, 'M j, Y g:i A') . ($lead->createdBy ? ' by ' . $lead->createdBy->name : '')),
                'Verified'    => $lead->verified_at ? trim($date($lead->verified_at) . ($lead->verifiedBy ? ' by ' . $lead->verifiedBy->name : '')) : null,
                'First reply' => $date($lead->first_response_at, 'M j, Y g:i A'),
            ],
            'Interest' => [
                'Products'      => $lead->products->map(fn ($p) => $p->name . ((float) $p->pivot->quantity > 0 ? ' × ' . rtrim(rtrim(number_format((float) $p->pivot->quantity, 3, '.', ''), '0'), '.') : ''))->implode(', '),
                'Interest note' => $lead->product_interest_note,
                'Lead notes'    => $lead->notes,
            ],
            'More details' => $lead->customFieldValues
                ->filter(fn (LeadCustomFieldValue $v) => $v->customField !== null && trim($v->displayValue()) !== '')
                ->mapWithKeys(fn (LeadCustomFieldValue $v) => [$v->customField->label => $v->displayValue()])
                ->all(),
        ];

        return array_filter(array_map(fn (array $g) => array_filter($g, fn ($v) => $v !== null && $v !== ''), $groups));
    }

    /**
     * What happened before this submission — this version and the ones it revises. Oldest first, the newest few.
     *
     * @return list<array{version: int, label: string, color: string, by: ?string, at: \Illuminate\Support\Carbon, comment: ?string}>
     */
    private function history(Quotation $quotation, ?QuotationApproval $submission): array
    {
        $ids = [];
        for ($q = $quotation, $depth = 0; $q !== null && $depth < 10; $q = $q->parent, $depth++) {
            $ids[] = $q->id;
        }

        return QuotationApproval::query()
            ->with('byUser:id,name')
            ->whereIn('quotation_id', $ids)
            ->when($submission, fn ($w) => $w->where('id', '!=', $submission->id))
            ->latest('id')
            ->limit(8)
            ->get()
            ->reverse()
            ->map(fn (QuotationApproval $a) => [
                'version' => (int) $a->version,
                'label'   => $a->label(),
                'color'   => QuotationApproval::ACTIONS[$a->action][2] ?? '#64748b',
                'by'      => $a->byUser?->name,
                'at'      => $a->created_at,
                'comment' => $a->comment,
            ])
            ->values()
            ->all();
    }
}
