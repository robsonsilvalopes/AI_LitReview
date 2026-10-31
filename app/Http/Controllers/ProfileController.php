<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        return view('content.pages.profile', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        // Get password values and check if they're actually provided
        $password = $request->input('password');
        $passwordConfirmation = $request->input('password_confirmation');
        
        // Remove password fields from request if they're empty or null
        if (empty($password) || trim($password) === '') {
            $request->merge(['password' => null]);
            $request->merge(['password_confirmation' => null]);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // Only validate password if it's actually provided and not empty
        if ($request->has('password') && $request->input('password') !== null && !empty(trim($request->input('password')))) {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $validated = $request->validate($rules);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Only update password if it was provided and validated
        if (isset($validated['password']) && !empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            try {
                \Log::info('Avatar upload started', [
                    'file_name' => $request->file('avatar')->getClientOriginalName(),
                    'file_size' => $request->file('avatar')->getSize(),
                    'mime_type' => $request->file('avatar')->getMimeType()
                ]);
                
                // Delete old avatar if exists
                if ($user->avatar) {
                    try {
                        Storage::disk('s3')->delete($user->avatar);
                        \Log::info('Old avatar deleted', ['path' => $user->avatar]);
                    } catch (\Exception $e) {
                        \Log::warning('Could not delete old avatar: ' . $e->getMessage());
                    }
                }

                // Upload new avatar
                $file = $request->file('avatar');
                
                // Generate unique filename
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = 'avatars/' . $filename;
                
                // Upload using putFileAs for better control
                $uploadedPath = Storage::disk('s3')->putFileAs('avatars', $file, $filename, 'public');
                
                if (empty($uploadedPath)) {
                    // Try alternative method
                    $content = file_get_contents($file->getRealPath());
                    $uploaded = Storage::disk('s3')->put($path, $content, 'public');
                    
                    if (!$uploaded) {
                        throw new \Exception('Upload failed - file was not saved to S3');
                    }
                    $uploadedPath = $path;
                }
                
                $path = $uploadedPath;
                
                $user->avatar = $path;
                
                \Log::info('Avatar uploaded successfully', [
                    'path' => $path,
                    'user_id' => $user->id
                ]);
            } catch (\Exception $e) {
                \Log::error('Avatar upload failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload avatar: ' . $e->getMessage()
                ], 500);
            }
        }

        $user->save();
        $user->refresh();

        // Get avatar URL - use Laravel route for serving S3 files
        $avatarUrl = null;
        if ($user->avatar) {
            $avatarUrl = route('storage.s3', ['path' => $user->avatar]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $avatarUrl,
            ]
        ]);
    }
}
