<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $preferences = $request->user()->preferences ?? $request->user()->getDefaultPreferences();

        return response()->json(['preferences' => $preferences]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'preferences' => 'required|array',
        ]);

        $currentPreferences = $request->user()->preferences ?? [];
        $mergedPreferences = array_merge($currentPreferences, $request->preferences);

        $request->user()->update(['preferences' => $mergedPreferences]);

        return response()->json(['preferences' => $mergedPreferences]);
    }
}