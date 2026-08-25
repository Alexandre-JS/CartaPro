<?php

namespace App\Services\Learning;

use App\Models\LearningEvent;
use App\Models\LearningProfile;
use App\Models\MobileUser;
use App\Models\ReadinessScore;
use App\Models\StudyRecommendation;
use App\Models\Topic;
use App\Models\TopicMastery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LearningEngine
{
    public function ensureFresh(MobileUser $user): LearningProfile
    {
        $profile = LearningProfile::where('mobile_user_id', $user->id)->first();

        return $profile?->calculated_at?->gte(now()->subMinutes(5)) ? $profile : $this->refresh($user);
    }

    public function recordSyncEvents(MobileUser $user, array $data): void
    {
        $topicIds = Topic::whereIn('slug', collect($data['answers'] ?? [])->pluck('tema')->merge(collect($data['revisions'] ?? [])->pluck('tema'))->unique())
            ->pluck('id', 'slug');

        foreach ($data['answers'] ?? [] as $item) {
            $this->record($user, [
                'type' => 'question_answered',
                'entity_type' => 'question',
                'entity_id' => $item['perguntaId'],
                'topic_id' => $topicIds[$item['tema']] ?? null,
                'result' => $item['acertou'],
                'duration_ms' => $item['duracaoMs'] ?? null,
                'metadata' => ['source' => $item['origem'] ?? 'simulado', 'selected_index' => $item['escolhida'] ?? null],
                'deduplication_key' => $item['clientId'],
                'occurred_at' => $this->fromMillis($item['data']),
            ]);
        }

        foreach ($data['exams'] ?? [] as $item) {
            $this->record($user, [
                'type' => 'simulation_completed',
                'entity_type' => 'simulation',
                'entity_id' => (string) $item['numero'],
                'result' => $item['total'] > 0 && ($item['acertos'] / $item['total']) >= .72,
                'duration_ms' => $item['tempoSegundos'] * 1000,
                'metadata' => ['total' => $item['total'], 'correct_answers' => $item['acertos']],
                'deduplication_key' => $item['clientId'],
                'occurred_at' => $this->fromMillis($item['data']),
            ]);
        }

        foreach ($data['readContents'] ?? [] as $contentKey) {
            $this->record($user, [
                'type' => 'lesson_read',
                'entity_type' => 'study_content',
                'entity_id' => $contentKey,
                'metadata' => [],
                'deduplication_key' => $contentKey,
                'occurred_at' => now(),
            ]);
        }
    }

    public function refresh(MobileUser $user): LearningProfile
    {
        $now = now();
        $answers = DB::table('mobile_answers')
            ->where('mobile_user_id', $user->id)
            ->select('topic')
            ->selectRaw('COUNT(*) as answers_count')
            ->selectRaw('SUM(CASE WHEN correct = 1 THEN 1 ELSE 0 END) as correct_answers')
            ->selectRaw('AVG(duration_ms) as average_duration_ms')
            ->selectRaw('MAX(answered_at) as last_practiced_at')
            ->groupBy('topic')->get();
        $topics = Topic::whereIn('slug', $answers->pluck('topic'))->get()->keyBy('slug');

        foreach ($answers as $row) {
            $topic = $topics->get($row->topic);
            if (! $topic) {
                continue;
            }
            $accuracy = $row->answers_count ? $row->correct_answers / $row->answers_count : 0;
            $confidence = min((int) $row->answers_count, 20) / 20;
            $recency = Carbon::parse($row->last_practiced_at)->gte($now->copy()->subDays(30)) ? 5 : 0;
            $score = (int) round(($accuracy * 80) + ($confidence * 15) + $recency);

            TopicMastery::updateOrCreate(
                ['mobile_user_id' => $user->id, 'topic_id' => $topic->id],
                [
                    'score' => min(100, $score),
                    'answers_count' => $row->answers_count,
                    'correct_answers' => $row->correct_answers,
                    'average_duration_ms' => $row->average_duration_ms ? (int) round($row->average_duration_ms) : null,
                    'last_practiced_at' => $row->last_practiced_at,
                    'calculated_at' => $now,
                ],
            );
        }

        $masteries = TopicMastery::where('mobile_user_id', $user->id)->with('topic:id,name,slug')->get();
        $recentExams = DB::table('mobile_exam_history')->where('mobile_user_id', $user->id)->latest('completed_at')->limit(10)->get();
        $masteryScore = $masteries->isEmpty() ? 0 : (int) round($masteries->avg('score'));
        $examScore = $recentExams->isEmpty() ? 0 : (int) round($recentExams->avg(fn ($exam) => $exam->total ? ($exam->correct_answers / $exam->total) * 100 : 0));
        $readiness = $recentExams->isEmpty() ? (int) round($masteryScore * .8) : (int) round(($masteryScore * .7) + ($examScore * .3));
        $breakdown = $masteries->mapWithKeys(fn (TopicMastery $mastery) => [$mastery->topic->slug => [
            'topicId' => $mastery->topic_id,
            'name' => $mastery->topic->name,
            'score' => $mastery->score,
            'answers' => $mastery->answers_count,
        ]])->all();

        ReadinessScore::updateOrCreate(['mobile_user_id' => $user->id], [
            'score' => min(100, $readiness),
            'breakdown' => $breakdown,
            'level' => match (true) {
                $readiness >= 80 => 'ready',
                $readiness >= 60 => 'progressing',
                $readiness > 0 => 'needs_focus',
                default => 'not_started',
            },
            'calculated_at' => $now,
        ]);

        $this->refreshRecommendations($user, $masteries);
        $lastActivity = LearningEvent::where('mobile_user_id', $user->id)->max('occurred_at');

        return LearningProfile::updateOrCreate(['mobile_user_id' => $user->id], [
            'last_activity_at' => $lastActivity,
            'calculated_at' => $now,
        ]);
    }

    private function refreshRecommendations(MobileUser $user, $masteries): void
    {
        StudyRecommendation::where('mobile_user_id', $user->id)->where('status', 'active')->update(['status' => 'completed']);

        if ($masteries->isEmpty()) {
            StudyRecommendation::updateOrCreate(
                ['mobile_user_id' => $user->id, 'topic_id' => null, 'type' => 'start_practice'],
                ['reason' => 'Responda às primeiras perguntas para iniciar o seu diagnóstico.', 'priority' => 100, 'status' => 'active'],
            );
        }

        foreach ($masteries->where('score', '<', 75)->sortBy('score')->take(3) as $index => $mastery) {
            StudyRecommendation::updateOrCreate(
                ['mobile_user_id' => $user->id, 'topic_id' => $mastery->topic_id, 'type' => 'practice_topic'],
                ['reason' => 'Reforce '.$mastery->topic->name.'; o domínio atual é '.$mastery->score.'%.', 'priority' => 90 - ($index * 10), 'status' => 'active'],
            );
        }

        $dueTopics = DB::table('mobile_revisions')->where('mobile_user_id', $user->id)->where('scheduled_for', '<=', now())
            ->select('topic')->selectRaw('COUNT(*) as total')->groupBy('topic')->get();
        $topics = Topic::whereIn('slug', $dueTopics->pluck('topic'))->get()->keyBy('slug');
        foreach ($dueTopics as $due) {
            if ($topic = $topics->get($due->topic)) {
                StudyRecommendation::updateOrCreate(
                    ['mobile_user_id' => $user->id, 'topic_id' => $topic->id, 'type' => 'review_due'],
                    ['reason' => $due->total.' pergunta(s) estão prontas para revisão.', 'priority' => 95, 'status' => 'active'],
                );
            }
        }
    }

    private function record(MobileUser $user, array $event): void
    {
        LearningEvent::updateOrCreate(
            ['mobile_user_id' => $user->id, 'type' => $event['type'], 'deduplication_key' => $event['deduplication_key']],
            $event + ['mobile_user_id' => $user->id],
        );
    }

    private function fromMillis(int $milliseconds): Carbon
    {
        return Carbon::createFromTimestampUTC(intdiv($milliseconds, 1000));
    }
}
