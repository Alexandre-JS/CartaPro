@extends('layouts.admin')
@section('title', 'Turmas')
@section('page-title', 'Turmas e alunos')
@section('page-subtitle', 'Organize alunos para aplicação e acompanhamento de provas.')
@section('content')
<x-admin.page-header id="classrooms-title" title="Turmas" description="Organize alunos para aplicação e acompanhamento de provas." :count="$classrooms->total()" count-label="turmas">
    <details class="pv-create-menu"><summary class="btn"><i class="bi bi-plus-lg" aria-hidden="true"></i>Nova turma</summary>
        <form class="pv-create-form" method="POST" action="{{ route('admin.classrooms.store') }}">@csrf
            @if(auth()->user()->isAdmin())<x-admin.field as="select" name="school_id" label="Escola" required><option value="">Selecione</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}</option>@endforeach</x-admin.field>@endif
            <x-admin.field name="name" label="Nome da turma" :value="old('name')" required /><x-admin.field name="code" label="Código" :value="old('code')" required /><x-admin.field name="year" label="Ano" type="number" :value="old('year', now()->year)" min="2000" max="2100" />
            <label class="pv-checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Ativa</label><x-admin.button type="submit" loading-label="A criar…">Criar turma</x-admin.button>
        </form>
    </details>
</x-admin.page-header>

<x-admin.data-toolbar class="classrooms-filters" label="Filtrar turmas">
    @if(auth()->user()->isAdmin())<select name="school_id" aria-label="Filtrar por escola"><option value="">Todas as escolas</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(request('school_id') == $school->id)>{{ $school->name }}</option>@endforeach</select><x-admin.button type="submit"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</x-admin.button>@if(request()->filled('school_id'))<x-admin.button variant="secondary" :href="route('admin.classrooms.index')">Limpar</x-admin.button>@endif
    @endif
</x-admin.data-toolbar>

<x-admin.table class="classrooms-table" labelledby="classrooms-title" caption="Turmas">
<x-slot:head><tr><th scope="col">Turma</th><th scope="col">Escola</th><th scope="col">Alunos</th><th scope="col">Sessões</th><th scope="col">Estado</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
@forelse($classrooms as $classroom)
<tr><td class="classroom-main"><strong>{{ $classroom->name }}</strong><small>{{ $classroom->code }} · {{ $classroom->year ?: 'Ano não definido' }}</small></td><td>{{ $classroom->school->name }}</td><td><strong>{{ $classroom->students_count }}</strong><small>alunos</small></td><td><strong>{{ $classroom->sessions_count }}</strong><small>sessões</small></td><td><x-admin.state :type="$classroom->is_active ? 'active' : 'neutral'">{{ $classroom->is_active ? 'Ativa' : 'Inativa' }}</x-admin.state></td><td class="actions"><x-admin.row-actions :view-href="route('admin.classrooms.show', $classroom)" label="Ações da turma"><a href="#students-{{ $classroom->id }}" role="menuitem"><i class="bi bi-people" aria-hidden="true"></i>Gerir alunos</a><button type="button" role="menuitem" class="is-danger" data-dialog-open="delete-classroom-{{ $classroom->id }}"><i class="bi bi-trash3" aria-hidden="true"></i>Remover</button></x-admin.row-actions></td></tr>
@empty
<x-admin.empty-state table :colspan="6" icon="people" title="Ainda não existem turmas" description="Crie a primeira turma para começar a acompanhar alunos." />
@endforelse
</x-admin.table>
<x-admin.pagination :paginator="$classrooms" />

@foreach($classrooms as $classroom)
<details class="classroom-student-drawer" id="students-{{ $classroom->id }}"><summary><span><i class="bi bi-people" aria-hidden="true"></i> Alunos de <strong>{{ $classroom->name }}</strong></span><small>{{ $classroom->students_count }} alunos</small></summary>
    <form class="student-quick-form" method="POST" action="{{ route('admin.students.store', $classroom) }}">@csrf<x-admin.field name="name" label="Nome do aluno" required /><x-admin.field name="identifier" label="Identificador" /><x-admin.field name="phone" label="Telefone" /><x-admin.button size="small" type="submit" loading-label="A adicionar…">Adicionar aluno</x-admin.button></form>
    <div class="classroom-student-list">@forelse($classroom->students as $student)<div><a href="{{ route('admin.students.show',$student) }}"><strong>{{ $student->name }}</strong></a><small>{{ trim($student->identifier.' '.$student->phone) ?: 'Sem identificador ou telefone' }}</small><button type="button" class="btn danger small" data-dialog-open="delete-student-{{ $student->id }}" aria-label="Remover {{ $student->name }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>@empty<x-admin.empty-state title="Sem alunos nesta turma" description="Adicione o primeiro aluno usando o formulário acima." icon="people" />@endforelse</div>
</details>
<x-admin.dialog id="delete-classroom-{{ $classroom->id }}" title="Remover turma?" description="A remoção poderá ser impedida se existirem alunos ou resultados associados." size="small"><p>Vai tentar remover <strong>{{ $classroom->name }}</strong>.</p><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit" loading-label="A remover…">Remover turma</x-admin.button></form></x-slot:footer></x-admin.dialog>
@foreach($classroom->students as $student)<x-admin.dialog id="delete-student-{{ $student->id }}" title="Remover aluno?" description="O histórico associado deve ser preservado sempre que existirem resultados."><p>Vai tentar remover <strong>{{ $student->name }}</strong> da turma.</p><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.students.destroy', $student) }}">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit" loading-label="A remover…">Remover aluno</x-admin.button></form></x-slot:footer></x-admin.dialog>@endforeach
@endforeach
@endsection
