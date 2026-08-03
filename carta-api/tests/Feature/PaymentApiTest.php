<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Unlock;
use App\Providers\PaymentServiceProvider;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixado aqui para os testes verificarem comportamento e não o .env da
        // máquina: 90 dias comprados têm de dar 90 dias de acesso.
        config([
            'payments.provider' => 'fake',
            'payments.plans.completo.preco' => 500,
            'payments.plans.completo.dias' => 90,
            'payments.plans.completo.periodo' => '3 meses',
        ]);
    }

    public function test_catalogue_exposes_price_and_the_number_that_will_be_charged(): void
    {
        [, $token] = $this->mobileUser(['phone' => '+258 84 123 4567']);

        $this->withToken($token)->getJson('/api/v1/mobile/payments/plans')
            ->assertOk()
            ->assertJsonPath('moeda', 'MZN')
            ->assertJsonPath('planos.0.chave', 'completo')
            ->assertJsonPath('planos.0.preco', 500)
            // O ecrã de desbloqueio pedia para pagar sem dizer quanto nem para
            // que número: ambos passam a vir do servidor.
            ->assertJsonPath('telefone', '+258 84 123 4567')
            ->assertJsonPath('acesso.plano', 'gratis')
            // A promessa vem do servidor para o negócio a afinar sem deploy.
            ->assertJsonPath('promessa', 'Chega ao INATRO sem dúvidas.')
            ->assertJsonPath('garantia', 'Não gostaste? Devolvemos em 7 dias, sem perguntas.')
            ->assertJsonPath('planos.0.periodo', '3 meses');
    }

    public function test_guarantee_can_be_switched_off_without_a_deploy(): void
    {
        [, $token] = $this->mobileUser(['phone' => '841234567']);

        config(['payments.garantia' => '']);

        $this->withToken($token)->getJson('/api/v1/mobile/payments/plans')
            ->assertOk()->assertJsonPath('garantia', null);
    }

    // ---- Devolução em 7 dias ----

    public function test_refund_takes_the_access_away(): void
    {
        [$user, $token] = $this->mobileUser(['phone' => '841234567']);
        $id = $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])->json('id');

        // Sem esta metade, devolver o dinheiro deixava o plano completo activo
        // para sempre — a garantia pagava-se a si própria em fraude.
        app(PaymentService::class)->reembolsar(Payment::find($id), 'Pedido do aluno');

        $this->assertSame(Payment::REEMBOLSADO, Payment::find($id)->status);
        $this->assertFalse(Unlock::where('mobile_user_id', $user->id)->first()->is_active);
        $this->withToken($token)->getJson('/api/v1/mobile/unlock')->assertOk()->assertJsonPath('plano', 'gratis');
    }

    public function test_refunding_a_renewal_keeps_the_time_already_bought(): void
    {
        [$user, $token] = $this->mobileUser(['phone' => '841234567']);
        Unlock::create([
            'phone' => '841234567', 'plan' => 'completo', 'unlocked_at' => now(),
            'expires_at' => now()->addDays(100), 'is_active' => true, 'mobile_user_id' => $user->id,
        ]);

        $id = $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])->json('id');
        app(PaymentService::class)->reembolsar(Payment::find($id));

        // Devolve-se a renovação, não o que já estava comprado.
        $unlock = Unlock::where('mobile_user_id', $user->id)->first();
        $this->assertTrue($unlock->is_active);
        $this->assertEqualsWithDelta(100, now()->diffInDays($unlock->expires_at), 1);
    }

    public function test_a_pending_payment_cannot_be_refunded(): void
    {
        $payment = $this->pagamentoPendenteEmola();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PaymentService::class)->reembolsar($payment);
    }

    public function test_refund_window_closes_after_the_promised_days(): void
    {
        [, $token] = $this->mobileUser(['phone' => '841234567']);
        $id = $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])->json('id');

        $this->assertTrue(Payment::find($id)->reembolsavel());

        $this->travel(8)->days();
        $this->assertFalse(Payment::find($id)->reembolsavel(), 'Passados 7 dias, a garantia deixa de cobrir.');
    }

    public function test_successful_payment_grants_access_without_any_otp(): void
    {
        [$user, $token] = $this->mobileUser(['phone' => '841234567']);

        $resposta = $this->withToken($token)
            ->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])
            ->assertStatus(201)
            ->assertJsonPath('estado', Payment::PAGO)
            ->assertJsonPath('acesso.plano', 'pago');

        // O C2B prova a posse do número com o PIN; o SMS deixa de ser preciso.
        $this->assertDatabaseCount('unlock_challenges', 0);

        $unlock = Unlock::where('mobile_user_id', $user->id)->firstOrFail();
        $this->assertTrue($unlock->is_active);
        $this->assertSame('500.00', $unlock->amount);
        $this->assertEqualsWithDelta(90, now()->diffInDays($unlock->expires_at), 1);

        $this->assertDatabaseHas('payments', [
            'id' => $resposta->json('id'),
            'status' => Payment::PAGO,
            'unlock_id' => $unlock->id,
        ]);
    }

    public function test_failed_payment_grants_nothing_and_explains_why(): void
    {
        // O driver falso reprova números terminados em 0, para o app ter como
        // exercitar o caminho de erro.
        [, $token] = $this->mobileUser(['phone' => '841234560']);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])
            ->assertStatus(202)
            ->assertJsonPath('estado', Payment::FALHADO)
            ->assertJsonPath('acesso.plano', 'gratis')
            ->assertJsonPath('mensagem', 'Saldo insuficiente na carteira. Carrega e volta — o teu acesso fica à espera.');

        $this->assertDatabaseCount('unlocks', 0);
    }

    public function test_a_student_cannot_read_another_students_payment(): void
    {
        [, $token] = $this->mobileUser(['phone' => '841111111']);
        $alheio = $this->withToken($this->mobileUser(['phone' => '842222222', 'email' => 'outro@example.test'])[1])
            ->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])->json('id');

        // Sem o filtro por conta, um id sequencial expunha os pagamentos alheios.
        $this->withToken($token)->getJson("/api/v1/mobile/payments/{$alheio}")->assertNotFound();
    }

    public function test_repeated_taps_do_not_create_a_second_charge(): void
    {
        [, $token] = $this->mobileUser(['phone' => '841234567']);
        $this->comGatewayPendente();

        $primeiro = $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])->assertStatus(202);
        $segundo = $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])->assertStatus(202);

        $this->assertSame($primeiro->json('id'), $segundo->json('id'), 'Tocar duas vezes não pode cobrar duas vezes.');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_changing_the_wallet_number_opens_a_new_payment(): void
    {
        [, $token] = $this->mobileUser(['phone' => '841234567']);
        $this->comGatewayPendente();

        $primeiro = $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa']);
        // Corrigir o número é outra intenção, não a repetição da mesma.
        $segundo = $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'mpesa', 'wallet_phone' => '850000001',
        ]);

        $this->assertNotSame($primeiro->json('id'), $segundo->json('id'));
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_renewal_extends_the_remaining_time_instead_of_shortening_it(): void
    {
        [$user, $token] = $this->mobileUser(['phone' => '841234567']);
        Unlock::create([
            'phone' => '841234567', 'plan' => 'completo', 'unlocked_at' => now(),
            'expires_at' => now()->addDays(100), 'is_active' => true, 'mobile_user_id' => $user->id,
        ]);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])->assertStatus(201);

        // Quem paga cedo não pode sair prejudicado: soma-se ao que resta.
        $this->assertEqualsWithDelta(190, now()->diffInDays(Unlock::where('mobile_user_id', $user->id)->first()->expires_at), 1);
    }

    public function test_unknown_plan_is_refused(): void
    {
        [, $token] = $this->mobileUser(['phone' => '841234567']);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'gratuito', 'method' => 'mpesa'])->assertStatus(422);
        $this->assertDatabaseCount('unlocks', 0);
    }

    public function test_account_without_a_valid_number_cannot_pay(): void
    {
        [, $token] = $this->mobileUser(['phone' => '123']);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'mpesa'])->assertStatus(422);
    }

    /**
     * O driver falso aprova qualquer pagamento sem cobrar nada. Um deploy que
     * ficasse com PAYMENTS_PROVIDER por definir tornava o plano completo
     * gratuito sem ninguém dar por isso.
     */
    public function test_fake_gateway_is_refused_outside_local_and_testing(): void
    {
        $app = $this->createApplication();
        $app->detectEnvironment(fn () => 'production');
        $app['config']->set('payments.provider', 'fake');

        $this->expectException(RuntimeException::class);
        (new GatewayManager($app))->para('mpesa');
    }


    // ---- Dois métodos, e a carteira que pode não ser a da conta ----

    public function test_catalogue_offers_every_configured_wallet_with_its_prefixes(): void
    {
        // Explícito para o catálogo não depender do .env de quem corre os testes.
        config(['payments.provider' => 'fake']);
        [, $token] = $this->mobileUser(['phone' => '841234567']);

        $resposta = $this->withToken($token)->getJson('/api/v1/mobile/payments/plans')->assertOk();

        $this->assertSame(['mpesa', 'emola', 'mkesh', 'cartao'], array_column($resposta->json('metodos'), 'chave'));
        $this->assertSame(['84', '85'], $resposta->json('metodos.0.prefixos'));
        $this->assertSame(['86', '87'], $resposta->json('metodos.1.prefixos'));
        $this->assertSame(['82'], $resposta->json('metodos.2.prefixos'));
        // O cartão não se escolhe pelo número: vai sem prefixos, e o app não
        // deve pedir carteira nenhuma para ele.
        $this->assertSame([], $resposta->json('metodos.3.prefixos'));
        // O app pré-selecciona o método a partir do número da conta.
        $resposta->assertJsonPath('metodoSugerido', 'mpesa');
    }

    public function test_movitel_account_number_suggests_emola(): void
    {
        [, $token] = $this->mobileUser(['phone' => '861234567']);

        $this->withToken($token)->getJson('/api/v1/mobile/payments/plans')
            ->assertOk()->assertJsonPath('metodoSugerido', 'emola');
    }

    public function test_paying_with_a_wallet_on_another_number_is_allowed(): void
    {
        // Conta registada num número Movitel, dinheiro numa carteira Vodacom:
        // cobrar sempre o número da conta deixava esta pessoa sem forma de pagar.
        [$user, $token] = $this->mobileUser(['phone' => '861234567']);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'mpesa', 'wallet_phone' => '84 999 8888',
        ])->assertStatus(201)->assertJsonPath('estado', Payment::PAGO);

        // Cobra-se a carteira indicada…
        $this->assertDatabaseHas('payments', ['phone_normalized' => '258849998888', 'method' => 'mpesa']);
        // …mas o acesso é da conta, e o desbloqueio fica no número dela.
        $unlock = Unlock::where('mobile_user_id', $user->id)->firstOrFail();
        $this->assertSame('258861234567', $unlock->phone_normalized);
    }

    public function test_wallet_number_must_match_the_chosen_method(): void
    {
        [, $token] = $this->mobileUser(['phone' => '841234567']);

        // 86 é Movitel: pagar M-Pesa com ele falharia sempre na operadora.
        $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'mpesa', 'wallet_phone' => '861234567',
        ])->assertStatus(422)->assertJsonPath('errors.wallet_phone.0', 'Este número é Movitel. Escolhe e-Mola ou usa um número Vodacom.');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unknown_prefix_is_allowed_through(): void
    {
        [, $token] = $this->mobileUser(['phone' => '841234567']);

        // Um prefixo fora da nossa lista não pode impedir alguém de pagar: a
        // atribuição de numeração muda e mais vale a operadora recusar.
        $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'mpesa', 'wallet_phone' => '889998888',
        ])->assertStatus(201);
    }

    public function test_malformed_wallet_number_is_refused(): void
    {
        [, $token] = $this->mobileUser(['phone' => '841234567']);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'mpesa', 'wallet_phone' => '12345',
        ])->assertStatus(422);
    }

    public function test_method_without_credentials_is_not_offered_nor_accepted(): void
    {
        config([
            'payments.provider' => 'real',
            'payments.methods.mpesa.driver' => 'mpesa',
            'payments.methods.emola.driver' => 'paysuite',
            'payments.paysuite.token' => null,
            'payments.mpesa.api_key' => 'k',
            'payments.mpesa.public_key' => 'p',
            // Sem credenciais DebitoPay, os métodos que dependem dela caem fora.
            'payments.debitopay.api_key' => null,
            'payments.debitopay.merchant_id' => null,
            'payments.debitopay.wallet_code' => null,
            'payments.debitopay.wallets' => [],
        ]);
        [, $token] = $this->mobileUser(['phone' => '861234567']);

        $metodos = $this->withToken($token)->getJson('/api/v1/mobile/payments/plans')->json('metodos');
        $this->assertSame(['mpesa'], array_column($metodos, 'chave'), 'Sem token, a e-Mola não deve aparecer.');

        $this->withToken($token)->postJson('/api/v1/mobile/payments', ['plan' => 'completo', 'method' => 'emola'])
            ->assertStatus(422);
    }

    // ---- Webhook da PaySuite ----

    public function test_webhook_without_a_valid_signature_grants_nothing(): void
    {
        config(['payments.paysuite.webhook_secret' => 'segredo']);
        $payment = $this->pagamentoPendenteEmola();

        $corpo = ['event' => 'payment.success', 'data' => ['reference' => $payment->reference, 'amount' => 500]];

        // É a assinatura que separa uma confirmação legítima de um curl.
        $this->postJson('/api/v1/webhooks/paysuite', $corpo, ['X-Webhook-Signature' => 'errada'])
            ->assertStatus(401);

        $this->assertSame(Payment::PENDENTE, $payment->fresh()->status);
        $this->assertDatabaseCount('unlocks', 0);
    }

    public function test_signed_webhook_confirms_the_payment_and_opens_access(): void
    {
        config(['payments.paysuite.webhook_secret' => 'segredo']);
        $payment = $this->pagamentoPendenteEmola();

        $this->enviarWebhook([
            'event' => 'payment.success',
            'data' => [
                'reference' => $payment->reference,
                'amount' => 500,
                'transaction' => ['id' => 'tr_123', 'method' => 'emola'],
            ],
        ])->assertOk()->assertJsonPath('estado', 'recebido');

        $this->assertSame(Payment::PAGO, $payment->fresh()->status);
        $this->assertSame('tr_123', $payment->fresh()->provider_transaction_id);
        $this->assertDatabaseHas('unlocks', ['mobile_user_id' => $payment->mobile_user_id, 'is_active' => true]);
    }

    public function test_webhook_with_a_different_amount_is_ignored(): void
    {
        config(['payments.paysuite.webhook_secret' => 'segredo']);
        $payment = $this->pagamentoPendenteEmola();

        // Aceitar o valor do webhook deixaria alguém pagar 1 MZN e receber tudo.
        $this->enviarWebhook([
            'event' => 'payment.success',
            'data' => ['reference' => $payment->reference, 'amount' => 1],
        ])->assertOk()->assertJsonPath('estado', 'ignorado');

        $this->assertSame(Payment::PENDENTE, $payment->fresh()->status);
        $this->assertDatabaseCount('unlocks', 0);
    }

    public function test_webhook_is_refused_when_no_secret_is_configured(): void
    {
        config(['payments.paysuite.webhook_secret' => '']);

        // Um webhook por verificar é pior do que webhook nenhum.
        $this->postJson('/api/v1/webhooks/paysuite', ['event' => 'payment.success'])->assertStatus(503);
    }

    private function pagamentoPendenteEmola(): Payment
    {
        [$user] = $this->mobileUser(['phone' => '861234567']);

        return Payment::create([
            'mobile_user_id' => $user->id, 'plan' => 'completo', 'amount' => 500, 'currency' => 'MZN',
            'provider' => 'paysuite', 'method' => 'emola', 'phone_normalized' => '258861234567',
            'status' => Payment::PENDENTE, 'reference' => Payment::novaReferencia(),
            'conversation_id' => Payment::novaConversa(),
        ]);
    }

    private function enviarWebhook(array $corpo)
    {
        $json = json_encode($corpo);

        return $this->call('POST', '/api/v1/webhooks/paysuite', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $json, 'segredo'),
        ], $json);
    }

    /** Gateway que fica à espera do PIN, como acontece na vida real. */
    private function comGatewayPendente(): void
    {
        $pendente = new class implements PaymentGateway
        {
            public function name(): string
            {
                return 'fake';
            }

            public function charge(Payment $payment): PaymentResult
            {
                return PaymentResult::pendente('INS-9', 'À espera do PIN.');
            }

            public function query(Payment $payment): PaymentResult
            {
                return PaymentResult::pendente();
            }
        };

        $this->app->instance(GatewayManager::class, new class($this->app, $pendente) extends GatewayManager
        {
            public function __construct(private $aplicacao, private PaymentGateway $gateway)
            {
                parent::__construct($aplicacao);
            }

            public function para(string $metodo): PaymentGateway
            {
                return $this->gateway;
            }

            public function disponiveis(): array
            {
                return ['mpesa', 'emola'];
            }
        });
    }
}
