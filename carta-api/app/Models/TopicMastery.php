<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mobile_user_id', 'topic_id', 'score', 'answers_count', 'correct_answers', 'average_duration_ms', 'last_practiced_at', 'calculated_at'])]
class TopicMastery extends Model
{
    protected function casts(): array
    {
        return ['last_practiced_at' => 'datetime', 'calculated_at' => 'datetime'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
