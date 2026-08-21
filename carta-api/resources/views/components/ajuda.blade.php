{{--
    Ajuda de campo: um "!" que revela a explicação ao passar o rato ou ao focar.

    O texto vive aqui em vez de num `<small>` permanente porque um formulário
    com uma legenda debaixo de cada campo lê-se pior — a informação que só
    importa na dúvida passa a competir com a que importa sempre. Quem já sabe
    preenche sem ruído; quem hesita tem a resposta a um gesto de distância.

    Acessível de propósito: `tabindex` e `role` fazem-no alcançável por teclado
    e anunciável por leitores de ecrã, que é o que separa isto de um adorno.
--}}
@props(['texto'])

<span class="ajuda" tabindex="0" role="note" aria-label="Ajuda: {{ $texto }}">
    <span class="ajuda-marca" aria-hidden="true">!</span>
    <span class="ajuda-balao">{{ $texto }}</span>
</span>
