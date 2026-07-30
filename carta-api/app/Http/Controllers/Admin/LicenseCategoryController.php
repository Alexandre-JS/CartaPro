<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LicenseCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', ['categories' => LicenseCategory::orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        LicenseCategory::create($this->validated($request));

        return back()->with('status', 'Categoria adicionada.');
    }

    public function update(Request $request, LicenseCategory $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return back()->with('status', 'Categoria atualizada.');
    }

    public function destroy(LicenseCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('status', 'Categoria removida.');
    }

    private function validated(Request $request, ?LicenseCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('license_categories')->ignore($category)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
