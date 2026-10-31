<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Routing\Route;
use App\Models\Project;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MenuServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap services.
   */
  public function boot(): void
  {
    // Use View Composer to ensure Auth is available when view is rendered
    View::composer('*', function ($view) {
      // Get user ID - Auth should be available when view is rendered
      $userId = Auth::id();
      $cacheKey = $userId ? "menu_data_user_{$userId}" : 'menu_data_guest';
      
      try {
        $menuData = Cache::remember($cacheKey, 3600, function () use ($userId) {
          $verticalMenuJson = file_get_contents(base_path('resources/menu/verticalMenu.json'));
          // Decode as array to allow modification
          $verticalMenuData = json_decode($verticalMenuJson, true);

          // Try to load projects, but don't block if database is slow/unavailable
          try {
            // Use DB::table for faster query (no Eloquent overhead)
            // Filter by user_id (owner) OR user as collaborator if user is authenticated
            if ($userId) {
              // Get projects where user is owner OR collaborator
              $projects = DB::table('projects')
                ->leftJoin('project_collaborators', 'projects.id', '=', 'project_collaborators.project_id')
                ->where(function($query) use ($userId) {
                  $query->where('projects.user_id', $userId)
                        ->orWhere('project_collaborators.user_id', $userId);
                })
                ->select('projects.*')
                ->distinct()
                ->orderBy('projects.id')
                ->limit(100)
                ->get();
            } else {
              // If not authenticated, don't show any projects
              $projects = collect([]);
            }
            
            // Find the Projects menu item and add projects as submenus
            foreach ($verticalMenuData['menu'] as $key => $menuItem) {
              if (isset($menuItem['name']) && $menuItem['name'] === 'Projects') {
                // Initialize submenu array
                $verticalMenuData['menu'][$key]['submenu'] = [];
                
                // Add each project as a submenu item
                foreach ($projects as $project) {
                  $verticalMenuData['menu'][$key]['submenu'][] = [
                    'name' => $project->name,
                    'url' => '/projects/' . $project->id,
                    'slug' => 'projects.show',
                    'icon' => 'menu-icon icon-base ri ri-file-line'
                  ];
                }
                
                // Log for debugging
                if (config('app.debug')) {
                  Log::info('MenuServiceProvider: Added projects to menu', [
                    'user_id' => $userId,
                    'projects_count' => count($verticalMenuData['menu'][$key]['submenu'])
                  ]);
                }
                
                break;
              }
            }
          } catch (\PDOException $e) {
            // Database connection failed - use menu without projects
            // Log error in debug mode
            if (config('app.debug')) {
              Log::warning('MenuServiceProvider: PDOException loading projects', ['error' => $e->getMessage()]);
            }
          } catch (\Illuminate\Database\QueryException $e) {
            // Query failed - use menu without projects
            // Log error in debug mode
            if (config('app.debug')) {
              Log::warning('MenuServiceProvider: QueryException loading projects', ['error' => $e->getMessage()]);
            }
          } catch (\Exception $e) {
            // Any other error - use menu without projects
            // Log error in debug mode
            if (config('app.debug')) {
              Log::warning('MenuServiceProvider: Exception loading projects', ['error' => $e->getMessage()]);
            }
          }

          // Convert back to object for Blade templates
          return json_decode(json_encode($verticalMenuData));
        });
      } catch (\Exception $e) {
        // If cache fails, load menu without projects
        $verticalMenuJson = file_get_contents(base_path('resources/menu/verticalMenu.json'));
        $menuData = json_decode($verticalMenuJson);
      }

      // Share menuData to the view
      $view->with('menuData', [$menuData]);
    });
  }
}
