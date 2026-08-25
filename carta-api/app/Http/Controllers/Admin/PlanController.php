<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', ['plans' => Plan::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'string'],
            'is_purchasable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
        $data['features'] = collect(preg_split('/\\r?\\n/', (string) ($data['features'] ?? '')))->map(fn (string $feature) => trim($feature))->filter()->values()->all();
        $data['is_purchasable'] = $request->boolean('is_purchasable');
        $data['is_active'] = $request->boolean('is_active');
        $plan->update($data);

        return back()->with('status', "Plano {$plan->name} atualizado.");
    }
}
