@extends('layouts.admin')
@section('title',$question->exists ? 'Editar pergunta' : 'Nova pergunta')
@section('page-title',$question->exists ? 'Editar pergunta' : 'Nova pergunta')
@section('page-subtitle','Preencha os dados essenciais e envie para aprovação quando estiver pronta.')
@section('content')
<form class="card form-card" method="POST" action="{{ $question->exists ? route('admin.questions.update',$question) : route('admin.questions.store') }}">@csrf @if($question->exists)@method('PUT')@endif
<div class="form-grid">
<div class="field"><label>Tema</label><select name="topic_id" required><option value="">Selecione</option>@foreach($topics as $topic)<option value="{{ $topic->id }}" @selected(old('topic_id',$question->topic_id)==$topic->id)>{{ $topic->name }}</option>@endforeach</select></div>
<div class="field"><label>Identificador</label><input name="external_id" value="{{ old('external_id',$question->external_id) }}" placeholder="ex.: pri-001" required></div>
<div class="field"><label>Tipo</label><select name="type"><option value="teorico" @selected(old('type',$question->type)==='teorico')>Teórica</option><option value="pratico" @selected(old('type',$question->type)==='pratico')>Prática</option></select></div>
<div class="field"><label>Ordem</label><input type="number" min="0" name="sort_order" value="{{ old('sort_order',$question->sort_order ?? 0) }}" required></div>
<div class="field full"><label>Categorias de carta</label><div class="checks">@foreach($categories as $category)<label><input type="checkbox" name="categories[]" value="{{ $category->slug }}" @checked(in_array($category->slug,old('categories',$question->categories ?? [$categories->first()?->slug])))> {{ $category->name }}</label>@endforeach</div></div>
<div class="field full"><label>Enunciado</label><textarea name="statement" required>{{ old('statement',$question->statement) }}</textarea></div>
<div class="field full"><label>Opções <small>Marque a resposta correta.</small></label><div id="options">@foreach(old('option_items',$question->options ?? ['', '']) as $index=>$option)<div class="question-option"><span class="option-letter">{{ chr(65+$index) }}</span><input style="flex:1" name="option_items[]" value="{{ $option }}" required><label><input type="radio" name="correct_index" value="{{ $index }}" @checked((int)old('correct_index',$question->correct_index ?? 0)===$index)> Correta</label><button class="btn danger small remove-option" type="button">×</button></div>@endforeach</div><button class="btn light small" id="add-option" type="button">＋ Adicionar opção</button></div>
<div class="field"><label>Artigo de referência</label><select name="article_id"><option value="">Sem artigo</option>@foreach($articles as $article)<option value="{{ $article->id }}" @selected(old('article_id',$question->article_id)===$article->id)>Artigo {{ $article->number }} — {{ $article->title }}</option>@endforeach</select></div>
<div class="field"><label>Sinal da biblioteca</label><select name="sign_id"><option value="">Sem sinal</option>@foreach($signs as $sign)<option value="{{ $sign->id }}" @selected(old('sign_id',$question->sign_id)===$sign->id)>{{ $sign->name }}</option>@endforeach</select></div>
<div class="field full"><label>Explicação</label><textarea name="explanation" required>{{ old('explanation',$question->explanation) }}</textarea></div>
<div class="field full"><label>Outra imagem <small>Caminho opcional quando não utilizar a biblioteca de sinais.</small></label><input name="image" value="{{ old('image',$question->image) }}"></div>
<div class="field full"><label>Publicação</label><div class="checks"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$question->exists ? $question->is_active : true))> Ativa</label><label><input type="checkbox" name="is_locked" value="1" @checked(old('is_locked',$question->is_locked))> Conteúdo bloqueado</label></div></div>
@if($question->status==='rejected' && $question->rejection_reason)<div class="field full"><div class="errors"><strong>Motivo da rejeição:</strong> {{ $question->rejection_reason }}</div></div>@endif
</div><div class="form-actions"><a class="btn light" href="{{ route('admin.questions.index') }}">Cancelar</a>@if(auth()->user()->isAdmin())<button class="btn light" name="action" value="draft">Guardar rascunho</button><button class="btn" name="action" value="approve">Guardar aprovada</button>@else<button class="btn" name="action" value="review">Enviar para aprovação</button>@endif</div></form>
<script>
const options=document.getElementById('options');
function refreshOptions(){[...options.children].forEach((row,index)=>{row.querySelector('.option-letter').textContent=String.fromCharCode(65+index);row.querySelector('input[type=radio]').value=index;row.querySelector('.remove-option').disabled=options.children.length<=2;});}
document.getElementById('add-option').addEventListener('click',()=>{const row=document.createElement('div');row.className='question-option';row.innerHTML='<span class="option-letter"></span><input style="flex:1" name="option_items[]" required><label><input type="radio" name="correct_index"> Correta</label><button class="btn danger small remove-option" type="button">×</button>';options.appendChild(row);refreshOptions();});
options.addEventListener('click',event=>{if(event.target.classList.contains('remove-option')){event.target.closest('.question-option').remove();refreshOptions();}});refreshOptions();
</script>
@endsection
