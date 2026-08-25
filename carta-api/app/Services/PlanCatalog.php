<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PlanCatalog
{
    public function all(bool $activeOnly = true): Collection
    {
        return Plan::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->get();
    }

    public function purchasable(string $code): array
    {
        $requested = $code;
        $code = Plan::canonical($code);
        $plan = Plan::where('code', $code)->where('is_active', true)->where('is_purchasable', true)->first();

        if (! $plan) {
            throw ValidationException::withMessages(['plan' => 'Plano desconhecido ou indisponível para compra.']);
        }

        // Compatibilidade com configurações/testes anteriores durante a migração.
        $legacy = $requested === Plan::LEGACY_COMPLETE ? config('payments.plans.completo') : null;

        return [
            'chave' => $code,
            'nome' => $legacy['nome'] ?? $plan->name,
            'descricao' => $legacy['descricao'] ?? $plan->description,
            'preco' => (float) ($legacy['preco'] ?? $plan->price),
            'dias' => (int) ($legacy['dias'] ?? $plan->duration_days),
            'periodo' => $legacy['periodo'] ?? ($plan->duration_days ? $plan->duration_days.' dias' : null),
        ];
    }
}
