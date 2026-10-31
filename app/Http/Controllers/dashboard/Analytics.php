<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AvailableModel;
use App\Models\Project;
use App\Models\Study;

class Analytics extends Controller
{
  public function index()
  {
    $user = Auth::user();
    
    // Get all projects the user has access to
    $userProjectIds = Project::where('user_id', $user->id)
      ->orWhereHas('collaborators', function($q) use ($user) {
        $q->where('user_id', $user->id);
      })
      ->pluck('id');
    
    $projects = Project::whereIn('id', $userProjectIds)
      ->with('user')
      ->withCount('studies')
      ->orderBy('created_at', 'desc')
      ->get();
    
    // Total projects count
    $totalProjects = $projects->count();
    
    // Count PDFs in included studies (status_selecao_final = 1 or status_selecao_inicial = 1)
    $includedPdfsCount = Study::whereIn('project_id', $userProjectIds)
      ->whereNotNull('pdf_path')
      ->where(function($q) {
        $q->where('status_selecao_final', true)
          ->orWhere('status_selecao_inicial', true);
      })
      ->count();
    
    // OpenRouter data
    $openRouterBalance = null;
    $openRouterModelsCount = null;
    $hasOpenRouterKey = !empty($user->openrouter_api_key);
    
    if ($hasOpenRouterKey) {
      try {
        // Get balance from OpenRouter
        $balanceResponse = Http::withHeaders([
          'Authorization' => 'Bearer ' . $user->openrouter_api_key,
          'Content-Type' => 'application/json',
        ])
          ->timeout(10)
          ->get('https://openrouter.ai/api/v1/credits');
        
        if ($balanceResponse->successful()) {
          $balanceData = $balanceResponse->json();
          
          // API returns: {"data":{"total_credits":5,"total_usage":0.29351245}}
          // Calculate remaining balance: total_credits - total_usage
          if (isset($balanceData['data']) && is_array($balanceData['data'])) {
            $data = $balanceData['data'];
            $totalCredits = isset($data['total_credits']) && is_numeric($data['total_credits']) ? (float)$data['total_credits'] : 0;
            $totalUsage = isset($data['total_usage']) && is_numeric($data['total_usage']) ? (float)$data['total_usage'] : 0;
            $openRouterBalance = $totalCredits - $totalUsage;
          } else {
            $openRouterBalance = null;
          }
        }
        
        // Get models count from OpenRouter
        $modelsResponse = Http::withHeaders([
          'Authorization' => 'Bearer ' . $user->openrouter_api_key,
          'Content-Type' => 'application/json',
        ])
          ->timeout(10)
          ->get('https://openrouter.ai/api/v1/models');
        
        if ($modelsResponse->successful()) {
          $modelsData = $modelsResponse->json('data', []);
          $openRouterModelsCount = count($modelsData);
        }
      } catch (\Exception $e) {
        Log::warning('Error fetching OpenRouter data', [
          'error' => $e->getMessage()
        ]);
      }
    }
    
    // Local models count
    $localModelsCount = null;
    $hasLocalUrl = !empty($user->ollama_url);
    
    if ($hasLocalUrl) {
      try {
        $ollamaUrl = $user->ollama_url;
        if (!str_ends_with($ollamaUrl, '/')) {
          $ollamaUrl .= '/';
        }
        
        // Try localhost and host.docker.internal
        $urlsToTry = [$ollamaUrl];
        if (str_contains($ollamaUrl, 'localhost') || str_contains($ollamaUrl, '127.0.0.1')) {
          $dockerUrl = str_replace(['localhost', '127.0.0.1'], 'host.docker.internal', $ollamaUrl);
          $urlsToTry[] = $dockerUrl;
        }
        
        foreach ($urlsToTry as $url) {
          try {
            $response = Http::timeout(2)
              ->connectTimeout(2)
              ->get($url . 'api/tags');
            
            if ($response->successful()) {
              $data = $response->json();
              $models = $data['models'] ?? [];
              $localModelsCount = count($models);
              break;
            }
          } catch (\Exception $e) {
            continue;
          }
        }
      } catch (\Exception $e) {
        Log::warning('Error fetching local models', [
          'error' => $e->getMessage()
        ]);
      }
    }
    
    // Selected models (from available_models table)
    $selectedModels = AvailableModel::where('user_id', $user->id)
      ->orderBy('type')
      ->orderBy('name')
      ->get();
    
    return view('content.dashboard.dashboards-analytics', [
      'projects' => $projects,
      'totalProjects' => $totalProjects,
      'includedPdfsCount' => $includedPdfsCount,
      'openRouterBalance' => $openRouterBalance,
      'openRouterModelsCount' => $openRouterModelsCount,
      'hasOpenRouterKey' => $hasOpenRouterKey,
      'localModelsCount' => $localModelsCount,
      'hasLocalUrl' => $hasLocalUrl,
      'selectedModels' => $selectedModels,
    ]);
  }
}
