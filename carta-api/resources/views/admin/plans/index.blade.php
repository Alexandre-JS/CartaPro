@extends('layouts.admin')
@section('title','Planos')
@section('page-title','Catálogo de planos')
@section('page-subtitle','Atualize preço, duração e disponibilidade sem alterar o código da aplicação.')
@section('content')
<div class="plans-page">
    <x-admin.page-header title="Planos ProntoVia" description="O backend aplica a prioridade School > Plus > Free. Altere apenas o que a operação realmente precisa." :count="$plans->count()" count-label="planos" />
    <div class="plans-list">
        @foreach($plans as $plan)
            <article class="admin-surface plan-editor">
                <div class="plan-editor-head"><div><span class="plan-code">{{ strtoupper($plan->name) }}</span><h2>{{ $plan->name }}</h2><p>{{ $plan->description ?: 'Sem descrição.' }}</p></div><x-admin.state :type="$plan->is_active ? 'approved' : 'neutral'">{{ $plan->is_active ? 'Ativo' : 'Inativo' }}</x-admin.state></div>
                <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="form-grid">@csrf @method('PUT')
                    <div class="field"><label for="plan-name-{{ $plan->id }}">Nome</label><input id="plan-name-{{ $plan->id }}" name="name" value="{{ old('name',$plan->name) }}" required></div>
                    <div class="field"><label for="plan-price-{{ $plan->id }}">Preço</label><input id="plan-price-{{ $plan->id }}" name="price" type="number" step="0.01" min="0" value="{{ old('price',$plan->price) }}" required></div>
                    <div class="field"><label for="plan-currency-{{ $plan->id }}">Moeda</label><input id="plan-currency-{{ $plan->id }}" name="currency" maxlength="3" value="{{ old('currency',$plan->currency) }}" required></div>
                    <div class="field"><label for="plan-days-{{ $plan->id }}">Duração (dias)</label><input id="plan-days-{{ $plan->id }}" name="duration_days" type="number" min="1" value="{{ old('duration_days',$plan->duration_days) }}"></div>
                    <div class="field"><label for="plan-order-{{ $plan->id }}">Ordem</label><input id="plan-order-{{ $plan->id }}" name="sort_order" type="number" min="0" value="{{ old('sort_order',$plan->sort_order) }}" required></div>
                    <div class="field full"><label for="plan-description-{{ $plan->id }}">Descrição</label><textarea id="plan-description-{{ $plan->id }}" name="description" rows="2">{{ old('description',$plan->description) }}</textarea></div>
                    <div class="field full"><label for="plan-features-{{ $plan->id }}">Recursos (um por linha)</label><textarea id="plan-features-{{ $plan->id }}" name="features" rows="3">{{ old('features',implode("\n",$plan->features ?: [])) }}</textarea></div>
                    <div class="checks full"><label><input type="checkbox" name="is_purchasable" value="1" @checked($plan->is_purchasable)> Disponível para compra individual</label><label><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Plano ativo</label></div>
                    <div class="form-actions full"><x-admin.button type="submit">Guardar alterações</x-admin.button></div>
                </form>
            </article>
        @endforeach
    </div>
</div>
@endsection
