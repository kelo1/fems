<?php

namespace App\Http\Controllers;

use App\Models\LicenseType;
use App\Models\FEMSAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LicenseTypeController extends Controller
{
    /**
     * Display a listing of the license types.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return LicenseType::all();
    }

    /**
     * Store a newly created license type in storage.
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
            'name' => 'required|string|unique:license_types,name|max:255',
            'description' => 'required|string|max:1000',
        ]);

        // Create a new license type
        $licenseType = LicenseType::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'License type created successfully',
            'name' => $licenseType->name,
            'description' => $licenseType->description,
        ], 201);
    }

    /**
     * Remove the specified license type from storage.
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

        // Find the license type by ID
        $licenseType = LicenseType::find($id);

        if (!$licenseType) {
            return response()->json([
                'message' => 'License type not found',
            ], 404);
        }

        // Delete the license type
        $licenseType->delete();

        return response()->json([
            'message' => 'License type deleted successfully',
        ], 200);
    }
}
