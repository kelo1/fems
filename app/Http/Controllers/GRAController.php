<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GRAController extends Controller
{
   
    //show 
    public function show($id)
    {
        try {
            // Find the GRA user by ID
            $gra = GRA::findOrFail($id);

            // Return the user details as a JSON response
            return response()->json([
                'message' => 'User details retrieved successfully',
                'user' => $gra
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
