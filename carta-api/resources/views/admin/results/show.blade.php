@extends('layouts.admin')
@section('title','Resultado de '.$attempt->student->name)
@section('page-title','Resultado de '.$attempt->student->name)
@section('page-subtitle','Relatório completo e não editável da submissão.')
@section('content')
<div class="toolbar"><x-admin.button variant="secondary" :href="route('admin.results.index')">← Voltar aos resultados</x-admin.button><div class="actions"><x-admin.button variant="secondary" :href="route('admin.students.show',$attempt->student)">Histórico do estudante</x-admin.button><x-admin.state>Somente leitura</x-admin.state></div></div>
<section class="metric-grid">
    <article class="card metric-card"><span class="metric-icon green">✓</span><div><span>Respostas certas</span><strong>{{ $attempt->score }}/{{ $attempt->total }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon blue">%</span><div><span>Percentagem</span><strong>{{ $percentage }}%</strong></div></article>
    <article class="card metric-card"><span class="metric-icon yellow">20</span><div><span>Nota</span><strong>{{ $values }}</strong><small>valores</small></div></article>
    <article class="card metric-card"><span class="metric-icon {{ $attempt->qualifiesForAptitude() ? 'green' : 'yellow' }}">{{ $attempt->qualifiesForAptitude() ? '✓' : '—' }}</span><div><span>Para aptidão</span><strong style="font-size:18px">{{ $attempt->qualifiesForAptitude() ? 'Nota válida' : 'Abaixo de 14' }}</strong></div></article>
</section>
<section class="card detail-card" style="margin-top:14px;max-width:none"><dl class="detail-grid">
    <div class="detail-field"><dt>Estudante</dt><dd>{{ $attempt->student->name }}</dd></div><div class="detail-field"><dt>Identificador</dt><dd>{{ $attempt->student->identifier ?: '—' }}</dd></div>
    <div class="detail-field"><dt>Prova</dt><dd>{{ $attempt->session->exam->name }}</dd></div><div class="detail-field"><dt>Sessão</dt><dd>{{ $attempt->session->code }}</dd></div>
    <div class="detail-field"><dt>Turma</dt><dd>{{ $attempt->session->classroom->name }}</dd></div><div class="detail-field"><dt>Escola</dt><dd>{{ $attempt->session->exam->school?->name ?? $attempt->session->classroom->school?->name ?? '—' }}</dd></div>
    <div class="detail-field"><dt>Submetido em</dt><dd>{{ $attempt->submitted_at->format('d/m/Y H:i:s') }}</dd></div><div class="detail-field"><dt>Temas a reforçar</dt><dd>{{ collect($attempt->weak_topics)->map(fn($topic) => str($topic)->replace('_',' ')->title())->join(', ') ?: 'Nenhum' }}</dd></div>
</dl></section>
<div class="toolbar" style="margin-top:24px"><div><h2>Conferência das respostas</h2><p>Comparação entre o que o estudante respondeu e o gabarito da prova.</p></div><strong>{{ $answerReview->count() }} perguntas</strong></div>
<section class="answer-review">
@foreach($answerReview as $item)
    @php($question = $item['question'])
    <article class="card answer-card {{ $item['is_correct'] ? 'answer-correct' : 'answer-wrong' }}">
        <div class="answer-heading"><span class="option-letter">{{ $loop->iteration }}</span><div><small>{{ $question->external_id }} · {{ $question->topic->name }}</small><h3>{{ $question->statement }}</h3></div><x-admin.state :type="$item['is_correct'] ? 'approved' : 'rejected'">{{ $item['is_correct'] ? 'Correta' : 'Incorreta' }}</x-admin.state></div>
        @if($question->image)<div class="detail-image"><img src="{{ $question->image }}" alt="Imagem da pergunta"></div>@endif
        <div class="answer-comparison"><div><small>Resposta do estudante</small><strong>{{ $item['selected_index'] !== null ? chr(65 + $item['selected_index']).'. ' : '' }}{{ $item['selected_answer'] }}</strong></div><div><small>Resposta correta</small><strong>{{ chr(65 + $question->correct_index) }}. {{ $item['correct_answer'] }}</strong></div></div>
        <div class="answer-explanation"><small>Explicação</small><p>{{ $question->explanation }}</p></div>
    </article>
@endforeach
</section>
<style>.answer-review{display:grid;gap:14px}.answer-card{padding:20px;border-left:4px solid}.answer-card.answer-correct{border-left-color:var(--green-600)}.answer-card.answer-wrong{border-left-color:var(--red)}.answer-heading{display:grid;grid-template-columns:32px 1fr auto;align-items:start;gap:12px}.answer-heading h3{margin:3px 0 0;font-size:15px}.answer-heading small,.answer-comparison small,.answer-explanation small{color:var(--muted);font-size:10px;font-weight:700;text-transform:uppercase}.answer-comparison{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px}.answer-comparison>div{display:grid;gap:5px;padding:13px;border-radius:9px;background:#f7f9f7}.answer-explanation{margin-top:12px;padding-top:12px;border-top:1px solid var(--line)}.answer-explanation p{margin:4px 0 0}@media(max-width:760px){.answer-heading{grid-template-columns:32px 1fr}.answer-heading>.status{grid-column:2}.answer-comparison{grid-template-columns:1fr}}</style>
@endsection
