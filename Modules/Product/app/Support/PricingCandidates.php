<?php

namespace Modules\Product\Support;

final class PricingCandidates
{
    /**
     * Pick whichever discount candidate yields the lowest final price for the
     * customer. Candidates must expose `discount_type` ('flat'|'percentage')
     * and `discount_value` (e.g. ProductDiscount rows or CampaignDiscountCandidate).
     */
    public static function pickBest(iterable $candidates, float $originalPrice): mixed
    {
        $best      = null;
        $bestFinal = null;

        foreach ($candidates as $candidate) {
            $amount = $candidate->discount_type === 'percentage'
                ? $originalPrice * ((float) $candidate->discount_value / 100)
                : min((float) $candidate->discount_value, $originalPrice);
            $final = max(0.0, $originalPrice - $amount);

            if ($bestFinal === null || $final < $bestFinal) {
                $best      = $candidate;
                $bestFinal = $final;
            }
        }

        return $best;
    }
}
