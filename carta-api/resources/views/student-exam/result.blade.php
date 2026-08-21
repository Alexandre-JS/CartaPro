@extends('student-exam.layout')
@section('title','Nota da prova')
@section('content')
<section class="card result-card"><span class="eyebrow">Prova submetida</span><h1>{{ $student->name }}</h1><p>{{ $session->exam->name }}</p><div class="grade"><strong>{{ $values }}</strong><span>valores</span></div><div class="result-details"><span>{{ $score }}/{{ $total }} respostas certas</span><span>{{ $percentage }}%</span></div><p class="muted">A sua resposta foi registada. O resultado completo será acompanhado pela escola.</p></section>
@endsection
