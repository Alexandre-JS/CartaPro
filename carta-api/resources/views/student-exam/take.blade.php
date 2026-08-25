@extends('student-exam.layout')
@section('title',$session->exam->name)
@section('content')
<div class="exam-head"><div><span class="eyebrow">{{ $student->name }}</span><h1>{{ $session->exam->name }}</h1><p>{{ $session->exam->questions->count() }} perguntas · Sessão {{ $session->code }}</p></div></div>
<form method="POST" action="{{ $submitUrl }}" onsubmit="return confirm('Deseja submeter definitivamente a prova?')">@csrf
@foreach($session->exam->questions as $question)<article class="card question"><h2><span>{{ $loop->iteration }}</span>{{ $question->statement }}</h2>@if($question->image)<img src="{{ $question->image }}" alt="Imagem da pergunta">@endif<div class="options">@foreach($question->options as $option)<label><input type="radio" name="answers[{{ $question->external_id }}]" value="{{ $loop->index }}"><span>{{ chr(65+$loop->index) }}</span>{{ $option }}</label>@endforeach</div></article>@endforeach
<div class="submit-bar"><p>Confirme as respostas antes de terminar.</p><button>Submeter prova</button></div></form>
@endsection
