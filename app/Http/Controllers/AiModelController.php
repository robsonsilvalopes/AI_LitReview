<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiModelController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $hasApiKey = !empty($user?->openrouter_api_key);
        return view('content.pages.ai-models', [
            'hasOpenRouterKey' => $hasApiKey
        ]);
    }

    public function data(Request $request)
    {
        $user = Auth::user();
        $apiKey = $user?->openrouter_api_key;

        if (empty($apiKey)) {
            return response()->json([
                'data' => [],
                'error' => 'OpenRouter API key not configured.'
            ], 200);
        }

        $cacheKey = 'openrouter_models_v1_user_' . $user->id;
        $models = Cache::remember($cacheKey, 600, function () use ($apiKey) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(20)
                    ->get('https://openrouter.ai/api/v1/models');

                if (!$response->successful()) {
                    Log::warning('OpenRouter models fetch failed', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return null;
                }

                return $response->json('data', []);
            } catch (\Exception $e) {
                Log::warning('OpenRouter models fetch exception', [
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });

        if ($models === null) {
            return response()->json([
                'data' => [],
                'error' => 'Unable to fetch OpenRouter models.'
            ], 200);
        }

        $data = collect($models)->map(function ($model) {
            $pricing = data_get($model, 'pricing', []);
            $popularity = null;
            foreach (['popularity', 'top', 'rank', 'usage'] as $key) {
                $value = data_get($model, $key);
                if (is_numeric($value)) {
                    $popularity = (float) $value;
                    break;
                }
            }
            return [
                'id' => data_get($model, 'id', '-'),
                'name' => data_get($model, 'name', 'Unknown'),
                'description' => data_get($model, 'description', ''),
                'context_length' => data_get($model, 'context_length', null),
                'pricing' => $pricing,
                'raw' => $model,
                'popularity' => $popularity
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function localModels(Request $request)
    {
        $user = Auth::user();
        $ollamaUrl = $user?->ollama_url ?? 'http://localhost:11434/';

        // Ensure URL ends with /
        if (!str_ends_with($ollamaUrl, '/')) {
            $ollamaUrl .= '/';
        }

        // If running in Docker and URL contains localhost, try host.docker.internal
        $urlsToTry = [$ollamaUrl];
        if (str_contains($ollamaUrl, 'localhost') || str_contains($ollamaUrl, '127.0.0.1')) {
            // Try host.docker.internal (works on Docker Desktop for Windows/Mac/Linux)
            $dockerUrl = str_replace(['localhost', '127.0.0.1'], 'host.docker.internal', $ollamaUrl);
            $urlsToTry[] = $dockerUrl;
        }

        $lastError = null;
        $successfulUrl = null;
        $successfulResponse = null;
        
        // Try URLs with very short timeout to avoid blocking
        foreach ($urlsToTry as $url) {
            try {
                $response = Http::timeout(0.5)
                    ->connectTimeout(0.5)
                    ->get($url . 'api/tags');

                if ($response->successful()) {
                    $successfulUrl = $url;
                    $successfulResponse = $response;
                    break;
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Connection timeout - try next URL
                $lastError = $e;
                continue;
            } catch (\Exception $e) {
                $lastError = $e;
                // Continue to next URL
                continue;
            }
        }

        // If we found a working URL, use its response
        if ($successfulUrl && $successfulResponse) {
            try {
                $data = $successfulResponse->json();

                $models = $data['models'] ?? [];

                $formattedModels = collect($models)->map(function ($model) {
                    return [
                        'id' => $model['name'] ?? 'unknown',
                        'name' => $model['name'] ?? 'Unknown',
                        'model_id' => $model['name'] ?? 'unknown',
                        'size' => isset($model['size']) ? $this->formatBytes($model['size']) : '-',
                        'modified_at' => isset($model['modified_at']) ? date('Y-m-d H:i:s', strtotime($model['modified_at'])) : '-',
                        'raw' => $model
                    ];
                })->values();

                return response()->json(['data' => $formattedModels]);
            } catch (\Exception $e) {
                Log::warning('Ollama models parse exception', [
                    'error' => $e->getMessage(),
                    'url' => $successfulUrl
                ]);
                return response()->json([
                    'data' => [],
                    'error' => 'Unable to parse Ollama response: ' . $e->getMessage()
                ], 200);
            }
        }

        // If no URL worked, return error with helpful message
        Log::warning('Ollama models fetch failed for all URLs', [
            'tried_urls' => $urlsToTry,
            'last_error' => $lastError ? $lastError->getMessage() : 'Unknown'
        ]);
        
        $errorMessage = 'Unable to connect to Ollama.';
        
        // Provide helpful error message for Docker users
        if (str_contains($ollamaUrl, 'localhost') || str_contains($ollamaUrl, '127.0.0.1')) {
            $errorMessage .= ' If you are running in Docker, try using "host.docker.internal" instead of "localhost" in Settings (e.g., http://host.docker.internal:11434/).';
        } else {
            $errorMessage .= ' Please check your Ollama URL in Settings and ensure Ollama is running.';
        }
        
        if ($lastError) {
            $errorMessage .= ' Error: ' . $lastError->getMessage();
        }
        
        return response()->json([
            'data' => [],
            'error' => $errorMessage
        ], 200);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
