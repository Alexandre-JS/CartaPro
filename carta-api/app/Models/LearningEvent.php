<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mobile_user_id', 'type', 'entity_type', 'entity_id', 'topic_id', 'result', 'duration_ms', 'metadata', 'deduplication_key', 'occurred_at'])]
class LearningEvent extends Model
{
    protected function casts(): array
    {
        return ['result' => 'boolean', 'metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
