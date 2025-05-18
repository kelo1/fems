<?php

namespace App\Http\Controllers;

use App\Models\EquipmentActivity;
use App\Models\Equipment;
use App\Models\Client;
use App\Models\ServiceProviderDevice;
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
        try {
            // Check if the user is authenticated
            $user = Auth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Validate the request
            $request->validate([
                'device_serial_number' => 'required|string|exists:service_provider_devices,device_serial_number',
                'equipment_id' => 'required|exists:equipment,id',
                'activity' => 'nullable|string',
                'next_maintenance_date' => 'nullable|date',
                'service_provider_id' => 'nullable|exists:service_providers,id',
                'client_id' => 'nullable|exists:clients,id',
            ]);

            // Check if the device_serial_number exists in ServiceProviderDevices and it belongs to the service provider
            $deviceExists = ServiceProviderDevice::where('device_serial_number', $request->device_serial_number)
                ->where('service_provider_id', $user->id)
                ->exists();

            if (!$deviceExists) {
                return response()->json(['message' => 'The device could not be validated, please contact FEMS Admin'], 403);
            }

            // Check if the equipment is active
            $equipment = Equipment::find($request->equipment_id);

            if ($equipment && $equipment->isActive == 0) {
                return response()->json(['message' => 'The equipment is not verified, you cannot create an activity. Please contact FEMS Admin'], 403);
            }

            \DB::beginTransaction();

            // Create the equipment activity
            $activity = EquipmentActivity::create([
                'activity' => $request->activity,
                'next_maintenance_date' => $request->next_maintenance_date,
                'service_provider_id' => $user->id,
                'device_serial_number' => $request->device_serial_number,
                'client_id' => $request->client_id,
                'equipment_id' => $request->equipment_id,
                'created_by' => $user->id,
                'created_by_type' => get_class($user),
            ]);

            // Update the parent Equipment table if next_maintenance_date is provided
            if ($request->next_maintenance_date !== null) {
                $equipment = Equipment::findOrFail($request->equipment_id);
                $equipment->update([
                    'expiry_date' => $request->next_maintenance_date,
                ]);
            }

           

            $client = Client::findOrFail($request->client_id);

            // Insert the old record into the client_history table
            \DB::table('client_history')->insert([
                'client_id' => $request->client_id,
                'old_service_provider_id' => $client->created_by,
                'new_service_provider_id' =>  $user->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]);

            // Update the client record
            $client->created_by = $user->id;
            $client->save();



            // Deactivate the current service provider record
            $deactivated = \DB::table('equipment_service_providers')
            ->where('equipment_id', $request->equipment_id)
            ->where('status_service_provider', 1)
            ->update(['status_service_provider' => 0]);

            if ($deactivated === 0) {
                \Log::warning("No active service provider found for equipment ID {$equipment_id}");
            }

            // Insert a new record with the updated service_provider_id
            \DB::table('equipment_service_providers')->insert([
                'equipment_id' => $request->equipment_id,
                'serial_number' => $equipment->serial_number,
                'service_provider_id' =>  $user->id,
                'status_service_provider' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);


            \DB::commit();

            return response()->json(['message' => 'Equipment activity created successfully'], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rollback the transaction and log validation errors
            \DB::rollBack();
            \Log::error('Validation error in store method', [
                'errors' => $e->errors(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Rollback the transaction and log model not found errors
            \DB::rollBack();
            \Log::error('Model not found in store method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Resource not found', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            // Rollback the transaction and log general errors
            \DB::rollBack();
            \Log::error('Error creating equipment activity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to create equipment activity', 'error' => $e->getMessage()], 500);
        }
    }
}
