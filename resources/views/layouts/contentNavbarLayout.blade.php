@isset($pageConfigs)
{!! Helper::updatePageConfig($pageConfigs) !!}
@endisset
@extends('layouts/commonMaster')

@php
/* Display elements */
$contentNavbar = $contentNavbar ?? true;
$containerNav = $containerNav ?? 'container-xxl';
$isNavbar = $isNavbar ?? true;
$isMenu = $isMenu ?? true;
$isFlex = $isFlex ?? false;
$isFooter = $isFooter ?? true;
$customizerHidden = $customizerHidden ?? '';

/* HTML Classes */
$navbarDetached = 'navbar-detached';
$menuFixed = isset($configData['menuFixed']) ? $configData['menuFixed'] : '';
if (isset($navbarType)) {
$configData['navbarType'] = $navbarType;
}
$navbarType = isset($configData['navbarType']) ? $configData['navbarType'] : '';
$footerFixed = isset($configData['footerFixed']) ? $configData['footerFixed'] : '';
$menuCollapsed = isset($configData['menuCollapsed']) ? $configData['menuCollapsed'] : '';

/* Content classes */
$container = ($container ?? 'container-xxl');

@endphp

@section('layoutContent')
<div class="layout-wrapper layout-content-navbar {{ $isMenu ? '' : 'layout-without-menu' }}">
    <div class="layout-container">

        @if ($isMenu)
        @include('layouts/sections/menu/verticalMenu')
        @endif


        <!-- Layout page -->
        <div class="layout-page">

            {{-- Below commented code read by artisan command while installing jetstream. !! Do not remove if you want to use jetstream. --}}
            {{-- <x-banner /> --}}

            <!-- BEGIN: Navbar-->
            @if ($isNavbar)
            @include('layouts/sections/navbar/navbar')
            @endif
            <!-- END: Navbar-->


            <!-- Content wrapper -->
            <div class="content-wrapper">

                <!-- Content -->
                @if ($isFlex)
                <div class="{{ $container }} d-flex align-items-stretch flex-grow-1 p-0">
                    @else
                    <div class="{{ $container }} flex-grow-1 container-p-y">
                        @endif

                        @yield('content')

                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    @if ($isFooter)
                    @include('layouts/sections/footer/footer')
                    @endif
                    <!-- / Footer -->
                    <div class="content-backdrop fade"></div>
                </div>
                <!--/ Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        @if ($isMenu)
        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
        @endif
        <!-- Drag Target Area To SlideIn Menu On Small Screens -->
        <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
    <div id="toastNotification" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-header">
            <i id="toastIcon" class="icon-base ri ri-checkbox-circle-fill icon-sm me-2"></i>
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage"></div>
    </div>
</div>

<!-- Profile Modal (Global) -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">My Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="profileModalForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="d-flex align-items-start align-items-sm-center gap-4">
                                <div class="avatar-wrapper">
                                    @php
                                        $user = Auth::user();
                                        $userInitials = $user ? strtoupper(substr($user->name, 0, 2)) : 'U';
                                        $avatarUrl = $user && $user->avatar ? route('storage.s3', ['path' => $user->avatar]) : asset('assets/json/img/avatars/1.png');
                                    @endphp
                                    <img src="{{ $avatarUrl }}" 
                                         alt="user-avatar" 
                                         class="d-block rounded" 
                                         id="modalUploadedAvatar" 
                                         style="width: 100px; height: 100px; object-fit: cover; cursor: pointer;"
                                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23ddd\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' font-size=\'40\' fill=\'%23999\'%3E{{ $userInitials }}%3C/text%3E%3C/svg%3E';" />
                                    <div class="button-wrapper mt-3">
                                        <label for="modalAvatar" class="btn btn-sm btn-primary me-3 mb-4" tabindex="0">
                                            <span class="d-none d-sm-block">Upload new photo</span>
                                            <i class="icon-base ri ri-upload-2-line d-block d-sm-none"></i>
                                            <input type="file" id="modalAvatar" name="avatar" class="account-file-input" hidden accept="image/png, image/jpeg, image/jpg, image/gif" />
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-danger account-image-reset mb-4" id="modalRemoveAvatar">
                                            <i class="icon-base ri ri-refresh-line d-block d-sm-none"></i>
                                            <span class="d-none d-sm-block">Remove</span>
                                        </button>
                                        <div class="text-muted">Allowed JPG, GIF or PNG. Max size of 2MB</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-4 mt-2">
                        <div class="col-md-6 form-control-validation">
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control" id="modalName" name="name" value="{{ $user->name ?? '' }}" placeholder="Enter your name" required />
                                <label for="modalName">Name</label>
                            </div>
                        </div>
                        <div class="col-md-6 form-control-validation">
                            <div class="form-floating form-floating-outline">
                                <input type="email" class="form-control" id="modalEmail" name="email" value="{{ $user->email ?? '' }}" placeholder="Enter your email" required />
                                <label for="modalEmail">Email</label>
                            </div>
                        </div>
                        <div class="col-md-6 form-control-validation">
                            <div class="form-password-toggle">
                                <div class="input-group input-group-merge">
                                    <div class="form-floating form-floating-outline">
                                        <input type="password" id="modalPassword" class="form-control" name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                        <label for="modalPassword">New Password (leave blank to keep current)</label>
                                    </div>
                                    <span class="input-group-text cursor-pointer"><i class="icon-base ri ri-eye-off-line icon-20px"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 form-control-validation">
                            <div class="form-password-toggle">
                                <div class="input-group input-group-merge">
                                    <div class="form-floating form-floating-outline">
                                        <input type="password" id="modalPasswordConfirmation" class="form-control" name="password_confirmation" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                        <label for="modalPasswordConfirmation">Confirm New Password</label>
                                    </div>
                                    <span class="input-group-text cursor-pointer"><i class="icon-base ri ri-eye-off-line icon-20px"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Project Modal (Global) -->
<div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createProjectForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col mb-3">
                            <label for="create_project_name" class="form-label">Project Name <span class="text-danger">*</span></label>
                            <input type="text" id="create_project_name" name="name" class="form-control" placeholder="Enter project name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="create_project_description" class="form-label">Description</label>
                            <textarea id="create_project_description" name="description" class="form-control" rows="4" placeholder="Enter project description"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="create_project_objetivo" class="form-label">Objective</label>
                            <textarea id="create_project_objetivo" name="objetivo" class="form-control" rows="4" placeholder="Enter project objective"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="create_project_research_time_interval" class="form-label">Research Time Interval</label>
                            <input type="text" id="create_project_research_time_interval" name="research_time_interval" class="form-control" placeholder="e.g., 2020-2024">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="create_project_area" class="form-label">Area</label>
                            <input type="text" id="create_project_area" name="area" class="form-control" placeholder="Enter research area">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="create_project_type_of_publication" class="form-label">Type of Publication</label>
                            <input type="text" id="create_project_type_of_publication" name="type_of_publication" class="form-control" placeholder="e.g., Journal Article, Conference Paper">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('profile-modal-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileModalForm = document.getElementById('profileModalForm');
    const modalAvatarInput = document.getElementById('modalAvatar');
    const modalUploadedAvatar = document.getElementById('modalUploadedAvatar');
    const modalRemoveAvatarBtn = document.getElementById('modalRemoveAvatar');
    
    // Preview avatar on file selection
    if (modalAvatarInput) {
        modalAvatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    modalUploadedAvatar.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Remove avatar
    if (modalRemoveAvatarBtn) {
        modalRemoveAvatarBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to remove your avatar?')) {
                modalAvatarInput.value = '';
                @php
                    $user = Auth::user();
                    $userInitials = $user ? strtoupper(substr($user->name, 0, 2)) : 'U';
                @endphp
                modalUploadedAvatar.src = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23ddd\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' font-size=\'40\' fill=\'%23999\'%3E{{ $userInitials }}%3C/text%3E%3C/svg%3E';
            }
        });
    }
    
    if (profileModalForm) {
        profileModalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = profileModalForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            const formDataObj = new FormData();
            
            // Add name and email
            formDataObj.append('name', document.getElementById('modalName').value);
            formDataObj.append('email', document.getElementById('modalEmail').value);
            
            // Add avatar if selected
            if (modalAvatarInput && modalAvatarInput.files.length > 0) {
                formDataObj.append('avatar', modalAvatarInput.files[0]);
            }
            
            // Only add password fields if BOTH password fields are filled
            const password = document.getElementById('modalPassword').value.trim();
            const passwordConfirmation = document.getElementById('modalPasswordConfirmation').value.trim();
            
            // Only send password if both fields are filled and match
            if (password && password !== '' && passwordConfirmation && passwordConfirmation !== '') {
                if (password === passwordConfirmation) {
                    formDataObj.append('password', password);
                    formDataObj.append('password_confirmation', passwordConfirmation);
                } else {
                    showToast('Password and confirmation do not match!', 'error');
                    return;
                }
            }
            
            // Add CSRF token
            formDataObj.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch('{{ route("profile.update") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formDataObj
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Network response was not ok');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Profile update response:', data);
                if (data.success) {
                    // Show success toast
                    showToast('Profile updated successfully!', 'success');
                    
                    // Update avatar if changed
                    if (data.user && data.user.avatar) {
                        console.log('Updating avatar to:', data.user.avatar);
                        modalUploadedAvatar.src = data.user.avatar;
                        // Update navbar avatar
                        const navbarAvatar = document.getElementById('navbar-avatar-small');
                        if (navbarAvatar) {
                            navbarAvatar.src = data.user.avatar;
                        }
                        const navbarAvatarLarge = document.getElementById('navbar-avatar');
                        if (navbarAvatarLarge) {
                            navbarAvatarLarge.src = data.user.avatar;
                        }
                    }
                    // Clear password fields
                    document.getElementById('modalPassword').value = '';
                    document.getElementById('modalPasswordConfirmation').value = '';
                    // Clear file input
                    modalAvatarInput.value = '';
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                    if (modal) {
                        modal.hide();
                    }
                    // Reload page to update navbar and ensure all avatars are updated
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Error updating profile: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating profile: ' + error.message, 'error');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }
});
</script>
@endpush

@push('toast-script')
<script>
// Toast notification function - must be available globally
window.showToast = function(message, type = 'success') {
    const toastElement = document.getElementById('toastNotification');
    const toastIcon = document.getElementById('toastIcon');
    const toastTitle = document.getElementById('toastTitle');
    const toastMessage = document.getElementById('toastMessage');
    
    if (!toastElement) {
        console.error('Toast element not found!');
        alert(message); // Fallback to alert if toast not available
        return;
    }
    
    if (!toastIcon || !toastTitle || !toastMessage) {
        console.error('Toast elements not found!');
        alert(message); // Fallback to alert if toast elements not available
        return;
    }
    
    // Set icon and title based on type
    const types = {
        success: { icon: 'ri-checkbox-circle-fill', color: 'text-success', title: 'Success' },
        error: { icon: 'ri-error-warning-fill', color: 'text-danger', title: 'Error' },
        warning: { icon: 'ri-alert-fill', color: 'text-warning', title: 'Warning' },
        info: { icon: 'ri-information-line', color: 'text-info', title: 'Info' }
    };
    
    const config = types[type] || types.success;
    
    // Remove all icon classes and add the new one
    toastIcon.className = 'icon-base ' + config.icon + ' icon-sm me-2 ' + config.color;
    toastTitle.textContent = config.title;
    toastMessage.textContent = message;
    
    // Show toast
    const toast = new bootstrap.Toast(toastElement, {
        delay: 3000
    });
    toast.show();
    
    console.log('Toast shown:', message, type);
};
</script>
@endpush

@push('create-project-script')
<script>
(function() {
    'use strict';
    
    // Function to add project to menu dynamically
    function addProjectToMenu(project) {
        // Find the Projects menu item
        const menuItems = document.querySelectorAll('#layout-menu .menu-item');
        let projectsMenuItem = null;
        let projectsMenuLink = null;
        let projectsSubmenu = null;
        
        for (let item of menuItems) {
            const link = item.querySelector('.menu-link');
            if (link && link.textContent.trim().includes('Projects')) {
                projectsMenuItem = item;
                projectsMenuLink = link;
                // Find or create submenu
                projectsSubmenu = item.querySelector('.menu-sub');
                break;
            }
        }
        
        if (!projectsMenuItem) {
            console.error('Projects menu item not found');
            return;
        }
        
        // Create submenu if it doesn't exist
        if (!projectsSubmenu) {
            projectsSubmenu = document.createElement('ul');
            projectsSubmenu.className = 'menu-sub';
            projectsMenuItem.appendChild(projectsSubmenu);
        }
        
        // Add menu-toggle class to the link if it doesn't have it and submenu has items
        if (!projectsMenuLink.classList.contains('menu-toggle')) {
            projectsMenuLink.classList.add('menu-toggle');
        }
        
        // Create new menu item for the project
        const newMenuItem = document.createElement('li');
        newMenuItem.className = 'menu-item';
        
        const newMenuLink = document.createElement('a');
        newMenuLink.href = '/projects/' + project.id;
        newMenuLink.className = 'menu-link';
        
        const icon = document.createElement('i');
        icon.className = 'menu-icon icon-base ri ri-file-line';
        
        const textDiv = document.createElement('div');
        textDiv.textContent = project.name;
        
        newMenuLink.appendChild(icon);
        newMenuLink.appendChild(textDiv);
        newMenuItem.appendChild(newMenuLink);
        
        // Insert in correct position based on ID (ascending order)
        const existingItems = Array.from(projectsSubmenu.children);
        let insertBefore = null;
        
        for (let item of existingItems) {
            const link = item.querySelector('.menu-link');
            if (link) {
                const href = link.getAttribute('href');
                const match = href.match(/\/projects\/(\d+)/);
                if (match) {
                    const existingId = parseInt(match[1]);
                    if (existingId > project.id) {
                        insertBefore = item;
                        break;
                    }
                }
            }
        }
        
        if (insertBefore) {
            projectsSubmenu.insertBefore(newMenuItem, insertBefore);
        } else {
            projectsSubmenu.appendChild(newMenuItem);
        }
        
        // Re-initialize menu if needed
        if (window.Helpers && window.Helpers.mainMenu) {
            // Menu should auto-detect new items, but we can force a refresh
            console.log('Project added to menu:', project.name);
        }
    }
    
    // Function to remove project from menu
    function removeProjectFromMenu(projectId) {
        const menuLinks = document.querySelectorAll('#layout-menu .menu-sub .menu-link');
        for (let link of menuLinks) {
            if (link.getAttribute('href') === '/projects/' + projectId) {
                const menuItem = link.closest('.menu-item');
                if (menuItem) {
                    menuItem.remove();
                    
                    // Check if submenu is now empty, remove menu-toggle class
                    const submenu = link.closest('.menu-sub');
                    if (submenu && submenu.children.length === 0) {
                        const projectsMenuLink = submenu.closest('.menu-item').querySelector('.menu-link');
                        if (projectsMenuLink) {
                            projectsMenuLink.classList.remove('menu-toggle');
                        }
                    }
                }
                break;
            }
        }
    }
    
    // Function to update project name in menu
    function updateProjectInMenu(projectId, newName) {
        const menuLinks = document.querySelectorAll('#layout-menu .menu-sub .menu-link');
        for (let link of menuLinks) {
            if (link.getAttribute('href') === '/projects/' + projectId) {
                const textDiv = link.querySelector('div');
                if (textDiv) {
                    textDiv.textContent = newName;
                }
                break;
            }
        }
    }
    
    // Make functions globally available
    window.addProjectToMenu = addProjectToMenu;
    window.removeProjectFromMenu = removeProjectFromMenu;
    window.updateProjectInMenu = updateProjectInMenu;
    
    // Handle create project form
    document.addEventListener('DOMContentLoaded', function() {
        const createForm = document.getElementById('createProjectForm');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    name: document.getElementById('create_project_name').value,
                    description: document.getElementById('create_project_description').value,
                    objetivo: document.getElementById('create_project_objetivo') ? document.getElementById('create_project_objetivo').value : '',
                    research_time_interval: document.getElementById('create_project_research_time_interval') ? document.getElementById('create_project_research_time_interval').value : '',
                    area: document.getElementById('create_project_area') ? document.getElementById('create_project_area').value : '',
                    type_of_publication: document.getElementById('create_project_type_of_publication') ? document.getElementById('create_project_type_of_publication').value : '',
                    _token: document.querySelector('meta[name="csrf-token"]').content
                };

                // Show loading state
                const submitButton = createForm.querySelector('button[type="submit"]');
                const originalText = submitButton ? submitButton.textContent : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Creating...';
                }

                fetch('/projects', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': formData._token,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: formData.name,
                        description: formData.description,
                        objetivo: formData.objetivo,
                        research_time_interval: formData.research_time_interval,
                        area: formData.area,
                        type_of_publication: formData.type_of_publication
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.message || 'Network response was not ok');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.project && data.project.id) {
                    // Add project to menu dynamically (only if user is authenticated)
                    if (data.project) {
                        addProjectToMenu(data.project);
                    }
                        
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createProjectModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Reset form
                        createForm.reset();
                        
                        // Redirect to project page
                        window.location.href = '/projects/' + data.project.id;
                    } else {
                        const errorMsg = data.message || 'Unknown error';
                        alert('Error creating project: ' + errorMsg);
                    }
                })
                .catch(error => {
                    console.error('Error creating project:', error);
                    alert('Error creating project: ' + error.message);
                })
                .finally(() => {
                    // Restore button state
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                });
            });
        }
    });
})();
</script>
@endpush

@endsection
