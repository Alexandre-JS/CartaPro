@if(session('status') || session('warning') || $errors->any())
    <section class="admin-messages" aria-label="Mensagens do sistema">
        @if(session('status'))
            <div class="admin-toast admin-toast--success" role="status" aria-live="polite" data-message-toast><i class="bi bi-check-circle" aria-hidden="true"></i><div><strong>Operação concluída</strong><span>{{ session('status') }}</span></div><button type="button" data-message-dismiss aria-label="Fechar mensagem">×</button></div>
        @endif
        @if(session('warning'))
            <div class="admin-message admin-message--warning" role="status" aria-live="polite"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><div><strong>Atenção</strong><span>{{ session('warning') }}</span></div><button type="button" data-message-dismiss aria-label="Fechar mensagem">×</button></div>
        @endif
        @if($errors->any())
            <div class="admin-message admin-message--error" role="alert" tabindex="-1" data-error-summary><i class="bi bi-x-circle" aria-hidden="true"></i><div><strong>Existem dados que precisam da sua atenção</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div><button type="button" data-message-dismiss aria-label="Fechar mensagem">×</button></div>
        @endif
    </section>
@endif
