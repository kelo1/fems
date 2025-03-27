<?php

namespace App\Http\Controllers;

use App\Models\FEMSAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class FEMSAdminController extends Controller
{
    public function index()
    {
        $femsAdmins = FEMSAdmin::all();
        return response()->json($femsAdmins);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:fems_admins',
            'password' => 'required|string|min:8',
        ]);

        $femsAdmin = FEMSAdmin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'FEMS Admin created successfully', 'femsAdmin' => $femsAdmin], 201);
    }

    public function show($id)
    {
        $femsAdmin = FEMSAdmin::findOrFail($id);
        return response()->json($femsAdmin);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:fems_admins,email,' . $id,
            'password' => 'sometimes|required|string|min:8',
        ]);

        $femsAdmin = FEMSAdmin::findOrFail($id);
        $femsAdmin->update([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password ? Hash::make($request->password) : $femsAdmin->password,
        ]);

        return response()->json(['message' => 'FEMS Admin updated successfully', 'femsAdmin' => $femsAdmin]);
    }

    public function destroy($id)
    {
        $femsAdmin = FEMSAdmin::findOrFail($id);
        $femsAdmin->delete();
        return response()->json(['message' => 'FEMS Admin deleted successfully']);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $femsAdmin = FEMSAdmin::where('email', $credentials['email'])->first();

        if ($femsAdmin && Hash::check($credentials['password'], $femsAdmin->password)) {
            $token = $femsAdmin->createToken('auth_token')->plainTextToken;
            return response()->json(['message' => 'Login successful', 'femsAdmin' => $femsAdmin, 'token' => $token]);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function logout()
    {
        Auth::guard('fems_admin')->user()->tokens()->delete();
        return response()->json(['message' => 'Logout successful']);
    }
}
