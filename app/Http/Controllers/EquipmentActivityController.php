<?php

namespace App\Http\Controllers;

use App\Models\EquipmentActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EquipmentActivityController extends Controller
{
    public function index()
    {
        // Retrieve all equipment activities with related client and service provider details
        $activities = EquipmentActivity::with(['client', 'serviceProvider', 'equipment'])->get();
        return response()->json(['activities' => $activities], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'activity' => 'required|string|max:255',
            'next_maintenance_date' => 'nullable|date',
            'service_provider_id' => 'nullable|integer|exists:service_providers,id',
            'client_id' => 'nullable|integer|exists:clients,id',
        ]);

        $activity = EquipmentActivity::create([
            'activity' => $request->activity,
            'next_maintenance_date' => $request->next_maintenance_date,
            'service_provider_id' => $request->service_provider_id,
            'client_id' => $request->client_id,
            'created_by' => $user->id,
            'created_by_type' => get_class($user),
        ]);

        return response()->json(['message' => 'Equipment activity created successfully', 'activity' => $activity], 201);
    }

    public function show($id)
    {
        $activity = EquipmentActivity::with(['client', 'serviceProvider'])->findOrFail($id);
        return response()->json(['activity' => $activity], 200);
    }

    public function update(Request $request, $id)
    {
        $activity = EquipmentActivity::findOrFail($id);

        $request->validate([
            'activity' => 'sometimes|string|max:255',
            'next_maintenance_date' => 'nullable|date',
            'service_provider_id' => 'nullable|integer|exists:service_providers,id',
            'client_id' => 'nullable|integer|exists:clients,id',
        ]);

        $activity->update($request->only([
            'activity',
            'next_maintenance_date',
            'service_provider_id',
            'client_id',
        ]));

        return response()->json(['message' => 'Equipment activity updated successfully', 'activity' => $activity], 200);
    }

    public function destroy($id)
    {
        $activity = EquipmentActivity::findOrFail($id);
        $activity->delete();

        return response()->json(['message' => 'Equipment activity deleted successfully'], 200);
    }
}
