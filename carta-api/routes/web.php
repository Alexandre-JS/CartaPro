<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DetailController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\ExamSessionController;
use App\Http\Controllers\Admin\GlossaryController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LicenseCategoryController;
use App\Http\Controllers\Admin\MobileUserController;
use App\Http\Controllers\Admin\PaymentAdminController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PublicationController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SchoolOperationsController;
use App\Http\Controllers\Admin\SignCategoryController;
use App\Http\Controllers\Admin\SignController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Admin\UnlockController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentExamController;
use App\Http\Controllers\Website\CandidateController;
use App\Http\Controllers\Website\HomeController;
use App\Http\Controllers\Website\SchoolLandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('website.home');
Route::get('/candidatos', CandidateController::class)->name('website.candidates');
Route::get('/escolas', SchoolLandingController::class)->name('website.schools');
Route::get('/sitemap.xml', function () {
    $urls = collect([
        ['loc' => route('website.home'), 'priority' => '1.0'],
        ['loc' => route('website.candidates'), 'priority' => '0.9'],
        ['loc' => route('website.schools'), 'priority' => '0.9'],
    ]);

    return response()->view('website.sitemap', compact('urls'))
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('website.sitemap');
Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\nDisallow: /admin/\nSitemap: ".route('website.sitemap')."\n", 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('website.robots');
Route::get('/prova/{code}', [StudentExamController::class, 'entry'])->name('student-exam.entry');
Route::post('/prova/{code}/entrar', [StudentExamController::class, 'enter'])->name('student-exam.enter');
Route::get('/prova/{code}/realizar/{student}', [StudentExamController::class, 'take'])->middleware('signed')->name('student-exam.take');
Route::post('/prova/{code}/submeter/{student}', [StudentExamController::class, 'submit'])->middleware('signed')->name('student-exam.submit');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
    Route::resource('questions', QuestionController::class)->except('show')->middleware('permission:question.create');
    Route::get('/questions/{question}', [DetailController::class, 'question'])->whereNumber('question')->middleware('permission:question.create')->name('questions.show');
    Route::get('/signs', [SignController::class, 'index'])->name('signs.index');
    Route::get('/signs/{sign}', [DetailController::class, 'sign'])->whereNumber('sign')->name('signs.show');
    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/{article}', [DetailController::class, 'article'])->whereNumber('article')->name('articles.show');
    Route::get('/topics/{topic}', [DetailController::class, 'topic'])->whereNumber('topic')->name('topics.show');
    // Material de estudo: escolas leem, admin gere.
    Route::get('/lessons', [LessonController::class, 'index'])->name('lessons.index');
    Route::get('/lessons/{lesson}', [DetailController::class, 'lesson'])->whereNumber('lesson')->name('lessons.show');
    Route::get('/glossary', [GlossaryController::class, 'index'])->name('glossary.index');
    Route::get('/glossary/{term}', [DetailController::class, 'glossaryTerm'])->whereNumber('term')->name('glossary.show');
    Route::resource('classrooms', ClassroomController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:classroom.manage');
    Route::get('/classrooms/{classroom}', [DetailController::class, 'classroom'])->whereNumber('classroom')->middleware('permission:student.view')->name('classrooms.show');
    Route::post('/classrooms/{classroom}/students', [StudentController::class, 'store'])->middleware('permission:classroom.manage')->name('students.store');
    Route::get('/students/{student}', [StudentController::class, 'show'])->middleware('permission:student.view')->name('students.show');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->middleware('permission:classroom.manage')->name('students.destroy');
    Route::get('/exams/topic-options', [ExamController::class, 'topicOptions'])->name('exams.topic-options');
    Route::resource('exams', ExamController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->middleware('permission:exam.create');
    Route::get('/exams/{exam}', [DetailController::class, 'exam'])->whereNumber('exam')->middleware('permission:exam.create')->name('exams.show');
    Route::resource('sessions', ExamSessionController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:exam.publish');
    Route::get('/sessions/{session}', [DetailController::class, 'session'])->whereNumber('session')->middleware('permission:analytics.view')->name('sessions.show');
    Route::get('/results', [ResultController::class, 'index'])->middleware('permission:analytics.view')->name('results.index');
    Route::get('/results/export', [ResultController::class, 'export'])->middleware('permission:analytics.view')->name('results.export');
    // Painel por turma: médias, temas mais falhados, evolução e prontidão.
    Route::get('/results/turma/{classroom}', [ResultController::class, 'classroom'])->whereNumber('classroom')->middleware('permission:analytics.view')->name('results.classroom');
    Route::get('/results/{attempt}', [DetailController::class, 'result'])->whereNumber('attempt')->middleware('permission:analytics.view')->name('results.show');
    Route::get('/approvals', [ApprovalController::class, 'index'])->middleware('permission:question.review')->name('approvals.index');
    Route::patch('/approvals/{question}/approve', [ApprovalController::class, 'approve'])->middleware('permission:question.review')->name('approvals.approve');
    Route::patch('/approvals/{question}/reject', [ApprovalController::class, 'reject'])->middleware('permission:question.review')->name('approvals.reject');
    Route::middleware('role:admin')->group(function () {
        Route::resource('schools', SchoolController::class)->except('show');
        Route::get('/schools/{school}', [DetailController::class, 'school'])->whereNumber('school')->name('schools.show');
        Route::resource('users', UserController::class)->except('show');
        Route::get('/users/{user}', [DetailController::class, 'user'])->whereNumber('user')->name('users.show');
        Route::get('/mobile-users', [MobileUserController::class, 'index'])->name('mobile-users.index');
        Route::get('/mobile-users/{mobileUser}', [MobileUserController::class, 'show'])->name('mobile-users.show');
        Route::patch('/mobile-users/{mobileUser}/status', [MobileUserController::class, 'updateStatus'])->name('mobile-users.status');
        Route::resource('topics', TopicController::class)->except(['index', 'show']);
        Route::resource('signs', SignController::class)->except(['index', 'show']);
        // Categorias e subcategorias de sinais: até aqui viviam em
        // config/estudo.php e mudá-las exigia um deploy.
        Route::resource('sign-categories', SignCategoryController::class)->except('show');
        Route::resource('articles', ArticleController::class)->except(['index', 'show']);
        Route::resource('lessons', LessonController::class)->except(['index', 'show']);
        Route::resource('glossary', GlossaryController::class)->only(['store', 'update', 'destroy']);
        Route::post('/articles/import', [ArticleController::class, 'import'])->name('articles.import');
        Route::resource('categories', LicenseCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
        Route::patch('/exams/{exam}/archive', [ExamController::class, 'archive'])->name('exams.archive');
        // Gratuita ou plano completo, sem ter de apagar e recriar a prova.
        Route::patch('/exams/{exam}/plano', [ExamController::class, 'plan'])->name('exams.plan');
        // Leva ao app uma prova de escola já aplicada, sem lhe tocar.
        Route::post('/exams/{exam}/copia-publica', [ExamController::class, 'duplicatePublic'])->name('exams.duplicate-public');
        Route::get('/publications', [PublicationController::class, 'index'])->name('publications.index');
        Route::post('/publications', [PublicationController::class, 'publish'])->name('publications.publish');
        Route::patch('/publications/{package}/restore', [PublicationController::class, 'restore'])->name('publications.restore');
        Route::get('/publications/{package}/download', [PublicationController::class, 'download'])->name('publications.download');
        Route::get('/publications/{package}', [DetailController::class, 'publication'])->whereNumber('package')->name('publications.show');
        Route::resource('unlocks', UnlockController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->whereNumber('plan')->name('plans.update');
        Route::patch('/unlocks/{unlock}/associar', [UnlockController::class, 'bind'])->name('unlocks.bind');
        // Devolução em 7 dias: retira o acesso ao mesmo tempo que se devolve o
        // dinheiro na carteira, que continua a ser um passo manual.
        Route::patch('/pagamentos/{payment}/devolver', [PaymentAdminController::class, 'refund'])
            ->middleware('payments.enabled')
            ->name('pagamentos.devolver');
        Route::get('/unlocks/{unlock}', [DetailController::class, 'unlock'])->whereNumber('unlock')->name('unlocks.show');
    });
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/school-operations', [SchoolOperationsController::class, 'index'])->middleware('permission:classroom.manage')->name('school-operations.index');
    Route::post('/instructors', [SchoolOperationsController::class, 'instructorStore'])->middleware('permission:instructor.manage')->name('instructors.store');
    Route::put('/instructors/{instructor}', [SchoolOperationsController::class, 'instructorUpdate'])->middleware('permission:instructor.manage')->name('instructors.update');
    Route::post('/instructors/{instructor}/classrooms', [SchoolOperationsController::class, 'instructorAttach'])->middleware('permission:instructor.manage')->name('instructors.attach');
    Route::post('/school-memberships/invite', [SchoolOperationsController::class, 'membershipInvite'])->middleware('permission:classroom.manage')->name('school-memberships.invite');
    Route::patch('/school-memberships/{membership}/status', [SchoolOperationsController::class, 'membershipStatus'])->middleware('permission:classroom.manage')->name('school-memberships.status');
    Route::post('/assignments', [SchoolOperationsController::class, 'assignmentStore'])->middleware('permission:assignment.manage')->name('assignments.store');
    Route::patch('/assignments/{assignment}/status', [SchoolOperationsController::class, 'assignmentStatus'])->middleware('permission:assignment.manage')->name('assignments.status');
});
