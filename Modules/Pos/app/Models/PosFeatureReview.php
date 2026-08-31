<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Business\Models\Business;

class PosFeatureReview extends Model
{
    protected $table = 'pos_feature_reviews';

    protected $fillable = [
        'business_id',
        'reviewer_key',
        'reviewer_name',
        'feature_key',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
