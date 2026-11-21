<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /** API keys of the literature databases and open-access sources, stored encrypted. */
    private const SECRET_FIELDS = ['core_api_key', 'ieee_api_key', 'scopus_api_key', 'scopus_insttoken', 'pubmed_api_key'];

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
            'unpaywall_email' => 'nullable|email|max:255',
            'core_api_key' => 'nullable|string|max:255',
            'ieee_api_key' => 'nullable|string|max:255',
            'scopus_api_key' => 'nullable|string|max:255',
            'scopus_insttoken' => 'nullable|string|max:255',
            'pubmed_api_key' => 'nullable|string|max:255',
            'clear' => 'nullable|array',
            'clear.*' => ['string', \Illuminate\Validation\Rule::in(self::SECRET_FIELDS)],
        ]);

        // Literature database and open-access keys: a filled field replaces the saved key, an
        // empty one keeps it, and "clear" removes it (the page never receives the saved keys)
        foreach (self::SECRET_FIELDS as $field) {
            if (in_array($field, $validated['clear'] ?? [], true)) {
                $user->{$field} = null;
            } elseif (!empty($validated[$field])) {
                $user->{$field} = trim($validated[$field]);
            }
        }
        if ($request->has('unpaywall_email')) {
            $user->unpaywall_email = $validated['unpaywall_email'] ?: null;
        }

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
