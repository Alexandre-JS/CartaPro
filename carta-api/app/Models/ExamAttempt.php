<?php

namespace App\Models;

use App\Support\Grading;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_session_id', 'student_id', 'answers', 'score', 'total', 'passed', 'weak_topics', 'topic_breakdown', 'duration_seconds', 'submitted_at'])]
class ExamAttempt extends Model
{
    protected function casts(): array
    {
        return ['answers' => 'array', 'weak_topics' => 'array', 'topic_breakdown' => 'array', 'passed' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function percentage(): float
    {
        return Grading::percentage($this->score, $this->total);
    }

    public function gradeValues(): float
    {
        return Grading::values($this->score, $this->total);
    }

    public function qualifiesForAptitude(): bool
    {
        return Grading::qualifiesForAptitude($this->score, $this->total);
    }
}
