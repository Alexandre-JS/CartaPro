@extends('layouts.admin')
@section('title',$question->exists ? 'Editar pergunta' : 'Nova pergunta')
@section('page-title',$question->exists ? 'Editar pergunta' : 'Nova pergunta')
@section('page-subtitle','Preencha os dados essenciais e envie para aprovação quando estiver pronta.')
@section('content')
<form class="card form-card" method="POST" enctype="multipart/form-data" action="{{ $question->exists ? route('admin.questions.update',$question) : route('admin.questions.store') }}">@csrf @if($question->exists)@method('PUT')@endif
<div class="form-grid">
<div class="field"><label>Tema</label><select name="topic_id" required><option value="">Selecione</option>@foreach($topics as $topic)<option value="{{ $topic->id }}" @selected(old('topic_id',$question->topic_id)==$topic->id)>{{ $topic->name }}</option>@endforeach</select></div>
<div class="field"><label>Identificador</label><input value="{{ $question->external_id ?? 'Gerado automaticamente' }}" disabled><small>@if($question->exists)Fixo — as respostas das provas já submetidas são guardadas por este identificador.@else Derivado do tema (ex.: velocidade-001).@endif</small></div>
<div class="field"><label>Tipo</label><select name="type"><option value="teorico" @selected(old('type',$question->type)==='teorico')>Teórica</option><option value="pratico" @selected(old('type',$question->type)==='pratico')>Prática</option></select></div>
<div class="field"><label>Ordem <small>Opcional.</small></label><input type="number" min="0" name="sort_order" value="{{ old('sort_order',$proximaOrdem) }}" placeholder="A seguir à última do tema"><small>Deixe vazio para colocar no fim; escreva um número para mudar a posição.</small></div>
<div class="field full"><label>Categorias de carta</label><div class="checks">@foreach($categories as $category)<label><input type="checkbox" name="categories[]" value="{{ $category->slug }}" @checked(in_array($category->slug,old('categories',$question->categories ?? [$categories->first()?->slug])))> {{ $category->name }}</label>@endforeach</div></div>
<div class="field full"><label>Enunciado</label><textarea name="statement" required>{{ old('statement',$question->statement) }}</textarea></div>
<div class="field full"><label>Opções <small>Marque a resposta correta.</small></label><div id="options">@foreach(old('option_items',$question->options ?? ['', '']) as $index=>$option)<div class="question-option"><span class="option-letter">{{ chr(65+$index) }}</span><input style="flex:1" name="option_items[]" value="{{ $option }}" required><label><input type="radio" name="correct_index" value="{{ $index }}" @checked((int)old('correct_index',$question->correct_index ?? 0)===$index)> Correta</label><button class="btn danger small remove-option" type="button">×</button></div>@endforeach</div><button class="btn light small" id="add-option" type="button">＋ Adicionar opção</button></div>
<div class="field"><label>Artigo de referência</label><select name="article_id"><option value="">Sem artigo</option>@foreach($articles as $article)<option value="{{ $article->id }}" @selected(old('article_id',$question->article_id)===$article->id)>Artigo {{ $article->number }} — {{ $article->title }}</option>@endforeach</select></div>
<div class="field full"><label>Explicação <small>Opcional — mostrada ao aluno depois de responder.</small></label><textarea name="explanation" placeholder="Pode ficar vazia e ser escrita mais tarde.">{{ old('explanation',$question->explanation) }}</textarea></div>

{{-- Imagem: da biblioteca de sinais ou ficheiro próprio, nunca as duas. --}}
<div class="field full"><label>Imagem da pergunta</label>
    <div class="checks">
        <label><input type="radio" name="image_source" value="none" @checked(!$question->sign_id && !$question->image)> Sem imagem</label>
        <label><input type="radio" name="image_source" value="sign" @checked((bool)$question->sign_id)> Da biblioteca de sinais</label>
        <label><input type="radio" name="image_source" value="upload" @checked(!$question->sign_id && $question->image)> Carregar uma imagem</label>
    </div>
</div>
<div class="field full" data-image="sign"><label>Sinal</label>
    <select name="sign_id" id="sign-select">
        <option value="">Selecione o sinal</option>
        @foreach($signs as $sign)<option value="{{ $sign->id }}" data-imagem="{{ $sign->file_path }}" @selected(old('sign_id',$question->sign_id)===$sign->id)>{{ $sign->name }}@if(!$sign->file_path) — (ainda sem imagem)@endif</option>@endforeach
    </select>
    <small>A imagem vem sempre do sinal: se ela for trocada na biblioteca, esta pergunta acompanha.</small>
</div>
<div class="field full" data-image="upload"><label>Ficheiro <small>SVG, PNG, JPEG ou WebP até 2 MB.</small></label>
    <input type="file" name="image_file" id="image-file" accept=".svg,.png,.jpg,.jpeg,.webp">
    <small>Para o que não pertence à biblioteca de sinais — uma fotografia de via, um esquema de manobra.</small>
    @if($question->image)<label class="checks"><input type="checkbox" name="remove_image" value="1"> Remover a imagem actual</label>@endif
</div>
<div class="field full" id="image-preview-field" hidden><label>Pré-visualização</label><img id="image-preview" alt="Pré-visualização da imagem da pergunta" style="max-height:160px"></div>

<div class="field full"><label>Publicação</label><div class="checks"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$question->exists ? $question->is_active : true))> Ativa</label><label><input type="checkbox" name="is_locked" value="1" @checked(old('is_locked',$question->is_locked))> Conteúdo bloqueado</label></div></div>
@if($question->status==='rejected' && $question->rejection_reason)<div class="field full"><div class="errors"><strong>Motivo da rejeição:</strong> {{ $question->rejection_reason }}</div></div>@endif
</div><div class="form-actions"><a class="btn light" href="{{ route('admin.questions.index') }}">Cancelar</a>@if(auth()->user()->isAdmin())<button class="btn light" name="action" value="draft">Guardar rascunho</button><button class="btn" name="action" value="approve">Guardar aprovada</button>@else<button class="btn" name="action" value="review">Enviar para aprovação</button>@endif</div></form>
<script>
const options=document.getElementById('options');
function refreshOptions(){[...options.children].forEach((row,index)=>{row.querySelector('.option-letter').textContent=String.fromCharCode(65+index);row.querySelector('input[type=radio]').value=index;row.querySelector('.remove-option').disabled=options.children.length<=2;});}
document.getElementById('add-option').addEventListener('click',()=>{const row=document.createElement('div');row.className='question-option';row.innerHTML='<span class="option-letter"></span><input style="flex:1" name="option_items[]" required><label><input type="radio" name="correct_index"> Correta</label><button class="btn danger small remove-option" type="button">×</button>';options.appendChild(row);refreshOptions();});
options.addEventListener('click',event=>{if(event.target.classList.contains('remove-option')){event.target.closest('.question-option').remove();refreshOptions();}});refreshOptions();

// Origem da imagem: mostra só os campos da opção escolhida e limpa a outra,
// para não chegar ao servidor um sinal e um ficheiro ao mesmo tempo.
(function(){
const sources=[...document.querySelectorAll('input[name=image_source]')],sign=document.getElementById('sign-select'),file=document.getElementById('image-file'),previewField=document.getElementById('image-preview-field'),preview=document.getElementById('image-preview'),atual=@json($question->imagemPublica());
function refreshImage(){
    const escolha=(sources.find(s=>s.checked)||{}).value||'none';
    document.querySelectorAll('[data-image]').forEach(f=>f.hidden=f.dataset.image!==escolha);
    if(escolha!=='sign')sign.value='';
    if(escolha!=='upload')file.value='';
    let src=escolha==='sign'?(sign.selectedOptions[0]||{}).dataset?.imagem:(escolha==='upload'?atual:'');
    if(escolha==='upload'&&file.files[0])src=URL.createObjectURL(file.files[0]);
    previewField.hidden=!src;
    if(src)preview.src=src;
}
sources.forEach(s=>s.addEventListener('change',refreshImage));sign.addEventListener('change',refreshImage);file.addEventListener('change',refreshImage);refreshImage();
})();
</script>
@endsection
