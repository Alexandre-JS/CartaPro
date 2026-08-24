@extends('layouts.admin')
@section('title', 'Turmas')
@section('page-title', 'Turmas e alunos')
@section('page-subtitle', 'Organize alunos para aplicação e acompanhamento de provas.')
@section('content')
<section class="split-grid">
<form class="card inline-form" method="POST" action="{{ route('admin.classrooms.store') }}">@csrf
    @if(auth()->user()->isAdmin())<x-admin.field as="select" name="school_id" label="Escola" required full><option value="">Selecione</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}</option>@endforeach</x-admin.field>@endif
    <x-admin.field name="name" label="Nome da turma" :value="old('name')" required />
    <x-admin.field name="code" label="Código" :value="old('code')" required />
    <x-admin.field name="year" label="Ano" type="number" :value="old('year', now()->year)" min="2000" max="2100" />
    <div class="field"><div class="checks"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Ativa</label></div></div>
    <div class="form-actions full"><x-admin.button type="submit" loading-label="A criar…">Criar turma</x-admin.button></div>
</form>
<div><div class="toolbar"><div><h2>Turmas cadastradas</h2><p>{{ $classrooms->total() }} turmas</p></div></div>
@forelse($classrooms as $classroom)
<article class="card approval-card"><div class="approval-head"><div><x-admin.state :type="$classroom->is_active ? 'active' : 'neutral'">{{ $classroom->school->name }}</x-admin.state><h3>{{ $classroom->name }} · {{ $classroom->code }}</h3><small>{{ $classroom->students_count }} alunos · {{ $classroom->sessions_count }} sessões</small></div><x-admin.button variant="danger" size="small" data-dialog-open="delete-classroom-{{ $classroom->id }}">Remover</x-admin.button></div>
<form class="student-quick-form" method="POST" action="{{ route('admin.students.store', $classroom) }}">@csrf
    <x-admin.field name="name" label="Nome do aluno" required /><x-admin.field name="identifier" label="Identificador" /><x-admin.field name="phone" label="Telefone" /><x-admin.button size="small" type="submit" loading-label="A adicionar…">Adicionar aluno</x-admin.button>
</form>
<div class="question-options">@forelse($classroom->students as $student)<div class="question-option"><span class="option-letter">{{ str($student->name)->substr(0, 1) }}</span><span><strong><a href="{{ route('admin.students.show',$student) }}">{{ $student->name }}</a></strong><br><small>{{ trim($student->identifier.' '.$student->phone) ?: 'Sem identificador ou telefone' }}</small></span><x-admin.button variant="danger" size="small" data-dialog-open="delete-student-{{ $student->id }}" aria-label="Remover {{ $student->name }}">×</x-admin.button></div>@empty<x-admin.empty-state title="Sem alunos nesta turma" description="Adicione o primeiro aluno usando o formulário acima." icon="people" />@endforelse</div></article>
@empty<x-admin.empty-state class="card" title="Ainda não existem turmas" description="Crie a primeira turma para começar a acompanhar alunos." icon="people" />@endforelse
<x-admin.pagination :paginator="$classrooms" /></div></section>
@foreach($classrooms as $classroom)
<x-admin.dialog id="delete-classroom-{{ $classroom->id }}" title="Remover turma?" description="A remoção poderá ser impedida se existirem alunos ou resultados associados." size="small"><p>Vai tentar remover <strong>{{ $classroom->name }}</strong>.</p><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit" loading-label="A remover…">Remover turma</x-admin.button></form></x-slot:footer></x-admin.dialog>
@foreach($classroom->students as $student)<x-admin.dialog id="delete-student-{{ $student->id }}" title="Remover aluno?" description="O histórico associado deve ser preservado sempre que existirem resultados." size="small"><p>Vai tentar remover <strong>{{ $student->name }}</strong> da turma.</p><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.students.destroy', $student) }}">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit" loading-label="A remover…">Remover aluno</x-admin.button></form></x-slot:footer></x-admin.dialog>@endforeach
@endforeach
@endsection
