<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Unlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DebitoPayPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.provider' => 'real',
            'payments.methods.mpesa.driver' => 'debitopay',
            'payments.methods.emola.driver' => 'debitopay',
            'payments.methods.mkesh.driver' => 'debitopay',
            'payments.methods.cartao.driver' => 'debitopay',
            'payments.debitopay.base_url' => 'https://gateway.example/functions/v1',
            'payments.debitopay.api_key' => 'sk_sandbox_test',
            'payments.debitopay.merchant_id' => 'merchant-test',
            'payments.debitopay.wallet_code' => '12345',
            // Fixas de propósito: sem isto o teste lia as carteiras reais do
            // .env de quem o corre e passava a depender delas.
            'payments.debitopay.wallets' => [],
            'payments.debitopay.webhook_secret' => 'webhook-secret',
        ]);
    }

    /**
     * O contrato é um só endpoint com `action`. Errar o corpo aqui é o tipo de
     * falha que só aparece em produção, com dinheiro real a não entrar.
     */
    public function test_backend_creates_debitopay_charge_without_exposing_secret_to_app(): void
    {
        Http::fake([
            'gateway.example/*' => Http::response([
                'success' => true,
                'payment_id' => 'pay-123',
                'payment_method' => 'mpesa',
                'status' => 'pending',
            ], 200),
        ]);

        [, $token] = $this->mobileUser(['phone' => '841234567']);

        $response = $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo',
            'method' => 'mpesa',
        ])->assertAccepted()->assertJsonPath('estado', Payment::PENDENTE);

        $payment = Payment::findOrFail($response->json('id'));
        $this->assertSame('debitopay', $payment->provider);
        $this->assertSame('pay-123', $payment->provider_transaction_id);

        Http::assertSent(fn ($request) => $request->url() === 'https://gateway.example/functions/v1/payment-orchestrator'
            && $request->hasHeader('Authorization', 'Bearer sk_sandbox_test')
            && $request->hasHeader('X-Idempotency-Key', $payment->reference)
            && $request['action'] === 'process'
            && $request['merchant_id'] === 'merchant-test'
            && $request['wallet_code'] === '12345'
            && $request['payment_method'] === 'mpesa'
            && $request['phone'] === '258841234567'
            && $request['source_id'] === $payment->reference);

        $this->assertArrayNotHasKey('api_key', $response->json());
    }

    /** O M-Pesa confirma na própria resposta: o acesso abre sem esperar webhook. */
    public function test_synchronous_mpesa_success_grants_access_immediately(): void
    {
        Http::fake([
            'gateway.example/*' => Http::response([
                'success' => true,
                'payment_id' => 'pay-sync',
                'status' => 'success',
                'transactionId' => 'DD55JOL0XYT',
            ], 200),
        ]);

        [$user, $token] = $this->mobileUser(['phone' => '841234567']);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo',
            'method' => 'mpesa',
        ])->assertCreated()->assertJsonPath('estado', Payment::PAGO);

        $this->assertTrue(Unlock::where('mobile_user_id', $user->id)->firstOrFail()->is_active);
    }

    /** O cartão não tem carteira móvel: manda return_url e devolve checkout_url. */
    public function test_card_payment_returns_checkout_url_and_sends_no_phone(): void
    {
        Http::fake([
            'gateway.example/*' => Http::response([
                'success' => true,
                'payment_id' => 'pay-card',
                'payment_method' => 'visa_mastercard',
                'status' => 'pending',
                'checkout_url' => 'https://debitopay.com/checkout/card?payment_id=pay-card',
            ], 200),
        ]);

        [, $token] = $this->mobileUser(['phone' => '841234567']);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo',
            'method' => 'cartao',
        ])->assertAccepted()
            ->assertJsonPath('checkoutUrl', 'https://debitopay.com/checkout/card?payment_id=pay-card');

        Http::assertSent(fn ($request) => $request['payment_method'] === 'visa_mastercard'
            && ! isset($request['phone'])
            && str_contains((string) $request['return_url'], 'ref='));
    }

    /**
     * Um número Vodacom não pode pagar e-Mola — mas pode pagar com cartão, que
     * não olha ao prefixo. A validação da carteira é dos métodos móveis.
     */
    public function test_wallet_prefix_is_enforced_for_mobile_money_but_not_for_card(): void
    {
        Http::fake(['gateway.example/*' => Http::response(['success' => true, 'status' => 'pending'], 200)]);

        [, $token] = $this->mobileUser(['phone' => '841234567']);

        $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'emola',
        ])->assertStatus(422)->assertJsonValidationErrors('wallet_phone');

        $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'cartao',
        ])->assertAccepted();
    }

    /** A consulta é outro POST ao mesmo endpoint, com a resposta aninhada. */
    public function test_polling_checks_status_through_the_orchestrator(): void
    {
        Http::fakeSequence()
            ->push(['success' => true, 'payment_id' => 'pay-789', 'status' => 'pending'], 200)
            ->push(['success' => true, 'payment' => [
                'id' => 'pay-789',
                'status' => 'success',
                'provider_reference' => 'DD55JOL0XYT',
                'amount' => 129,
                'currency' => 'MZN',
            ]], 200);

        [$user, $token] = $this->mobileUser(['phone' => '841234567']);
        $id = $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'mpesa',
        ])->json('id');

        $this->withToken($token)->getJson("/api/v1/mobile/payments/{$id}")
            ->assertOk()->assertJsonPath('estado', Payment::PAGO);

        Http::assertSent(fn ($request) => ($request['action'] ?? null) === 'check-status'
            && $request['payment_id'] === 'pay-789');

        $this->assertTrue(Unlock::where('mobile_user_id', $user->id)->firstOrFail()->is_active);
    }

    /** Um pedido recusado não pode ficar pendurado a fingir que espera. */
    public function test_rejected_charge_is_marked_failed(): void
    {
        Http::fake([
            'gateway.example/*' => Http::response(['success' => false, 'error' => 'INVALID_API_KEY'], 401),
        ]);

        [, $token] = $this->mobileUser(['phone' => '841234567']);

        $id = $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'mpesa',
        ])->json('id');

        $this->assertSame(Payment::FALHADO, Payment::findOrFail($id)->status);
    }

    public function test_signed_completed_webhook_grants_access_once(): void
    {
        Http::fake([
            'gateway.example/*' => Http::response([
                'success' => true, 'payment_id' => 'pay-456', 'status' => 'pending',
            ], 200),
        ]);

        [$user, $token] = $this->mobileUser(['phone' => '841234567']);
        $paymentId = $this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'mpesa',
        ])->json('id');
        $payment = Payment::findOrFail($paymentId);

        $resposta = $this->enviarWebhook('payment.completed', [
            'payment_id' => 'pay-456',
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
            'currency' => 'MZN',
        ]);

        $resposta->assertOk()->assertJsonPath('estado', 'recebido');

        // Uma repetição legítima do fornecedor não pode prolongar o plano duas vezes.
        $this->enviarWebhook('payment.completed', [
            'payment_id' => 'pay-456',
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
            'currency' => 'MZN',
        ])->assertOk();

        $this->assertSame(Payment::PAGO, $payment->fresh()->status);
        $this->assertDatabaseCount('unlocks', 1);
        $this->assertTrue(Unlock::where('mobile_user_id', $user->id)->firstOrFail()->is_active);
    }

    /**
     * Um chargeback devolve o dinheiro. Deixar o acesso aberto seria pagar o
     * plano a quem o contestou.
     */
    public function test_chargeback_webhook_revokes_access(): void
    {
        Http::fake([
            'gateway.example/*' => Http::response([
                'success' => true, 'payment_id' => 'pay-cb', 'status' => 'success',
            ], 200),
        ]);

        [$user, $token] = $this->mobileUser(['phone' => '841234567']);
        $payment = Payment::findOrFail($this->withToken($token)->postJson('/api/v1/mobile/payments', [
            'plan' => 'completo', 'method' => 'mpesa',
        ])->json('id'));

        $this->assertSame(Payment::PAGO, $payment->fresh()->status);

        $this->enviarWebhook('payment.chargeback', [
            'payment_id' => 'pay-cb',
            'reference' => $payment->reference,
        ])->assertOk();

        $this->assertSame(Payment::REEMBOLSADO, $payment->fresh()->status);
        $this->assertFalse(Unlock::where('mobile_user_id', $user->id)->firstOrFail()->is_active);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/debitopay', [
            'event' => 'payment.completed', 'data' => ['payment_id' => 'forged'],
        ], ['X-Webhook-Signature' => 'invalid'])->assertUnauthorized();
    }

    /** A assinatura é HMAC-SHA256 sobre o corpo bruto, não sobre o corpo re-serializado. */
    private function enviarWebhook(string $evento, array $dados): TestResponse
    {
        $payload = json_encode(['event' => $evento, 'data' => $dados], JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/v1/webhooks/debitopay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $payload, 'webhook-secret'),
        ], $payload);
    }
}
