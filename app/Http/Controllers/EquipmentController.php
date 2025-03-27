<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;

class EquipmentController extends Controller
{
    public function index()
    {
        return Equipment::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'service_provider_id' => 'nullable|integer|exists:service_providers,id',
            'client_id' => 'nullable|integer|exists:clients,id',
            'date_of_manufacturing' => 'required|date',
            'expiry_date' => 'required|date|after:date_of_manufacturing',
        ]);

        // Generate a UUID for the serial_number
        $serial_number = (string) Str::uuid();

        // Merge the serial_number into the request data
        $equipmentData = array_merge($request->all(), ['serial_number' => $serial_number]);

        // Create the equipment with the merged data
        $equipment = Equipment::create($equipmentData);

        return response()->json(['message' => 'Equipment created successfully', 'equipment' => $equipment], 201);
    }


    public function massUpload(Request $request)
    {
        $request->validate([
            'equipments' => 'required|array',
            'equipments.*.name' => 'required|string|max:255',
            'equipments.*.description' => 'required|string',
            'equipments.*.service_provider_id' => 'nullable|integer|exists:service_providers,id',
            'equipments.*.client_id' => 'nullable|integer|exists:clients,id',
            'equipments.*.date_of_manufacturing' => 'required|date',
            'equipments.*.expiry_date' => 'required|date|after:date_of_manufacturing',
        ]);

        $equipments = $request->input('equipments');
        $equipmentData = [];

        foreach ($equipments as $equipment) {
            $equipment['serial_number'] = (string) Str::uuid();
            $equipmentData[] = $equipment;
        }

        Equipment::insert($equipmentData);

        return response()->json(['message' => 'Equipments uploaded successfully'], 201);
    }

    public function show($id)
    {
        $equipment = Equipment::with('serviceProvider', 'client', 'serial_number')->findOrFail($id);
        return response()->json($equipment);
    }

    public function getEquipmentByServiceProvider($service_provider_id)
    {
        $equipment = Equipment::where('service_provider_id', $service_provider_id)->with('serviceProvider', 'client', 'serial_number')->get();
        return response()->json($equipment);
    }

    public function getEquipmentByClient($client_id)
    {
        $equipment = Equipment::where('client_id', $client_id)->with('serviceProvider', 'client', 'serial_number')->get();
        return response()->json($equipment);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'service_provider_id' => 'sometimes|required|integer|exists:service_providers,id',
            'client_id' => 'sometimes|nullable|integer|exists:clients,id',
            'date_of_manufacturing' => 'sometimes|required|date',
            'expiry_date' => 'sometimes|required|date|after:date_of_manufacturing',
        ]);

        $equipment = Equipment::findOrFail($id);
        $equipment->update($request->except('qr_code', 'serial_number'));

        return response()->json(['message' => 'Equipment updated successfully', 'equipment' => $equipment]);
    }

    public function destroy($id)
    {
        $equipment = Equipment::findOrFail($id);
        $equipment->delete();

        return response()->json(['message' => 'Equipment deleted successfully']);
    }

    public function checkExpiredEquipment()
    {
        $expiredEquipment = Equipment::where('expiry_date', '<', Carbon::now())->get();

        if ($expiredEquipment->isEmpty()) {
            return response()->json(['message' => 'No expired equipment found']);
        }

        return response()->json(['message' => 'Expired equipment found', 'data' => $expiredEquipment]);
    }

    public function checkExpiringSoonEquipment()
    {
        $expiringSoonEquipment = Equipment::whereBetween('expiry_date', [Carbon::now(), Carbon::now()->addWeek()])->get();

        if ($expiringSoonEquipment->isEmpty()) {
            return response()->json(['message' => 'No equipment expiring within a week found']);
        }

        return response()->json(['message' => 'Equipment expiring within a week found', 'data' => $expiringSoonEquipment]);
    }

  
}
