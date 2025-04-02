<?php

namespace App\Http\Controllers;

use App\Models\UserType;
use App\Models\FEMSAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return UserType::all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
 
    /**
     * Store a newly created user type in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Ensure the authenticated user is a FEMSAdmin
        $admin = Auth::user();
        if (!$admin instanceof FEMSAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate the request
        $request->validate([
            'user_type' => 'required|string|unique:user_types,user_type|max:255',
        ]);

        // Create a new user type
        $userType = UserType::create([
            'user_type' => $request->user_type,
        ]);

        return response()->json([
            'message' => 'User type created successfully',
            'user_type' => $userType,
        ], 201);
    }


    /**
     * Remove the specified user type from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // Ensure the authenticated user is a FEMSAdmin
        $admin = Auth::user();
        if (!$admin instanceof FEMSAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Find the user type by ID
        $userType = UserType::find($id);

        if (!$userType) {
            return response()->json([
                'message' => 'User type not found',
            ], 404);
        }

        // Delete the user type
        $userType->delete();

        return response()->json([
            'message' => 'User type deleted successfully',
        ], 200);
    }
}
