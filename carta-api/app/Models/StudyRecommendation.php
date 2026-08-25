<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mobile_user_id', 'topic_id', 'type', 'reason', 'priority', 'status', 'expires_at'])]
class StudyRecommendation extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
