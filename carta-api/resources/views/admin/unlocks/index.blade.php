@extends('layouts.admin')
@section('title','Pagamentos')
@section('page-title','Pagamentos e acessos')
@section('page-subtitle','Acompanhe pagamentos, associações de conta e desbloqueios concedidos pelo apoio.')
@section('content')
<div class="payments-page">
    <x-admin.page-header id="payments-page-title" title="Pagamentos e acessos" description="O acesso pago só fica ativo quando o desbloqueio está ligado à conta correta." :count="$total" count-label="registos">
        <x-admin.button data-dialog-open="create-unlock"><i class="bi bi-plus-lg" aria-hidden="true"></i>Registar pagamento</x-admin.button>
    </x-admin.page-header>
    <section class="payments-summary" aria-label="Resumo de pagamentos"><div><span>Total de registos</span><strong>{{ $total }}</strong><small>histórico administrativo</small></div><div><span>Ativos</span><strong>{{ $ativos }}</strong><small>com acesso vigente</small></div><div><span>Associados</span><strong>{{ $associados }}</strong><small>ligados a uma conta</small></div><div><span>Ação pendente</span><strong>{{ $semConta }}</strong><small>ativos sem conta</small></div></section>
    @if($semConta)<div class="payments-alert"><i class="bi bi-person-exclamation" aria-hidden="true"></i><span><strong>{{ $semConta }} pagamento(s) ativo(s) sem conta associada.</strong> Associe a conta quando o aluno já estiver registado; até lá, o plano permanece gratuito.</span></div>@endif
    <form method="get" class="data-toolbar payments-filters" aria-label="Pesquisar pagamentos"><label class="pv-table-search"><i class="bi bi-search" aria-hidden="true"></i><input name="q" value="{{ request('q') }}" placeholder="Pesquisar por telefone ou referência" aria-label="Telefone ou referência"></label><x-admin.button type="submit" variant="secondary"><i class="bi bi-search" aria-hidden="true"></i>Pesquisar</x-admin.button>@if(request('q'))<x-admin.button variant="secondary" :href="route('admin.unlocks.index')">Limpar</x-admin.button>@endif</form>
    <x-admin.table class="payments-table" labelledby="payments-page-title" caption="Pagamentos e acessos"><x-slot:head><tr><th scope="col">Telefone</th><th scope="col">Plano e método</th><th scope="col">Data</th><th scope="col">Conta</th><th scope="col">Estado</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
        @forelse($unlocks as $unlock)<tr><td class="payment-phone"><strong>{{ $unlock->phone }}</strong><small>{{ $unlock->payment_reference ?: 'Sem referência' }}</small></td><td class="payment-method"><strong>{{ $unlock->plan }}</strong><small>{{ strtoupper($unlock->payment_method ?: 'manual') }}</small></td><td>{{ $unlock->unlocked_at->format('d/m/Y') }}<small class="payment-expiry">@if($unlock->expires_at) até {{ $unlock->expires_at->format('d/m/Y') }}@else sem validade definida @endif</small></td><td>@if($unlock->mobileUser)<x-admin.state type="approved">Associado</x-admin.state><small class="payment-account">{{ $unlock->mobileUser->email }}</small>@else<x-admin.state type="review">Sem conta</x-admin.state>@endif</td><td><x-admin.state :type="$unlock->is_active ? 'approved' : 'neutral'">{{ $unlock->is_active ? 'Ativo' : 'Inativo' }}</x-admin.state></td><td class="actions"><x-admin.row-actions label="Ações do pagamento"><button type="button" role="menuitem" data-dialog-open="edit-unlock-{{ $unlock->id }}"><i class="bi bi-pencil" aria-hidden="true"></i>Editar</button>@unless($unlock->mobileUser)<form method="POST" action="{{ route('admin.unlocks.bind',$unlock) }}" role="menuitem">@csrf @method('PATCH')<button type="submit"><i class="bi bi-link-45deg" aria-hidden="true"></i>Associar conta</button></form>@endunless<form method="POST" action="{{ route('admin.unlocks.destroy',$unlock) }}" role="menuitem" onsubmit="return confirm('Remover este desbloqueio?')">@csrf @method('DELETE')<button type="submit" class="is-danger"><i class="bi bi-trash3" aria-hidden="true"></i>Remover</button></form></x-admin.row-actions></td></tr>@empty<x-admin.empty-state table :colspan="6" icon="credit-card" title="Nenhum pagamento registado" description="Registe o primeiro pagamento para acompanhar o acesso do aluno." />@endforelse
    </x-admin.table><x-admin.pagination :paginator="$unlocks" />
</div>
@foreach($unlocks as $unlock)
<x-admin.dialog id="edit-unlock-{{ $unlock->id }}" title="Editar pagamento" description="Atualize os dados do desbloqueio sem alterar a conta associada.">
    <form id="edit-unlock-form-{{ $unlock->id }}" method="POST" action="{{ route('admin.unlocks.update', $unlock) }}" class="modal-form-grid">
        @csrf @method('PUT')
        <x-admin.field name="phone" label="Telefone" value="{{ $unlock->phone }}" required />
        <x-admin.field name="plan" label="Plano" value="{{ $unlock->plan }}" required />
        <x-admin.field as="select" name="payment_method" label="Método"><option value="mpesa" @selected($unlock->payment_method === 'mpesa')>M-Pesa</option><option value="emola" @selected($unlock->payment_method === 'emola')>e-Mola</option><option value="manual" @selected($unlock->payment_method === 'manual')>Manual</option><option value="outro" @selected($unlock->payment_method === 'outro')>Outro</option></x-admin.field>
        <x-admin.field name="payment_reference" label="Referência" value="{{ $unlock->payment_reference }}" />
        <x-admin.field name="unlocked_at" label="Data" type="datetime-local" value="{{ optional($unlock->unlocked_at)->format('Y-m-d\\TH:i') }}" required />
        <x-admin.field name="expires_at" label="Validade" type="datetime-local" value="{{ optional($unlock->expires_at)->format('Y-m-d\\TH:i') }}" />
        <x-admin.field name="notes" label="Notas" as="textarea" rows="3" class="modal-form-full">{{ $unlock->notes }}</x-admin.field>
        <label class="pv-checkbox modal-form-full"><input type="checkbox" name="is_active" value="1" @checked($unlock->is_active)> Acesso ativo</label>
    </form>
    <x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><x-admin.button type="submit" form="edit-unlock-form-{{ $unlock->id }}">Guardar alterações</x-admin.button></x-slot:footer>
</x-admin.dialog>
@endforeach
<x-admin.dialog id="create-unlock" title="Registar pagamento" description="Registe o pagamento recebido e defina o período de acesso.">
    <form id="create-unlock-form" method="POST" action="{{ route('admin.unlocks.store') }}" class="modal-form-grid">@csrf
        <x-admin.field name="phone" label="Telefone" placeholder="+258 84..." required /><x-admin.field name="plan" label="Plano" value="completo" required /><x-admin.field as="select" name="payment_method" label="Método"><option value="mpesa">M-Pesa</option><option value="emola">e-Mola</option><option value="manual">Manual</option><option value="outro">Outro</option></x-admin.field><x-admin.field name="payment_reference" label="Referência" /><x-admin.field name="unlocked_at" label="Data" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" required /><x-admin.field name="expires_at" label="Validade" type="datetime-local" /><x-admin.field name="notes" label="Notas" as="textarea" rows="3" class="modal-form-full" /><label class="pv-checkbox modal-form-full"><input type="checkbox" name="is_active" value="1" checked> Acesso ativo</label>
    </form><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><x-admin.button type="submit" form="create-unlock-form">Guardar pagamento</x-admin.button></x-slot:footer>
</x-admin.dialog>
@endsection
