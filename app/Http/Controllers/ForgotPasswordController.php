<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Notifications\ForgotPasswordNotification;

class ForgotPasswordController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }
     /**
     * Send Forgot password Link via email
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function submitForgetPasswordForm(Request $request)
    {
        // Validate the email field
        $fields = $request->validate([
            'email' => 'required|email',
            'user_type' => 'required|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
        ]);

        $email = $fields['email'];
        $userType = strtoupper($fields['user_type']);

        // Map user types to their respective models
        $modelMap = [
            'SERVICE_PROVIDER' => \App\Models\ServiceProvider::class,
            'FSA_AGENT' => \App\Models\FireServiceAgent::class,
            'GRA_PERSONNEL' => \App\Models\GRA::class,
        ];

        $model = $modelMap[$userType] ?? null;

        if (!$model) {
            return response()->json(['message' => 'Invalid user type provided'], 400);
        }

        // Retrieve the user by email
        $user = $model::where('email', $email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Generate a unique token
        $token = Str::random(64);

        // Insert the token into the password_resets table
        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => Carbon::now(),
        ]);

        // Determine the user's name
        $userName = $user->name ?? 'User';

        // Send the password reset notification
        $user->notify(new ForgotPasswordNotification($user, $userName, $email, $token, $userType));

        // Return a success response
        return response()->json([
            'token' => $token,
            'message' => 'We have e-mailed your password reset link!',
        ], 201);
    }

    /**
     * Reset Password
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function submitResetPasswordForm(Request $request)
    {
        // Validate the input fields
        $fields = $request->validate([
            'user_type' => 'required|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
            'id' => 'required|integer',
            'token' => 'required|string',
            'password' => 'required|string|confirmed',
        ]);

        $userType = strtoupper($fields['user_type']);

        // Map user types to their respective models
        $modelMap = [
            'SERVICE_PROVIDER' => \App\Models\ServiceProvider::class,
            'FSA_AGENT' => \App\Models\FireServiceAgent::class,
            'GRA_PERSONNEL' => \App\Models\GRA::class,
        ];

        $model = $modelMap[$userType] ?? null;

        if (!$model) {
            return response()->json(['message' => 'Invalid user type provided'], 400);
        }

        // Check if the token exists in the password_resets table
        $updatePassword = DB::table('password_resets')
            ->where('token', $fields['token'])
            ->first();

        if (!$updatePassword) {
            return response()->json(['message' => 'Invalid token!'], 400);
        }

        // Retrieve the user by ID
        $user = $model::find($fields['id']);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Update the user's password
        $user->update([
            'password' => Hash::make($fields['password']),
            'updated_at' => Carbon::now(),
        ]);

        // Delete the token from the password_resets table
        DB::table('password_resets')->where(['email' => $updatePassword->email])->delete();

        // Return a success response
        return response()->json([
            'message' => 'Password changed successfully!',
        ], 200);
    }
}
