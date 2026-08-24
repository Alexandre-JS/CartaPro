@extends('layouts.admin')
@section('title','Provas')
@section('page-title','Provas')
@section('page-subtitle','Provas públicas com publicação controlada e provas privadas das escolas.')
@section('content')
<div class="toolbar"><div><h2 id="exam-list-title">Provas organizadas</h2><p>{{ $exams->total() }} provas · cada prova contém perguntas selecionadas</p></div><x-admin.button :href="route('admin.exams.create')">＋ Nova prova</x-admin.button></div>
<x-admin.table labelledby="exam-list-title">
<x-slot:head><tr><th scope="col">Prova</th><th scope="col">Acesso</th><th scope="col">Publicação</th><th scope="col">Categorias</th><th scope="col">Perguntas</th><th scope="col">Aprovação</th><th scope="col">Ações</th></tr></x-slot:head>
@forelse($exams as $exam)
<tr><td><strong>{{ $exam->name }}</strong><br><small>{{ $exam->creator?->name }}</small></td>
<td><x-admin.state :type="$exam->is_public ? 'active' : 'neutral'">{{ $exam->is_public ? 'Pública · app' : 'Privada · escola' }}</x-admin.state><br><small>{{ $exam->school?->name }}</small></td>
<td>@if($exam->is_public)<x-admin.state :type="$exam->publication_status">{{ ['published'=>'Publicada','archived'=>'Arquivada','draft'=>'Rascunho'][$exam->publication_status] ?? ucfirst($exam->publication_status) }}</x-admin.state><br><x-admin.state :type="$exam->is_locked ? 'review' : 'approved'">{{ $exam->is_locked ? 'Plano completo' : 'Gratuita' }}</x-admin.state>@else<x-admin.state>Não aplicável</x-admin.state>@endif</td>
<td>{{ implode(', ', $exam->license_categories ?: [$exam->license_category]) }}</td><td>{{ $exam->questions_count }}</td><td>{{ $exam->pass_score }}/{{ $exam->question_count }}</td>
<td class="actions"><x-admin.button variant="secondary" size="small" :href="route('admin.exams.show',$exam)">Ver</x-admin.button><x-admin.button variant="secondary" size="small" :href="route('admin.exams.edit',$exam)">Editar</x-admin.button>
@if(auth()->user()->isAdmin() && $exam->is_public && $exam->publication_status !== 'published')<form method="POST" action="{{ route('admin.exams.publish',$exam) }}">@csrf @method('PATCH')<x-admin.button size="small" type="submit">Publicar no app</x-admin.button></form>@elseif(auth()->user()->isAdmin() && $exam->is_public)<form method="POST" action="{{ route('admin.exams.archive',$exam) }}">@csrf @method('PATCH')<x-admin.button variant="warning" size="small" type="submit">Arquivar</x-admin.button></form>@endif
@if(auth()->user()->isAdmin() && $exam->is_public)<form method="POST" action="{{ route('admin.exams.plan',$exam) }}">@csrf @method('PATCH')<x-admin.button variant="secondary" size="small" type="submit">{{ $exam->is_locked ? 'Abrir ao plano gratuito' : 'Marcar plano completo' }}</x-admin.button></form>@elseif(auth()->user()->isAdmin() && $exam->sessions_count)<x-admin.button variant="secondary" size="small" data-dialog-open="copy-exam-{{ $exam->id }}">Publicar cópia no app</x-admin.button>@endif
<x-admin.button variant="danger" size="small" data-dialog-open="delete-exam-{{ $exam->id }}">Remover</x-admin.button></td></tr>
@empty<x-admin.empty-state table :colspan="7" title="Ainda não existem provas" description="Crie a primeira prova para avaliar os alunos." />@endforelse
</x-admin.table><x-admin.pagination :paginator="$exams" />
@foreach($exams as $exam)
@if(auth()->user()->isAdmin() && !$exam->is_public && $exam->sessions_count)<x-admin.dialog id="copy-exam-{{ $exam->id }}" title="Publicar uma cópia no aplicativo?" description="A prova original e os resultados da escola permanecem intactos." size="small"><p>Será criada uma cópia pública de <strong>{{ $exam->name }}</strong>.</p><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.exams.duplicate-public',$exam) }}">@csrf<x-admin.button type="submit" loading-label="A publicar…">Criar cópia pública</x-admin.button></form></x-slot:footer></x-admin.dialog>@endif
<x-admin.dialog id="delete-exam-{{ $exam->id }}" title="Remover prova?" description="Esta ação só será concluída se a prova puder ser eliminada com segurança." size="small"><p>Vai remover <strong>{{ $exam->name }}</strong>.</p><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.exams.destroy',$exam) }}">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit" loading-label="A remover…">Remover prova</x-admin.button></form></x-slot:footer></x-admin.dialog>
@endforeach
@endsection
