<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ContentPackage;
use App\Models\Exam;
use App\Models\MobileUser;
use App\Models\Question;
use App\Models\School;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $questionQuery = Question::query()->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id));

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
        ]);
    }
}
