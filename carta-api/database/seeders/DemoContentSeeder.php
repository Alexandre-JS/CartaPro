<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Question;
use App\Models\Sign;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@prontovia.co.mz')->firstOrFail();
        $topics = Topic::whereIn('slug', ['velocidade', 'sinais_perigo', 'prioridade'])->get()->keyBy('slug');
        $articles = Article::whereIn('number', [7, 18, 24, 30, 31])->get()->keyBy('number');
        $sign = Sign::where('slug', 'curva-perigosa')->first();

        $questions = [
            [
                'topic' => 'velocidade', 'external_id' => 'vel-001', 'type' => 'teorico',
                'statement' => 'Ao aproximar-se de uma escola, o condutor deve:',
                'options' => ['Aumentar a velocidade para não atrasar o trânsito', 'Reduzir a velocidade e estar atento aos peões', 'Buzinar continuamente', 'Circular sempre pela berma'],
                'correct_index' => 1, 'explanation' => 'Junto a escolas há maior risco de atravessamento de crianças. A velocidade deve ser ajustada às condições da via e ao ambiente envolvente.',
                'article_ref' => 24, 'status' => 'approved', 'is_locked' => false,
            ],
            [
                'topic' => 'velocidade', 'external_id' => 'vel-002', 'type' => 'teorico',
                'statement' => 'Em chuva intensa, a distância de travagem tende a:',
                'options' => ['Diminuir', 'Manter-se igual', 'Aumentar', 'Desaparecer se os pneus forem novos'],
                'correct_index' => 2, 'explanation' => 'Com piso molhado há menor aderência, por isso é necessário reduzir a velocidade e aumentar a distância de segurança.',
                'article_ref' => 18, 'status' => 'review', 'is_locked' => false,
            ],
            [
                'topic' => 'sinais_perigo', 'external_id' => 'sig-001', 'type' => 'pratico',
                'statement' => 'Este sinal indica:',
                'options' => ['Curva perigosa', 'Estrada sem saída', 'Proibição de ultrapassar', 'Passagem de nível'],
                'correct_index' => 0, 'explanation' => 'O sinal triangular com bordo vermelho alerta para uma curva perigosa à frente.',
                'article_ref' => null, 'status' => 'approved', 'is_locked' => false, 'image' => '/images/signs/curva-perigosa.svg',
            ],
            [
                'topic' => 'sinais_perigo', 'external_id' => 'sig-002', 'type' => 'teorico',
                'statement' => 'Perante um sinal de perigo, o condutor deve:',
                'options' => ['Ignorar se conhecer a via', 'Aumentar a atenção e adaptar a velocidade', 'Parar sempre imediatamente', 'Circular no eixo da via'],
                'correct_index' => 1, 'explanation' => 'Os sinais de perigo existem para antecipar riscos. A condução deve tornar-se mais prudente antes do local assinalado.',
                'article_ref' => 7, 'status' => 'draft', 'is_locked' => true,
            ],
            [
                'topic' => 'prioridade', 'external_id' => 'pri-001', 'type' => 'teorico',
                'statement' => 'Num cruzamento sem sinalização, em regra, deve ceder passagem ao veículo que se apresenta:',
                'options' => ['Pela esquerda', 'Pela direita', 'Por trás', 'Com maior velocidade'],
                'correct_index' => 1, 'explanation' => 'Na ausência de sinalização específica, aplica-se a regra geral de prioridade à direita.',
                'article_ref' => 30, 'status' => 'approved', 'is_locked' => false,
            ],
            [
                'topic' => 'prioridade', 'external_id' => 'pri-002', 'type' => 'teorico',
                'statement' => 'Ao entrar numa rotunda, o condutor deve:',
                'options' => ['Entrar sem observar', 'Ceder passagem aos veículos que nela circulam', 'Parar sempre dentro da rotunda', 'Circular sempre na faixa exterior'],
                'correct_index' => 1, 'explanation' => 'Quem entra numa rotunda deve ceder passagem aos veículos que já circulam nela, salvo sinalização em contrário.',
                'article_ref' => 31, 'status' => 'rejected', 'is_locked' => false,
                'rejection_reason' => 'Rever a redação da opção sobre a faixa exterior.',
            ],
        ];

        foreach ($questions as $position => $data) {
            $status = $data['status'];
            $topic = $topics->get($data['topic']);
            unset($data['topic']);

            $question = Question::firstOrCreate(['external_id' => $data['external_id']], $data + [
                'topic_id' => $topic->id,
                'author_id' => $admin->id,
                'categories' => ['ligeiro', 'pesado', 'profissional_publico'],
                'image' => $data['image'] ?? null,
                'is_active' => true,
                'sort_order' => $position + 1,
                'reviewed_by' => in_array($status, ['approved', 'rejected'], true) ? $admin->id : null,
                'reviewed_at' => in_array($status, ['approved', 'rejected'], true) ? now() : null,
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);
            $question->update([
                'author_id' => $question->author_id ?: $admin->id,
                'article_id' => $question->article_ref ? $articles->get($question->article_ref)?->id : null,
                'sign_id' => $question->external_id === 'sig-001' ? $sign?->id : null,
            ]);
        }
    }
}
