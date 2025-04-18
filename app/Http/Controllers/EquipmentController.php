<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentClient;
use App\Models\EquipmentServiceProvider;
use App\Models\ServiceProvider;
use App\Models\Client;
use App\Models\QRCode;
use App\Models\FEMSAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class EquipmentController extends Controller
{
    public function index()
    {
        try {
            // Authenticate the user as FEMSAdmin
            $user = Auth::user();
            if (!($user instanceof \App\Models\FEMSAdmin)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Retrieve all equipment with associated records
            $equipment = Equipment::with([
                'equipmentClients' => function ($query) {
                    $query->where('status_client', 1); // Only active clients
                },
                'equipmentServiceProviders' => function ($query) {
                    $query->where('status_service_provider', 1); // Only active service providers
                },
                'equipmentActivities' // Include all activities
            ])->get();

            return response()->json(['message' => 'Equipment retrieved successfully', 'data' => $equipment], 200);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error in index method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while retrieving equipment'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Authenticate the user
            $user = Auth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Validate the request
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'service_provider_id' => 'nullable|integer|exists:service_providers,id',
                'client_id' => 'nullable|integer|exists:clients,id',
                'date_of_manufacturing' => 'required|date',
                'expiry_date' => 'required|date|after:date_of_manufacturing',
            ]);

            // Log the user ID and user class
            \Log::info('Equipment creation initiated by user', [
                'user_id' => $user->id,
                'user_class' => get_class($user),
            ]);

            // Generate a UUID for the serial_number
            $uuid = (string) Str::uuid();
            $serial_number = substr(md5($uuid), 0, 15);

            // Create the equipment
            $equipment = Equipment::create(array_merge($request->all(), [
                'serial_number' => $serial_number, // Use the same serial_number
                'created_by' => $user->id,
                'created_by_type' => get_class($user),
            ]));

            // Create a record in EquipmentClient
            if ($request->client_id) {
                EquipmentClient::create([
                    'equipment_id' => $equipment->id,
                    'serial_number' => $equipment->serial_number, // Use the same serial_number from Equipment
                    'client_id' => $request->client_id,
                    'status_client' => 1,
                ]);
            }

            // Create a record in EquipmentServiceProvider
            if ($request->service_provider_id) {
                EquipmentServiceProvider::create([
                    'equipment_id' => $equipment->id,
                    'serial_number' => $equipment->serial_number, // Use the same serial_number from Equipment
                    'service_provider_id' => $request->service_provider_id,
                    'status_service_provider' => 1,
                ]);
            }

            return response()->json(['message' => 'Equipment created successfully', 'equipment' => $equipment], 201);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error in store method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while creating the equipment'], 500);
        }
    }

    public function massUpload(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'equipments' => 'required|array',
            'equipments.*.name' => 'required|string|max:255',
            'equipments.*.description' => 'required|string',
            'equipments.*.service_provider_id' => 'nullable|integer|exists:service_providers,id',
            'equipments.*.client_id' => 'nullable|integer|exists:clients,id',
            'equipments.*.date_of_manufacturing' => 'required|date',
            'equipments.*.expiry_date' => 'required|date|after:date_of_manufacturing',
        ]);

        try {
            // Authenticate the user
            $user = Auth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Log the user ID and user class
            \Log::info('Mass equipment upload initiated by user', [
                'user_id' => $user->id,
                'user_class' => get_class($user),
            ]);

            $equipments = $request->input('equipments');
            $equipmentData = [];
            $clientData = [];
            $serviceProviderData = [];

            foreach ($equipments as $equipment) {
                // Generate a UUID for the serial_number
                $uuid = (string) Str::uuid();
                $serial_number = substr(md5($uuid), 0, 15);

                // Prepare equipment data
                $equipmentData[] = array_merge($equipment, [
                    'serial_number' => $serial_number,
                    'created_by' => $user->id,
                    'created_by_type' => get_class($user),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Prepare client data if client_id is provided
                if (isset($equipment['client_id'])) {
                    $clientData[] = [
                        'equipment_id' => null, // Will be updated after equipment insertion
                        'serial_number' => $serial_number,
                        'client_id' => $equipment['client_id'],
                        'status_client' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Prepare service provider data if service_provider_id is provided
                if (isset($equipment['service_provider_id'])) {
                    $serviceProviderData[] = [
                        'equipment_id' => null, // Will be updated after equipment insertion
                        'serial_number' => $serial_number,
                        'service_provider_id' => $equipment['service_provider_id'],
                        'status_service_provider' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Insert equipment data
            $insertedEquipments = Equipment::insert($equipmentData);

            // Retrieve the inserted equipment IDs
            $insertedEquipmentIds = Equipment::latest()->take(count($equipmentData))->pluck('id')->toArray();

            // Update client and service provider data with the correct equipment IDs
            foreach ($clientData as $index => &$client) {
                $client['equipment_id'] = $insertedEquipmentIds[$index];
            }

            foreach ($serviceProviderData as $index => &$serviceProvider) {
                $serviceProvider['equipment_id'] = $insertedEquipmentIds[$index];
            }

            // Insert client and service provider data
            if (!empty($clientData)) {
                \DB::table('equipment_clients')->insert($clientData);
            }

            if (!empty($serviceProviderData)) {
                \DB::table('equipment_service_providers')->insert($serviceProviderData);
            }

            return response()->json(['message' => 'Equipments uploaded successfully'], 201);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error in massUpload method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while uploading equipments'], 500);
        }
    }

    public function show($id)
    {
        $equipment = Equipment::with('serviceProvider', 'client', 'serial_number')->findOrFail($id);
        return response()->json($equipment);
    }

    public function getEquipmentByServiceProvider($service_provider_id)
    {
        try {
            // Retrieve equipment for the given service provider with associated records
            $equipment = Equipment::with([
                'equipmentClients' => function ($query) {
                    $query->where('status_client', 1); // Only active clients
                },
                'equipmentServiceProviders' => function ($query) {
                    $query->where('status_service_provider', 1); // Only active service providers
                },
                'equipmentActivities' // Include all activities
            ])->whereHas('equipmentServiceProviders', function ($query) use ($service_provider_id) {
                $query->where('service_provider_id', $service_provider_id);
            })->get();

            if ($equipment->isEmpty()) {
                return response()->json(['message' => 'No equipment found for the specified service provider'], 404);
            }

            // Add equipmentStatus to each equipment record
            $equipmentWithStatus = $equipment->map(function ($item) {
                $item->equipment_status = $this->determineEquipmentStatus($item->id);
                return $item;
            });

            return response()->json([
                'message' => 'Equipment retrieved successfully',
                'data' => $equipmentWithStatus,
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error in getEquipmentByServiceProvider method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while retrieving equipment'], 500);
        }
    }

    public function getEquipmentByClient($client_id)
    {
        try {
            // Retrieve equipment for the given client with associated records
            $equipment = Equipment::with([
                'equipmentClients' => function ($query) {
                    $query->where('status_client', 1); // Only active clients
                },
                'equipmentServiceProviders' => function ($query) {
                    $query->where('status_service_provider', 1); // Only active service providers
                },
                'equipmentActivities' => function ($query) use ($client_id) {
                    $query->where('client_id', $client_id); // Filter activities by client_id
                }
            ])->whereHas('equipmentClients', function ($query) use ($client_id) {
                $query->where('client_id', $client_id);
            })->get();

            if ($equipment->isEmpty()) {
                return response()->json(['message' => 'No equipment found for the specified client'], 404);
            }

            // Add equipmentStatus to each equipment record
            $equipmentWithStatus = $equipment->map(function ($item) {
                $item->equipment_status = $this->determineEquipmentStatus($item->id);
                return $item;
            });

            return response()->json([
                'message' => 'Equipment retrieved successfully',
                'data' => $equipmentWithStatus,
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error in getEquipmentByClient method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while retrieving equipment'], 500);
        }
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

    public function updateClientOrServiceProvider(Request $request, $equipment_id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate the request to ensure at least one of the IDs is provided
        if (!$request->has('client_id') && !$request->has('service_provider_id')) {
            return response()->json(['message' => 'Either client_id or service_provider_id is required'], 400);
        }

        $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'service_provider_id' => 'nullable|integer|exists:service_providers,id',
        ]);

        try {
            \DB::beginTransaction();
            \DB::enableQueryLog();

            // Retrieve the equipment record
            $equipment = Equipment::findOrFail($equipment_id);

            // Check if the equipment is active
            if ($equipment->isActive == 0) {
                return response()->json(['message' => "The equipment isn't certified by FEMS, please contact Admin"], 403);
            }

            \Log::info('Equipment fetched:', ['equipment' => $equipment]);

            // Handle client_id if provided
            if ($request->has('client_id')) {
                // Update the client_id in the equipment record if null
                if ($equipment->client_id === null) {
                    $equipment->client_id = $request->client_id;
                    $equipment->save();
                }

                // Deactivate the current client record
                $deactivated = \DB::table('equipment_clients')
                    ->where('equipment_id', $equipment_id)
                    ->where('status_client', 1)
                    ->update(['status_client' => 0]);

                if ($deactivated === 0) {
                    \Log::warning("No active client found for equipment ID {$equipment_id}");
                }

                // Insert a new record with the updated client_id
                \DB::table('equipment_clients')->insert([
                    'equipment_id' => $equipment->id,
                    'serial_number' => $equipment->serial_number,
                    'client_id' => $request->client_id,
                    'status_client' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                \Log::info('Client updated successfully', [
                    'equipment_id' => $equipment_id,
                    'client_id' => $request->client_id,
                ]);
            }

            // Handle service_provider_id if provided
            if ($request->has('service_provider_id')) {
                // Deactivate the current service provider record
                $deactivated = \DB::table('equipment_service_providers')
                    ->where('equipment_id', $equipment_id)
                    ->where('status_service_provider', 1)
                    ->update(['status_service_provider' => 0]);

                if ($deactivated === 0) {
                    \Log::warning("No active service provider found for equipment ID {$equipment_id}");
                }

                // Insert a new record with the updated service_provider_id
                \DB::table('equipment_service_providers')->insert([
                    'equipment_id' => $equipment->id,
                    'serial_number' => $equipment->serial_number,
                    'service_provider_id' => $request->service_provider_id,
                    'status_service_provider' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                \Log::info('Service provider updated successfully', [
                    'equipment_id' => $equipment_id,
                    'service_provider_id' => $request->service_provider_id,
                ]);
            }

            \DB::commit();
            \Log::info('Update query log:', \DB::getQueryLog());

            return response()->json([
                'message' => 'Update successful',
                'equipment_id' => $equipment_id,
                'updated_client_id' => $request->client_id ?? null,
                'updated_service_provider_id' => $request->service_provider_id ?? null,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error in updateClient method:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while updating'], 500);
        }
    }

   
    public function getEquipmentByID($id)
    {     

        try {
            // Retrieve the equipment record with its associated client, service provider, and filtered activities
            $equipment = Equipment::with([
                'equipmentClients' => function ($query) {
                    $query->where('status_client', 1); // Only active clients
                },
                'equipmentServiceProviders' => function ($query) {
                    $query->where('status_service_provider', 1); // Only active service providers
                },
                'equipmentActivities' => function ($query) use ($id) {
                    $query->where('equipment_id', $id); // Filter activities by equipment_id
                }
            ])->findOrFail($id);

            // Hide the equipment_clients and equipment_service_providers relationships in the equipment object
            $equipment->makeHidden(['equipmentClients', 'equipmentServiceProviders']);
            
            // Determine the equipment status
            $equipmentStatus = $this->determineEquipmentStatus($id);

            // Format the response as an associative array
            $response = [
                'equipment' => $equipment,
                'clients' => $equipment->equipmentClients,
                'service_providers' => $equipment->equipmentServiceProviders,
                'activities' => $equipment->equipmentActivities,
                'equipment_status' => $equipmentStatus,
            ];

            return response()->json(['message' => 'Equipment retrieved successfully', 'data' => $response], 200);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error in getEquipmentByID method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while retrieving the equipment'], 500);
        }
    }


    public function getEquipmentBySerialNumber($serial_number)
    {
        try {
            // Retrieve the equipment record with its associated client and service provider data
            $equipment = Equipment::with([
                'equipmentClients' => function ($query) {
                    $query->where('status_client', 1);
                },
                'equipmentServiceProviders' => function ($query) {
                    $query->where('status_service_provider', 1);
                }
            ])->where('serial_number', $serial_number)->first();

            if (!$equipment) {
                return response()->json(['message' => 'Equipment not found'], 404);
            }

            $id = Equipment::where('serial_number', $serial_number)->value('id');

              // Determine the equipment status
              $equipmentStatus = $this->determineEquipmentStatus($id);

            // Hide the equipment_clients and equipment_service_providers relationships in the equipment object
            $equipment->makeHidden(['equipmentClients', 'equipmentServiceProviders']);

            // Format the response as an associative array
            $response = [
                'equipment' => $equipment,
                'clients' => $equipment->equipmentClients,
                'service_providers' => $equipment->equipmentServiceProviders,
                'equipment_status' => $equipmentStatus,
            ];

            return response()->json(['message' => 'Equipment retrieved successfully', 'data' => $response], 200);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error in getEquipmentBySerialNumber method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while retrieving the equipment'], 500);
        }
    }

    public function checkEquipmentStatus($id)
    {
        try {
            // Retrieve the equipment record by ID
            $equipment = Equipment::findOrFail($id);

            // Check if the equipment is expired
            $expiredEquipment = Equipment::where('id', $id)
                ->where('expiry_date', '<', Carbon::now())
                ->exists();

            if ($expiredEquipment) {
                return response()->json([
                    'equipment_id' => $id,
                    'equipment_status' => 'Expired',
                ], 200);
            }

            // Check if the equipment is expiring soon
            $expiringSoonEquipment = Equipment::where('id', $id)
                ->whereBetween('expiry_date', [Carbon::now(), Carbon::now()->addWeek()])
                ->exists();

            if ($expiringSoonEquipment) {
                return response()->json([
                    'equipment_id' => $id,
                    'equipment_status' => 'Renewal Due Soon',
                ], 200);
            }

            // If neither expired nor expiring soon, return Active
            return response()->json([
                'equipment_id' => $id,
                'equipment_status' => 'Active',
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error in checkEquipmentStatus method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while checking equipment status'], 500);
        }
    }

    private function determineEquipmentStatus($id)
    {
        // Check if the equipment is expired
        $expiredEquipment = Equipment::where('id', $id)
            ->where('expiry_date', '<', Carbon::now())
            ->exists();

        if ($expiredEquipment) {
            return 'Expired';
        }

        // Check if the equipment is expiring soon
        $expiringSoonEquipment = Equipment::where('id', $id)
            ->whereBetween('expiry_date', [Carbon::now(), Carbon::now()->addWeek()])
            ->exists();

        if ($expiringSoonEquipment) {
            return 'Renewal Due Soon';
        }

        // If neither expired nor expiring soon, return Active
        return 'Active';
    }

}
