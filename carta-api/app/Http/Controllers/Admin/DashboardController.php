<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ContentPackage;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\MobileUser;
use App\Models\Question;
use App\Models\School;
use App\Models\Student;
use App\Models\Topic;
use App\Support\Grading;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $questionQuery = Question::query()->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id));

        $analytics = $request->user()->isSchool()
            ? $this->schoolAnalytics($request->user()->school_id)
            : $this->platformAnalytics();

        return view('admin.dashboard', [
            'topicsCount' => Topic::count(),
            'questionsCount' => (clone $questionQuery)->count(),
            'activeQuestionsCount' => (clone $questionQuery)->where('is_active', true)->count(),
            'lockedQuestionsCount' => (clone $questionQuery)->where('is_locked', true)->count(),
            'approvedCount' => (clone $questionQuery)->where('status', 'approved')->count(),
            'reviewCount' => (clone $questionQuery)->where('status', 'review')->count(),
            'rejectedCount' => (clone $questionQuery)->where('status', 'rejected')->count(),
            'draftCount' => (clone $questionQuery)->where('status', 'draft')->count(),
            'recentQuestions' => (clone $questionQuery)->with('topic')->latest('updated_at')->limit(5)->get(),
            'classroomsCount' => Classroom::when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id))->count(),
            'examsCount' => Exam::when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id))->count(),
            'lastPackage' => ContentPackage::where('status', 'published')->latest('published_at')->first(),
            'schoolsCount' => School::where('is_active', true)->count(),
            'mobileUsersCount' => MobileUser::count(),
            'activeMobileUsersCount' => MobileUser::where('is_active', true)->count(),
            'mobileUsersWithActivityCount' => DB::table('mobile_answers')->distinct('mobile_user_id')->count('mobile_user_id'),
            'mobileExamsCompletedCount' => DB::table('mobile_exam_history')->count(),
        ] + $analytics);
    }

    /** @return array<string, mixed> */
    private function schoolAnalytics(int $schoolId): array
    {
        $attemptQuery = ExamAttempt::query()->whereHas('session.classroom', fn (Builder $query) => $query->where('school_id', $schoolId));
        $attempts = (clone $attemptQuery)->where('submitted_at', '>=', now()->subDays(30))->get(['student_id', 'score', 'total', 'weak_topics', 'submitted_at']);
        $studentsCount = Student::whereHas('classroom', fn (Builder $query) => $query->where('school_id', $schoolId))->where('is_active', true)->count();
        $validGradeSql = Grading::validGradeSql();
        $readyStudentsCount = (clone $attemptQuery)->select('student_id')
            ->selectRaw('SUM(CASE WHEN '.$validGradeSql.' THEN 1 ELSE 0 END) as valid_grades')
            ->groupBy('student_id')->havingRaw('SUM(CASE WHEN '.$validGradeSql.' THEN 1 ELSE 0 END) >= ?', [Grading::requiredValidGrades()])
            ->get()->count();
        $average = $attempts->isEmpty() ? 0 : (int) round($attempts->avg(fn ($attempt) => $attempt->total > 0 ? ($attempt->score / $attempt->total) * 100 : 0));
        $activeStudents = $attempts->pluck('student_id')->unique()->count();
        $daily = collect(range(6, 0))->map(function (int $daysAgo) use ($attempts): array {
            $date = now()->subDays($daysAgo);
            $dayAttempts = $attempts->filter(fn ($attempt) => $attempt->submitted_at?->isSameDay($date));

            return ['label' => $date->translatedFormat('D'), 'date' => $date->format('d/m'), 'count' => $dayAttempts->count(),
                'average' => $dayAttempts->isEmpty() ? 0 : (int) round($dayAttempts->avg(fn ($attempt) => $attempt->total ? ($attempt->score / $attempt->total) * 100 : 0))];
        });
        $weakTopics = $attempts->flatMap(fn ($attempt) => $attempt->weak_topics ?? [])->countBy()->sortDesc()->take(5)
            ->map(fn (int $count, string $topic) => ['name' => str($topic)->replace('_', ' ')->title()->toString(), 'count' => $count])->values();

        return [
            'studentsCount' => $studentsCount,
            'attemptsLast30Count' => $attempts->count(),
            'averageLast30' => $average,
            'readyStudentsCount' => $readyStudentsCount,
            'activeSessionsCount' => ExamSession::whereHas('classroom', fn (Builder $query) => $query->where('school_id', $schoolId))->where('status', 'in_progress')->count(),
            'studentParticipation' => $studentsCount ? (int) round(($activeStudents / $studentsCount) * 100) : 0,
            'dailySchoolActivity' => $daily,
            'schoolWeakTopics' => $weakTopics,
        ];
    }

    /** @return array<string, mixed> */
    private function platformAnalytics(): array
    {
        $months = collect(range(5, 0))->map(function (int $monthsAgo): array {
            $month = now()->subMonths($monthsAgo);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            return [
                'label' => $month->translatedFormat('M'),
                'users' => MobileUser::whereBetween('created_at', [$start, $end])->count(),
                'exams' => DB::table('mobile_exam_history')->whereBetween('completed_at', [$start, $end])->count(),
            ];
        });
        $mobileUsers = MobileUser::count();
        $activeUsers = DB::table('mobile_answers')->where('created_at', '>=', now()->subDays(30))->distinct()->count('mobile_user_id');
        $schoolActivity = School::query()->select(['schools.id', 'schools.name'])
            ->withCount(['classrooms as attempts_count' => fn ($query) => $query->join('exam_sessions', 'classrooms.id', '=', 'exam_sessions.classroom_id')->join('exam_attempts', 'exam_sessions.id', '=', 'exam_attempts.exam_session_id')->where('exam_attempts.submitted_at', '>=', now()->subDays(30))])
            ->orderByDesc('attempts_count')->limit(5)->get();

        return [
            'newMobileUsersCount' => MobileUser::where('created_at', '>=', now()->subDays(30))->count(),
            'mobileExamsLast30Count' => DB::table('mobile_exam_history')->where('completed_at', '>=', now()->subDays(30))->count(),
            'mobileEngagementRate' => $mobileUsers ? (int) round(($activeUsers / $mobileUsers) * 100) : 0,
            'publicationAgeDays' => ContentPackage::where('status', 'published')->latest('published_at')->first()?->published_at?->diffInDays(now()),
            'platformMonthlyTrend' => $months,
            'schoolActivity' => $schoolActivity,
        ];
    }
}
