@extends('layouts.admin')
@section('title','Aprovação')
@section('page-title','Fila de aprovação')
@section('page-subtitle','Revise perguntas antes de chegarem ao aplicativo.')
@section('content')
<nav class="tabs">@foreach(['review'=>'Por aprovar','approved'=>'Aprovadas','rejected'=>'Rejeitadas'] as $value=>$label)<a class="{{ $status===$value ? 'active' : '' }}" href="{{ route('admin.approvals.index',['status'=>$value]) }}">{{ $label }} ({{ $counts[$value] ?? 0 }})</a>@endforeach</nav>
<form class="filters"><input type="hidden" name="status" value="{{ $status }}"><select name="school_id"><option value="">Todas as escolas e autoria interna</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(request('school_id')==$school->id)>{{ $school->name }}</option>@endforeach</select><button class="btn light">Filtrar</button></form>
@forelse($questions as $question)<article class="card approval-card"><div class="approval-head"><div><span class="status {{ $question->status }}">#{{ $question->external_id }} · {{ ['review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'][$question->status] }}</span><h3>{{ $question->statement }}</h3><small>{{ $question->topic->name }} · {{ $question->school?->name ?? 'Autoria interna' }} · Artigo {{ $question->article_ref ?: '—' }}</small></div><a class="btn light small" href="{{ route('admin.questions.edit',$question) }}">Ver detalhes</a></div>
@if($question->image)<img src="{{ $question->image }}" alt="Imagem da pergunta" style="display:block;max-width:130px;max-height:120px;margin:12px 0;object-fit:contain">@endif
<div class="question-options">@foreach($question->options as $index=>$option)<div class="question-option {{ $index===$question->correct_index ? 'correct' : '' }}"><span class="option-letter">{{ chr(65+$index) }}</span><span>{{ $option }}</span>@if($index===$question->correct_index)<strong style="margin-left:auto;color:#239a3f">✓</strong>@endif</div>@endforeach</div>
<small><strong>Explicação:</strong> {{ $question->explanation }}</small>
@if($status==='review')<div class="approval-actions"><form method="POST" action="{{ route('admin.approvals.approve',$question) }}">@csrf @method('PATCH')<button class="btn">Aprovar</button></form><form class="reject-field" method="POST" action="{{ route('admin.approvals.reject',$question) }}">@csrf @method('PATCH')<input name="rejection_reason" placeholder="Motivo da rejeição" required><button class="btn danger">Rejeitar</button></form></div>@elseif($question->reviewer)<div style="margin-top:12px"><small>Revista por {{ $question->reviewer->name }} em {{ $question->reviewed_at?->format('d/m/Y H:i') }}.</small></div>@endif
</article>@empty<div class="card empty">Não existem perguntas neste estado.</div>@endforelse
<div class="pagination">{{ $questions->links() }}</div>
@endsection
