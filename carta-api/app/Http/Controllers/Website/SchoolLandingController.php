<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SchoolLandingController extends Controller
{
    public function __invoke(): View
    {
        return view('website.escolas');
    }
}
