<?php

namespace App\Http\Controllers;

use App\Models\FireServiceAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class FireServiceAgentController extends Controller
{
   
    // Show Fire Service Agent details by ID
    public function show($id)
    {
        try {
            // Find the Fire Service Agent user by ID
            $fireServiceAgent = FireServiceAgent::findOrFail($id);

            // Return the user details as a JSON response
            return response()->json([
                'message' => 'User details retrieved successfully',
                'user' => $fireServiceAgent
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
