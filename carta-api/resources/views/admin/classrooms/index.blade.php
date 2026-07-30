@extends('layouts.admin')

@section('title', 'Turmas')
@section('page-title', 'Turmas e alunos')
@section('page-subtitle', 'Organize alunos para aplicação e acompanhamento de provas.')

@section('content')
<section class="split-grid">
    <form class="card inline-form" method="POST" action="{{ route('admin.classrooms.store') }}">
        @csrf

        @if (auth()->user()->isAdmin())
            <div class="field full">
                <label>Escola</label>
                <select name="school_id" required>
                    <option value="">Selecione</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}">{{ $school->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="field">
            <label>Nome da turma</label>
            <input name="name" required>
        </div>
        <div class="field">
            <label>Código</label>
            <input name="code" required>
        </div>
        <div class="field">
            <label>Ano</label>
            <input type="number" name="year" value="{{ now()->year }}">
        </div>
        <div class="field">
            <div class="checks">
                <label><input type="checkbox" name="is_active" value="1" checked> Ativa</label>
            </div>
        </div>
        <div class="form-actions full">
            <button class="btn">Criar turma</button>
        </div>
    </form>

    <div>
        <div class="toolbar">
            <div>
                <h2>Turmas cadastradas</h2>
                <p>{{ $classrooms->total() }} turmas</p>
            </div>
        </div>

        @forelse ($classrooms as $classroom)
            <article class="card approval-card">
                <div class="approval-head">
                    <div>
                        <span class="status {{ $classroom->is_active ? 'active' : 'inactive' }}">{{ $classroom->school->name }}</span>
                        <h3>{{ $classroom->name }} · {{ $classroom->code }}</h3>
                        <small>{{ $classroom->students_count }} alunos · {{ $classroom->sessions_count }} sessões</small>
                    </div>
                    <form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}" onsubmit="return confirm('Remover esta turma?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn danger small">Remover</button>
                    </form>
                </div>

                <form class="filters" method="POST" action="{{ route('admin.students.store', $classroom) }}">
                    @csrf
                    <input name="name" placeholder="Nome do aluno" required>
                    <input name="identifier" placeholder="Identificador">
                    <input name="phone" placeholder="Telefone">
                    <button class="btn small">Adicionar aluno</button>
                </form>

                <div class="question-options">
                    @forelse ($classroom->students as $student)
                        <div class="question-option">
                            <span class="option-letter">{{ str($student->name)->substr(0, 1) }}</span>
                            <span>
                                <strong><a href="{{ route('admin.students.show',$student) }}">{{ $student->name }}</a></strong><br>
                                <small>{{ $student->identifier }} {{ $student->phone }}</small>
                            </span>
                            <form style="margin-left:auto" method="POST" action="{{ route('admin.students.destroy', $student) }}" onsubmit="return confirm('Remover este aluno?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger small">×</button>
                            </form>
                        </div>
                    @empty
                        <small>Sem alunos nesta turma.</small>
                    @endforelse
                </div>
            </article>
        @empty
            <div class="card empty">Ainda não existem turmas.</div>
        @endforelse

        <div class="pagination">{{ $classrooms->links() }}</div>
    </div>
</section>
@endsection
