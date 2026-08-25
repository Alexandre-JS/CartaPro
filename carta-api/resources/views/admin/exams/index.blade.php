@extends('layouts.admin')
@section('title','Provas')
@section('page-title','Provas')
@section('page-subtitle','Provas públicas com publicação controlada e provas privadas das escolas.')
@section('content')
<x-admin.page-header id="exam-list-title" title="Provas" description="Crie provas, acompanhe a publicação e controle o acesso dos alunos." :count="$exams->total()" count-label="provas">
    <x-admin.button :href="route('admin.exams.create')"><i class="bi bi-plus-lg" aria-hidden="true"></i>Nova prova</x-admin.button>
</x-admin.page-header>

<x-admin.table class="exams-table" labelledby="exam-list-title" caption="Provas">
<x-slot:head><tr><th scope="col">Prova</th><th scope="col">Acesso</th><th scope="col">Publicação</th><th scope="col">Perguntas</th><th scope="col">Aprovação</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
@forelse($exams as $exam)
<tr><td class="exam-main"><strong>{{ $exam->name }}</strong><small>{{ $exam->school?->name ?? 'ProntoVia · plataforma' }}</small></td><td><x-admin.state :type="$exam->is_public ? 'active' : 'neutral'">{{ $exam->is_public ? 'Pública · app' : 'Privada · escola' }}</x-admin.state></td><td><x-admin.state :type="$exam->is_public ? $exam->publication_status : 'neutral'">{{ $exam->is_public ? (['published'=>'Publicada','archived'=>'Arquivada','draft'=>'Rascunho'][$exam->publication_status] ?? ucfirst($exam->publication_status)) : 'Não aplicável' }}</x-admin.state><small class="exam-meta">{{ $exam->is_locked ? 'Plano completo' : 'Gratuita' }}</small></td><td><strong>{{ $exam->questions_count }}</strong><small class="exam-meta">selecionadas</small></td><td><strong>{{ $exam->pass_score }}/{{ $exam->question_count }}</strong><small class="exam-meta">mínimo</small></td><td class="actions"><x-admin.row-actions :view-href="route('admin.exams.show',$exam)" label="Ações da prova"><a href="{{ route('admin.exams.edit',$exam) }}" role="menuitem"><i class="bi bi-pencil" aria-hidden="true"></i>Editar</a>@if(auth()->user()->isAdmin() && $exam->is_public && $exam->publication_status !== 'published')<form method="POST" action="{{ route('admin.exams.publish',$exam) }}" role="menuitem">@csrf @method('PATCH')<button type="submit"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>Publicar no app</button></form>@elseif(auth()->user()->isAdmin() && $exam->is_public)<form method="POST" action="{{ route('admin.exams.archive',$exam) }}" role="menuitem">@csrf @method('PATCH')<button type="submit"><i class="bi bi-archive" aria-hidden="true"></i>Arquivar</button></form>@endif @if(auth()->user()->isAdmin() && $exam->is_public)<form method="POST" action="{{ route('admin.exams.plan',$exam) }}" role="menuitem">@csrf @method('PATCH')<button type="submit"><i class="bi bi-unlock" aria-hidden="true"></i>{{ $exam->is_locked ? 'Abrir ao plano gratuito' : 'Marcar plano completo' }}</button></form>@elseif(auth()->user()->isAdmin() && $exam->sessions_count)<button type="button" role="menuitem" data-dialog-open="copy-exam-{{ $exam->id }}"><i class="bi bi-copy" aria-hidden="true"></i>Publicar cópia no app</button>@endif<button type="button" role="menuitem" class="is-danger" data-dialog-open="delete-exam-{{ $exam->id }}"><i class="bi bi-trash3" aria-hidden="true"></i>Remover</button></x-admin.row-actions></td></tr>
@empty
<x-admin.empty-state table :colspan="6" icon="clipboard" title="Ainda não existem provas" description="Crie a primeira prova para avaliar os alunos." />
@endforelse
</x-admin.table>
<x-admin.pagination :paginator="$exams" />

@foreach($exams as $exam)
    @if(auth()->user()->isAdmin() && !$exam->is_public && $exam->sessions_count)<x-admin.dialog id="copy-exam-{{ $exam->id }}" title="Publicar uma cópia no aplicativo?" description="A prova original e os resultados da escola permanecem intactos." size="small"><p>Será criada uma cópia pública de <strong>{{ $exam->name }}</strong>.</p><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.exams.duplicate-public',$exam) }}">@csrf<x-admin.button type="submit" loading-label="A publicar…">Criar cópia pública</x-admin.button></form></x-slot:footer></x-admin.dialog>@endif
    <x-admin.dialog id="delete-exam-{{ $exam->id }}" title="Remover prova?" description="Esta ação só será concluída se a prova puder ser eliminada com segurança."><p>Vai remover <strong>{{ $exam->name }}</strong>.</p><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.exams.destroy',$exam) }}">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit" loading-label="A remover…">Remover prova</x-admin.button></form></x-slot:footer></x-admin.dialog>
@endforeach
@endsection
