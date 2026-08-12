<?php

namespace Tests;

use App\Models\MobileApiToken;
use App\Models\MobileUser;
use App\Models\Unlock;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Nenhum teste toca numa base de dados que não seja a descartável.
     *
     * Isto não é zelo excessivo: já aconteceu. Com a configuração em cache
     * (`bootstrap/cache/config.php`), o Laravel deixa de avaliar os ficheiros
     * de `config/` e passa a ignorar as variáveis que o `phpunit.xml` define —
     * incluindo `DB_CONNECTION=sqlite`. A suite corre na mesma, em silêncio,
     * contra a base de dados de desenvolvimento, e o `RefreshDatabase` começa
     * por lhe fazer `migrate:fresh`. Foi assim que a base local foi apagada.
     *
     * O sintoma que se vê primeiro são falhas 419 nos testes web, porque o
     * `APP_ENV` em cache também deixa de ser `testing`. Se isso acontecer:
     * `php artisan optimize:clear`.
     *
     * A verificação vive aqui, e não no `setUp`, por uma questão de ordem: o
     * `setUp` do framework cria a aplicação e **logo a seguir** corre os traits,
     * e é aí que o `RefreshDatabase` faz o `migrate:fresh`. Um guarda no `setUp`
     * chegaria depois do estrago. `refreshApplication` é o último ponto em que a
     * configuração já existe e ainda ninguém tocou na base de dados.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $ligacao = config('database.default');
        $base = config("database.connections.{$ligacao}.database");

        if ($ligacao !== 'sqlite' || ! in_array($base, [':memory:', ''], true)) {
            $this->fail(
                "Os testes estão apontados a «{$ligacao}» / «{$base}» em vez de sqlite em memória. "
                .'A configuração está provavelmente em cache: corra `php artisan optimize:clear` antes de repetir.'
            );
        }
    }

    /** Cria uma conta móvel e devolve [utilizador, token em claro]. */
    protected function mobileUser(array $attributes = []): array
    {
        $user = MobileUser::create(array_merge([
            'name' => 'Aluno CartaPro',
            'email' => 'aluno'.Str::random(6).'@example.test',
            'phone' => '84'.random_int(1000000, 9999999),
            'password' => 'segredo123',
            'license_category' => 'ligeiro',
            'is_active' => true,
        ], $attributes));

        $plain = Str::random(80);
        MobileApiToken::create([
            'mobile_user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(90),
        ]);

        return [$user, $plain];
    }

    /** Conta móvel com plano pago já ativo e ligado à conta. */
    protected function paidMobileUser(array $attributes = []): array
    {
        [$user, $token] = $this->mobileUser($attributes);

        Unlock::create([
            'phone' => $user->phone,
            'mobile_user_id' => $user->id,
            'plan' => 'completo',
            'unlocked_at' => now(),
            'is_active' => true,
        ]);

        return [$user, $token];
    }
}
