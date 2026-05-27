<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use App\Models\EmailLog;
use App\Notifications\CustomerResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class CustomerAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('frontend.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('customer.dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegistrationForm()
    {
        if (!Setting::get('registration_open', true)) {
            return redirect()->route('customer.login')->with('error', 'Pendaftaran anggota baru sedang ditutup.');
        }

        $categories = \App\Models\CustomerCategory::where('is_active', true)->get();
        $customFields = json_decode(Setting::get('registration_custom_fields', '[]'), true);
        $defaultCategoryId = Setting::get('default_customer_category_id');
        
        return view('frontend.auth.register', compact('categories', 'customFields', 'defaultCategoryId'));
    }

    public function register(Request $request)
    {
        if (!Setting::get('registration_open', true)) {
            return back()->with('error', 'Pendaftaran anggota baru sedang ditutup.');
        }

        // Base Validation
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'customer_category_id' => 'required|exists:customer_categories,id',
        ];

        // Custom Fields Validation
        $customFields = json_decode(Setting::get('registration_custom_fields', '[]'), true);
        $customData = [];
        $selectedCategory = $request->customer_category_id;

        if (!empty($customFields)) {
            foreach ($customFields as $field) {
                // Check visibility
                $visibleCategories = $field['visible_for_categories'] ?? [];
                if (!empty($visibleCategories) && !in_array($selectedCategory, $visibleCategories)) {
                    continue; // Skip validation if not visible
                }

                $fieldName = 'custom_' . $field['name']; // Prefix to avoid conflict
                $rules[$fieldName] = $field['required'] ? 'required' : 'nullable';
                
                if ($field['type'] === 'number') {
                    $rules[$fieldName] .= '|numeric';
                }
                if ($field['type'] === 'email') {
                    $rules[$fieldName] .= '|email';
                }
                
                // Collect data if present
                if ($request->has($fieldName)) {
                    $customData[$field['name']] = $request->input($fieldName);
                }
            }
        }

        $request->validate($rules);

        $autoVerify = Setting::get('auto_verify_registration', true);

        $customer = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'customer_category_id' => $request->customer_category_id,
            'email_verified_at' => $autoVerify ? now() : null,
            'is_verified' => $autoVerify,
            'verified_at' => $autoVerify ? now() : null,
            'custom_fields' => !empty($customData) ? $customData : null,
        ]);

        Auth::guard('customer')->login($customer);

        return redirect(route('customer.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Show forgot password form
     */
    public function showForgotPasswordForm()
    {
        return view('frontend.auth.forgot-password');
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $genericStatus = 'Jika email terdaftar, link reset password telah dikirim.';

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Same response as the success case to prevent email enumeration.
            return back()->with('status', $genericStatus);
        }

        // Generate token
        $token = Str::random(64);

        // Store token in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Send notification
        try {
            $user->notify(new CustomerResetPassword($token));
        } catch (\Exception $e) {
            EmailLog::logFailed(
                $request->email,
                'Reset Password',
                $e->getMessage(),
                CustomerResetPassword::class
            );
        }

        return back()->with('status', $genericStatus);
    }

    /**
     * Show reset password form
     */
    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('frontend.auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Find the reset record
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return back()->withErrors([
                'email' => 'Invalid password reset request.',
            ]);
        }

        // Check if token matches
        if (!Hash::check($request->token, $record->token)) {
            return back()->withErrors([
                'email' => 'Invalid or expired reset token.',
            ]);
        }

        // Check if token is expired (60 minutes).
        // Carbon 3 returns a signed value; abs() keeps the comparison robust regardless of clock skew.
        if (abs(now()->diffInMinutes($record->created_at)) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors([
                'email' => 'Reset token has expired. Please request a new one.',
            ]);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors([
                'email' => 'User not found.',
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('customer.login')->with('status', 'Password has been reset successfully! Please login with your new password.');
    }
}