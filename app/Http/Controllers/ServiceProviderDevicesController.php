<?php

namespace App\Http\Controllers;

use App\Models\ServiceProviderDevice;
use Illuminate\Http\Request;

class ServiceProviderDevicesController extends Controller
{
    public function index()
    {
        // Retrieve all devices with their associated service provider details
        $devices = ServiceProviderDevice::with('serviceProvider')->get();

        return response()->json(['data' => $devices], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'service_provider_id' => 'required|exists:service_providers,id',
            'description' => 'nullable|string|max:1000', // Optional description field
        ]);

        // Generate a unique device serial number
        $deviceSerialNumber = $this->generateDeviceSerialNumber();

        // Create the device
        $device = ServiceProviderDevice::create([
            'device_serial_number' => $deviceSerialNumber,
            'service_provider_id' => $request->service_provider_id,
            'description' => $request->description, // Save the optional description
        ]);

        return response()->json([
            'message' => 'Device created successfully',
            'data' => $device,
        ], 201);
    }

    public function destroy($id)
    {
        $device = ServiceProviderDevice::findOrFail($id);
        $device->delete();

        return response()->json(['message' => 'Device deleted successfully'], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            // Find the device
            $device = ServiceProviderDevice::findOrFail($id);

            // Validate the request
            $request->validate([
                'service_provider_id' => 'required|exists:service_providers,id',
                'description' => 'nullable|string|max:1000', // Optional description field
            ]);

            // Update the device
            $device->update([
                'service_provider_id' => $request->service_provider_id,
                'description' => $request->description, // Update the optional description
            ]);

            return response()->json([
                'message' => 'Device updated successfully',
                'data' => $device,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in update method', [
                'errors' => $e->errors(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Device not found in update method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Device not found', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            \Log::error('Error updating device', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to update device', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            // Retrieve the device by ID with its associated service provider
            $device = ServiceProviderDevice::with('serviceProvider')->findOrFail($id);

            return response()->json([
                'message' => 'Device retrieved successfully',
                'data' => $device,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Log model not found errors
            \Log::error('Device not found in show method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Device not found', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            // Log general errors
            \Log::error('Error retrieving device', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to retrieve device', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate a unique device serial number.
     *
     * @return string
     */
    private function generateDeviceSerialNumber()
    {
        do {
            // Generate a random alphanumeric string (e.g., "DEV-ABC123XYZ")
            $serialNumber = 'DEV-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 13));
        } while (ServiceProviderDevice::where('device_serial_number', $serialNumber)->exists());

        return $serialNumber;
    }
}
