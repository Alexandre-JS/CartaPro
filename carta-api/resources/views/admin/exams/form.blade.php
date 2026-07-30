@extends('layouts.admin')
@section('title', 'Nova prova')
@section('page-title', 'Criar prova')
@section('page-subtitle', 'Escolha manualmente ou gere uma seleção com o mínimo possível de repetições.')
@section('content')
<form class="card form-card" method="POST" action="{{ route('admin.exams.store') }}">@csrf
<div class="form-grid">
    <div class="field"><label>Nome da prova</label><input name="name" value="{{ old('name') }}" required></div>
    @if(auth()->user()->isAdmin())<div class="field"><label>Visibilidade</label><select name="visibility" id="visibility"><option value="public" @selected(old('visibility') === 'public')>Pública — aplicativo</option><option value="private" @selected(old('visibility','private') === 'private')>Privada — escola</option></select></div><div class="field" id="school-field"><label>Escola da prova privada</label><select name="school_id"><option value="">Selecione a escola</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}</option>@endforeach</select></div>@else<input type="hidden" name="visibility" value="private">@endif
    <div class="field"><label>Tipo das perguntas</label><select name="type" id="question-type"><option value="teorico">Teóricas</option><option value="pratico" @selected(old('type') === 'pratico')>Práticas</option></select></div>
    <div class="field"><label>Duração (minutos)</label><input type="number" name="duration_minutes" min="1" max="300" value="{{ old('duration_minutes', $defaultQuestionCount ? \App\Support\Grading::durationMinutes() : 60) }}" required></div>
    <div class="field"><label>Regra de aprovação</label><input value="{{ $passPercentage }}% — {{ \App\Support\Grading::passScore($defaultQuestionCount) }} acertos em {{ $defaultQuestionCount }} ({{ \App\Support\Grading::passValues() }} valores)" disabled><small>Definida em config/grading.php e calculada proporcionalmente à quantidade escolhida.</small></div>

    <div class="field full"><label>Modo de seleção</label>
        <select name="selection_mode" id="selection-mode">
            <option value="blueprint" @selected(old('selection_mode', 'blueprint') === 'blueprint')>Por critérios — sorteia do banco aprovado (recomendado)</option>
            <option value="manual" @selected(old('selection_mode') === 'manual')>Manual — escolher pergunta a pergunta</option>
        </select>
        <small>No modo por critérios os critérios ficam guardados na prova e a distribuição é equilibrada entre os temas.</small>
    </div>

    {{-- Modo por critérios (blueprint) --}}
    <div class="field" data-mode="blueprint"><label>Categoria de carta</label>
        <select name="blueprint_category">
            <option value="ligeiro" @selected(old('blueprint_category','ligeiro') === 'ligeiro')>Ligeiro</option>
            <option value="pesado" @selected(old('blueprint_category') === 'pesado')>Pesado</option>
            <option value="profissional_publico" @selected(old('blueprint_category') === 'profissional_publico')>Profissional / público</option>
        </select>
    </div>
    <div class="field" data-mode="blueprint"><label>Número de perguntas</label>
        <input type="number" name="blueprint_question_count" min="1" max="100" value="{{ old('blueprint_question_count', $defaultQuestionCount) }}">
        <small>A nota de passagem é derivada automaticamente ({{ $passPercentage }}%).</small>
    </div>
    <div class="field full" data-mode="blueprint"><label>Temas a incluir <small>Nenhum selecionado = todos os temas ativos.</small></label>
        <div class="topic-picker">
            @foreach($topics as $topic)
                <label class="topic-choice"><input type="checkbox" name="blueprint_topic_ids[]" value="{{ $topic->id }}" @checked(in_array($topic->id, old('blueprint_topic_ids', [])))> <span>{{ $topic->name }}</span></label>
            @endforeach
        </div>
    </div>

    {{-- Modo manual --}}
    <div class="field" data-mode="manual"><label>Quantidade para seleção automática</label><div class="automatic-selection"><input id="automatic-count" type="number" min="1" max="100" value="{{ $defaultQuestionCount }}"><button class="btn light" id="automatic-select" type="button">Selecionar automaticamente</button></div><small>Prioriza perguntas nunca usadas e depois as menos utilizadas.</small></div>
    <div class="field full" data-mode="manual"><label>Pesquisar perguntas</label><input id="question-search" placeholder="Identificador, enunciado, tema ou categoria"></div>
    <div class="field full" data-mode="manual"><label>Perguntas da prova <small>O identificador e a utilização ajudam a evitar repetições.</small></label><div class="question-picker" id="question-picker">
        @forelse($questions as $question)
        <label class="question-choice" data-type="{{ $question->type }}" data-usage="{{ $question->exams_count }}" data-search="{{ str($question->external_id.' '.$question->statement.' '.$question->topic->name.' '.implode(' ', $question->categories))->lower() }}">
            <input type="checkbox" name="question_ids[]" value="{{ $question->id }}" @checked(in_array($question->id, old('question_ids', [])))>
            <span class="question-description"><strong>{{ $question->external_id }} — {{ $question->statement }}</strong><small>{{ $question->topic->name }} · {{ implode(', ', $question->categories) }} · {{ ucfirst($question->type) }}</small>@if($question->exams_count)<small class="question-exams">Usada em {{ $question->exams_count }} {{ $question->exams_count === 1 ? 'prova' : 'provas' }}: {{ $question->exams->pluck('name')->join(', ') }}</small>@else<small class="question-unused">Nunca usada numa prova</small>@endif</span>
            <span class="usage-badge {{ $question->exams_count ? 'used' : 'unused' }}">{{ $question->exams_count }}×</span>
        </label>
        @empty<div class="empty">Não existem perguntas aprovadas disponíveis.</div>@endforelse
    </div><small id="selected-count">0 perguntas selecionadas</small></div>
</div>
@if(session('warning'))<p class="alert warning">{{ session('warning') }}</p>@endif
<div class="form-actions"><a class="btn light" href="{{ route('admin.exams.index') }}">Cancelar</a><button class="btn">Criar prova</button></div>
</form>
<script>
(function(){
var search=document.getElementById('question-search'),type=document.getElementById('question-type'),visibility=document.getElementById('visibility'),school=document.getElementById('school-field'),choices=[].slice.call(document.querySelectorAll('.question-choice')),count=document.getElementById('selected-count'),automaticCount=document.getElementById('automatic-count'),automaticSelect=document.getElementById('automatic-select'),mode=document.getElementById('selection-mode');
function refreshMode(){var current=mode.value;[].slice.call(document.querySelectorAll('[data-mode]')).forEach(function(field){field.style.display=field.dataset.mode===current?'grid':'none'})}
function refresh(){var term=search.value.toLowerCase();choices.forEach(function(item){item.style.display=item.dataset.type===type.value&&item.dataset.search.includes(term)?'flex':'none'});var selected=document.querySelectorAll('.question-choice input:checked').length;count.textContent=selected+' '+(selected===1?'pergunta selecionada':'perguntas selecionadas');if(school&&visibility)school.style.display=visibility.value==='private'?'grid':'none'}
function autoSelect(){var wanted=Math.max(1,Math.min(100,Number(automaticCount.value)||25)),eligible=choices.filter(function(item){return item.dataset.type===type.value});choices.forEach(function(item){item.querySelector('input').checked=false});eligible.sort(function(a,b){var difference=Number(a.dataset.usage)-Number(b.dataset.usage);return difference||Math.random()-.5});eligible.slice(0,wanted).forEach(function(item){item.querySelector('input').checked=true});refresh();if(eligible.length<wanted)count.textContent+=' — existem apenas '+eligible.length+' disponíveis'}
search.addEventListener('input',refresh);type.addEventListener('change',refresh);automaticSelect.addEventListener('click',autoSelect);mode.addEventListener('change',refreshMode);if(visibility)visibility.addEventListener('change',refresh);choices.forEach(function(item){item.querySelector('input').addEventListener('change',refresh)});refresh();refreshMode();
})();
</script>
@endsection
