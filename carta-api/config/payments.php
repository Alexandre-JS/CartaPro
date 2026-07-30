<?php

return [

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
    */

    'methods' => [
        'mpesa' => [
            'nome' => 'M-Pesa',
            'operadora' => 'Vodacom',
            'prefixos' => array_filter(explode(',', env('MPESA_PREFIXES', '84,85'))),
            'driver' => env('MPESA_DRIVER', 'mpesa'),
        ],
        'emola' => [
            'nome' => 'e-Mola',
            'operadora' => 'Movitel',
            'prefixos' => array_filter(explode(',', env('EMOLA_PREFIXES', '86,87'))),
            'driver' => env('EMOLA_DRIVER', 'paysuite'),
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

];
