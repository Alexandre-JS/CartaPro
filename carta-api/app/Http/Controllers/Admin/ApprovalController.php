<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->value() ?: 'review';
        abort_unless(in_array($status, ['review', 'approved', 'rejected'], true), 404);

        return view('admin.approvals.index', [
            'status' => $status,
            'questions' => Question::with(['topic', 'reviewer', 'school'])->where('status', $status)
                ->when($request->filled('school_id'), fn ($query) => $query->where('school_id', $request->integer('school_id')))
                ->latest()->paginate(12)->withQueryString(),
            'counts' => Question::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'schools' => School::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function approve(Request $request, Question $question): RedirectResponse
    {
        $question->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('status', 'Pergunta aprovada e disponível para publicação.');
    }

    public function reject(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        $question->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return back()->with('status', 'Pergunta rejeitada e devolvida para correção.');
    }
}
