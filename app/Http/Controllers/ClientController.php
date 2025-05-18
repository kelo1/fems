<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Corporate_clients;
use App\Models\Individual_clients;
use App\Models\CorporateType;
use App\Models\ClientHistory;
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


    public function createCorporateClient(Request $request)
    {
    $corporateClientsController = new CorporateClientsController();
    return $corporateClientsController->store($request);

    }

    public function createIndividualClient(Request $request)
    {
        $individualClientsController = new IndividualClientsController();
        return $individualClientsController->store($request);
    }

    // Bulk upload clients
    public function bulkUpload(Request $request)
    {
    
        // Authenticate user
        $user = Auth::user();

        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }
        

        DB::enableQueryLog(); // Enable query logging

        try {
            DB::transaction(function () use ($request, $user) {
                \Log::info('Transaction started');

                $clients = $request->input('clients');
                $clientIds = [];

                foreach ($clients as $client) {

                    $randomPassword = Str::random(12);

                    $otp = $this->generateOTP();
                    $email_verification = Str::uuid()->toString();

                    $clientId = Client::insertGetId([
                        'email' => $client['email'],
                        'phone' => $client['phone'],
                        'password' => Hash::make($randomPassword),
                        'client_type' => strtoupper($client['client_type']),
                        'OTP' => $otp,
                        'email_token' => $email_verification,
                        'created_by' => $user->id,
                        'created_by_type' => get_class($user), // Store the type of user who created the client
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    $clientIds[$client['email']] = $clientId;

                     // Send the generated password to the client via email or SMS
               // Mail::to($client['email'])->send(new \App\Mail\ClientPasswordMail($randomPassword));
               
                // Optionally, you can also send the password via SMS using a service like Twilio or AWS SNS
                // $snsClient = new SnsClient([
                //     'region' => 'your-region',
                //     'version' => 'latest',
                //     'credentials' => [
                //         'key' => 'your-access-key-id',

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
                            'branch_name' => $client['branch_name'] ?? null,
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
        $user = Auth::user();

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
                'document_type' => 'sometimes|string|in:PASSPORT,NATIONAL_ID',
                'document' => 'sometimes:|file|mimes:pdf,jpg,jpeg,png|max:2048',
    
            ];
        } elseif ($isCorporate) {
            $rules += [
                'company_name' => 'sometimes|string',
                'company_address' => 'sometimes|string',
                'branch_name' => 'sometimes|string',
                'company_email' => 'sometimes|email',
                'company_phone' => 'sometimes|string',
                'corporate_type_id' => 'sometimes|integer|exists:corporate_types,id',
                'certificate_of_incorporation' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'gps_address' => 'nullable|string', 
            ];
        }

        $validatedData = $request->validate($rules);
        \Log::info("Validated Data:", $validatedData);

        if (empty($validatedData)) {
            return response()->json(['message' => 'No valid data provided for update'], 400);
        }

        DB::transaction(function () use ($client, $validatedData, $isIndividual, $isCorporate, $request) {
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

                // Handle document upload for individual clients
                if ($request->hasFile('document')) {
                    $documentFile = $request->file('document');
                    $documentFileName = strtolower($validatedData['document_type']) . '_upload_' . $client->id . '_' . now()->format('YmdHis') . '.' . $documentFile->getClientOriginalExtension();
                    $documentFile->storeAs('uploads/individual_clients', $documentFileName, 'public');
                    $individualUpdates['document'] = $documentFileName;
                }

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
                    'branch_name', 'company_registration', 'corporate_type_id', 'gps_address'
                ]));

                // Handle certificate_of_incorporation upload for corporate clients
                if ($request->hasFile('certificate_of_incorporation')) {
                    $certificateFile = $request->file('certificate_of_incorporation');
                    $certificateFileName = 'certificate_' . $client->id . '_' . now()->format('YmdHis') . '.' . $certificateFile->getClientOriginalExtension();
                    $certificateFile->storeAs('uploads/corporate_clients', $certificateFileName, 'public');
                    $corporateUpdates['certificate_of_incorporation'] = $certificateFileName;
                }

                // Handle company_registration upload for corporate clients
                if ($request->hasFile('company_registration')) {
                    $registrationFile = $request->file('company_registration');
                    $registrationFileName = 'registration_' . $client->id . '_' . now()->format('YmdHis') . '.' . $registrationFile->getClientOriginalExtension();
                    $registrationFile->storeAs('uploads/corporate_clients', $registrationFileName, 'public');
                    $corporateUpdates['company_registration'] = $registrationFileName;
                }

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
        $user = Auth::user();

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
        $user = Auth::user();

        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }

        \Log::info("Soft deleting client with ID: $id");

        // Find the client
        $client = Client::find($id);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        $isIndividual = strtoupper($client->client_type) === 'INDIVIDUAL';
        $isCorporate = strtoupper($client->client_type) === 'CORPORATE';

        DB::transaction(function () use ($client, $isIndividual, $isCorporate) {
            if ($isIndividual) {
                // Soft delete from individual_clients table
                $individualClient = Individual_clients::where('client_id', $client->id)->first();
                if ($individualClient) {
                    $individualClient->delete();
                    \Log::info("Soft deleted individual client record for ID: {$client->id}");
                }
            } elseif ($isCorporate) {
                // Soft delete from corporate_clients table
                $corporateClient = Corporate_clients::where('client_id', $client->id)->first();
                if ($corporateClient) {
                    $corporateClient->delete();
                    \Log::info("Soft deleted corporate client record for ID: {$client->id}");
                }
            }

            // Soft delete from clients table
            $client->delete();
            \Log::info("Soft deleted client record for ID: {$client->id}");
        });

        return response()->json(['message' => 'Client soft deleted successfully'], 200);
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
    // Get all corporate clients filtered by user type
    // This method retrieves corporate clients based on the user type (FEMSAdmin or other users)
    public function getClientByCorporateType(Request $request)
    {
        try {
          
            // Authenticate the user
            $user = Auth::user();
            if (!$user) {
                return response(['message' => 'Unauthorized'], 403);
            }

            if (!$request->has('corporate_type_id')) {
                return response()->json(['message' => 'Corporate type ID is required'], 400);
            }

            // Validate the request
            $request->validate([
                'corporate_type_id' => 'required|exists:corporate_types,id',
                'user_type' => 'sometimes|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
                'user_id' => 'sometimes|integer',
            ]);

            // Extract parameters from the request
            $corporate_type_id = $request->corporate_type_id;
            $rawUserType = $request->user_type ?? null;
            $userId = $request->user_id ?? null;
    
            // Resolve and map user_type to model class name
            $mappedUserType = null;
            if ($rawUserType) {
                $mappedUserType = $this->resolveModelUserType($rawUserType);
                \Log::info('Mapped User Type:', ['from' => $rawUserType, 'to' => $mappedUserType]);
            }
    
            // Fetch the corporate type
            $corporateType = CorporateType::find($corporate_type_id);
            if (!$corporateType) {
                return response()->json(['message' => 'Corporate type not found'], 404);
            }
    
            // Determine authenticated user type
            $authenticatedUserType = get_class($user);
    
            // Base query
            $query = DB::table('corporate_clients')
                ->join('clients', 'corporate_clients.client_id', '=', 'clients.id')
                ->where('corporate_clients.corporate_type_id', $corporate_type_id);
    
            if ($authenticatedUserType === 'App\Models\FEMSAdmin') {
                // FEMSAdmin can see all, with optional filters
                if ($mappedUserType) {
                    $query->where('clients.created_by_type', 'App\Models\\' . $mappedUserType);
                }
    
                if ($userId) {
                    $query->where('clients.created_by', $userId);
                }
            } else {
                // Other users only see their own clients
                $query->where('clients.created_by', $user->id)
                    ->where('clients.created_by_type', $authenticatedUserType);
    
                // Apply user_type and user_id filters only if they match the current user
                if ($mappedUserType && $authenticatedUserType === 'App\Models\\' . $mappedUserType) {
                    $query->where('clients.created_by_type', 'App\Models\\' . $mappedUserType);
                }
    
                if ($userId && $userId === $user->id) {
                    $query->where('clients.created_by', $userId);
                }
            }
    
            $clients = $query->select(
                'clients.id as client_id',
                'clients.email',
                'clients.phone',
                'clients.client_type',
                'clients.created_by',
                'clients.created_by_type',
                'corporate_clients.company_name',
                'corporate_clients.company_address',
                'corporate_clients.company_email',
                'corporate_clients.company_phone',
                'corporate_clients.certificate_of_incorporation',
                'corporate_clients.company_registration',
                'corporate_clients.gps_address',
                'corporate_clients.corporate_type_id'
            )->get();
    
            // Get the base URL from the environment variable
            $baseURL = env('AWS_URL', config('filesystems.disks.s3.url')); // Fallback to S3 URL if AWS_URL is not set
    
            // Add certificate and registration URLs to each corporate client
            $clientsWithUrls = $clients->map(function ($client) use ($baseURL) {
                $client->certificate_url = $client->certificate_of_incorporation
                    ? $baseURL . '/uploads/corporate_clients/' . $client->certificate_of_incorporation
                    : null;
    
                $client->registration_url = $client->company_registration
                    ? $baseURL . '/uploads/corporate_clients/' . $client->company_registration
                    : null;
    
                return $client;
            });
    
            return response()->json([
                'message' => 'Clients retrieved successfully',
                'corporate_type' => $corporateType->name,
                'clients' => $clientsWithUrls,
            ], 200);
    
        } catch (\Exception $e) {
            \Log::error('Error in getClientsByCorporateType method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }
    

    private function resolveModelUserType($type)
    {
        $map = [
            'SERVICE_PROVIDER' => 'ServiceProvider',
            'FSA_AGENT' => 'FireServiceAgent',
            'GRA_PERSONNEL' => 'GRA',
        ];

        return $map[strtoupper($type)] ?? null;
    }

    // Get all individual clients filtered by user type
    // This method retrieves individual clients based on the user type (FEMSAdmin or other users)
    public function getClientByIndividual(Request $request)
    {
        try {
          
            // Authenticate the user
            $user = Auth::user();

            if (!$user) {
                return response(['message' => 'Unauthorized'], 403);
            }

            // Validate the request
            $request->validate([
                'user_type' => 'sometimes|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
                'user_id' => 'sometimes|integer',
            ]);

            $rawUserType = $request->user_type ?? null;
            $userId = $request->user_id ?? null;

            // Resolve and map user_type to model class name
            $mappedUserType = null;
            if ($rawUserType) {
                $mappedUserType = $this->resolveModelUserType($rawUserType);
                \Log::info('Mapped User Type:', ['from' => $rawUserType, 'to' => $mappedUserType]);
            }

            // Determine authenticated user type
            $authenticatedUserType = get_class($user);

            // Base query
            $query = DB::table('individual_clients')
                ->join('clients', 'individual_clients.client_id', '=', 'clients.id');

            if ($authenticatedUserType === 'App\Models\FEMSAdmin') {
                // FEMSAdmin can see all individual clients, with optional filters
                if ($mappedUserType) {
                    $query->where('clients.created_by_type', 'App\Models\\' . $mappedUserType);
                }

                if ($userId) {
                    $query->where('clients.created_by', $userId);
                }
            } else {
                // Other users can only see individual clients they created
                $query->where('clients.created_by', $user->id)
                    ->where('clients.created_by_type', $authenticatedUserType);

                // Apply user_type and user_id filters only if they match the current user
                if ($mappedUserType && $authenticatedUserType === 'App\Models\\' . $mappedUserType) {
                    $query->where('clients.created_by_type', 'App\Models\\' . $mappedUserType);
                }

                if ($userId && $userId === $user->id) {
                    $query->where('clients.created_by', $userId);
                }
            }

            // Select fields from both tables
            $clients = $query->select(
                'clients.id as client_id',
                'clients.email',
                'clients.phone',
                'clients.client_type',
                'clients.created_by',
                'clients.created_by_type',
                'individual_clients.first_name',
                'individual_clients.middle_name',
                'individual_clients.last_name',
                'individual_clients.address',
                'individual_clients.gps_address',
                'individual_clients.document_type',
                'individual_clients.document'
            )->get();

            // Get the base URL from the AWS_URL environment variable
            $baseURL = env('AWS_URL', config('filesystems.disks.s3.url')); // Fallback to S3 URL if AWS_URL is not set

            // Add document URL to each individual client
            $clientsWithDocumentUrl = $clients->map(function ($client) use ($baseURL) {
                $client->document_url = $client->document
                    ? $baseURL . '/uploads/individual_clients/' . $client->document
                    : null;
                return $client;
            });

            return response()->json([
                'message' => 'Individual clients retrieved successfully',
                'clients' => $clientsWithDocumentUrl,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in getClientByIndividual method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }

    public function getClientUploads($id)
    {
        // Authenticate user
        $user = Auth::user();

        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Find the client
        $client = Client::find($id);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        // Determine client type
        $clientType = strtoupper($client->client_type);

        $baseURL = env('AWS_URL', config('filesystems.disks.s3.url')); // Use AWS S3 URL

        if ($clientType === 'INDIVIDUAL') {
            // Fetch uploads for individual clients
            $individualClient = Individual_clients::where('client_id', $id)->first();

            if (!$individualClient) {
                return response()->json(['message' => 'Individual client details not found'], 404);
            }

            return response()->json([
                'message' => 'Individual client uploads retrieved successfully',
                'uploads' => [
                    'document_type' => $individualClient->document_type,
                    'document' => $individualClient->document ? $baseURL . '/uploads/individual_clients/' . $individualClient->document : null,
                ],
            ], 200);
        } elseif ($clientType === 'CORPORATE') {
            // Fetch uploads for corporate clients
            $corporateClient = Corporate_clients::where('client_id', $id)->first();

            if (!$corporateClient) {
                return response()->json(['message' => 'Corporate client details not found'], 404);
            }

            return response()->json([
                'message' => 'Corporate client uploads retrieved successfully',
                'uploads' => [
                    'certificate_of_incorporation' => $corporateClient->certificate_of_incorporation ? $baseURL . '/uploads/corporate_clients/' . $corporateClient->certificate_of_incorporation : null,
                    'company_registration' => $corporateClient->company_registration ? $baseURL . '/uploads/corporate_clients/' . $corporateClient->company_registration : null,
                ],
            ], 200);
        } else {
            return response()->json(['message' => 'Invalid client type'], 400);
        }
    }

    public function deleteClientUpload(Request $request, $id)
    {
        try {
            // Authenticate user
            $user = Auth::user();

            if (!$user) {
                return response(['message' => 'Unauthorized'], 403);
            }

            // Validate the request
            $request->validate([
                'upload_type' => 'required|string|in:document,certificate_of_incorporation,company_registration',
            ]);

            // Find the client
            $client = Client::find($id);

            if (!$client) {
                return response()->json(['message' => 'Client not found'], 404);
            }

            // Determine client type
            $clientType = strtoupper($client->client_type);
            $uploadType = $request->upload_type;

            if ($clientType === 'INDIVIDUAL') {
                // Fetch individual client details
                $individualClient = Individual_clients::where('client_id', $id)->first();

                if (!$individualClient) {
                    return response()->json(['message' => 'Individual client details not found'], 404);
                }

                if ($uploadType === 'document') {
                    if ($individualClient->document) {
                        // Delete the file from S3
                        Storage::disk('s3')->delete('uploads/individual_clients/' . $individualClient->document);

                        // Update the database
                        $individualClient->update(['document' => null]);

                        return response()->json(['message' => 'Document deleted successfully'], 200);
                    } else {
                        return response()->json(['message' => 'No document found to delete'], 404);
                    }
                }
            } elseif ($clientType === 'CORPORATE') {
                // Fetch corporate client details
                $corporateClient = Corporate_clients::where('client_id', $id)->first();

                if (!$corporateClient) {
                    return response()->json(['message' => 'Corporate client details not found'], 404);
                }

                if ($uploadType === 'certificate_of_incorporation') {
                    if ($corporateClient->certificate_of_incorporation) {
                        // Delete the file from S3
                        Storage::disk('s3')->delete('uploads/corporate_clients/' . $corporateClient->certificate_of_incorporation);

                        // Update the database
                        $corporateClient->update(['certificate_of_incorporation' => null]);

                        return response()->json(['message' => 'Certificate of incorporation deleted successfully'], 200);
                    } else {
                        return response()->json(['message' => 'No certificate of incorporation found to delete'], 404);
                    }
                } elseif ($uploadType === 'company_registration') {
                    if ($corporateClient->company_registration) {
                        // Delete the file from S3
                        Storage::disk('s3')->delete('uploads/corporate_clients/' . $corporateClient->company_registration);

                        // Update the database
                        $corporateClient->update(['company_registration' => null]);

                        return response()->json(['message' => 'Company registration deleted successfully'], 200);
                    } else {
                        return response()->json(['message' => 'No company registration found to delete'], 404);
                    }
                }
            } else {
                return response()->json(['message' => 'Invalid client type'], 400);
            }
        } catch (\Exception $e) {
            // Log the exception
            \Log::error('Error in deleteClientUpload', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while deleting the upload', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateClientUploads(Request $request)
    {
        try {
    
            $user = Auth::user();
    
            if (!$user) {
                return response(['message' => 'Unauthorized'], 403);
            }
    
            // Validate request
            $request->validate([
                'id' => 'required|integer|exists:clients,id',
                'client_type' => 'required|string|in:INDIVIDUAL,CORPORATE',
                'file_meta' => 'required',
                //'files' => 'required',
               // 'files.*.upload_type' => 'required|string|in:document,certificate_of_incorporation,company_registration',
               // 'files.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            ]);
    
            $clientId = $request->input('id');
            $clientType = $request->input('client_type');
            $filesMetadataString = $request->input('file_meta');
            //$actualFiles = $request->file('files'); // Access the uploaded files
            $filesMetadata = json_decode($filesMetadataString, true);
    
    
            // Get client
            $client = Client::find($clientId);
            if (!$client) {
                return response()->json(['message' => 'Client not found'], 404);
            }
    
            // Match client type
            if (strtoupper($client->client_type) !== $clientType) {
                return response()->json(['message' => 'Client type mismatch'], 400);
            }
    
    
    
            //dd($filesMetadata, $actualFiles); // Inspect both
            if( $clientType === 'INDIVIDUAL'){
    
                $individualClient = Individual_clients::where('client_id', $clientId)->first();
    
                if (!$individualClient) {
                    return response()->json(['message' => 'Individual client details not found'], 404);
                }
    
                if (is_array($filesMetadata)) {
                    // Process the metadata
                
                    foreach ($filesMetadata as $index => $fileInfo) {
                        //dump($fileInfo);
                    // $fileinfo = $fileInfo;
    
                        if($fileInfo['upload_type'] === 'document' && $request->hasFile('document_file')){
    
                                $fileUploads = $request->file('document_file');
                                
                                $fileName = 'document_upload_' . $clientId . '_' . now()->format('YmdHis') . '.' . $fileUploads->getClientOriginalExtension();
                                $fileUploads->storeAs('uploads/individual_clients', $fileName, 'public');
    
                                $individualClient->update(['document' => $fileName]);
    
                                return response()->json(['message' => 'Uploads updated successfully for individual client'], 200);
                            }
                            else{
                                return response()->json(['error' => 'File Upload Failed', 'fileInfo' => $fileInfo], 400);
                            }
    
                        }
                        
                    }
                    else {
                        return response()->json(['error' => 'File Meta is not an array'], 400);
                    }
            } 
    
    
            /////////////////////
    
    
            if ($clientType === 'CORPORATE') {
    
                $corporateClient = Corporate_clients::where('client_id', $clientId)->first();
    
                if (!$corporateClient) {
                    return response()->json(['message' => 'Corporate client details not found'], 404);
                }
                
                if (is_array($filesMetadata)) {
    
                    foreach ($filesMetadata as $index => $meta) {
    
                        if (isset($meta['upload_type'])) {
    
                            $uploadType = $meta['upload_type'];
                            
    
                            if ($uploadType === 'certificate_of_incorporation' && $request->hasFile('certificate_of_incorporation_file')) {
                               
                                $file = $request->file('certificate_of_incorporation_file');
    
                                $fileName = $uploadType . '' . $clientId . '' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
                                $file->storeAs('uploads/corporate_clients', $fileName, 'public');
    
                                $corporateClient->update(['certificate_of_incorporation' => $fileName]);
                            } elseif ($uploadType === 'company_registration' && $request->hasFile('company_registration_file')) {
    
                                $file = $request->file('company_registration_file');
    
                                $fileName = $uploadType . '' . $clientId . '' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
                                $file->storeAs('uploads/corporate_clients', $fileName, 'public');
                                $corporateClient->update(['company_registration' => $fileName]);
                            }
                        }
                    }
    
                    return response()->json(['message' => 'Uploads updated successfully for corporate client'], 200);
                }
                else {
                    return response()->json(['error' => 'File Meta is not an array'], 400);
                }
            }
    
            return response()->json(['message' => 'Invalid client type'], 400);
    
               
        } catch (\Exception $e) {
            \Log::error('Error in updateClientUploads', ['error' => $e->getMessage()]);
        return response()->json(['message' => 'An error occurred while updating uploads', 'error' => $e->getMessage()], 500);
    }
    }

    public function getClientHistory($client_id)
    {
        try {
            // Retrieve client history for the given client_id
            $clientHistories = ClientHistory::with(['client', 'oldServiceProvider', 'newServiceProvider'])
                ->where('client_id', $client_id)
                ->get()
                ->map(function ($history) {
                    $client = $history->client;

                    // Determine if the client is individual or corporate
                    if ($client->client_type === 'INDIVIDUAL') {
                        $clientDetails = Individual_clients::where('client_id', $client->id)->first();
                    } elseif ($client->client_type === 'CORPORATE') {
                        $clientDetails = Corporate_clients::where('client_id', $client->id)->first();
                    } else {
                        $clientDetails = null;
                    }

                    return [
                        'client' => [
                            'id' => $client->id,
                            'type' => $client->client_type,
                            'details' => $clientDetails,
                        ],
                        'old_service_provider' => $history->oldServiceProvider,
                        'new_service_provider' => $history->newServiceProvider,
                    ];
                });

            if ($clientHistories->isEmpty()) {
                return response()->json(['message' => 'No history found for the specified client'], 200);
            }

            return response()->json([
                'message' => 'Client history retrieved successfully',
                'data' => $clientHistories,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in getClientHistory method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while retrieving the client history'], 500);
        }
    }
}
