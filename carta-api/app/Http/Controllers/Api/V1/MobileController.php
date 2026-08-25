<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\MobileApiToken;
use App\Models\MobileUser;
use App\Services\EntitlementService;
use App\Services\Learning\LearningEngine;
use App\Support\Grading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MobileController extends Controller
{
    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly LearningEngine $learning,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:mobile_users'],
            'phone' => ['required', 'string', 'max:30', 'unique:mobile_users'],
            'password' => ['required', 'string', 'min:6'],
        ]);
        $user = MobileUser::create($data + ['license_category' => 'ligeiro', 'is_active' => true]);

        return $this->session($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string'], 'password' => ['required', 'string']]);
        $user = MobileUser::where('email', $data['identifier'])->orWhere('phone', $data['identifier'])->first();
        abort_unless($user && $user->is_active && Hash::check($data['password'], $user->password), 422, 'Credenciais inválidas.');

        return $this->session($user);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userData($request->user()),
            'access' => $this->entitlements->describe($request->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', Rule::unique('mobile_users')->ignore($user)],
            'phone' => ['required', 'string', Rule::unique('mobile_users')->ignore($user)],
            'license_category' => ['required', 'in:ligeiro,pesado,profissional_publico'],
        ]);

        // Trocar de número invalida a verificação: o desbloqueio tem de ser
        // reconfirmado por OTP para o novo número.
        if ($data['phone'] !== $user->phone) {
            $data['phone_verified_at'] = null;
        }

        $user->forceFill($data)->save();

        return response()->json([
            'user' => $this->userData($user->fresh()),
            'access' => $this->entitlements->describe($user->fresh()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('mobile_token')->delete();

        return response()->json(['message' => 'Sessão terminada.']);
    }

    /** Descarga completa — usada no primeiro login em cada dispositivo. */
    public function snapshot(Request $request): JsonResponse
    {
        return response()->json($this->buildSnapshot($request->user(), null));
    }

    /**
     * Sincronização incremental.
     *
     * Antes: o app disparava um sync a cada resposta e enviava o histórico
     * completo (tráfego O(n²) pago pelo aluno), e a resposta apagava a base
     * local antes de a repor — perdendo respostas gravadas nesse intervalo.
     *
     * Agora: o cliente envia apenas o que mudou e recebe apenas o que mudou
     * desde `since`; nada é apagado do lado do cliente.
     */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
            'answers' => ['array', 'max:500'],
            'answers.*.clientId' => ['required', 'uuid'],
            'answers.*.perguntaId' => ['required', 'string'],
            'answers.*.tema' => ['required', 'string'],
            'answers.*.acertou' => ['required', 'boolean'],
            'answers.*.data' => ['required', 'integer'],
            'answers.*.escolhida' => ['nullable', 'integer', 'min:0', 'max:255'],
            'answers.*.duracaoMs' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'answers.*.origem' => ['nullable', 'string', 'max:20'],
            'exams' => ['array', 'max:200'],
            'exams.*.clientId' => ['required', 'uuid'],
            'exams.*.numero' => ['required', 'integer'],
            'exams.*.total' => ['required', 'integer'],
            'exams.*.acertos' => ['required', 'integer'],
            'exams.*.tempoSegundos' => ['required', 'integer'],
            'exams.*.data' => ['required', 'integer'],
            'revisions' => ['array', 'max:500'],
            'revisions.*.perguntaId' => ['required', 'string'],
            'revisions.*.tema' => ['required', 'string'],
            'revisions.*.agendadaPara' => ['required', 'integer'],
            'revisions.*.intervaloDias' => ['required', 'integer', 'min:0'],
            'revisions.*.facilidade' => ['nullable', 'numeric', 'min:1.3', 'max:3.0'],
            'revisions.*.repeticoes' => ['nullable', 'integer', 'min:0'],
            'revisions.*.lapsos' => ['nullable', 'integer', 'min:0'],
            'readContents' => ['array', 'max:500'],
            'readContents.*' => ['string', 'max:190'],
        ]);

        $user = $request->user();
        $since = isset($data['since']) ? Carbon::parse($data['since']) : null;

        DB::transaction(function () use ($data, $user): void {
            $now = now();

            foreach ($data['answers'] ?? [] as $item) {
                DB::table('mobile_answers')->updateOrInsert(
                    ['mobile_user_id' => $user->id, 'client_id' => $item['clientId']],
                    [
                        'question_external_id' => $item['perguntaId'],
                        'topic' => $item['tema'],
                        'correct' => $item['acertou'],
                        'selected_index' => $item['escolhida'] ?? null,
                        'duration_ms' => $item['duracaoMs'] ?? null,
                        'source' => $item['origem'] ?? 'simulado',
                        'answered_at' => $this->fromMillis($item['data']),
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }

            foreach ($data['exams'] ?? [] as $item) {
                DB::table('mobile_exam_history')->updateOrInsert(
                    ['mobile_user_id' => $user->id, 'client_id' => $item['clientId']],
                    [
                        'number' => $item['numero'],
                        'total' => $item['total'],
                        'correct_answers' => $item['acertos'],
                        'duration_seconds' => $item['tempoSegundos'],
                        'completed_at' => $this->fromMillis($item['data']),
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }

            foreach ($data['revisions'] ?? [] as $item) {
                DB::table('mobile_revisions')->updateOrInsert(
                    ['mobile_user_id' => $user->id, 'question_external_id' => $item['perguntaId']],
                    [
                        'topic' => $item['tema'],
                        'scheduled_for' => $this->fromMillis($item['agendadaPara']),
                        'interval_days' => $item['intervaloDias'],
                        'ease_factor' => $item['facilidade'] ?? 2.5,
                        'repetitions' => $item['repeticoes'] ?? 0,
                        'lapses' => $item['lapsos'] ?? 0,
                        'last_reviewed_at' => $now,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }

            foreach ($data['readContents'] ?? [] as $key) {
                DB::table('mobile_read_contents')->updateOrInsert(
                    ['mobile_user_id' => $user->id, 'content_key' => $key],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }

            $this->learning->recordSyncEvents($user, $data);
        });

        if (collect(['answers', 'exams', 'revisions', 'readContents'])->contains(fn (string $key) => ! empty($data[$key]))) {
            $this->learning->refresh($user);
        }

        return response()->json($this->buildSnapshot($user, $since));
    }

    public function exams(Request $request): JsonResponse
    {
        $paid = $this->entitlements->isPaid($request->user());

        return response()->json(['data' => Exam::where(['is_active' => true, 'is_public' => true, 'publication_status' => 'published'])
            ->withCount(['questions', 'questions as locked_questions_count' => fn ($query) => $query->where('is_locked', true)])
            ->orderBy('id')->get()
            ->map(function (Exam $exam) use ($paid) {
                $exam->has_locked_questions = $exam->locked_questions_count > 0;

                return $this->examSummary($exam, $paid);
            })]);
    }

    public function exam(Request $request, Exam $exam): JsonResponse
    {
        abort_unless($exam->is_active && $exam->is_public && $exam->publication_status === 'published', 404);
        $exam->load(['questions.topic', 'questions.sign']);

        $paid = $this->entitlements->isPaid($request->user());

        /*
         * O cadeado da própria prova era ignorado aqui: filtravam-se as
         * perguntas bloqueadas mas nunca se olhava para `is_locked` da prova,
         * pelo que uma prova marcada como paga continuava a ser jogada por
         * quem não pagou, desde que tivesse perguntas livres.
         */
        abort_if($exam->is_locked && ! $paid, 402, 'Esta prova exige o plano completo.');

        /*
         * Uma prova é inteira ou não é entregue. Retirar as perguntas
         * bloqueadas e servir o resto dava ao aluno um exame de 11 de 30
         * perguntas com a nota de passagem calculada sobre 30 — reprovava sem
         * perceber porquê.
         */
        $questions = $exam->questions;

        abort_if(
            ! $paid && $questions->contains(fn ($question) => $question->is_locked),
            402,
            'Esta prova exige o plano completo.',
        );

        abort_if($questions->isEmpty(), 404, 'Esta prova ainda não tem perguntas.');

        /*
         * `array_merge` e não `+`: o resumo já traz `perguntas` com a contagem,
         * e a união de arrays em PHP mantém o valor da esquerda. Com `+`, a
         * lista de perguntas era descartada em silêncio e o app recebia
         * `perguntas: 25` onde esperava os enunciados — falhava a abrir a prova
         * e caía no modo offline, a queixar-se de que a prova não tinha sido
         * descarregada. A resposta dava 200; só o conteúdo é que estava errado.
         */
        return response()->json(array_merge($this->examSummary($exam, $paid), [
            'perguntas' => $questions->map(fn ($question) => $question->toPackageArray())->values(),
            'perguntasBloqueadas' => $exam->questions->count() - $questions->count(),
        ]));
    }

    private function examSummary(Exam $exam, bool $paid): array
    {
        $category = $exam->gradingCategory();
        $total = $exam->questions_count ?? $exam->questions->count();

        return [
            'id' => $exam->id,
            'nome' => $exam->name,
            'categoriasCarta' => $exam->license_categories ?: [$exam->license_category],
            'tipo' => $exam->type,
            'perguntas' => $total,
            'notaPassagem' => $exam->pass_score ?: Grading::passScore($total, $category),
            'percentagemPassagem' => Grading::passPercentage($category),
            'valoresPassagem' => Grading::passValues($category),
            'minutos' => $exam->duration_minutes ?: Grading::durationMinutes($category),
            'plano' => $paid ? 'pago' : 'gratis',
            /*
             * O app precisa de saber que a prova existe mas está fechada — sem
             * isto a lista mostrava-a como qualquer outra e o aluno só descobria
             * ao tentar entrar. Uma prova com perguntas bloqueadas conta como
             * fechada: é servida inteira ou não é servida.
             */
            'bloqueado' => ! $paid && ($exam->is_locked || $exam->has_locked_questions),
        ];
    }

    /**
     * @param  Carbon|null  $since  quando presente, devolve só o que mudou depois.
     */
    private function buildSnapshot(MobileUser $user, ?Carbon $since): array
    {
        $scope = fn (string $table) => DB::table($table)->where('mobile_user_id', $user->id)
            ->when($since, fn ($query) => $query->where('updated_at', '>', $since));

        return [
            'serverTime' => now()->toIso8601String(),
            'cursor' => now()->toIso8601String(),
            'incremental' => $since !== null,
            'user' => $this->userData($user),
            'answers' => $scope('mobile_answers')->orderBy('answered_at')->get()->map(fn ($row) => [
                'clientId' => $row->client_id,
                'perguntaId' => $row->question_external_id,
                'tema' => $row->topic,
                'acertou' => (bool) $row->correct,
                'escolhida' => $row->selected_index !== null ? (int) $row->selected_index : null,
                'duracaoMs' => $row->duration_ms !== null ? (int) $row->duration_ms : null,
                'origem' => $row->source,
                'data' => $this->toMillis($row->answered_at),
            ])->values(),
            'exams' => $scope('mobile_exam_history')->orderByDesc('completed_at')->get()->map(fn ($row) => [
                'clientId' => $row->client_id,
                'numero' => (int) $row->number,
                'total' => (int) $row->total,
                'acertos' => (int) $row->correct_answers,
                'tempoSegundos' => (int) $row->duration_seconds,
                'data' => $this->toMillis($row->completed_at),
            ])->values(),
            'revisions' => $scope('mobile_revisions')->get()->map(fn ($row) => [
                'perguntaId' => $row->question_external_id,
                'tema' => $row->topic,
                'agendadaPara' => $this->toMillis($row->scheduled_for),
                'intervaloDias' => (int) $row->interval_days,
                'facilidade' => (float) $row->ease_factor,
                'repeticoes' => (int) $row->repetitions,
                'lapsos' => (int) $row->lapses,
            ])->values(),
            'readContents' => $scope('mobile_read_contents')->pluck('content_key')->values(),
            'access' => $this->entitlements->describe($user),
        ];
    }

    private function fromMillis(int $millis): string
    {
        return date('Y-m-d H:i:s', intdiv($millis, 1000));
    }

    private function toMillis(?string $timestamp): int
    {
        return $timestamp ? strtotime($timestamp) * 1000 : 0;
    }

    private function session(MobileUser $user, int $status = 200): JsonResponse
    {
        $plain = Str::random(80);
        MobileApiToken::create(['mobile_user_id' => $user->id, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDays(90)]);

        return response()->json([
            'token' => $plain,
            'user' => $this->userData($user),
            'access' => $this->entitlements->describe($user),
        ], $status);
    }

    private function userData(MobileUser $user): array
    {
        return [
            'id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'telefone' => $user->phone,
            'telefoneVerificado' => (bool) $user->phone_verified_at,
            'categoriaCarta' => $user->license_category,
        ];
    }
}
