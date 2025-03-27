<?php

namespace App\Http\Controllers;

use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ServiceProviderController extends Controller
{


    // Show Service Provider details by ID
    public function show($id)
    {
        try {
            // Find the Service Provider user by ID
            $serviceProvider = ServiceProvider::findOrFail($id);

            // Return the user details as a JSON response
            return response()->json([
                'message' => 'User details retrieved successfully',
                'user' => $serviceProvider
            ], 200);
        } catch (\Exception $e) {
            // Handle the case where the user is not found
            return response()->json([
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
