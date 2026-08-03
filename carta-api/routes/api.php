<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\DebitoPayWebhookController;
use App\Http\Controllers\Api\V1\ExamSessionController;
use App\Http\Controllers\Api\V1\ManagementController;
use App\Http\Controllers\Api\V1\MobileController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaySuiteWebhookController;
use App\Http\Controllers\Api\V1\UnlockController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Público — apenas o que não transporta conteúdo nem dados pessoais
    |--------------------------------------------------------------------------
    */
    Route::post('/mobile/register', [MobileController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/mobile/login', [MobileController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    /*
     * Webhook da PaySuite (confirmação da e-Mola). Público por necessidade — é
     * o fornecedor que chama —, pelo que a assinatura HMAC é a única coisa que
     * separa uma confirmação legítima de alguém a oferecer-se o plano completo.
     */
    Route::post('/webhooks/paysuite', PaySuiteWebhookController::class)
        ->name('webhooks.paysuite')
        ->middleware('throttle:60,1');

    Route::post('/webhooks/debitopay', DebitoPayWebhookController::class)
        ->name('webhooks.debitopay')
        ->middleware('throttle:120,1');

    // Prova da escola: o ecrã de entrada só mostra o estado da sessão.
    // A pauta da turma deixou de ser exposta e a submissão exige bilhete.
    Route::get('/sessions/{code}', [ExamSessionController::class, 'show'])->middleware('throttle:30,1');
    Route::post('/sessions/{code}/entrar', [ExamSessionController::class, 'enter'])->middleware('throttle:10,1');
    Route::get('/sessions/{code}/perguntas', [ExamSessionController::class, 'questions'])->middleware('throttle:60,1');
    Route::post('/sessions/{code}/submeter', [ExamSessionController::class, 'submit'])->middleware('throttle:20,1');

    /*
    |--------------------------------------------------------------------------
    | App do aluno — exige sessão móvel
    |--------------------------------------------------------------------------
    | Todo o conteúdo vive aqui: o pacote transporta a resposta correta e a
    | explicação de cada pergunta, pelo que não pode ser servido anonimamente.
    | O plano (gratis/pago) é decidido no servidor pelo EntitlementService.
    */
    Route::middleware('mobile.auth')->group(function () {
        Route::prefix('mobile')->group(function () {
            Route::get('/me', [MobileController::class, 'me']);
            Route::put('/me', [MobileController::class, 'update']);
            Route::post('/logout', [MobileController::class, 'logout']);
            Route::get('/snapshot', [MobileController::class, 'snapshot']);
            Route::post('/sync', [MobileController::class, 'sync']);
            Route::get('/exams', [MobileController::class, 'exams']);
            Route::get('/exams/{exam}', [MobileController::class, 'exam'])->whereNumber('exam');

            // Desbloqueio ligado à conta, com prova de posse do número.
            // Continua a servir os pagamentos que o apoio ao cliente regista à
            // mão; num pagamento C2B o PIN já prova a posse e o OTP é dispensado.
            Route::get('/unlock', [UnlockController::class, 'status']);
            Route::post('/unlock/request', [UnlockController::class, 'requestCode'])->middleware('throttle:5,10');
            Route::post('/unlock/confirm', [UnlockController::class, 'confirmCode'])->middleware('throttle:10,10');

            // Pagamento dentro do app. O limite no POST é apertado de propósito:
            // cada tentativa faz aparecer um pedido de PIN no telemóvel do aluno.
            Route::get('/payments/plans', [PaymentController::class, 'plans']);
            Route::post('/payments', [PaymentController::class, 'store'])->middleware('throttle:5,10');
            // O polling não é uma leitura barata: enquanto o pagamento está
            // pendente, cada chamada faz um check-status à DebitoPay. Sem
            // limite, um ecrã que sondasse em ciclo apertado gastava processos
            // do servidor e esbarrava no tecto de 60/min do fornecedor —
            // devolvendo 429 a toda a gente, não só a quem sondava.
            Route::get('/payments/{payment}', [PaymentController::class, 'show'])
                ->whereNumber('payment')
                ->middleware('throttle:30,1');
        });

        Route::get('/topics', [ContentController::class, 'topics']);
        Route::get('/questions', [ContentController::class, 'questions']);
        Route::get('/content-package', [ContentController::class, 'package']);
        Route::get('/signs', [ContentController::class, 'signs']);
        Route::get('/articles', [ContentController::class, 'articles']);
        Route::get('/categories', [ContentController::class, 'categories']);
        Route::get('/packages', [ContentController::class, 'packages']);
    });

    /*
    |--------------------------------------------------------------------------
    | Painel (admin / escola)
    |--------------------------------------------------------------------------
    */
    Route::middleware('api.auth')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/perguntas', [ManagementController::class, 'questions']);
        Route::post('/perguntas', [ManagementController::class, 'storeQuestion']);
        Route::put('/perguntas/{question}', [ManagementController::class, 'updateQuestion']);
        Route::post('/perguntas/{question}/aprovar', [ManagementController::class, 'approveQuestion'])->middleware('role:admin');
        Route::post('/perguntas/{question}/rejeitar', [ManagementController::class, 'rejectQuestion'])->middleware('role:admin');

        Route::get('/sinais', [ManagementController::class, 'signs']);
        Route::get('/artigos', [ManagementController::class, 'articles']);
        Route::get('/temas', [ManagementController::class, 'topics']);
        Route::get('/categorias-carta', [ManagementController::class, 'categories']);
        Route::get('/provas', [ManagementController::class, 'exams']);
        Route::post('/provas', [ManagementController::class, 'storeExam']);
        Route::post('/provas/{exam}/aplicar', [ManagementController::class, 'applyExam']);
        Route::get('/provas/{exam}/resultados', [ManagementController::class, 'examResults']);
        Route::get('/escolas/{school}/turmas', [ManagementController::class, 'classrooms']);
        Route::post('/escolas/{school}/turmas', [ManagementController::class, 'storeClassroom']);
        Route::get('/turmas/{classroom}/alunos', [ManagementController::class, 'students']);
        Route::post('/turmas/{classroom}/alunos', [ManagementController::class, 'storeStudent']);
        Route::get('/turmas/{classroom}/analitica', [ManagementController::class, 'classroomAnalytics']);

        Route::middleware('role:admin')->group(function () {
            Route::post('/sinais', [ManagementController::class, 'storeSign']);
            Route::put('/sinais/{sign}', [ManagementController::class, 'updateSign']);
            Route::post('/artigos/importar', [ManagementController::class, 'importArticles']);
            Route::post('/temas', [ManagementController::class, 'storeTopic']);
            Route::put('/temas/{topic}', [ManagementController::class, 'updateTopic']);
            Route::post('/categorias-carta', [ManagementController::class, 'storeCategory']);
            Route::put('/categorias-carta/{category}', [ManagementController::class, 'updateCategory']);
            Route::post('/publicar', [ManagementController::class, 'publish']);
            Route::get('/pacotes-gestao', [ManagementController::class, 'managedPackages']);
            Route::get('/escolas', [ManagementController::class, 'schools']);
            Route::post('/escolas', [ManagementController::class, 'storeSchool']);
            Route::put('/escolas/{school}', [ManagementController::class, 'updateSchool']);
            Route::get('/utilizadores', [ManagementController::class, 'users']);
            Route::post('/utilizadores', [ManagementController::class, 'storeUser']);
            Route::put('/utilizadores/{user}', [ManagementController::class, 'updateUser']);
            Route::get('/desbloqueios', [ManagementController::class, 'unlocks']);
            Route::post('/desbloqueios', [ManagementController::class, 'storeUnlock']);
        });
    });
});
