@php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
@endphp

<!--  Brand demo (display only for navbar-full and hide on below xl) -->
@if(isset($navbarFull))
<div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-6">
    <a href="{{url('/')}}" class="app-brand-link gap-2">
        <span class="app-brand-logo demo">@include('_partials.macros')</span>
        <span class="app-brand-text demo menu-text fw-bold">{{config('variables.templateName')}}</span>
    </a>
</div>
@endif

<!-- ! Not required for layout-without-menu -->
@if(!isset($navbarHideToggle))
<div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 {{ isset($contentNavbar) ? ' d-xl-none ' : '' }}">
    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
        <i class="icon-base ri ri-menu-line icon-md"></i>
    </a>
</div>
@endif

<div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <ul class="navbar-nav flex-row align-items-center ms-auto">
        <!-- User -->
        <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="avatar avatar-online">
                    @php
                        $user = Auth::user();
                        $userInitials = $user ? strtoupper(substr($user->name, 0, 2)) : 'U';
                        $avatarUrl = $user && $user->avatar ? route('storage.s3', ['path' => $user->avatar]) : asset('assets/json/img/avatars/1.png');
                    @endphp
                    @if($user && $user->avatar)
                        <img src="{{ $avatarUrl }}" alt="avatar" class="rounded-circle" id="navbar-avatar-small" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'avatar-initial rounded-circle bg-label-primary\'><span>{{ $userInitials }}</span></div>';" />
                    @else
                        <div class="avatar-initial rounded-circle bg-label-primary">
                            <span>{{ $userInitials }}</span>
                        </div>
                    @endif
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#profileModal" style="cursor: pointer;">
                        <div class="d-flex">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar avatar-online">
                                    @php
                                        $user = Auth::user();
                                        $userInitials = $user ? strtoupper(substr($user->name, 0, 2)) : 'U';
                                        $avatarUrl = $user && $user->avatar ? route('storage.s3', ['path' => $user->avatar]) : asset('assets/json/img/avatars/1.png');
                                    @endphp
                                    @if($user && $user->avatar)
                                        <img src="{{ $avatarUrl }}" alt="avatar" class="w-px-40 h-auto rounded-circle" id="navbar-avatar" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'avatar-initial rounded-circle bg-label-primary\'><span>{{ $userInitials }}</span></div>';" />
                                    @else
                                        <div class="avatar-initial rounded-circle bg-label-primary">
                                            <span>{{ $userInitials }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0">{{ $user->name ?? 'User' }}</h6>
                                <small class="text-body-secondary">{{ $user->email ?? '' }}</small>
                            </div>
                        </div>
                    </a>
                </li>
                <li>
                    <div class="dropdown-divider my-1"></div>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('profile') }}">
                        <i class="icon-base ri ri-user-3-line icon-md me-3"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('settings') }}">
                        <i class="icon-base ri ri-settings-4-line icon-md me-4"></i><span>Settings</span>
                    </a>
                </li>
                <li>
                    <div class="dropdown-divider my-1"></div>
                </li>
                <li>
                    <div class="d-grid px-4 pt-2 pb-1">
                        <form method="POST" action="{{ route('logout') }}" class="w-100">
                            @csrf
                            <button type="submit" class="btn btn-danger d-flex w-100">
                                <small class="align-middle">Logout</small>
                                <i class="ri ri-logout-box-r-line ms-2 icon-xs"></i>
                            </button>
                        </form>
                    </div>
                </li>
            </ul>
        </li>
        <!--/ User -->
    </ul>
</div>