@extends('student-exam.layout')
@section('title','Entrar na prova')
@section('content')
<section class="card entry-card"><span class="eyebrow">{{ $session->classroom->school->name ?? 'ProntoVia' }}</span><h1>{{ $session->exam->name }}</h1><p>Turma {{ $session->classroom->name }}</p>@if($session->status === 'in_progress')<form method="POST" action="{{ route('student-exam.enter',$session->code) }}">@csrf<div class="field"><label>Seu nome completo</label><input name="name" value="{{ old('name') }}" autocomplete="name" required><small>Este nome será apresentado à escola junto da sua nota.</small></div><div class="field"><label>Código da sessão</label><input name="code" value="{{ $session->code }}" maxlength="6" required></div><button>Entrar na prova</button></form>@elseif($session->status === 'scheduled')<div class="message">Aguarde o professor iniciar esta sessão.</div>@else<div class="message">Esta sessão já foi encerrada.</div>@endif</section>
@endsection
