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
use App\Http\Controllers\Admin\PublicationController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SignController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Admin\UnlockController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentExamController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
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
    Route::resource('questions', QuestionController::class)->except('show');
    Route::get('/questions/{question}', [DetailController::class, 'question'])->whereNumber('question')->name('questions.show');
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
    Route::resource('classrooms', ClassroomController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/classrooms/{classroom}', [DetailController::class, 'classroom'])->whereNumber('classroom')->name('classrooms.show');
    Route::post('/classrooms/{classroom}/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::resource('exams', ExamController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::get('/exams/{exam}', [DetailController::class, 'exam'])->whereNumber('exam')->name('exams.show');
    Route::resource('sessions', ExamSessionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/sessions/{session}', [DetailController::class, 'session'])->whereNumber('session')->name('sessions.show');
    Route::get('/results', [ResultController::class, 'index'])->name('results.index');
    Route::get('/results/export', [ResultController::class, 'export'])->name('results.export');
    // Painel por turma: médias, temas mais falhados, evolução e prontidão.
    Route::get('/results/turma/{classroom}', [ResultController::class, 'classroom'])->whereNumber('classroom')->name('results.classroom');
    Route::get('/results/{attempt}', [DetailController::class, 'result'])->whereNumber('attempt')->name('results.show');
    Route::middleware('role:admin')->group(function () {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::patch('/approvals/{question}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::patch('/approvals/{question}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
        Route::resource('schools', SchoolController::class)->except('show');
        Route::get('/schools/{school}', [DetailController::class, 'school'])->whereNumber('school')->name('schools.show');
        Route::resource('users', UserController::class)->except('show');
        Route::get('/users/{user}', [DetailController::class, 'user'])->whereNumber('user')->name('users.show');
        Route::get('/mobile-users', [MobileUserController::class, 'index'])->name('mobile-users.index');
        Route::get('/mobile-users/{mobileUser}', [MobileUserController::class, 'show'])->name('mobile-users.show');
        Route::patch('/mobile-users/{mobileUser}/status', [MobileUserController::class, 'updateStatus'])->name('mobile-users.status');
        Route::resource('topics', TopicController::class)->except(['index', 'show']);
        Route::resource('signs', SignController::class)->except(['index', 'show']);
        Route::resource('articles', ArticleController::class)->except(['index', 'show']);
        Route::resource('lessons', LessonController::class)->except(['index', 'show']);
        Route::resource('glossary', GlossaryController::class)->only(['store', 'update', 'destroy']);
        Route::post('/articles/import', [ArticleController::class, 'import'])->name('articles.import');
        Route::resource('categories', LicenseCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
        Route::patch('/exams/{exam}/archive', [ExamController::class, 'archive'])->name('exams.archive');
        Route::get('/publications', [PublicationController::class, 'index'])->name('publications.index');
        Route::post('/publications', [PublicationController::class, 'publish'])->name('publications.publish');
        Route::patch('/publications/{package}/restore', [PublicationController::class, 'restore'])->name('publications.restore');
        Route::get('/publications/{package}/download', [PublicationController::class, 'download'])->name('publications.download');
        Route::get('/publications/{package}', [DetailController::class, 'publication'])->whereNumber('package')->name('publications.show');
        Route::resource('unlocks', UnlockController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/unlocks/{unlock}/associar', [UnlockController::class, 'bind'])->name('unlocks.bind');
        Route::get('/unlocks/{unlock}', [DetailController::class, 'unlock'])->whereNumber('unlock')->name('unlocks.show');
    });
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
