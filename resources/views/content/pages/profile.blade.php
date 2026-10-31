@extends('layouts/contentNavbarLayout')

@section('title', 'My Profile')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <form id="profileForm" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="d-flex align-items-start align-items-sm-center gap-4">
                                <div class="avatar-wrapper">
                                    @php
                                        $userInitials = $user ? strtoupper(substr($user->name, 0, 2)) : 'U';
                                        $avatarUrl = $user && $user->avatar ? route('storage.s3', ['path' => $user->avatar]) : asset('assets/json/img/avatars/1.png');
                                    @endphp
                                    <img src="{{ $avatarUrl }}" 
                                         alt="user-avatar" 
                                         class="d-block rounded" 
                                         id="uploadedAvatar" 
                                         style="width: 100px; height: 100px; object-fit: cover; cursor: pointer;"
                                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23ddd\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' font-size=\'40\' fill=\'%23999\'%3E{{ $userInitials }}%3C/text%3E%3C/svg%3E';" />
                                    <div class="button-wrapper mt-3">
                                        <label for="avatar" class="btn btn-sm btn-primary me-3 mb-4" tabindex="0">
                                            <span class="d-none d-sm-block">Upload new photo</span>
                                            <i class="icon-base ri ri-upload-2-line d-block d-sm-none"></i>
                                            <input type="file" id="avatar" name="avatar" class="account-file-input" hidden accept="image/png, image/jpeg, image/jpg, image/gif" />
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-danger account-image-reset mb-4" id="removeAvatar">
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
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ $user->name }}" placeholder="Enter your name" required />
                                <label for="name">Name</label>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6 form-control-validation">
                            <div class="form-floating form-floating-outline">
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ $user->email }}" placeholder="Enter your email" required />
                                <label for="email">Email</label>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6 form-control-validation">
                            <div class="form-password-toggle">
                                <div class="input-group input-group-merge">
                                    <div class="form-floating form-floating-outline">
                                        <input type="password" id="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                        <label for="password">New Password (leave blank to keep current)</label>
                                    </div>
                                    <span class="input-group-text cursor-pointer"><i class="icon-base ri ri-eye-off-line icon-20px"></i></span>
                                </div>
                                @error('password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6 form-control-validation">
                            <div class="form-password-toggle">
                                <div class="input-group input-group-merge">
                                    <div class="form-floating form-floating-outline">
                                        <input type="password" id="password_confirmation" class="form-control" name="password_confirmation" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                        <label for="password_confirmation">Confirm New Password</label>
                                    </div>
                                    <span class="input-group-text cursor-pointer"><i class="icon-base ri ri-eye-off-line icon-20px"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    const avatarInput = document.getElementById('avatar');
    const uploadedAvatar = document.getElementById('uploadedAvatar');
    const removeAvatarBtn = document.getElementById('removeAvatar');
    
    // Preview avatar on file selection
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    uploadedAvatar.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Remove avatar
    if (removeAvatarBtn) {
        removeAvatarBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to remove your avatar?')) {
                avatarInput.value = '';
                uploadedAvatar.src = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23ddd\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' font-size=\'40\' fill=\'%23999\'%3E{{ strtoupper(substr($user->name, 0, 2)) }}%3C/text%3E%3C/svg%3E';
            }
        });
    }
    
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = profileForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            const formDataObj = new FormData();
            
            // Add name and email
            formDataObj.append('name', document.getElementById('name').value);
            formDataObj.append('email', document.getElementById('email').value);
            
            // Add avatar if selected
            if (avatarInput && avatarInput.files.length > 0) {
                formDataObj.append('avatar', avatarInput.files[0]);
            }
            
            // Only add password fields if BOTH password fields are filled
            const password = document.getElementById('password').value.trim();
            const passwordConfirmation = document.getElementById('password_confirmation').value.trim();
            
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
                    
                    // Update displayed name/email if needed
                    if (data.user) {
                        document.getElementById('name').value = data.user.name;
                        document.getElementById('email').value = data.user.email;
                        // Update avatar if changed
                        if (data.user.avatar) {
                            console.log('Updating avatar to:', data.user.avatar);
                            uploadedAvatar.src = data.user.avatar;
                            uploadedAvatar.onerror = function() {
                                console.error('Failed to load avatar image:', data.user.avatar);
                            };
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
                    }
                    // Clear password fields
                    document.getElementById('password').value = '';
                    document.getElementById('password_confirmation').value = '';
                    // Clear file input
                    avatarInput.value = '';
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
@endsection
