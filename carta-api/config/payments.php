<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disponibilidade
    |--------------------------------------------------------------------------
    |
    | Mantém toda a superfície de cobrança inacessível enquanto a integração
    | não estiver pronta. Ativar pagamentos exige uma decisão explícita no
    | .env de produção; apenas configurar credenciais não chega.
    |
    */

    'enabled' => (bool) env('PAYMENTS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Fornecedor ativo
    |--------------------------------------------------------------------------
    |
    | 'fake' fecha o ciclo localmente sem credenciais: o pagamento é aprovado
    | de imediato. É o que permite desenvolver e testar o fluxo completo antes
    | de as credenciais serem emitidas. Em produção tem de ser 'real', que faz
    | cada método usar o seu driver.
    |
    */

    'provider' => env('PAYMENTS_PROVIDER', 'fake'),

    'currency' => env('PAYMENTS_CURRENCY', 'MZN'),

    /*
    |--------------------------------------------------------------------------
    | Métodos de pagamento
    |--------------------------------------------------------------------------
    |
    | `prefixos` são os dois dígitos nacionais que identificam a operadora. Em
    | Moçambique o número determina a carteira — 84/85 Vodacom, 86/87 Movitel —
    | pelo que o app consegue escolher o método certo e avisar antes de uma
    | transação que falharia de certeza.
    |
    | Ficam em configuração porque a atribuição de numeração muda: um prefixo
    | novo não pode impedir ninguém de pagar (ver App\Support\Carteira).
    |
    | `driver` diz quem executa. A e-Mola não tem API pública — a Movitel só
    | cede o WSDL por acordo comercial — pelo que passa por um agregador.
    |
    | `movel` distingue quem cobra no telemóvel de quem cobra numa página. As
    | carteiras móveis recebem um pedido de PIN no número que indicamos, e por
    | isso exigem — e validam — esse número. O cartão não tem número nenhum: os
    | dados são recolhidos no Hosted Checkout da DebitoPay, que é quem tem
    | certificação PCI-DSS. Pedir uma "carteira" a quem paga com Visa seria
    | inventar um requisito que o meio de pagamento não tem.
    |
    | `minimo` é o valor abaixo do qual o fornecedor recusa. Está aqui para o
    | aluno ser avisado antes da transação, em vez de receber um 400 opaco.
    |
    */

    'methods' => [
        'mpesa' => [
            'nome' => 'M-Pesa',
            'operadora' => 'Vodacom',
            'prefixos' => array_filter(explode(',', env('MPESA_PREFIXES', '84,85'))),
            'driver' => env('MPESA_DRIVER', 'debitopay'),
            'movel' => true,
            'minimo' => (float) env('MPESA_MIN', 10),
        ],
        'emola' => [
            'nome' => 'e-Mola',
            'operadora' => 'Movitel',
            'prefixos' => array_filter(explode(',', env('EMOLA_PREFIXES', '86,87'))),
            'driver' => env('EMOLA_DRIVER', 'debitopay'),
            'movel' => true,
            'minimo' => (float) env('EMOLA_MIN', 50),
        ],
        'mkesh' => [
            'nome' => 'mKesh',
            'operadora' => 'Tmcel',
            'prefixos' => array_filter(explode(',', env('MKESH_PREFIXES', '82'))),
            'driver' => env('MKESH_DRIVER', 'debitopay'),
            'movel' => true,
            'minimo' => (float) env('MKESH_MIN', 10),
        ],
        'cartao' => [
            'nome' => 'Visa / Mastercard',
            'operadora' => 'Cartão',
            // Sem prefixos: não é o número que decide, é o cartão.
            'prefixos' => [],
            'driver' => env('CARD_DRIVER', 'debitopay'),
            'movel' => false,
            'minimo' => (float) env('CARD_MIN', 50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | A promessa
    |--------------------------------------------------------------------------
    |
    | Ninguém paga por uma lista de funcionalidades: paga pelo que vai passar a
    | conseguir fazer. Uma frase — o parágrafo que a acompanhava só repetia o
    | título por outras palavras. Fica em configuração para o negócio a afinar
    | sem um deploy.
    |
    | `garantia` sai vazia de propósito. Prometer devolução do dinheiro é um
    | compromisso comercial que só o dono do negócio pode assumir — inventá-lo
    | seria vincular a CartaPro a algo que ninguém decidiu.
    |
    */

    'promessa' => env('PAYMENTS_PROMISE', 'Chega ao INATRO sem dúvidas.'),

    'garantia' => env('PAYMENTS_GUARANTEE', 'Não gostaste? Devolvemos em 7 dias, sem perguntas.'),

    /** Janela de devolução. Muda a promessa e a janela ao mesmo tempo. */
    'garantia_dias' => (int) env('PAYMENTS_GUARANTEE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Planos à venda
    |--------------------------------------------------------------------------
    |
    | O preço vive aqui e é servido ao app: até agora não existia em lado
    | nenhum, e o ecrã de desbloqueio pedia ao aluno que pagasse sem lhe dizer
    | quanto nem para onde.
    |
    */

    'plans' => [
        'completo' => [
            'nome' => env('PAYMENTS_PLAN_NAME', 'Plano completo'),
            'descricao' => 'Todo o banco de perguntas, exames completos e material sem cadeado.',
            'preco' => (float) env('PAYMENTS_PRICE', 129),
            'dias' => (int) env('PAYMENTS_DAYS', 90),
            // "90 dias" lê-se pior do que "3 meses"; o rótulo é do negócio.
            'periodo' => env('PAYMENTS_PERIOD_LABEL', '3 meses'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | M-Pesa (Vodacom OpenAPI)
    |--------------------------------------------------------------------------
    |
    | Nada aqui está fixo em código de propósito: o código de mercado, o host e
    | o prefixo de caminho só se confirmam no portal quando as credenciais de
    | comerciante forem emitidas. Trocar de sandbox para produção é mudar
    | MPESA_PATH_PREFIX de 'sandbox' para 'openapi' — sem tocar em código.
    |
    | MPESA_PUBLIC_KEY é a chave pública do portal em base64 (DER, sem cabeçalho
    | PEM). A API key é cifrada com ela para formar o Bearer da sessão.
    |
    */

    'mpesa' => [
        'host' => env('MPESA_HOST', 'openapi.m-pesa.com'),
        'path_prefix' => env('MPESA_PATH_PREFIX', 'sandbox'),
        'market' => env('MPESA_MARKET', 'vodacomMOZ'),
        'country' => env('MPESA_COUNTRY', 'MOZ'),

        'api_key' => env('MPESA_API_KEY'),
        'public_key' => env('MPESA_PUBLIC_KEY'),
        'service_provider_code' => env('MPESA_SERVICE_PROVIDER_CODE'),

        // O cliente tem de destrancar o telemóvel e escrever o PIN: a chamada
        // C2B fica pendurada até isso acontecer ou expirar.
        'timeout' => (int) env('MPESA_TIMEOUT', 65),

        // As sessões duram cerca de uma hora; pedir uma por transação gasta
        // tempo e é desnecessário.
        'session_ttl' => (int) env('MPESA_SESSION_TTL', 2400),
    ],

    /*
    |--------------------------------------------------------------------------
    | PaySuite (agregador: e-Mola, M-Pesa, mKesh, cartões)
    |--------------------------------------------------------------------------
    |
    | Caminho para a e-Mola enquanto não houver acordo directo com a Movitel.
    | Ao contrário do C2B do M-Pesa — que empurra o pedido de PIN para um
    | número que nós indicamos — a PaySuite devolve um `checkout_url` e recolhe
    | o número na própria página. O app abre esse endereço.
    |
    | A confirmação chega por webhook assinado em HMAC-SHA256; sem o segredo
    | definido, o webhook é recusado (ver PaySuiteWebhookController).
    |
    */

    'paysuite' => [
        'base_url' => env('PAYSUITE_BASE_URL', 'https://paysuite.tech/api/v1'),
        'token' => env('PAYSUITE_TOKEN'),
        'webhook_secret' => env('PAYSUITE_WEBHOOK_SECRET'),
        'timeout' => (int) env('PAYSUITE_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | DebitoPay — orquestrador M-Pesa / e-Mola / mKesh / cartões
    |--------------------------------------------------------------------------
    |
    | A chave secreta fica exclusivamente no backend. O wallet_code pode ser
    | comum ou específico por método, conforme as carteiras criadas no portal.
    | O webhook é HMAC-SHA256 sobre o corpo bruto da requisição.
    |
    | O `base_url` fica em ambiente e não fixo em código porque a DebitoPay tem
    | dois endereços em circulação: o das Edge Functions (que é o que a própria
    | documentação usa nos exemplos, e o que responde hoje) e o vaidoso
    | `https://api.debitopay.com/v1`, que ainda não apresenta certificado TLS
    | válido. O primeiro é o predefinido por ser o que funciona; passar ao
    | segundo, quando estiver no ar, é mudar uma linha do .env.
    |
    */

    'debitopay' => [
        'base_url' => env('DEBITOPAY_BASE_URL', 'https://gyqoaningqhurhvdugne.supabase.co/functions/v1'),
        'api_key' => env('DEBITOPAY_API_KEY'),
        'merchant_id' => env('DEBITOPAY_MERCHANT_ID'),
        'wallet_code' => env('DEBITOPAY_WALLET_CODE'),
        'wallets' => [
            'mpesa' => env('DEBITOPAY_MPESA_WALLET_CODE'),
            'emola' => env('DEBITOPAY_EMOLA_WALLET_CODE'),
            'mkesh' => env('DEBITOPAY_MKESH_WALLET_CODE'),
            'cartao' => env('DEBITOPAY_CARD_WALLET_CODE'),
        ],

        /*
         * O nosso vocabulário para o deles. A DebitoPay também expõe 'payfast'
         * (cartões e EFT em ZAR, África do Sul); não está ligado porque a
         * CartaPro vende em MZN e o PayFast só aceita carteiras em ZAR — abri-lo
         * exigia uma carteira e um preço numa segunda moeda, que é decisão de
         * negócio, não de código. O driver cobra-o sem alterações no dia em que
         * existir esse método com a sua carteira.
         */
        'metodos' => [
            'mpesa' => 'mpesa',
            'emola' => 'emola',
            'mkesh' => 'mkesh',
            'cartao' => 'visa_mastercard',
        ],

        // Para onde o Hosted Checkout devolve o cliente depois do cartão.
        'return_url' => env('DEBITOPAY_RETURN_URL'),

        'webhook_secret' => env('DEBITOPAY_WEBHOOK_SECRET'),
        'timeout' => (int) env('DEBITOPAY_TIMEOUT', 30),
    ],

];
