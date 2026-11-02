<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;

/**
 * DECISION: a lead's score is the manual Hot / Warm / Cold that staff or the head set
 * (crm_leads.score). Next to it the board shows a suggested score from simple rules, as a hint
 * only — it is never saved:
 *
 *   source          referral / walk-in 25 · web, WhatsApp, phone 15 · social, email, SMS 10 · other 5
 *   estimated value ≥ 2× the average of the leads shown 25 · ≥ the average 15 · any 5
 *   recent activity a message, activity or logged follow-up in 3 days 25 · 7 days 15 · 14 days 5
 *                   · nothing for 30 days −10
 *   quotation sent  25 (the lead is in Quotation Sent or Negotiation)
 *
 *   hot ≥ 60 · warm ≥ 35 · else cold
 */
class LeadScoreService
{
    const HOT_FROM  = 60;
    const WARM_FROM = 35;

    private const SOURCE_POINTS = [
        'referral' => 25, 'walk-in' => 25,
        'website' => 15, 'public-form' => 15, 'google-form' => 15, 'whatsapp' => 15, 'phone' => 15,
        'facebook' => 10, 'instagram' => 10, 'social' => 10, 'email' => 10, 'message' => 10,
    ];

    /**
     * Suggested scores for many leads with a fixed number of queries.
     *
     * @param  Collection<int, Lead>  $leads
     * @return array<int, array{score: string, label: string, points: int, reasons: list<string>}>
     */
    public function suggestFor(Collection $leads): array
    {
        if ($leads->isEmpty()) {
            return [];
        }

        $ids          = $leads->pluck('id')->map(fn ($id) => (int) $id)->all();
        $lastActivity = $this->lastActivity($ids);
        $values       = $leads->pluck('estimated_value')->filter(fn ($v) => $v !== null && (float) $v > 0)->map(fn ($v) => (float) $v);
        $average      = $values->isEmpty() ? null : $values->avg();

        $out = [];
        foreach ($leads as $lead) {
            $out[(int) $lead->id] = $this->suggest($lead, $lastActivity[(int) $lead->id] ?? null, $average);
        }

        return $out;
    }

    /** @return array{score: string, label: string, points: int, reasons: list<string>} */
    public function suggest(Lead $lead, ?Carbon $lastActivity, ?float $averageValue): array
    {
        $points  = 0;
        $reasons = [];

        $source = self::SOURCE_POINTS[$lead->source] ?? 5;
        $points += $source;
        if ($source >= 15) {
            $reasons[] = (Lead::sourceLabel($lead->source) ?? 'Source') . ' lead';
        }

        $value = $lead->estimated_value !== null ? (float) $lead->estimated_value : 0.0;
        if ($value > 0) {
            if ($averageValue !== null && $value >= 2 * $averageValue) {
                $points += 25;
                $reasons[] = 'High estimated value';
            } elseif ($averageValue !== null && $value >= $averageValue) {
                $points += 15;
                $reasons[] = 'Above-average value';
            } else {
                $points += 5;
            }
        }

        $days = $lastActivity?->diffInDays(now());
        if ($days !== null && $days <= 3) {
            $points += 25;
            $reasons[] = 'Active in the last 3 days';
        } elseif ($days !== null && $days <= 7) {
            $points += 15;
            $reasons[] = 'Active this week';
        } elseif ($days !== null && $days <= 14) {
            $points += 5;
        } elseif ($days === null || $days > 30) {
            $points -= 10;
            $reasons[] = 'No recent activity';
        }

        // The quotation workflow puts the lead in Quotation Sent, then Negotiation
        if (in_array($lead->stage?->system_key, [LeadStage::STATUS_QUOTATION_SENT, LeadStage::STATUS_NEGOTIATION], true)) {
            $points += 25;
            $reasons[] = 'Quotation sent';
        }

        $score = $points >= self::HOT_FROM ? 'hot' : ($points >= self::WARM_FROM ? 'warm' : 'cold');

        return ['score' => $score, 'label' => Lead::scoreLabel($score), 'points' => max(0, $points), 'reasons' => $reasons];
    }

    /**
     * The latest message, activity or logged follow-up per lead.
     *
     * @param  int[]  $ids
     * @return array<int, Carbon>
     */
    private function lastActivity(array $ids): array
    {
        $morph = (new Lead)->getMorphClass();
        $rows  = collect()
            ->concat(DB::table('crm_activities')->where('subject_type', $morph)->whereIn('subject_id', $ids)
                ->groupBy('subject_id')->selectRaw('subject_id as lead_id, max(occurred_at) as at')->get())
            ->concat(DB::table('crm_lead_messages')->whereIn('lead_id', $ids)
                ->groupBy('lead_id')->selectRaw('lead_id, max(created_at) as at')->get())
            ->concat(DB::table('crm_follow_ups')->whereIn('lead_id', $ids)->whereNotNull('done_at')
                ->groupBy('lead_id')->selectRaw('lead_id, max(done_at) as at')->get());

        $latest = [];
        foreach ($rows as $row) {
            if ($row->at === null) {
                continue;
            }
            $at = Carbon::parse($row->at);
            $id = (int) $row->lead_id;
            if (! isset($latest[$id]) || $at->gt($latest[$id])) {
                $latest[$id] = $at;
            }
        }

        return $latest;
    }
}
