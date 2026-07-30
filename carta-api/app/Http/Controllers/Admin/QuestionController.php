<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\LicenseCategory;
use App\Models\Question;
use App\Models\Sign;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $questions = Question::with(['topic', 'author', 'school'])
            ->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('statement', 'like', '%'.$request->string('q')->value().'%')
                ->orWhere('external_id', 'like', '%'.$request->string('q')->value().'%')))
            ->when($request->filled('topic'), fn ($query) => $query->where('topic_id', $request->integer('topic')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->value()))
            ->when($request->filled('category'), fn ($query) => $query->whereJsonContains('categories', $request->string('category')->value()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.questions.index', [
            'questions' => $questions,
            'topics' => Topic::orderBy('name')->get(),
            'categories' => LicenseCategory::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.questions.form', $this->formData(new Question));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['status'] = $request->user()->isSchool() || $request->input('action') === 'review' ? 'review' : ($request->input('action') === 'approve' ? 'approved' : 'draft');
        $data['author_id'] = $request->user()->id;
        $data['school_id'] = $request->user()->school_id;
        Question::create($data);

        return redirect()->route('admin.questions.index')->with('status', 'Pergunta criada com sucesso.');
    }

    public function edit(Question $question): View
    {
        $this->authorizeQuestion(request(), $question);

        return view('admin.questions.form', $this->formData($question));
    }

    public function update(Request $request, Question $question): RedirectResponse
    {
        $this->authorizeQuestion($request, $question);
        $data = $this->validated($request, $question);
        if ($request->user()->isSchool() || $request->input('action') === 'review') {
            $data['status'] = 'review';
            $data['rejection_reason'] = null;
        } elseif ($request->input('action') === 'approve') {
            $data['status'] = 'approved';
            $data['reviewed_by'] = $request->user()->id;
            $data['reviewed_at'] = now();
        } elseif ($request->input('action') === 'draft') {
            $data['status'] = 'draft';
        }
        $question->update($data);

        return redirect()->route('admin.questions.index')->with('status', 'Pergunta atualizada com sucesso.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $this->authorizeQuestion(request(), $question);
        $question->delete();

        return back()->with('status', 'Pergunta removida.');
    }

    private function validated(Request $request, ?Question $question = null): array
    {
        $data = $request->validate([
            'topic_id' => ['required', 'exists:topics,id'],
            'external_id' => ['required', 'alpha_dash', 'max:80', Rule::unique('questions')->ignore($question)],
            'type' => ['required', Rule::in(['teorico', 'pratico'])],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => [Rule::exists('license_categories', 'slug')->where('is_active', true)],
            'statement' => ['required', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'sign_id' => ['nullable', 'exists:signs,id'],
            'option_items' => ['required', 'array', 'min:2'],
            'option_items.*' => ['required', 'string', 'max:500'],
            'correct_index' => ['required', 'integer', 'min:0'],
            'explanation' => ['required', 'string'],
            'article_ref' => ['nullable', 'integer', 'min:1'],
            'article_id' => ['nullable', 'exists:articles,id'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_locked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $options = array_values(array_filter(array_map('trim', $data['option_items'])));
        abort_if(count($options) < 2, 422, 'Indique pelo menos duas opções.');
        abort_if((int) $data['correct_index'] >= count($options), 422, 'A opção correta não existe.');

        unset($data['option_items']);
        $data['options'] = $options;
        if ($data['sign_id'] ?? null) {
            $data['image'] = Sign::find($data['sign_id'])?->file_path;
        }
        if ($data['article_id'] ?? null) {
            $data['article_ref'] = Article::find($data['article_id'])?->number;
        }
        $data['is_locked'] = $request->boolean('is_locked');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function formData(Question $question): array
    {
        return [
            'question' => $question,
            'topics' => Topic::where('is_active', true)->orderBy('name')->get(),
            'categories' => LicenseCategory::where('is_active', true)->orderBy('sort_order')->get(),
            'signs' => Sign::where('is_active', true)->orderBy('name')->get(),
            'articles' => Article::where('is_active', true)->orderBy('number')->get(),
        ];
    }

    private function authorizeQuestion(Request $request, Question $question): void
    {
        abort_if($request->user()->isSchool() && ($question->school_id !== $request->user()->school_id || $question->status === 'approved'), 403);
    }
}
