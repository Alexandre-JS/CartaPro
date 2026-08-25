<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function __invoke(): View
    {
        return view('website.candidatos', [
            // Mantém a página pública disponível durante deploys em que a
            // migration de monetização ainda não chegou à base de dados.
            'plans' => Schema::hasTable('plans')
                ? Plan::where('is_active', true)->orderBy('sort_order')->get()
                : collect(),
        ]);
    }
}
