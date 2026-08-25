@extends('layouts.admin')
@section('title', $exam->name)
@section('page-title', $exam->name)
@section('page-subtitle', 'Configuração, publicação e perguntas selecionadas.')
@section('content')
<section class="exam-detail" aria-labelledby="exam-detail-title">
    <header class="exam-detail-head">
        <div><a class="exam-detail-back" href="{{ route('admin.exams.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar às provas</a><div class="exam-detail-title-row"><h2 id="exam-detail-title">{{ $exam->name }}</h2><x-admin.state :type="$exam->is_public ? 'active' : 'neutral'">{{ $exam->is_public ? 'Pública · app' : 'Privada · escola' }}</x-admin.state></div><p>{{ $exam->school?->name ?? 'ProntoVia · plataforma' }} · criada por {{ $exam->creator?->name ?? 'Sistema' }}</p></div>
        <div class="exam-detail-actions"><x-admin.button variant="secondary" :href="route('admin.exams.index')">Voltar</x-admin.button><x-admin.button :href="route('admin.exams.edit', $exam)"><i class="bi bi-pencil" aria-hidden="true"></i>Editar prova</x-admin.button></div>
    </header>

    <section class="exam-summary" aria-label="Resumo da prova">
        <div><span>Acesso</span><strong>{{ $exam->is_public ? 'Aplicativo' : 'Escola' }}</strong></div><div><span>Publicação</span><strong>{{ $exam->is_public ? (['published'=>'Publicada','archived'=>'Arquivada','draft'=>'Rascunho'][$exam->publication_status] ?? ucfirst($exam->publication_status)) : 'Não aplicável' }}</strong></div><div><span>Perguntas</span><strong>{{ $exam->questions_count ?? $exam->questions->count() }}</strong></div><div><span>Nota mínima</span><strong>{{ $exam->pass_score }}/{{ $exam->question_count }}</strong></div><div><span>Sessões</span><strong>{{ $exam->sessions_count }}</strong></div><div><span>Tentativas</span><strong>{{ $exam->attempts_count }}</strong></div>
    </section>

    <section class="exam-detail-grid">
        <article class="exam-detail-surface"><div class="exam-detail-section-head"><div><h3>Configuração</h3><p>Parâmetros usados na aplicação da prova.</p></div></div><dl class="exam-definition-list"><div><dt>Tipo</dt><dd>{{ ucfirst($exam->type) }}</dd></div><div><dt>Categorias</dt><dd>{{ implode(', ', $exam->license_categories ?: [$exam->license_category]) }}</dd></div><div><dt>Duração</dt><dd>{{ $exam->duration_minutes }} minutos</dd></div><div><dt>Acesso</dt><dd>{{ $exam->is_locked ? 'Plano completo' : 'Gratuita' }}</dd></div><div><dt>Modo de seleção</dt><dd>{{ $exam->selection_mode === 'blueprint' ? 'Por critérios' : 'Manual' }}</dd></div><div><dt>Estado</dt><dd>{{ $exam->is_active ? 'Ativa' : 'Inativa' }}</dd></div></dl></article>
        <article class="exam-detail-surface"><div class="exam-detail-section-head"><div><h3>Estado editorial</h3><p>Disponibilidade e publicação no aplicativo.</p></div><i class="bi bi-cloud-check" aria-hidden="true"></i></div><div class="exam-publication-status"><x-admin.state :type="$exam->is_public ? $exam->publication_status : 'neutral'">{{ $exam->is_public ? (['published'=>'Publicada','archived'=>'Arquivada','draft'=>'Rascunho'][$exam->publication_status] ?? ucfirst($exam->publication_status)) : 'Privada · escola' }}</x-admin.state><small>{{ $exam->published_at ? 'Publicada em '.$exam->published_at->format('d/m/Y H:i') : 'Ainda não publicada no aplicativo.' }}</small></div></article>
    </section>

    <section class="exam-detail-surface exam-questions-surface" aria-labelledby="selected-questions-title"><div class="exam-detail-section-head"><div><h3 id="selected-questions-title">Perguntas selecionadas</h3><p>{{ $exam->questions->count() }} perguntas nesta prova.</p></div><span class="exam-detail-muted">Nota mínima {{ $exam->pass_score }}/{{ $exam->question_count }}</span></div><div class="exam-question-table"><table class="data-table data-table--hover"><thead><tr><th>#</th><th>Enunciado</th><th>Tema</th><th>Tipo</th></tr></thead><tbody>@forelse($exam->questions as $index => $question)<tr><td>{{ $index + 1 }}</td><td title="{{ $question->statement }}">{{ str($question->statement)->limit(120) }}</td><td>{{ $question->topic?->name ?? '—' }}</td><td>{{ ucfirst($question->type) }}</td></tr>@empty<x-admin.empty-state table :colspan="4" title="Sem perguntas selecionadas" description="Edite a prova para adicionar perguntas." />@endforelse</tbody></table></div></section>
</section>
@endsection
