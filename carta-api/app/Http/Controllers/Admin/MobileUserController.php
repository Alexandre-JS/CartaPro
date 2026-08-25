<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\MobileUser;
use App\Models\Unlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MobileUserController extends Controller
{
    public function index(Request $request): View
    {
        $users = MobileUser::query()
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', '%'.$request->string('q')->value().'%')
                ->orWhere('email', 'like', '%'.$request->string('q')->value().'%')
                ->orWhere('phone', 'like', '%'.$request->string('q')->value().'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->value() === 'active'))
            ->withCount(['answers', 'examHistory as exams_count', 'readContents as read_contents_count'])
            ->withMax('tokens as last_seen_at', 'last_used_at')
            ->latest()->paginate(10)->withQueryString();

        $unlocks = Unlock::all()->keyBy(fn (Unlock $unlock) => $this->phoneKey($unlock->phone));

        return view('admin.mobile-users.index', compact('users', 'unlocks'));
    }

    public function show(MobileUser $mobileUser): View
    {
        $answers = $mobileUser->answers()->latest('answered_at')->get();
        $exams = $mobileUser->examHistory()->latest('completed_at')->get();
        $readContents = $mobileUser->readContents()->latest('updated_at')->get();
        $revisions = $mobileUser->revisions()->orderBy('scheduled_for')->get();
        $unlock = Unlock::all()->first(fn (Unlock $item) => $this->phoneKey($item->phone) === $this->phoneKey($mobileUser->phone));
        $weaknesses = $answers->groupBy('topic')->map(function ($items, string $topic): array {
            $total = $items->count();
            $correct = $items->where('correct', true)->count();

            return ['topic' => $topic, 'total' => $total, 'errors' => $total - $correct, 'accuracy' => round($correct / max(1, $total) * 100, 1)];
        })->sortBy([['accuracy', 'asc'], ['total', 'desc']])->values();
        $articleNumbers = $readContents->pluck('content_key')->map(fn (string $key) => (int) str($key)->afterLast(':')->value())->filter()->unique();
        $articles = Article::whereIn('number', $articleNumbers)->get()->keyBy('number');

        return view('admin.mobile-users.show', [
            'mobileUser' => $mobileUser, 'unlock' => $unlock, 'answers' => $answers, 'exams' => $exams,
            'readContents' => $readContents, 'revisions' => $revisions, 'weaknesses' => $weaknesses, 'articles' => $articles,
            'answerAccuracy' => round($answers->where('correct', true)->count() / max(1, $answers->count()) * 100, 1),
            'examAverage' => round($exams->sum('correct_answers') / max(1, $exams->sum('total')) * 100, 1),
            'lastSeenAt' => $mobileUser->tokens()->max('last_used_at'),
        ]);
    }

    public function updateStatus(MobileUser $mobileUser): RedirectResponse
    {
        $mobileUser->update(['is_active' => ! $mobileUser->is_active]);
        if (! $mobileUser->is_active) {
            $mobileUser->tokens()->delete();
        }

        return back()->with('status', $mobileUser->is_active ? 'Conta do aplicativo ativada.' : 'Conta do aplicativo desativada e sessões terminadas.');
    }

    private function phoneKey(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return strlen($digits) === 9 ? '258'.$digits : $digits;
    }
}
