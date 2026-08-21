<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\LicenseCategory;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * O mínimo para o painel abrir: um administrador e o catálogo base.
 *
 * Saiu de dentro do DatabaseSeeder para haver forma de repor uma instalação
 * sem trazer atrás a escola, a turma, os alunos e os resultados de exemplo.
 * Numa base que vai receber conteúdo a sério — a importação do INATRO, as
 * escolas reais — esses registos de demonstração são lixo que alguém tem
 * depois de apagar à mão, e no meio do qual é fácil publicar um exemplo por
 * engano.
 *
 *     php artisan db:seed --class=EssenciaisSeeder
 */
class EssenciaisSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'admin@cartapro.co.mz'], [
            'name' => 'Administrador CartaPro',
            'password' => 'CartaPro@2026',
        ]);

        collect([
            ['name' => 'Ligeiro', 'slug' => 'ligeiro', 'sort_order' => 1],
            ['name' => 'Pesado', 'slug' => 'pesado', 'sort_order' => 2],
            ['name' => 'Profissional/público', 'slug' => 'profissional_publico', 'sort_order' => 3],
        ])->each(fn (array $category) => LicenseCategory::updateOrCreate(['slug' => $category['slug']], $category + ['is_active' => true]));

        collect([
            ['name' => 'Velocidade e condução segura', 'slug' => 'velocidade', 'sort_order' => 1],
            ['name' => 'Sinais de perigo', 'slug' => 'sinais_perigo', 'sort_order' => 2],
            ['name' => 'Prioridade e cruzamentos', 'slug' => 'prioridade', 'sort_order' => 3],
        ])->each(fn (array $topic) => Topic::updateOrCreate(['slug' => $topic['slug']], $topic + ['is_active' => true]));

        /*
         * Textos de referência abreviados, não a letra da lei. Servem para o
         * painel ter artigos a que ligar as perguntas; quem quiser o texto
         * integral usa a importação de artigos (ArticleController::import).
         */
        collect([
            ['number' => 7, 'title' => 'Sinalização', 'text' => 'Os sinais de trânsito destinam-se a regular e orientar a circulação rodoviária.'],
            ['number' => 18, 'title' => 'Distância entre veículos', 'text' => 'O condutor deve manter distância suficiente para evitar acidentes em caso de paragem súbita.'],
            ['number' => 24, 'title' => 'Princípios gerais de velocidade', 'text' => 'A velocidade deve ser regulada considerando as características da via, do veículo e as condições de circulação.'],
            ['number' => 30, 'title' => 'Regra geral de prioridade', 'text' => 'Nos cruzamentos e entroncamentos o condutor deve ceder passagem aos veículos que se apresentem pela direita.'],
            ['number' => 31, 'title' => 'Rotundas', 'text' => 'Ao entrar numa rotunda, o condutor deve ceder passagem aos veículos que nela circulam.'],
        ])->each(fn (array $article) => Article::updateOrCreate(['number' => $article['number']], $article + ['is_active' => true]));
    }
}
