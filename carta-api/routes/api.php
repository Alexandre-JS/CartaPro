<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\DebitoPayWebhookController;
use App\Http\Controllers\Api\V1\ExamSessionController;
use App\Http\Controllers\Api\V1\InstructorController;
use App\Http\Controllers\Api\V1\LearningController;
use App\Http\Controllers\Api\V1\ManagementController;
use App\Http\Controllers\Api\V1\MobileController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaySuiteWebhookController;
use App\Http\Controllers\Api\V1\SchoolMembershipController;
use App\Http\Controllers\Api\V1\SchoolAssignmentController;
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
        ->middleware(['payments.enabled', 'throttle:60,1']);

    Route::post('/webhooks/debitopay', DebitoPayWebhookController::class)
        ->name('webhooks.debitopay')
        ->middleware(['payments.enabled', 'throttle:120,1']);

    // Prova da escola: o ecrã de entrada só mostra o estado da sessão.
    // A pauta da turma deixou de ser exposta e a submissão exige bilhete.
    Route::get('/sessions/{code}', [ExamSessionController::class, 'show'])->middleware('throttle:30,1');
    Route::post('/sessions/{code}/entrar', [ExamSessionController::class, 'enter'])->middleware('throttle:10,1');
    Route::get('/sessions/{code}/perguntas', [ExamSessionController::class, 'questions'])->middleware('throttle:60,1');
    Route::post('/sessions/{code}/submeter', [ExamSessionController::class, 'submit'])->middleware('throttle:20,1');

    /* App do aluno — a conta é necessária para dados pessoais e sincronização. */
    Route::middleware('mobile.auth')->group(function () {
        Route::prefix('mobile')->group(function () {
            Route::get('/me', [MobileController::class, 'me']);
            Route::put('/me', [MobileController::class, 'update']);
            Route::post('/logout', [MobileController::class, 'logout']);
            Route::get('/snapshot', [MobileController::class, 'snapshot']);
            Route::post('/sync', [MobileController::class, 'sync']);
            // Desbloqueio ligado à conta, com prova de posse do número.
            // Continua a servir os pagamentos que o apoio ao cliente regista à
            // mão; num pagamento C2B o PIN já prova a posse e o OTP é dispensado.
            Route::get('/unlock', [UnlockController::class, 'status']);
            Route::post('/unlock/request', [UnlockController::class, 'requestCode'])->middleware('throttle:5,10');
            Route::post('/unlock/confirm', [UnlockController::class, 'confirmCode'])->middleware('throttle:10,10');

            // Pagamento dentro do app. O limite no POST é apertado de propósito:
            // cada tentativa faz aparecer um pedido de PIN no telemóvel do aluno.
            Route::get('/payments/plans', [PaymentController::class, 'plans'])->middleware('payments.enabled');
            Route::post('/payments', [PaymentController::class, 'store'])->middleware(['payments.enabled', 'throttle:5,10']);
            // O polling não é uma leitura barata: enquanto o pagamento está
            // pendente, cada chamada faz um check-status à DebitoPay. Sem
            // limite, um ecrã que sondasse em ciclo apertado gastava processos
            // do servidor e esbarrava no tecto de 60/min do fornecedor —
            // devolvendo 429 a toda a gente, não só a quem sondava.
            Route::get('/payments/{payment}', [PaymentController::class, 'show'])
                ->whereNumber('payment')
                ->middleware(['payments.enabled', 'throttle:30,1']);
        });

    });

    /* Conteúdo Free: pode ser explorado sem conta; o servidor filtra o Plus. */
    Route::middleware('mobile.optional')->group(function () {
        // O catálogo é público. O controller entrega provas Free inteiras e
        // apenas os metadados/cadeado das provas ProntoVia+.
        Route::get('/mobile/exams', [MobileController::class, 'exams']);
        Route::get('/mobile/exams/{exam}', [MobileController::class, 'exam'])->whereNumber('exam');
        Route::get('/topics', [ContentController::class, 'topics']);
        Route::get('/questions', [ContentController::class, 'questions']);
        Route::get('/content-package', [ContentController::class, 'package']);
        Route::get('/signs', [ContentController::class, 'signs']);
        Route::get('/articles', [ContentController::class, 'articles']);
        Route::get('/categories', [ContentController::class, 'categories']);
        Route::get('/packages', [ContentController::class, 'packages']);
    });

    /* Histórico, escola, aprendizagem personalizada e tarefas exigem conta. */
    Route::middleware('mobile.auth')->group(function () {
        Route::get('/school-memberships', [SchoolMembershipController::class, 'index']);
        Route::patch('/school-memberships/{membership}/accept', [SchoolMembershipController::class, 'accept'])->whereNumber('membership');
        Route::patch('/school-memberships/{membership}/leave', [SchoolMembershipController::class, 'leave'])->whereNumber('membership');
        Route::get('/learning/profile', [LearningController::class, 'profile']);
        Route::get('/learning/events', [LearningController::class, 'events']);
        Route::get('/readiness', [LearningController::class, 'readiness']);
        Route::get('/recommendations', [LearningController::class, 'recommendations']);
        Route::get('/school-assignments', [SchoolAssignmentController::class, 'candidateIndex']);
        Route::patch('/school-assignment-progress/{progress}', [SchoolAssignmentController::class, 'updateProgress'])->whereNumber('progress');
    });

    /*
    |--------------------------------------------------------------------------
    | Painel (admin / escola)
    |--------------------------------------------------------------------------
    */
    Route::middleware('api.auth')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/perguntas', [ManagementController::class, 'questions'])->middleware('permission:question.create');
        Route::post('/perguntas', [ManagementController::class, 'storeQuestion'])->middleware('permission:question.create,question.submit');
        Route::put('/perguntas/{question}', [ManagementController::class, 'updateQuestion'])->middleware('permission:question.create');
        Route::post('/perguntas/{question}/aprovar', [ManagementController::class, 'approveQuestion'])->middleware('permission:question.review');
        Route::post('/perguntas/{question}/rejeitar', [ManagementController::class, 'rejectQuestion'])->middleware('permission:question.review');

        Route::get('/sinais', [ManagementController::class, 'signs']);
        Route::get('/artigos', [ManagementController::class, 'articles']);
        Route::get('/temas', [ManagementController::class, 'topics']);
        Route::get('/categorias-carta', [ManagementController::class, 'categories']);
        Route::get('/provas', [ManagementController::class, 'exams'])->middleware('permission:exam.create');
        Route::post('/provas', [ManagementController::class, 'storeExam'])->middleware('permission:exam.create');
        Route::post('/provas/{exam}/aplicar', [ManagementController::class, 'applyExam'])->middleware('permission:exam.publish');
        Route::get('/provas/{exam}/resultados', [ManagementController::class, 'examResults'])->middleware('permission:analytics.view');
        Route::get('/escolas/{school}/turmas', [ManagementController::class, 'classrooms'])->middleware('permission:student.view');
        Route::post('/escolas/{school}/turmas', [ManagementController::class, 'storeClassroom'])->middleware('permission:classroom.manage');
        Route::get('/turmas/{classroom}/alunos', [ManagementController::class, 'students'])->middleware('permission:student.view');
        Route::post('/turmas/{classroom}/alunos', [ManagementController::class, 'storeStudent'])->middleware('permission:classroom.manage');
        Route::get('/turmas/{classroom}/analitica', [ManagementController::class, 'classroomAnalytics'])->middleware('permission:analytics.view');
        Route::get('/escolas/{school}/vinculos', [SchoolMembershipController::class, 'schoolIndex'])->whereNumber('school')->middleware('permission:classroom.manage');
        Route::post('/escolas/{school}/vinculos', [SchoolMembershipController::class, 'invite'])->whereNumber('school')->middleware('permission:classroom.manage');
        Route::patch('/school-memberships/{membership}/status', [SchoolMembershipController::class, 'updateStatus'])->whereNumber('membership')->middleware('permission:classroom.manage');
        Route::get('/escolas/{school}/instrutores', [InstructorController::class, 'index'])->whereNumber('school')->middleware('permission:instructor.manage');
        Route::post('/escolas/{school}/instrutores', [InstructorController::class, 'store'])->whereNumber('school')->middleware('permission:instructor.manage');
        Route::put('/instrutores/{instructor}', [InstructorController::class, 'update'])->whereNumber('instructor')->middleware('permission:instructor.manage');
        Route::post('/instrutores/{instructor}/turmas/{classroom}', [InstructorController::class, 'attachClassroom'])->whereNumber('instructor')->whereNumber('classroom')->middleware('permission:instructor.manage');
        Route::delete('/instrutores/{instructor}/turmas/{classroom}', [InstructorController::class, 'detachClassroom'])->whereNumber('instructor')->whereNumber('classroom')->middleware('permission:instructor.manage');
        Route::get('/escolas/{school}/tarefas', [SchoolAssignmentController::class, 'index'])->whereNumber('school')->middleware('permission:assignment.manage');
        Route::post('/escolas/{school}/tarefas', [SchoolAssignmentController::class, 'store'])->whereNumber('school')->middleware('permission:assignment.manage');
        Route::put('/tarefas/{assignment}', [SchoolAssignmentController::class, 'update'])->whereNumber('assignment')->middleware('permission:assignment.manage');
        Route::patch('/tarefas/{assignment}/publicar', [SchoolAssignmentController::class, 'publish'])->whereNumber('assignment')->middleware('permission:assignment.manage');
        Route::patch('/tarefas/{assignment}/encerrar', [SchoolAssignmentController::class, 'close'])->whereNumber('assignment')->middleware('permission:assignment.manage');
        Route::get('/tarefas/{assignment}/progresso', [SchoolAssignmentController::class, 'progress'])->whereNumber('assignment')->middleware(['permission:assignment.manage', 'permission:analytics.view']);

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
            Route::get('/planos', [ManagementController::class, 'plans']);
            Route::put('/planos/{plan}', [ManagementController::class, 'updatePlan'])->whereNumber('plan');
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
