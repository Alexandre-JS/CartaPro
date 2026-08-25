<?php

namespace App\Services;

use App\Models\MobileUser;
use App\Models\Plan;
use App\Models\SchoolMembership;
use App\Models\Unlock;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolve o direito de acesso de uma conta.
 *
 * É o único local que decide se um utilizador é 'pago' ou 'gratis'. O cliente
 * nunca decide — antes o app guardava 'pago' em Preferences e nunca revalidava.
 */
class EntitlementService
{
    /**
     * Só conta o desbloqueio explicitamente ligado a esta conta.
     *
     * Coincidir o número não basta: era exatamente isso que permitia partilhar
     * um número pago por várias contas. O vínculo (`mobile_user_id`) é criado
     * uma única vez, na confirmação do OTP — ou manualmente pelo admin.
     */
    public function unlockFor(MobileUser $user): ?Unlock
    {
        return Unlock::query()
            ->where('mobile_user_id', $user->id)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('expires_at')
            ->first();
    }

    /** Existe pagamento para o número da conta, ainda por reclamar via OTP? */
    public function hasClaimablePayment(MobileUser $user): bool
    {
        $normalized = $user->phone_normalized ?: Phone::normalize($user->phone);

        return Unlock::query()
            ->where('phone_normalized', $normalized)
            ->whereNull('mobile_user_id')
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function isPaid(MobileUser $user): bool
    {
        return $this->planCode($user) !== Plan::FREE;
    }

    public function planCode(MobileUser $user): string
    {
        $atSchool = SchoolMembership::query()
            ->where('mobile_user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('school', fn (Builder $query) => $query->where('is_active', true))
            ->exists();

        if ($atSchool) {
            return Plan::SCHOOL;
        }

        return $this->unlockFor($user) ? Plan::PLUS : Plan::FREE;
    }

    /** Bloco 'access' devolvido ao app — a fonte de verdade do plano. */
    public function describe(MobileUser $user): array
    {
        $unlock = $this->unlockFor($user);
        $code = $this->planCode($user);

        return [
            'produto' => $code,
            'nomePlano' => match ($code) {
                Plan::PLUS => 'ProntoVia+',
                Plan::SCHOOL => 'ProntoVia Escolas',
                default => 'ProntoVia Free',
            },
            // Contrato legado, preservado até à futura fase mobile.
            'plano' => $code === Plan::FREE ? 'gratis' : 'pago',
            'telefone' => $user->phone,
            'expiraEm' => $unlock?->expires_at?->toIso8601String(),
            'verificadoEm' => now()->toIso8601String(),
            'diasRestantes' => $unlock?->expires_at ? max(0, (int) now()->diffInDays($unlock->expires_at, false)) : null,
            // Permite ao app mostrar "pagamento encontrado, confirme o código"
            // em vez de um genérico "ainda não pagou".
            'pagamentoPorReclamar' => ! $unlock && $this->hasClaimablePayment($user),
        ];
    }

    /**
     * Filtra o pacote publicado conforme o plano.
     *
     * Utilizadores gratuitos nunca recebem o enunciado, as opções, a resposta
     * correta nem a explicação do conteúdo bloqueado: recebem apenas a
     * contagem por tema, que alimenta os cadeados no app.
     */
    public function filterPackage(array $payload, bool $paid): array
    {
        $questions = $payload['perguntas'] ?? [];
        $exams = $payload['provas'] ?? [];

        $lockedByTopic = [];
        foreach ($questions as $question) {
            if ($question['bloqueado'] ?? false) {
                $topic = $question['tema'] ?? 'sem_tema';
                $lockedByTopic[$topic] = ($lockedByTopic[$topic] ?? 0) + 1;
            }
        }

        if (! $paid) {
            $questions = array_values(array_filter($questions, fn ($question) => ! ($question['bloqueado'] ?? false)));

            /*
             * Uma prova é inteira ou não é servida. Antes filtravam-se as
             * perguntas bloqueadas dentro de cada prova, o que entregava ao
             * aluno gratuito exames mutilados — 11 de 30 perguntas, com a nota
             * de passagem calculada sobre 30 — em vez de exames fechados.
             */
            $exams = array_values(array_map(function (array $exam) {
                $bloqueada = ($exam['bloqueado'] ?? false)
                    || collect($exam['perguntas'] ?? [])->contains(fn ($question) => $question['bloqueado'] ?? false);

                $exam['perguntas'] = $bloqueada ? [] : ($exam['perguntas'] ?? []);
                $exam['bloqueadoPorPlano'] = $bloqueada;

                return $exam;
            }, $exams));
        }

        $payload['perguntas'] = $questions;
        $payload['provas'] = $exams;
        $payload['plano'] = $paid ? 'pago' : 'gratis';
        $payload['bloqueadasPorTema'] = $paid ? [] : $lockedByTopic;
        $payload['totalBloqueadas'] = $paid ? 0 : array_sum($lockedByTopic);

        if (isset($payload['estudo'])) {
            $payload['estudo'] = $this->filterStudy($payload['estudo'], $paid);
        }

        return $payload;
    }

    /**
     * Material de estudo conforme o plano.
     *
     * Lições e sinais marcados como bloqueados não são enviados a plano
     * gratuito — nem o corpo da ficha nem o significado do sinal. Enviam-se
     * apenas as contagens, que alimentam os cadeados no app.
     */
    /**
     * O que sobra de um item bloqueado: o suficiente para o aluno o ver na
     * lista, nada do que lhe daria o conhecimento.
     *
     * Retirar os bloqueados por completo — como se fazia — tornava o cadeado
     * invisível: o ecrã afirmava "mais 42 sinais" e não havia 42 sinais em
     * lado nenhum, pelo que parecia que o cadeado não fazia nada. E o aluno
     * nunca via o que estava a perder, que é o que faz querer desbloquear.
     */
    private const MONTRA = [
        'sinais' => ['slug', 'nome', 'categoria', 'imagem'],
        /*
         * `sinais` e `artigos` são referências (slugs e números), não
         * conhecimento — e sem eles as ligações cruzadas partiam-se: o ecrã do
         * sinal faz `licao.sinais.includes(...)` e rebentava com TypeError
         * numa ficha bloqueada, deixando a página presa a carregar.
         */
        'licoes' => ['slug', 'titulo', 'resumo', 'grupo', 'minutosLeitura', 'sinais', 'artigos'],
        'artigos' => ['numero', 'capitulo', 'capituloTitulo', 'titulo'],
        'glossario' => ['slug', 'termo'],
    ];

    public function filterStudy(array $estudo, bool $paid): array
    {
        /*
         * Os artigos e o glossário juntaram-se aqui: eram as duas únicas
         * frentes que seguiam inteiras para o plano gratuito, não por decisão
         * mas por não existir sequer campo para as marcar.
         */
        $frentes = [
            'sinais' => 'sinaisBloqueados',
            'licoes' => 'licoesBloqueadas',
            'artigos' => 'artigosBloqueados',
            'glossario' => 'glossarioBloqueado',
        ];

        foreach ($frentes as $chave => $contador) {
            $itens = $estudo[$chave] ?? [];
            $bloqueados = 0;

            foreach ($itens as $item) {
                if ($item['bloqueado'] ?? false) {
                    $bloqueados++;
                }
            }

            if (! $paid) {
                $estudo[$chave] = array_values(array_map(
                    fn (array $item) => ($item['bloqueado'] ?? false)
                        ? $this->montra($item, self::MONTRA[$chave])
                        : $item,
                    $itens,
                ));
            }

            $estudo[$contador] = $paid ? 0 : $bloqueados;
        }

        return $estudo;
    }

    /** Só os campos de identificação, mais a marca de bloqueado. */
    private function montra(array $item, array $campos): array
    {
        return array_intersect_key($item, array_flip($campos)) + ['bloqueado' => true];
    }
}
