@extends('layouts.admin')
@section('title','Aprovação')
@section('page-title','Fila de aprovação')
@section('page-subtitle','Revise perguntas antes de chegarem ao aplicativo.')
@section('content')
<div class="approval-page">
    <x-admin.page-header id="approval-page-title" title="Fila de aprovação" description="Revise o conteúdo com contexto suficiente para decidir com segurança." :count="$questions->total()" count-label="perguntas">
        <x-admin.button variant="secondary" :href="route('admin.questions.index')"><i class="bi bi-journal-text" aria-hidden="true"></i>Banco de perguntas</x-admin.button>
    </x-admin.page-header>
    <nav class="approval-tabs" aria-label="Estado da aprovação">
        @foreach(['review'=>'Por aprovar','approved'=>'Aprovadas','rejected'=>'Rejeitadas'] as $value=>$label)
            <a class="{{ $status === $value ? 'is-active' : '' }}" href="{{ route('admin.approvals.index',['status'=>$value] + (request('school_id') ? ['school_id'=>request('school_id')] : [])) }}">{{ $label }} <span>{{ $counts[$value] ?? 0 }}</span></a>
        @endforeach
    </nav>
    <form method="get" class="data-toolbar approval-filters" aria-label="Filtrar fila de aprovação">
        <input type="hidden" name="status" value="{{ $status }}">
        <label class="field"><span>Autoria</span><select name="school_id"><option value="">Todas as escolas e autoria interna</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(request('school_id') == $school->id)>{{ $school->name }}</option>@endforeach</select></label>
        <x-admin.button type="submit" variant="secondary"><i class="bi bi-funnel" aria-hidden="true"></i>Aplicar filtro</x-admin.button>
    </form>
    <section class="approval-queue" aria-labelledby="approval-page-title">
        @forelse($questions as $question)
            <article class="approval-item">
                <header class="approval-item-head">
                    <div class="approval-item-context"><x-admin.state :type="$question->status === 'approved' ? 'approved' : ($question->status === 'rejected' ? 'rejected' : 'review')">{{ ['review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'][$question->status] }}</x-admin.state><span>{{ $question->topic->name }} · {{ $question->school?->name ?? 'Autoria interna' }}</span></div>
                    <a class="pv-row-primary" href="{{ route('admin.questions.edit',$question) }}">Ver detalhes</a>
                </header>
                <h3>{{ $question->statement }}</h3>
                @if($question->image)<img class="approval-image" src="{{ $question->image }}" alt="Imagem da pergunta">@endif
                <div class="approval-options">@foreach($question->options as $index=>$option)<div class="approval-option {{ $index === $question->correct_index ? 'is-correct' : '' }}"><span>{{ chr(65 + $index) }}</span><p>{{ $option }}</p>@if($index === $question->correct_index)<i class="bi bi-check2" aria-label="Resposta correta"></i>@endif</div>@endforeach</div>
                <p class="approval-explanation"><strong>Explicação:</strong> {{ $question->explanation }}</p>
                @if($status === 'review')
                    <footer class="approval-actions approval-actions--visual"><form method="POST" action="{{ route('admin.approvals.approve',$question) }}">@csrf @method('PATCH')<x-admin.button type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Aprovar</x-admin.button></form><form class="approval-reject-form" method="POST" action="{{ route('admin.approvals.reject',$question) }}">@csrf @method('PATCH')<input name="rejection_reason" placeholder="Motivo da rejeição" required><x-admin.button type="submit" variant="danger"><i class="bi bi-arrow-return-left" aria-hidden="true"></i>Rejeitar</x-admin.button></form></footer>
                @elseif($question->reviewer)
                    <footer class="approval-review-meta">Revista por {{ $question->reviewer->name }} em {{ $question->reviewed_at?->format('d/m/Y H:i') }}@if($question->rejection_reason) · Motivo: {{ $question->rejection_reason }}@endif</footer>
                @endif
            </article>
        @empty
            <x-admin.empty-state icon="check2-circle" title="Não existem perguntas neste estado" description="A fila será atualizada quando houver conteúdo para rever." />
        @endforelse
    </section>
    <x-admin.pagination :paginator="$questions" />
</div>
@endsection
