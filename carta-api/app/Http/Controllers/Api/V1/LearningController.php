<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LearningEvent;
use App\Models\ReadinessScore;
use App\Models\StudyRecommendation;
use App\Models\TopicMastery;
use App\Services\Learning\LearningEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function __construct(private readonly LearningEngine $learning) {}

    public function profile(Request $request): JsonResponse
    {
        $profile = $this->learning->ensureFresh($request->user());

        return response()->json([
            'profile' => $profile,
            'masteries' => TopicMastery::where('mobile_user_id', $request->user()->id)->with('topic:id,name,slug')->orderByDesc('score')->get(),
        ]);
    }

    public function readiness(Request $request): JsonResponse
    {
        $this->learning->ensureFresh($request->user());

        return response()->json(ReadinessScore::where('mobile_user_id', $request->user()->id)->firstOrFail());
    }

    public function recommendations(Request $request): JsonResponse
    {
        $this->learning->ensureFresh($request->user());

        return response()->json(['data' => StudyRecommendation::where('mobile_user_id', $request->user()->id)
            ->where('status', 'active')->with('topic:id,name,slug')->orderByDesc('priority')->get()]);
    }

    public function events(Request $request): JsonResponse
    {
        return response()->json(LearningEvent::where('mobile_user_id', $request->user()->id)
            ->with('topic:id,name,slug')->latest('occurred_at')->paginate(min($request->integer('por_pagina', 30), 100)));
    }
}
