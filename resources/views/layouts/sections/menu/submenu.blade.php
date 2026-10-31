@php
use Illuminate\Support\Facades\Route;
@endphp

<ul class="menu-sub {{ isset($submenuClass) ? $submenuClass : '' }}">
  @if (isset($menu))
    @foreach ($menu as $submenu)

    {{-- active menu method --}}
    @php
      $activeClass = null;
      $active = 'active open';
      $currentRouteName = Route::currentRouteName();
      $currentPath = request()->path(); // e.g., "projects/2"
      
      // Get submenu URL path - normalize it
      $submenuPath = null;
      if (isset($submenu->url)) {
          // Handle both absolute URLs and relative paths
          $url = $submenu->url;
          if (strpos($url, 'http') === 0) {
              // Absolute URL - extract path
              $parsed = parse_url($url);
              $submenuPath = isset($parsed['path']) ? trim($parsed['path'], '/') : null;
          } else {
              // Relative path - remove leading slash
              $submenuPath = trim($url, '/');
          }
      }

      // For projects, compare by exact path match
      // This ensures only the current project is highlighted, not all projects
      if ($submenuPath && $currentPath === $submenuPath) {
          $activeClass = 'active';
      }
      // Fallback: if route name matches and it's projects.show, check if we're on a project page
      elseif ($currentRouteName === 'projects.show' && $submenu->slug === 'projects.show') {
          // Extract project ID from current path
          if (preg_match('/^projects\/(\d+)$/', $currentPath, $matches)) {
              $currentProjectId = $matches[1];
              // Extract project ID from submenu URL
              if ($submenuPath && preg_match('/^projects\/(\d+)$/', $submenuPath, $submenuMatches)) {
                  $submenuProjectId = $submenuMatches[1];
                  if ($currentProjectId == $submenuProjectId) {
                      $activeClass = 'active';
                  }
              }
          }
      }
      // Fallback to slug comparison only if no URL is set and it's not a project
      elseif ($currentRouteName === $submenu->slug && !isset($submenu->url) && $submenu->slug !== 'projects.show') {
          $activeClass = 'active';
      }
      elseif (isset($submenu->submenu)) {
        if (gettype($submenu->slug) === 'array') {
          foreach($submenu->slug as $slug){
            if (str_contains($currentRouteName,$slug) and strpos($currentRouteName,$slug) === 0) {
                $activeClass = $active;
            }
          }
        }
        else{
          if (str_contains($currentRouteName,$submenu->slug) and strpos($currentRouteName,$submenu->slug) === 0) {
            $activeClass = $active;
          }
        }
      }
    @endphp

      <li class="menu-item {{$activeClass}}">
        <a href="{{ isset($submenu->url) ? url($submenu->url) : 'javascript:void(0)' }}" class="{{ isset($submenu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}" @if (isset($submenu->target) and !empty($submenu->target)) target="_blank" @endif>
          @if (isset($submenu->icon))
          <i class="{{ $submenu->icon }}"></i>
          @endif
          <div>{{ isset($submenu->name) ? __($submenu->name) : '' }}</div>
          @isset($submenu->badge)
            <div class="badge bg-{{ $submenu->badge[0] }} rounded-pill ms-auto">{{ $submenu->badge[1] }}</div>
          @endisset
        </a>

        {{-- submenu --}}
        @if (isset($submenu->submenu))
          @include('layouts.sections.menu.submenu',['menu' => $submenu->submenu])
        @endif
      </li>
    @endforeach
  @endif
</ul>
