<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        return view('content.pages.settings', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'openrouter_api_key' => 'nullable|string|max:255',
            'ollama_url' => 'nullable|string|max:255|url',
        ]);

        if (isset($validated['openrouter_api_key'])) {
            $user->openrouter_api_key = $validated['openrouter_api_key'] ?? null;
        }
        if (isset($validated['ollama_url'])) {
            $user->ollama_url = $validated['ollama_url'] ?? 'http://localhost:11434/';
        }
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }
}
