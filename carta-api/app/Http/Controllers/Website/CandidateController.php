<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function __invoke(): View
    {
        return view('website.candidatos');
    }
}
