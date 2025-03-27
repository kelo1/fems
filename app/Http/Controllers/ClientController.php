<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Corporate_clients;
use App\Models\Individual_clients;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Aws\S3\Exception\S3Exception;
use Aws\Sns\SnsClient;
use Aws\Exception\AwsException;
use App\Notifications\VerifyEmailNotification;
use Exception;

class ClientController extends Controller
{
    // Display all clients
    public function index()
    {
        return Client::all();
    }

    // OTP Generation Function
    public function generateOTP()
    {
        do {
            $otp = random_int(100000, 999999);
        } while (Client::where("otp", "=", $otp)->first());

        return $otp;
    }

    // Store a newly created resource in storage
    public function store(Request $request)
    {
        \Log::info('ClientController store method called', $request->all());

        // Ensure the request is made by an authenticated FEMSAdmin, FireServiceAgent, or ServiceProvider
        // if (!Auth::guard('fems_admin')->check() && !Auth::guard('fire_service_agent')->check() && !Auth::guard('service_provider')->check()) {
        //     return response(['message' => 'Unauthorized'], 403);
        // }
        $user = Auth::guard('fems_admin')->user() 
            ?? Auth::guard('fire_service_agent')->user() 
            ?? Auth::guard('service_provider')->user();

        \Log::info('Authenticated User:', ['user' => $user]);

        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }
        // Validate request
        $request->validate([
            'email' => 'required|string',
            'phone' => 'required|string|unique:clients,phone',
            'password' => 'required|string|confirmed',
            'client_type' => 'required|string',
        ]);

        // Generate OTP
        $otp = $this->generateOTP();
        $email_verification = Str::uuid()->toString();

        $client = Client::create([
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'client_type' => $request->client_type,
            'OTP' => $otp,
            'email_token' => $email_verification
        ]);

        // Check if client was created
        if (!$client) {
            \Log::error('Client creation failed');
            return response(['message' => 'Client creation failed'], 500);
        }

        // Get the last inserted item
        $itemId = DB::getPdo()->lastInsertId();
        \Log::info('Client created with ID', ['client_id' => $itemId]);

        // Get email and phone number
        $client_email = Client::where('id', $itemId)->value('email');
        $client_phone = Client::where('id', $itemId)->value('phone');

        $client_id = $itemId;
        $client_type = Client::where('id', $client_id)->value('client_type');

        // Check if client type is individual or corporate
        if (strtoupper($client_type) == 'INDIVIDUAL') {
            // Store individual client
            $request->merge(['client_id' => $client_id]);
            try {
                app('App\Http\Controllers\IndividualClientsController')->store($request);

                $individual_details = DB::table('individual_clients')->where('client_id', $client_id)->first();

                if (!$individual_details) {
                    \Log::error('Failed to create individual client details', ['client_id' => $client_id]);
                    throw new \Exception('Failed to create individual client details, client already exists');
                }

                $response = [
                    'message' => 'Individual Client created Successfully',
                    'client_id' => $itemId,
                    'email' => $client->email,
                    'first_name' => $individual_details->first_name,
                    'middle_name' => $individual_details->middle_name,
                    'last_name' => $individual_details->last_name,
                    'phone' => $client->phone,
                    'address' => $individual_details->address,
                    'gps_address' => $individual_details->gps_address,
                    'client_type' => $client->client_type,
                ];

                return response($response, 201);
            } catch (\Exception $e) {
                \Log::error('Failed to create individual client details, deleting client', ['client_id' => $client_id, 'error' => $e->getMessage()]);
                Client::where('id', $client_id)->delete();
                return response(['message' => 'Failed to create individual client details, client not created'], 500);
            }
        } elseif (strtoupper($client_type) == 'CORPORATE') {
            // Store Corporate client details
            $request->merge(['client_id' => $client_id, 'email' => $client_email, 'phone' => $client_phone]);
            try {
                app('App\Http\Controllers\CorporateClientsController')->store($request);

                $corporate_details = DB::table('corporate_clients')->where('client_id', $client_id)->first();

                if (!$corporate_details) {
                    \Log::error('Failed to create corporate client details', ['client_id' => $client_id]);
                    throw new \Exception('Failed to create corporate client details');
                }

                $response = [
                    'message' => 'Corporate Client created Successfully',
                    'client_id' => $itemId,
                    'company_name' => $corporate_details->company_name,
                    'company_address' => $corporate_details->company_address,
                    'company_email' => $corporate_details->company_email,
                    'certificate_of_incorporation' => $corporate_details->certificate_of_incorporation,
                    'gps_address' => $corporate_details->gps_address,
                    'phone' => $client->phone,
                    'corporate_type_id' => $corporate_details->corporate_type_id,
                    'client_type' => $client->client_type,
                ];

                return response($response, 201);
            } catch (\Exception $e) {
                \Log::error('Failed to create corporate client details, deleting client', ['client_id' => $client_id, 'error' => $e->getMessage()]);
                Client::where('id', $client_id)->delete();
                return response(['message' => 'Failed to create corporate client details, client not created'], 500);
            }
        } else {
            \Log::error('The set customer type does not exist', ['client_type' => $client_type]);
            return response(["Message" => "The set customer type does not exist"], 404);
        }
    }

    // Bulk upload clients
        public function bulkUpload(Request $request)
    {
        \Log::info('ClientController bulkUpload method called', $request->all());

        // Authenticate user
        $user = Auth::guard('fems_admin')->user() 
            ?? Auth::guard('fire_service_agent')->user() 
            ?? Auth::guard('service_provider')->user();

        \Log::info('Authenticated User:', ['user' => $user]);
        
        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }

        DB::enableQueryLog(); // Enable query logging

        try {
            DB::transaction(function () use ($request) {
                \Log::info('Transaction started');

                $clients = $request->input('clients');
                $clientIds = [];

                foreach ($clients as $client) {
                    $otp = $this->generateOTP();
                    $email_verification = Str::uuid()->toString();

                    $clientId = Client::insertGetId([
                        'email' => $client['email'],
                        'phone' => $client['phone'],
                        'password' => Hash::make($client['password']),
                        'client_type' => strtoupper($client['client_type']),
                        'OTP' => $otp,
                        'email_token' => $email_verification,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    $clientIds[$client['email']] = $clientId;
                    \Log::info('Inserted client and retrieved ID:', ['client_id' => $clientId]);
                }

                \Log::info('Client IDs:', $clientIds);

                $individualData = [];
                $corporateData = [];

                foreach ($clients as $client) {
                    $client_id = $clientIds[$client['email']] ?? null;

                    if (!$client_id) {
                        \Log::error("Client ID not found for email: " . $client['email']);
                        throw new \Exception("Client ID missing for email: " . $client['email']);
                    }

                    if (strtoupper($client['client_type']) == 'INDIVIDUAL') {
                        $individualData[] = [
                            'client_id' => $client_id,
                            'first_name' => $client['first_name'],
                            'middle_name' => $client['middle_name'] ?? null,
                            'last_name' => $client['last_name'],
                            'address' => $client['address'],
                            'gps_address' => $client['gps_address'] ?? null,
                            'document_type' => $client['document_type'],
                            'document' => $client['document'],
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                    } elseif (strtoupper($client['client_type']) == 'CORPORATE') {
                        $corporateData[] = [
                            'client_id' => $client_id,
                            'company_name' => $client['company_name'],
                            'company_address' => $client['company_address'],
                            'company_email' => $client['company_email'] ?? $client['email'],
                            'company_phone' => $client['company_phone'] ?? $client['phone'],
                            'certificate_of_incorporation' => $client['certificate_of_incorporation'] ?? null,
                            'company_registration' => $client['company_registration'] ?? null,
                            'gps_address' => $client['gps_address'] ?? null,
                            'corporate_type_id' => $client['corporate_type_id'],
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                    }
                }

                if (!empty($individualData)) {
                    DB::table('individual_clients')->insert($individualData);
                    \Log::info('Inserted individual client data');
                }

                if (!empty($corporateData)) {
                    DB::table('corporate_clients')->insert($corporateData);
                    \Log::info('Inserted corporate client data');
                }

                \Log::info('Transaction committed');
            });

            \Log::info('Executed Queries:', DB::getQueryLog()); // Log executed queries

            return response()->json(['message' => 'Clients uploaded successfully'], 201);
        } catch (\Exception $e) {
            \Log::error('Bulk upload failed', ['error' => $e->getMessage()]);
            \Log::info('Executed Queries:', DB::getQueryLog()); // Log executed queries on failure

            return response()->json(['message' => 'Bulk upload failed', 'error' => $e->getMessage()], 500);
        }
    }

    //Update a client
    public function update(Request $request, $id)
    {
        // Authenticate user
        $user = Auth::guard('fems_admin')->user() 
            ?? Auth::guard('fire_service_agent')->user() 
            ?? Auth::guard('service_provider')->user();

        \Log::info('Authenticated User:', ['user' => $user]);
        
        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }


        \Log::info("Updating client with ID: $id");

        $client = Client::find($id);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        $isIndividual = strtoupper($client->client_type) === 'INDIVIDUAL';
        $isCorporate = strtoupper($client->client_type) === 'CORPORATE';

        // Validation rules based on client type
        $rules = [
            'email' => 'sometimes|email|unique:clients,email,' . $id,
            'phone' => 'sometimes|string|unique:clients,phone,' . $id,
            'password' => 'sometimes|string|confirmed',
        ];

        if ($isIndividual) {
            $rules += [
                'first_name' => 'sometimes|string',
                'middle_name' => 'nullable|string',
                'last_name' => 'sometimes|string',
                'address' => 'sometimes|string',
                'gps_address' => 'nullable|string',
                'document_type' => 'sometimes|string',
                'document' => 'sometimes|string',
            ];
        } elseif ($isCorporate) {
            $rules += [
                'company_name' => 'sometimes|string',
                'company_address' => 'sometimes|string',
                'company_email' => 'sometimes|email',
                'company_phone' => 'sometimes|string',
                'certificate_of_incorporation' => 'nullable|string',
                'company_registration' => 'nullable|string',
                'corporate_type_id' => 'sometimes|exists:corporate_types,id',
                'gps_address' => 'nullable|string', 
            ];
        }

        $validatedData = $request->validate($rules);
        \Log::info("Validated Data:", $validatedData);

        if (empty($validatedData)) {
            return response()->json(['message' => 'No valid data provided for update'], 400);
        }

        DB::transaction(function () use ($client, $validatedData, $isIndividual, $isCorporate) {
            // Update client main table
            if (!empty($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            }

            $clientUpdates = array_intersect_key($validatedData, array_flip(['email', 'phone', 'password']));
            if (!empty($clientUpdates)) {
                $client->update($clientUpdates);
                \Log::info("Updated client table for ID: {$client->id}", $clientUpdates);
            } else {
                \Log::info("No updates detected for client table.");
            }

            // Update Individual or Corporate table if necessary
            if ($isIndividual) {
                $individualUpdates = array_intersect_key($validatedData, array_flip([
                    'first_name', 'middle_name', 'last_name', 'address',
                    'gps_address', 'document_type', 'document'
                ]));

                if (!empty($individualUpdates)) {
                    DB::table('individual_clients')
                        ->where('client_id', $client->id)
                        ->update($individualUpdates);
                    \Log::info("Updated individual client data for ID: {$client->id}", $individualUpdates);
                } else {
                    \Log::info("No updates detected for individual client table.");
                }
            } elseif ($isCorporate) {
                $corporateUpdates = array_intersect_key($validatedData, array_flip([
                    'company_name', 'company_address', 'company_email', 
                    'company_phone', 'certificate_of_incorporation', 
                    'company_registration', 'corporate_type_id', 'gps_address' // 🔥 INCLUDED HERE
                ]));

                if (!empty($corporateUpdates)) {
                    DB::table('corporate_clients')
                        ->where('client_id', $client->id)
                        ->update($corporateUpdates);
                    \Log::info("Updated corporate client data for ID: {$client->id}", $corporateUpdates);
                } else {
                    \Log::info("No updates detected for corporate client table.");
                }
            }
        });

        return response()->json(['message' => 'Client updated successfully'], 200);
    }


    public function show($id)
    {
        // Authenticate user
        $user = Auth::guard('fems_admin')->user() 
            ?? Auth::guard('fire_service_agent')->user() 
            ?? Auth::guard('service_provider')->user();

        \Log::info('Authenticated User:', ['user' => $user]);

        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }

        //Check if client is individual or corporate
         $client = Client::find($id);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        // Get client type
        $client_type = Client::where('id', $id)->value('client_type');

        $isIndividual = strtoupper($client_type) === 'INDIVIDUAL';
        $isCorporate = strtoupper($client_type) === 'CORPORATE';

        if ($isIndividual) {

            $client = Individual_clients::with('client')->where('client_id', $id)->first();
            \Log::info("Fetched Individual Client Data:", ['client' => $client]);
        }
        else{

            $client = Corporate_clients::with('client', 'corporateType')->where('client_id', $id)->first();
            \Log::info("Fetched Corporate Client Data:", ['client' => $client]);
        }

        return response()->json($client);
    }

    // Client login
    public function login(Request $request)
    {
        
        $fields = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        // Check Email
        $client = Client::where('email', $fields['email'])->first();

        if (!$client) {
            return response([
                'message' => 'Client not found!'
            ], 404);
        }

        // Check Password
        if (!$client || !Hash::check($fields['password'], $client->password)) {
            return response([
                'message' => 'Invalid Credentials!'
            ], 401);
        }

        $email_verified = Client::where('id', $client->id)->value('email_verified_at');
        $phone_verified = Client::where('id', $client->id)->value('sms_verified');

        // Check Client Validation Status
        if ($email_verified == null) {
            return response([
                'message' => 'Kindly verify your email!'
            ], 401);
        }

        if ($phone_verified == null) {
            return response([
                'message' => 'Kindly verify your phone number!'
            ], 401);
        }

        $client_token = $client->createToken($client->first_name)->plainTextToken;

        if (strtoupper($client->client_type) == 'INDIVIDUAL') {
            $individual_details = DB::table('individual_clients')->where('client_id', $client->id)->first();
            $response = [
                'message' => 'Login Successful',
                'client_id' => $client->id,
                'email' => $client->email,
                'first_name' => $individual_details->first_name,
                'middle_name' => $individual_details->middle_name,
                'last_name' => $individual_details->last_name,
                'phone' => $client->phone,
                'address' => $individual_details->address,
                'gps_address' => $individual_details->gps_address,
                'session_id' => session()->get('client_id'),
                'client_type' => $client->client_type,
                'token' => $client_token
            ];
        } elseif (strtoupper($client->client_type) == 'CORPORATE') {
            $corporate_details = DB::table('corporate_clients')->where('client_id', $client->id)->first();
            $response = [
                'message' => 'Login Successful',
                'client_id' => $client->id,
                'company_name' => $corporate_details->company_name,
                'company_address' => $corporate_details->company_address,
                'company_email' => $corporate_details->company_email,
                'certificate_of_incorporation' => $corporate_details->certificate_of_incorporation,
                'gps_address' => $corporate_details->gps_address,
                'phone' => $client->phone,
                'session_id' => session()->get('client_id'),
                'client_type' => $client->client_type,
                'token' => $client_token
            ];
        } else {
            return response([
                'message' => 'Client does not belong to a client_type!'
            ], 401);
        }

        return response($response, 200);
    }

    //Delete a client
    public function destroy($id)
    {
    // Authenticate user
    $user = Auth::guard('fems_admin')->user() 
    ?? Auth::guard('fire_service_agent')->user() 
    ?? Auth::guard('service_provider')->user();

    \Log::info('Authenticated User:', ['user' => $user]);

    if (!$user) {
        return response(['message' => 'Unauthorized'], 403);
    }

    \Log::info("Deleting client with ID: $id");

    $client = Client::find($id);

    if (!$client) {
        return response()->json(['message' => 'Client not found'], 404);
    }

    $isIndividual = strtoupper($client->client_type) === 'INDIVIDUAL';
    $isCorporate = strtoupper($client->client_type) === 'CORPORATE';

    DB::transaction(function () use ($client, $isIndividual, $isCorporate) {
        if ($isIndividual) {
            // Delete from individual_clients table
            $deleted = DB::table('individual_clients')
                ->where('client_id', $client->id)
                ->delete();
            \Log::info("Deleted from individual_clients: ", ['client_id' => $client->id, 'deleted' => $deleted]);
        } elseif ($isCorporate) {
            // Delete from corporate_clients table
            $deleted = DB::table('corporate_clients')
                ->where('client_id', $client->id)
                ->delete();
            \Log::info("Deleted from corporate_clients: ", ['client_id' => $client->id, 'deleted' => $deleted]);
        }

        // Delete from clients table
        $client->delete();
        \Log::info("Deleted client record for ID: {$client->id}");
    });

    return response()->json(['message' => 'Client deleted successfully'], 200);
    
    }


    // Client Logout
    public function logout(Request $request)
    {
        $request->bearerToken();

        $client_id = $request->id;

        $client = Client::find($client_id);

        $tokenId = $client_id;

        $client->tokens()->where('tokenable_id', $tokenId)->delete();

        return response([
            'message' => 'Logout Successful'
        ], 200);
    }
}
