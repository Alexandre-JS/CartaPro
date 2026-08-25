@extends('layouts.admin')
@section('title', $exam->exists ? 'Editar prova' : 'Nova prova')
@section('page-title', $exam->exists ? 'Editar prova' : 'Criar prova')
@section('page-subtitle', $exam->exists ? 'Altere a configuração e, se a prova ainda não foi respondida, as perguntas.' : 'Escolha manualmente ou gere uma seleção com o mínimo possível de repetições.')
@section('content')
@php($selada = $attemptCount > 0)
{{-- Uma prova de escola já aplicada não pode ser promovida no lugar: anular o
     `school_id` retirava à escola o acesso aos resultados dos seus alunos. --}}
@php($aplicada = $exam->exists && ! $exam->is_public && $exam->sessions()->exists())
<x-admin.page-header id="exam-edit-title" :title="$exam->exists ? 'Editar prova' : 'Criar prova'" :description="$exam->exists ? 'Altere a configuração e as perguntas enquanto a prova não tiver sido respondida.' : 'Defina o acesso, a duração e as perguntas da nova prova.'">
    <x-admin.button variant="secondary" :href="route('admin.exams.index')"><i class="bi bi-arrow-left" aria-hidden="true"></i>Voltar às provas</x-admin.button>
</x-admin.page-header>
<form class="exam-edit-form" aria-labelledby="exam-edit-title" method="POST" action="{{ $exam->exists ? route('admin.exams.update', $exam) : route('admin.exams.store') }}">@csrf @if($exam->exists)@method('PUT')@endif
<section class="exam-form-section"><header><h3>Informação da prova</h3><p>Defina o contexto e as regras principais.</p></header>
<div class="form-grid">
    <x-admin.field class="exam-name" name="name" label="Nome da prova" :value="$exam->name" required />
    @if(auth()->user()->isAdmin())<div class="field"><label>Visibilidade</label><select name="visibility" id="visibility" @disabled($aplicada)><option value="public" @selected(old('visibility', $exam->is_public ? 'public' : null) === 'public')>Pública — aplicativo</option><option value="private" @selected(old('visibility', $exam->is_public ? 'public' : 'private') === 'private')>Privada — escola</option></select>@if($aplicada)<input type="hidden" name="visibility" value="private"><small>Já aplicada em turmas: para a levar ao telemóvel use «Publicar cópia no app» na listagem, que mantém esta prova e os resultados da escola.</small>@else<small>Pública torna a prova visível no aplicativo, desligando-a da escola. Depois de gravar, falta carregar em «Publicar no app».</small>@endif</div><div class="field" id="school-field"><label>Escola da prova privada</label><select name="school_id"><option value="">Selecione a escola</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id', $exam->school_id) == $school->id)>{{ $school->name }}</option>@endforeach</select></div><div class="field full" id="plan-field"><div class="checks"><label><input type="checkbox" name="is_locked" value="1" @checked(old('is_locked', $exam->is_locked))> Só para o plano completo <small>— o aluno gratuito vê a prova, mas não a abre.</small></label></div></div>@else<input type="hidden" name="visibility" value="private">@endif
    <div class="field"><label>Tipo das perguntas</label><select name="type" id="question-type" @disabled($selada)><option value="teorico" @selected(old('type', $exam->type) === 'teorico')>Teóricas</option><option value="pratico" @selected(old('type', $exam->type) === 'pratico')>Práticas</option></select>@if($selada)<input type="hidden" name="type" value="{{ $exam->type }}">@endif</div>
    <x-admin.field name="duration_minutes" label="Duração (minutos)" type="number" min="1" max="300" :value="$exam->duration_minutes ?? \App\Support\Grading::durationMinutes()" required />
    <div class="field"><label>Regra de aprovação</label><input value="{{ $passPercentage }}% — {{ \App\Support\Grading::passScore($defaultQuestionCount) }} acertos em {{ $defaultQuestionCount }} ({{ \App\Support\Grading::passValues() }} valores)" disabled><small>Definida em config/grading.php e calculada proporcionalmente à quantidade escolhida.</small></div>

<div class="field full exam-form-divider"><h3>Seleção de perguntas</h3><p>Escolha como a prova será composta e reveja as perguntas selecionadas.</p></div>
@if($selada)
    {{-- Prova já respondida: as perguntas ficam seladas. --}}
    <div class="field full"><p class="alert warning">Esta prova já tem {{ $attemptCount }} {{ $attemptCount === 1 ? 'tentativa submetida' : 'tentativas submetidas' }}, por isso as perguntas não podem ser trocadas. As respostas dos alunos ficaram guardadas contra estas perguntas; substituí-las passaria a corrigir provas antigas com perguntas que ninguém viu. O nome, a duração e o acesso continuam editáveis.</p></div>
    <div class="field full"><label>Perguntas da prova <small>{{ $exam->questions->count() }} perguntas, seladas.</small></label>
        <ol class="sealed-questions">@foreach($exam->questions as $question)<li>{{ $question->statement }}</li>@endforeach</ol>
    </div>
@else
    <div class="field full"><label>Modo de seleção</label>
        <select name="selection_mode" id="selection-mode">
            {{-- Ao editar, o modo manual vem à frente com as perguntas actuais já
                 marcadas: gravar mantém a prova como está. O modo por critérios
                 continua disponível, mas volta a sortear tudo de novo. --}}
            <option value="manual" @selected(old('selection_mode', $exam->exists ? 'manual' : 'blueprint') === 'manual')>Manual — escolher pergunta a pergunta</option>
            <option value="blueprint" @selected(old('selection_mode', $exam->exists ? 'manual' : 'blueprint') === 'blueprint')>Por critérios — sorteia do banco aprovado{{ $exam->exists ? ' (substitui as perguntas actuais)' : ' (recomendado)' }}</option>
        </select>
        <small>No modo por critérios os critérios ficam guardados na prova e a distribuição é equilibrada entre os temas.</small>
    </div>

    {{-- Modo por critérios (blueprint) --}}
    <div class="field" data-mode="blueprint"><label>Categoria de carta</label>
        <select name="blueprint_category">
            <option value="ligeiro" @selected(old('blueprint_category', $exam->blueprint['category'] ?? 'ligeiro') === 'ligeiro')>Ligeiro</option>
            <option value="pesado" @selected(old('blueprint_category', $exam->blueprint['category'] ?? null) === 'pesado')>Pesado</option>
            <option value="profissional_publico" @selected(old('blueprint_category', $exam->blueprint['category'] ?? null) === 'profissional_publico')>Profissional / público</option>
        </select>
    </div>
    <div class="field" data-mode="blueprint"><label>Número de perguntas</label>
        <input type="number" name="blueprint_question_count" min="1" max="100" value="{{ old('blueprint_question_count', $exam->blueprint['question_count'] ?? $defaultQuestionCount) }}">
        <small>A nota de passagem é derivada automaticamente ({{ $passPercentage }}%).</small>
    </div>
    <div class="field full" data-mode="blueprint"><label>Temas a incluir</label>
        <div class="topic-selector">
            <div class="topic-selector-head"><span id="topic-selection-summary">Todos os temas ativos</span><div><button class="btn light small" id="open-topic-picker" type="button">Escolher temas</button><button class="btn light small" id="clear-topics" type="button">Usar todos</button></div></div>
            <div class="selected-topics" id="selected-topics">
                @foreach($selectedTopics as $topic)<button type="button" class="selected-topic" data-topic-id="{{ $topic->id }}" data-topic-name="{{ $topic->name }}" data-question-count="{{ $topic->available_questions_count }}">{{ $topic->name }} <span>×</span><input type="hidden" name="blueprint_topic_ids[]" value="{{ $topic->id }}"></button>@endforeach
            </div>
        </div>
        <small>Sem seleção, a prova distribui perguntas por todos os temas ativos.</small>
    </div>

    {{-- Modo manual --}}
    @php($selecionadas = old('question_ids', $exam->exists ? $exam->questions->pluck('id')->all() : []))
    <div class="field" data-mode="manual"><label>Quantidade para seleção automática</label><div class="automatic-selection"><input id="automatic-count" type="number" min="1" max="100" value="{{ $exam->question_count ?: $defaultQuestionCount }}"><button class="btn light" id="automatic-select" type="button">Selecionar automaticamente</button></div><small>Prioriza perguntas nunca usadas e depois as menos utilizadas.</small></div>
    <div class="field full" data-mode="manual"><label>Pesquisar perguntas</label><input id="question-search" placeholder="Identificador, enunciado, tema ou categoria"></div>
    <div class="field full" data-mode="manual"><label>Perguntas da prova <small>O identificador e a utilização ajudam a evitar repetições.</small></label><div class="question-picker" id="question-picker">
        @forelse($questions as $question)
        <label class="question-choice" data-type="{{ $question->type }}" data-usage="{{ $question->exams_count }}" data-search="{{ str($question->external_id.' '.$question->statement.' '.$question->topic->name.' '.implode(' ', $question->categories))->lower() }}">
            <input type="checkbox" name="question_ids[]" value="{{ $question->id }}" @checked(in_array($question->id, $selecionadas))>
            <span class="question-description"><strong>{{ $question->statement }}</strong><small>{{ $question->topic->name }} · {{ ucfirst($question->type) }}</small>@if($question->exams_count)<small class="question-exams">Usada em {{ $question->exams_count }} {{ $question->exams_count === 1 ? 'prova' : 'provas' }}</small>@else<small class="question-unused">Nunca usada numa prova</small>@endif</span>
            <span class="usage-badge {{ $question->exams_count ? 'used' : 'unused' }}">{{ $question->exams_count }}×</span>
        </label>
        @empty<div class="empty">Não existem perguntas aprovadas disponíveis.</div>@endforelse
    </div><small id="selected-count">0 perguntas selecionadas</small></div>
@endif
</div>
 </section>
<dialog class="topic-picker-modal" id="topic-picker-modal" data-source="{{ route('admin.exams.topic-options') }}" aria-labelledby="topic-picker-title">
    <div class="topic-picker-head"><div><h2 id="topic-picker-title">Selecionar temas</h2><p id="topic-picker-count">0 selecionados</p></div><button type="button" id="close-topic-picker" aria-label="Fechar">×</button></div>
    <div class="topic-picker-search"><input type="search" id="topic-search" placeholder="Pesquisar tema" autocomplete="off"></div>
    <div class="topic-results" id="topic-results"></div>
    <div class="topic-picker-footer"><span id="topic-result-status"></span><button class="btn light small" id="load-more-topics" type="button" hidden>Carregar mais</button><button class="btn small" id="apply-topics" type="button">Concluir</button></div>
</dialog>
@if(session('warning'))<p class="alert warning">{{ session('warning') }}</p>@endif
<div class="form-actions exam-form-actions"><x-admin.button variant="secondary" :href="route('admin.exams.index')">Cancelar</x-admin.button><x-admin.button type="submit" loading-label="A guardar…"><i class="bi bi-check-lg" aria-hidden="true"></i>{{ $exam->exists ? 'Guardar alterações' : 'Criar prova' }}</x-admin.button></div>
</form>
<script>
(function(){
var search=document.getElementById('question-search'),type=document.getElementById('question-type'),visibility=document.getElementById('visibility'),school=document.getElementById('school-field'),choices=[].slice.call(document.querySelectorAll('.question-choice')),count=document.getElementById('selected-count'),automaticCount=document.getElementById('automatic-count'),automaticSelect=document.getElementById('automatic-select'),mode=document.getElementById('selection-mode');
function refreshAccess(){if(school&&visibility)school.style.display=visibility.value==='private'?'grid':'none';var plan=document.getElementById('plan-field');if(plan&&visibility)plan.style.display=visibility.value==='public'?'grid':'none'}
if(visibility)visibility.addEventListener('change',refreshAccess);refreshAccess();
// A prova selada não tem selector de perguntas: o resto do formulário continua a funcionar.
if(!mode)return;
var topicModal=document.getElementById('topic-picker-modal'),topicSearch=document.getElementById('topic-search'),topicResults=document.getElementById('topic-results'),selectedTopics=document.getElementById('selected-topics'),topicSummary=document.getElementById('topic-selection-summary'),topicCount=document.getElementById('topic-picker-count'),topicStatus=document.getElementById('topic-result-status'),loadMore=document.getElementById('load-more-topics'),selectedTopicMap=new Map(),nextTopicPage=null,topicTimer=null;
[].slice.call(selectedTopics.querySelectorAll('.selected-topic')).forEach(function(item){selectedTopicMap.set(String(item.dataset.topicId),{id:item.dataset.topicId,name:item.dataset.topicName,question_count:Number(item.dataset.questionCount)||0})});
function renderSelectedTopics(){
    selectedTopics.replaceChildren();
    selectedTopicMap.forEach(function(topic){var chip=document.createElement('button');chip.type='button';chip.className='selected-topic';chip.dataset.topicId=topic.id;chip.textContent=topic.name+' ';var close=document.createElement('span');close.textContent='×';chip.appendChild(close);var input=document.createElement('input');input.type='hidden';input.name='blueprint_topic_ids[]';input.value=topic.id;chip.appendChild(input);chip.addEventListener('click',function(){selectedTopicMap.delete(String(topic.id));renderSelectedTopics();renderTopicResultsState()});selectedTopics.appendChild(chip)});
    var total=selectedTopicMap.size;topicSummary.textContent=total?total+' '+(total===1?'tema selecionado':'temas selecionados'):'Todos os temas ativos';topicCount.textContent=total+' '+(total===1?'selecionado':'selecionados');
}
function renderTopicResultsState(){[].slice.call(topicResults.querySelectorAll('.topic-result')).forEach(function(button){var selected=selectedTopicMap.has(button.dataset.topicId);button.classList.toggle('selected',selected);button.setAttribute('aria-pressed',selected?'true':'false');button.querySelector('.topic-result-mark').textContent=selected?'✓':'+'})}
function appendTopic(topic){var button=document.createElement('button');button.type='button';button.className='topic-result';button.dataset.topicId=topic.id;button.innerHTML='<span><strong></strong><small></small></span><b class="topic-result-mark">+</b>';button.querySelector('strong').textContent=topic.name;button.querySelector('small').textContent=topic.question_count+' '+(topic.question_count===1?'pergunta disponível':'perguntas disponíveis');button.addEventListener('click',function(){var key=String(topic.id);selectedTopicMap.has(key)?selectedTopicMap.delete(key):selectedTopicMap.set(key,topic);renderSelectedTopics();renderTopicResultsState()});topicResults.appendChild(button)}
function loadTopics(page){var url=new URL(topicModal.dataset.source,location.origin);url.searchParams.set('page',page||1);url.searchParams.set('q',topicSearch.value.trim());url.searchParams.set('type',type.value);var category=document.querySelector('[name=blueprint_category]');if(category)url.searchParams.set('category',category.value);topicStatus.textContent='A carregar…';loadMore.hidden=true;fetch(url,{headers:{Accept:'application/json'}}).then(function(response){if(!response.ok)throw new Error('HTTP '+response.status);return response.json()}).then(function(payload){if((page||1)===1)topicResults.replaceChildren();payload.data.forEach(appendTopic);nextTopicPage=payload.next_page;loadMore.hidden=!nextTopicPage;topicStatus.textContent=payload.total+' '+(payload.total===1?'tema encontrado':'temas encontrados');if(!payload.data.length&&(page||1)===1)topicStatus.textContent='Nenhum tema encontrado';renderTopicResultsState()}).catch(function(error){topicStatus.textContent='Não foi possível carregar os temas.';console.error('[ProntoVia:provas] Falha ao pesquisar temas',{url:url.toString(),error:error})})}
document.getElementById('open-topic-picker').addEventListener('click',function(){topicModal.showModal();topicSearch.focus();loadTopics(1)});document.getElementById('close-topic-picker').addEventListener('click',function(){topicModal.close()});document.getElementById('apply-topics').addEventListener('click',function(){topicModal.close()});document.getElementById('clear-topics').addEventListener('click',function(){selectedTopicMap.clear();renderSelectedTopics();renderTopicResultsState()});loadMore.addEventListener('click',function(){if(nextTopicPage)loadTopics(nextTopicPage)});topicSearch.addEventListener('input',function(){clearTimeout(topicTimer);topicTimer=setTimeout(function(){loadTopics(1)},250)});topicModal.addEventListener('click',function(event){if(event.target===topicModal)topicModal.close()});renderSelectedTopics();
function refreshMode(){var current=mode.value;[].slice.call(document.querySelectorAll('[data-mode]')).forEach(function(field){field.style.display=field.dataset.mode===current?'grid':'none'})}
function refresh(){var term=search.value.toLowerCase();choices.forEach(function(item){item.style.display=item.dataset.type===type.value&&item.dataset.search.includes(term)?'flex':'none'});var selected=document.querySelectorAll('.question-choice input:checked').length;count.textContent=selected+' '+(selected===1?'pergunta selecionada':'perguntas selecionadas')}
function autoSelect(){var wanted=Math.max(1,Math.min(100,Number(automaticCount.value)||25)),eligible=choices.filter(function(item){return item.dataset.type===type.value});choices.forEach(function(item){item.querySelector('input').checked=false});eligible.sort(function(a,b){var difference=Number(a.dataset.usage)-Number(b.dataset.usage);return difference||Math.random()-.5});eligible.slice(0,wanted).forEach(function(item){item.querySelector('input').checked=true});refresh();if(eligible.length<wanted)count.textContent+=' — existem apenas '+eligible.length+' disponíveis'}
search.addEventListener('input',refresh);type.addEventListener('change',refresh);automaticSelect.addEventListener('click',autoSelect);mode.addEventListener('change',refreshMode);choices.forEach(function(item){item.querySelector('input').addEventListener('change',refresh)});refresh();refreshMode();
})();
</script>
@endsection
