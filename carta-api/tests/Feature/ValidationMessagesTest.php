<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressão: o projeto corre com APP_LOCALE=pt mas não tinha pasta lang/, pelo
 * que o Laravel devolvia a própria chave de tradução. O app mostrava
 * "validation.unique (and 1 more error)" ao criar conta.
 */
class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_is_portuguese_and_translations_exist(): void
    {
        $this->assertSame('pt', config('app.locale'));
        $this->assertFileExists(lang_path('pt/validation.php'));
    }

    public function test_validation_messages_are_translated(): void
    {
        $response = $this->postJson('/api/v1/mobile/register', [])->assertStatus(422);

        $mensagens = $response->json('errors');

        foreach (['name', 'email', 'phone', 'password'] as $campo) {
            $this->assertArrayHasKey($campo, $mensagens);
            // Nenhuma mensagem pode ser a chave de tradução em bruto.
            $this->assertStringNotContainsString('validation.', $mensagens[$campo][0], "Campo {$campo} sem tradução.");
        }

        $this->assertSame('Preencha o nome.', $mensagens['name'][0]);
        $this->assertSame('Preencha o email.', $mensagens['email'][0]);
        $this->assertSame('Preencha o número de telefone.', $mensagens['phone'][0]);
    }

    public function test_duplicate_account_says_what_to_do(): void
    {
        $this->mobileUser(['email' => 'repetido@example.test', 'phone' => '849999999']);

        $response = $this->postJson('/api/v1/mobile/register', [
            'name' => 'Outro Aluno',
            'email' => 'repetido@example.test',
            'phone' => '849999999',
            'password' => 'segredo123',
        ])->assertStatus(422);

        // Mensagem acionável em vez de "validation.unique".
        $this->assertStringContainsString('já tem conta', $response->json('errors.email.0'));
        $this->assertStringContainsString('Entre', $response->json('errors.email.0'));
        $this->assertStringContainsString('já tem conta', $response->json('errors.phone.0'));
    }

    public function test_no_translation_key_leaks_on_other_endpoints(): void
    {
        [, $token] = $this->mobileUser();

        $respostas = [
            $this->postJson('/api/v1/mobile/login', []),
            $this->withToken($token)->postJson('/api/v1/mobile/unlock/confirm', []),
            $this->postJson('/api/v1/sessions/QUALQUER/entrar', []),
        ];

        foreach ($respostas as $resposta) {
            $this->assertStringNotContainsString('validation.', $resposta->getContent());
        }
    }
}
