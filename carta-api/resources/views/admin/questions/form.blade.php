@extends('layouts.admin')
@section('title',$question->exists ? 'Editar pergunta' : 'Nova pergunta')
@section('page-title',$question->exists ? 'Editar pergunta' : 'Nova pergunta')
@section('page-subtitle','Crie o enunciado, defina as respostas e publique.')
@section('content')
<form class="question-form" method="POST" enctype="multipart/form-data" action="{{ $question->exists ? route('admin.questions.update',$question) : route('admin.questions.store') }}">@csrf @if($question->exists)@method('PUT')@endif
<div class="question-form-layout">
<div class="question-form-main card">
<section class="form-section" id="identificacao">
<header class="form-section-head"><h2>Identificação</h2></header>
<div class="form-grid">
<x-admin.field as="select" name="topic_id" label="Tema" required><option value="">Selecione</option>@foreach($topics as $topic)<option value="{{ $topic->id }}" @selected(old('topic_id',$question->topic_id)==$topic->id)>{{ $topic->name }}</option>@endforeach</x-admin.field>
<div class="field"><label>Identificador</label><input value="{{ $question->external_id ?? 'Gerado automaticamente' }}" disabled><small>@if($question->exists)Fixo — as respostas das provas já submetidas são guardadas por este identificador.@else Derivado do tema (ex.: velocidade-001).@endif</small></div>
<x-admin.field as="select" name="type" label="Tipo"><option value="teorico" @selected(old('type',$question->type)==='teorico')>Teórica</option><option value="pratico" @selected(old('type',$question->type)==='pratico')>Prática</option></x-admin.field>
<x-admin.field name="sort_order" label="Ordem" type="number" min="0" :value="$proximaOrdem" placeholder="A seguir à última do tema" hint="Opcional. Deixe vazio para colocar no fim." />
<div class="field full"><label>Categorias de carta</label><div class="checks">@foreach($categories as $category)<label><input type="checkbox" name="categories[]" value="{{ $category->slug }}" @checked(in_array($category->slug,old('categories',$question->categories ?? [$categories->first()?->slug])))> {{ $category->name }}</label>@endforeach</div></div>
</div></section>

<section class="form-section" id="conteudo">
<header class="form-section-head"><h2>Pergunta e respostas</h2></header>
<div class="form-grid">
<x-admin.field as="textarea" name="statement" label="Enunciado" :value="$question->statement" required full />
<div class="field full"><label>Opções <small>Marque a resposta correta.</small></label><div id="options">@foreach(old('option_items',$question->options ?? ['', '']) as $index=>$option)<div class="question-option"><span class="option-letter">{{ chr(65+$index) }}</span><input style="flex:1" name="option_items[]" value="{{ $option }}" required><label><input type="radio" name="correct_index" value="{{ $index }}" @checked((int)old('correct_index',$question->correct_index ?? 0)===$index)> Correta</label><button class="btn danger small remove-option" type="button">×</button></div>@endforeach</div><button class="btn light small" id="add-option" type="button">＋ Adicionar opção</button></div>
</div></section>

<section class="form-section" id="apoio">
<header class="form-section-head"><h2>Material de apoio <small>Opcional</small></h2></header>
<div class="support-grid">
<div class="support-copy">
<x-admin.field as="select" name="article_id" label="Artigo de referência"><option value="">Sem artigo</option>@foreach($articles as $article)<option value="{{ $article->id }}" @selected(old('article_id',$question->article_id)===$article->id)>Artigo {{ $article->number }} — {{ $article->title }}</option>@endforeach</x-admin.field>
<x-admin.field as="textarea" name="explanation" label="Explicação" :value="$question->explanation" placeholder="Explicação apresentada depois da resposta." />
</div>
<div class="support-image">

{{-- Imagem: da biblioteca de sinais ou ficheiro próprio, nunca as duas. --}}
<div class="field"><label>Imagem da pergunta</label>
    <div class="checks">
        <label><input type="radio" name="image_source" value="none" @checked(!$question->sign_id && !$question->image)> Sem imagem</label>
        <label><input type="radio" name="image_source" value="sign" @checked((bool)$question->sign_id)> Da biblioteca de sinais</label>
        <label><input type="radio" name="image_source" value="upload" @checked(!$question->sign_id && $question->image)> Carregar uma imagem</label>
    </div>
</div>
@php($selectedSignId = (int) old('sign_id', $question->sign_id))
@php($selectedSign = $signs->firstWhere('id', $selectedSignId))
@php($selectedSignImage = $selectedSign?->file_path && is_file(public_path(ltrim($selectedSign->file_path, '/'))) ? asset(ltrim($selectedSign->file_path, '/')) : '')
<div class="field" data-image-panel="sign"><label>Sinal</label>
    <input type="hidden" name="sign_id" id="sign-select" value="{{ $selectedSignId ?: '' }}">
    <div class="selected-sign" id="selected-sign">
        <div class="selected-sign-preview" id="selected-sign-preview">
            @if($selectedSignImage)<img src="{{ $selectedSignImage }}" alt="">@else<span>Imagem em falta</span>@endif
        </div>
        <div class="selected-sign-copy">
            <strong id="selected-sign-name">{{ $selectedSign?->name ?? 'Nenhum sinal selecionado' }}</strong>
            <small id="selected-sign-category">{{ $selectedSign?->categoriaNome() ?? 'Escolha um sinal na biblioteca.' }}</small>
        </div>
        <button class="btn light" type="button" id="open-sign-picker">{{ $selectedSign ? 'Trocar sinal' : 'Escolher sinal' }}</button>
        <button class="btn light sign-clear" type="button" id="clear-sign" @hidden(!$selectedSign) aria-label="Remover sinal selecionado">×</button>
    </div>
</div>
<div class="field" data-image-panel="upload"><label>Ficheiro <small>Até 2 MB</small></label>
    <input type="file" name="image_file" id="image-file" accept=".svg,.png,.jpg,.jpeg,.webp">
    <div class="file-selection" id="file-selection" hidden></div>
    @if($question->image)<label class="checks"><input type="checkbox" name="remove_image" value="1"> Remover a imagem actual</label>@endif
</div>
@php($questionImagePath = $question->imagemPublica())
@php($currentQuestionImage = $questionImagePath && is_file(public_path(ltrim($questionImagePath, '/'))) ? asset(ltrim($questionImagePath, '/')) : '')
<div class="field image-preview-field" id="image-preview-field" data-current-image="{{ $currentQuestionImage }}" hidden><img id="image-preview" alt=""><span id="image-preview-error" hidden>Não foi possível visualizar esta imagem.</span></div>
</div></div></section>

<dialog class="sign-picker" id="sign-picker" aria-labelledby="sign-picker-title">
    <div class="sign-picker-head">
        <div><h2 id="sign-picker-title">Selecionar sinal</h2><p>Escolha a imagem que acompanhará a pergunta.</p></div>
        <button type="button" class="sign-picker-close" id="close-sign-picker" aria-label="Fechar">×</button>
    </div>
    <div class="sign-picker-filters">
        <input type="search" id="sign-search" placeholder="Pesquisar pelo nome ou significado" autocomplete="off">
        <select id="sign-category-filter" aria-label="Filtrar por categoria">
            <option value="">Todas as categorias</option>
            @foreach($signs->pluck('category')->filter()->unique('id')->sortBy('name') as $category)<option value="{{ str($category->name)->lower() }}">{{ $category->name }}</option>@endforeach
        </select>
    </div>
    <div class="sign-picker-results" id="sign-picker-results">
        @forelse($signs as $sign)
            @php($signImage = $sign->file_path && is_file(public_path(ltrim($sign->file_path, '/'))) ? asset(ltrim($sign->file_path, '/')) : '')
            <button type="button" class="sign-choice" data-sign-id="{{ $sign->id }}" data-name="{{ $sign->name }}" data-category="{{ $sign->categoriaNome() }}" data-meaning="{{ $sign->meaning }}" data-image="{{ $signImage }}" data-stored-image="{{ $sign->file_path }}">
                <span class="sign-choice-image">@if($signImage)<img src="{{ $signImage }}" alt="" loading="lazy">@else<span>Imagem em falta</span>@endif</span>
                <span class="sign-choice-copy"><strong>{{ $sign->name }}</strong><small>{{ $sign->categoriaNome() }}</small>@if($sign->meaning)<span>{{ $sign->meaning }}</span>@endif</span>
            </button>
        @empty
            <p class="sign-picker-empty">Ainda não existem sinais ativos na biblioteca.</p>
        @endforelse
    </div>
    <p class="sign-picker-empty" id="sign-no-results" hidden>Nenhum sinal corresponde à pesquisa.</p>
</dialog>

<section class="form-section" id="publicacao">
<header class="form-section-head"><h2>Publicação</h2></header>
<div class="publication-options"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$question->exists ? $question->is_active : true))><span><strong>Pergunta ativa</strong></span></label><label><input type="checkbox" name="is_locked" value="1" @checked(old('is_locked',$question->is_locked))><span><strong>Conteúdo Premium</strong></span></label></div>
@if($question->status==='rejected' && $question->rejection_reason)<div class="errors"><strong>Motivo da rejeição:</strong> {{ $question->rejection_reason }}</div>@endif
</section>
<div class="form-actions"><x-admin.button variant="secondary" :href="route('admin.questions.index')">Cancelar</x-admin.button>@if(auth()->user()->isAdmin())<x-admin.button variant="secondary" type="submit" name="action" value="draft" loading-label="A guardar…">Guardar rascunho</x-admin.button><x-admin.button type="submit" name="action" value="approve" loading-label="A guardar…">Guardar aprovada</x-admin.button>@else<x-admin.button type="submit" name="action" value="review" loading-label="A enviar…">Enviar para aprovação</x-admin.button>@endif</div>
</div></div></form>
<script>
const options=document.getElementById('options');
function refreshOptions(){[...options.children].forEach((row,index)=>{row.querySelector('.option-letter').textContent=String.fromCharCode(65+index);row.querySelector('input[type=radio]').value=index;row.querySelector('.remove-option').disabled=options.children.length<=2;});}
document.getElementById('add-option').addEventListener('click',()=>{const row=document.createElement('div');row.className='question-option';row.innerHTML='<span class="option-letter"></span><input style="flex:1" name="option_items[]" required><label><input type="radio" name="correct_index"> Correta</label><button class="btn danger small remove-option" type="button">×</button>';options.appendChild(row);refreshOptions();});
options.addEventListener('click',event=>{if(event.target.classList.contains('remove-option')){event.target.closest('.question-option').remove();refreshOptions();}});refreshOptions();

// Origem da imagem: mostra só os campos da opção escolhida e limpa a outra,
// para não chegar ao servidor um sinal e um ficheiro ao mesmo tempo.
(function(){
const sources=[...document.querySelectorAll('input[name=image_source]')],sign=document.getElementById('sign-select'),file=document.getElementById('image-file'),previewField=document.getElementById('image-preview-field'),preview=document.getElementById('image-preview'),previewError=document.getElementById('image-preview-error'),fileSelection=document.getElementById('file-selection'),atual=previewField.dataset.currentImage;
const picker=document.getElementById('sign-picker'),choices=[...document.querySelectorAll('.sign-choice')],search=document.getElementById('sign-search'),categoryFilter=document.getElementById('sign-category-filter'),noResults=document.getElementById('sign-no-results');
let uploadedPreview='';
function imageDiagnostic(level,message,details={}){
    const logger=console[level]||console.log;
    logger.call(console,`[ProntoVia:imagem] ${message}`,{pagina:location.href,...details});
}
function monitorImage(image,context){
    const loaded=()=>{if(image===preview){preview.hidden=false;previewError.hidden=true;}imageDiagnostic('info','Imagem carregada',{context,src:image.currentSrc||image.src,largura:image.naturalWidth,altura:image.naturalHeight});};
    const failed=event=>{if(image===preview){preview.hidden=true;previewError.hidden=false;}imageDiagnostic('error','Falha ao carregar imagem',{context,src:image.currentSrc||image.src,baseUrl:document.baseURI,eventType:event?.type||'estado inicial'});};
    image.addEventListener('load',loaded);
    image.addEventListener('error',failed);
    if(image.src&&image.complete){image.naturalWidth>0?loaded():failed();}
}
monitorImage(preview,'Pré-visualização da pergunta');
document.querySelectorAll('.sign-choice img').forEach(image=>monitorImage(image,'Biblioteca de sinais no modal'));
choices.filter(choice=>!choice.dataset.image).forEach(choice=>imageDiagnostic('warn','Ficheiro do sinal indisponível',{sinal:choice.dataset.name,caminhoGuardado:choice.dataset.storedImage||null}));
function selectedChoice(){return choices.find(choice=>choice.dataset.signId===sign.value);}
function renderSelectedSign(){
    const choice=selectedChoice(),box=document.getElementById('selected-sign-preview');
    document.getElementById('selected-sign-name').textContent=choice?.dataset.name||'Nenhum sinal selecionado';
    document.getElementById('selected-sign-category').textContent=choice?.dataset.category||'Escolha um sinal na biblioteca.';
    document.getElementById('open-sign-picker').textContent=choice?'Trocar sinal':'Escolher sinal';
    document.getElementById('clear-sign').hidden=!choice;
    box.replaceChildren();
    if(choice?.dataset.image){const image=new Image();monitorImage(image,`Sinal selecionado: ${choice.dataset.name}`);image.src=choice.dataset.image;image.alt='';box.appendChild(image);}else{const empty=document.createElement('span');empty.textContent='Imagem em falta';box.appendChild(empty);}
}
function refreshImage(){
    const escolha=(sources.find(s=>s.checked)||{}).value||'none';
    document.querySelectorAll('[data-image-panel]').forEach(panel=>panel.hidden=panel.dataset.imagePanel!==escolha);
    let src=escolha==='sign'?(selectedChoice()?.dataset.image||''):(escolha==='upload'?(uploadedPreview||atual):'');
    imageDiagnostic('info','Estado da pré-visualização',{origem:escolha,temImagem:Boolean(src),ficheiroSelecionado:file.files[0]?.name||null});
    previewField.hidden=!src;
    if(src){preview.hidden=false;previewError.hidden=true;preview.src=src;}else{preview.removeAttribute('src');preview.hidden=false;previewError.hidden=true;}
}
function filterSigns(){
    const term=search.value.trim().toLocaleLowerCase('pt'),category=categoryFilter.value;
    let visible=0;
    choices.forEach(choice=>{const matchesText=!term||`${choice.dataset.name} ${choice.dataset.meaning}`.toLocaleLowerCase('pt').includes(term),matchesCategory=!category||choice.dataset.category.toLocaleLowerCase('pt')===category;choice.hidden=!(matchesText&&matchesCategory);if(!choice.hidden)visible++;});
    noResults.hidden=visible!==0;
}
sources.forEach(source=>source.addEventListener('change',()=>{
    if(source.value!=='sign'){sign.value='';renderSelectedSign();}
    if(source.value!=='upload'){
        file.value='';fileSelection.hidden=true;fileSelection.textContent='';
        uploadedPreview='';
    }
    refreshImage();
}));
file.addEventListener('change',()=>{
    const selectedFile=file.files[0];
    const uploadSource=sources.find(source=>source.value==='upload');
    if(selectedFile&&uploadSource&&!uploadSource.checked){
        uploadSource.checked=true;
        sign.value='';
        renderSelectedSign();
    }
    fileSelection.hidden=!file.files[0];
    fileSelection.textContent=file.files[0]?`${file.files[0].name} · ${(file.files[0].size/1024).toFixed(1)} KB`:'';
    uploadedPreview='';
    if(!selectedFile){refreshImage();return;}
    imageDiagnostic('info','Ficheiro selecionado',{nome:selectedFile.name,tipo:selectedFile.type,tamanho:selectedFile.size});
    const reader=new FileReader();
    reader.addEventListener('load',()=>{
        if(typeof reader.result!=='string'||!reader.result.startsWith('data:image/')){
            previewField.hidden=false;preview.hidden=true;previewError.hidden=false;
            imageDiagnostic('error','O ficheiro lido não produziu dados de imagem',{nome:selectedFile.name,tipo:selectedFile.type,resultado:typeof reader.result});
            return;
        }
        uploadedPreview=reader.result;
        imageDiagnostic('info','Ficheiro lido para pré-visualização',{nome:selectedFile.name,tipo:selectedFile.type,caracteres:uploadedPreview.length});
        refreshImage();
    });
    reader.addEventListener('error',()=>{previewField.hidden=false;preview.hidden=true;previewError.hidden=false;imageDiagnostic('error','Erro ao ler o ficheiro',{nome:selectedFile.name,error:reader.error});});
    reader.addEventListener('abort',()=>imageDiagnostic('warn','Leitura do ficheiro cancelada',{nome:selectedFile.name}));
    reader.readAsDataURL(selectedFile);
});
document.getElementById('open-sign-picker').addEventListener('click',()=>{picker.showModal();search.focus();});
document.getElementById('close-sign-picker').addEventListener('click',()=>picker.close());
picker.addEventListener('click',event=>{if(event.target===picker)picker.close();});
choices.forEach(choice=>choice.addEventListener('click',()=>{sign.value=choice.dataset.signId;renderSelectedSign();refreshImage();picker.close();}));
document.getElementById('clear-sign').addEventListener('click',()=>{sign.value='';renderSelectedSign();refreshImage();});
search.addEventListener('input',filterSigns);categoryFilter.addEventListener('change',filterSigns);
renderSelectedSign();refreshImage();
imageDiagnostic('info','Editor de pergunta iniciado',{imagemAtual:atual,sinaisDisponiveis:choices.length,origemSelecionada:(sources.find(source=>source.checked)||{}).value||'none'});
})();
</script>
@endsection
