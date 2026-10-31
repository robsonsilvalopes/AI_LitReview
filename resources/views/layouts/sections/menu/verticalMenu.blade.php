@php
use Illuminate\Support\Facades\Route;
@endphp
<aside id="layout-menu" class="layout-menu menu-vertical menu">

    <!-- ! Hide app brand if navbar-full -->
    <div class="app-brand demo">
        <a href="{{url('/')}}" class="app-brand-link">
            <span class="app-brand-logo demo me-1">@include('_partials.macros')</span>
            <span class="app-brand-text demo menu-text fw-semibold ms-2">{{config('variables.templateName')}}</span>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        @foreach ($menuData[0]->menu as $menu)

        {{-- adding active and open class if child is active --}}

        {{-- menu headers --}}
        @if (isset($menu->menuHeader))
        <li class="menu-header mt-7">
            <span class="menu-header-text">{{ __($menu->menuHeader) }}</span>
        </li>
        @else

        {{-- active menu method --}}
        @php
        $activeClass = null;
        $currentRouteName = Route::currentRouteName();
        $currentPath = request()->path();
        $menuPath = null;
        if (isset($menu->url)) {
          $url = $menu->url;
          if (strpos($url, 'http') === 0) {
            $parsed = parse_url($url);
            $menuPath = isset($parsed['path']) ? trim($parsed['path'], '/') : null;
          } else {
            $menuPath = trim($url, '/');
          }
        }

        // Check if this is the Projects menu item
        $isProjectsMenu = isset($menu->name) && $menu->name === 'Projects';
        
        // Check if Projects menu has submenus
        $hasProjectsSubmenu = false;
        if ($isProjectsMenu && isset($menu->submenu)) {
          if (is_array($menu->submenu) && count($menu->submenu) > 0) {
            $hasProjectsSubmenu = true;
          } elseif (is_object($menu->submenu)) {
            $submenuArray = (array)$menu->submenu;
            $hasProjectsSubmenu = count($submenuArray) > 0;
          }
        }

        if ($menuPath !== null && $currentPath === $menuPath) {
        $activeClass = 'active';
        }
        elseif ($currentRouteName === $menu->slug) {
        $activeClass = 'active';
        }
        elseif (isset($menu->submenu)) {
        if (gettype($menu->slug) === 'array') {
        foreach($menu->slug as $slug){
        if (str_contains($currentRouteName,$slug) and strpos($currentRouteName,$slug) === 0) {
        $activeClass = 'active open';
        }
        }
        }
        else{
        if (str_contains($currentRouteName,$menu->slug) and strpos($currentRouteName,$menu->slug) === 0) {
        $activeClass = 'active open';
        }
        }
        }
        
        // Always keep Projects menu open if it has submenus
        if ($isProjectsMenu && $hasProjectsSubmenu) {
          // Always add 'open' class
          if (empty($activeClass)) {
            $activeClass = 'open';
          } else {
            // If already has active class, ensure it also has open
            if (!str_contains($activeClass, 'open')) {
              $activeClass .= ' open';
            }
          }
          // Also add 'active' if we're on a project page
          if (str_contains($currentRouteName, 'projects.show') || str_contains(request()->path(), 'projects/')) {
            $activeClass = 'active open';
          }
        }
        @endphp

        {{-- main menu --}}
        <li class="menu-item {{$activeClass}}" @if (isset($menu->name) && $menu->name === 'Projects' && $hasProjectsSubmenu) data-menu-always-open="true" @endif>
            <div class="d-flex align-items-center w-100" style="position: relative;">
                <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}" class="{{ (isset($menu->submenu) && count((array)$menu->submenu) > 0) ? 'menu-link menu-toggle' : 'menu-link' }}" @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif @if (isset($menu->name) && $menu->name === 'Projects' && $hasProjectsSubmenu) onclick="return false;" @endif style="flex: 1;">
                    @isset($menu->icon)
                    <i class="{{ $menu->icon }}"></i>
                    @endisset
                    <div>{{ isset($menu->name) ? __($menu->name) : '' }}</div>
                    @isset($menu->badge)
                    <div class="badge rounded-pill bg-{{ $menu->badge[0] }} rounded-pill ms-auto">{{ $menu->badge[1] }}</div>
                    @endisset
                </a>
                @if (isset($menu->name) && $menu->name === 'Projects')
                <button type="button" class="btn btn-sm btn-icon btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal" onclick="event.stopPropagation(); event.preventDefault();" style="flex-shrink: 0; padding: 0.25rem 0.5rem; margin-left: 0.5rem;">
                    <i class="icon-base ri ri-add-line"></i>
                </button>
                @endif
            </div>

            {{-- submenu --}}
            @php
                $hasSubmenu = false;
                $submenuCount = 0;
                if (isset($menu->submenu)) {
                    if (is_array($menu->submenu)) {
                        $submenuCount = count($menu->submenu);
                        $hasSubmenu = $submenuCount > 0;
                    } elseif (is_object($menu->submenu)) {
                        // Convert object to array for counting
                        $submenuArray = (array)$menu->submenu;
                        $submenuCount = count($submenuArray);
                        $hasSubmenu = $submenuCount > 0;
                    }
                }
            @endphp
            @if ($hasSubmenu)
            @include('layouts.sections.menu.submenu',[
              'menu' => $menu->submenu,
              'submenuClass' => (isset($menu->name) && $menu->name === 'Projects') ? 'menu-sub-no-bullets' : ''
            ])
            @elseif (isset($menu->name) && $menu->name === 'Projects')
            {{-- Always show submenu container for Projects, even if empty --}}
            <ul class="menu-sub menu-sub-no-bullets"></ul>
            @endif
        </li>
        @endif
        @endforeach
    </ul>

</aside>
