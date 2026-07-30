<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TopicController extends Controller
{
    public function index(): View
    {
        return view('admin.topics.index', ['topics' => Topic::withCount('questions')->orderBy('sort_order')->paginate(15)]);
    }

    public function create(): View
    {
        return view('admin.topics.form', ['topic' => new Topic]);
    }

    public function store(Request $request): RedirectResponse
    {
        Topic::create($this->validated($request));

        return redirect()->route('admin.topics.index')->with('status', 'Tema criado com sucesso.');
    }

    public function edit(Topic $topic): View
    {
        return view('admin.topics.form', compact('topic'));
    }

    public function update(Request $request, Topic $topic): RedirectResponse
    {
        $topic->update($this->validated($request, $topic));

        return redirect()->route('admin.topics.index')->with('status', 'Tema atualizado com sucesso.');
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        $topic->delete();

        return back()->with('status', 'Tema removido.');
    }

    private function validated(Request $request, ?Topic $topic = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('topics')->ignore($topic)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
