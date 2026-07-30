<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Devolução operada pelo apoio ao cliente.
 *
 * O movimento do dinheiro na carteira continua a ser manual — nem o M-Pesa nem
 * a PaySuite reembolsam sem intervenção. O que esta acção garante é a outra
 * metade, que nenhum humano se pode lembrar de fazer à mão: retirar o acesso
 * que o pagamento concedeu.
 */
class PaymentAdminController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        $dados = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $this->payments->reembolsar($payment, $dados['motivo'] ?? null, $request->user()->id);

        return back()->with('status', sprintf(
            'Pagamento %s marcado como devolvido e acesso retirado. Falta devolver %s %s na carteira %s.',
            $payment->reference,
            number_format((float) $payment->amount, 2, ',', ' '),
            $payment->currency,
            $payment->phone_normalized,
        ));
    }
}
