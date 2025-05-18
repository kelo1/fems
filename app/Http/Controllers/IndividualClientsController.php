<?php

namespace App\Http\Controllers;
use App\Models\Client;
use App\Models\Individual_clients;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Aws\S3\Exception\S3Exception;
use Aws\Sns\SnsClient;
use Aws\Exception\AwsException;
use Exception;


class IndividualClientsController extends Controller
{
      /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    //Display all individual clients
    public function index(){

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Check if the user has the required role
        if (get_class($user) != "App\Models\FireServiceAgent" && get_class($user) != "App\Models\FEMSAdmin") {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        try {
            // Retrieve all corporate clients with their associated corporate_clients and service_provider details
            $individualClients = Client::where('client_type', 'INDIVIDUAL')
                ->with([
                    'individualClient', // Relationship to the corporate_clients table
                    'createdBy',       // Relationship to the service_provider who created the client
                ])
                ->get();
    
            // Return the response
            return response()->json([
                'message' => 'Individual clients retrieved successfully',
                'data' => $individualClients,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve corporate clients', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve corporate clients'], 500);
        }
    }


    public function getIndividualClients()
    {
        $user = Auth::user();
        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }

        try {
            // Retrieve all individual clients with their associated client details
            $individualClients = Individual_clients::with('client')
                ->whereHas('client', function ($query) use ($user) {
                    $query->where('created_by', $user->id)
                          ->where('created_by_type', get_class($user)); // Filter by created_by in the clients table
                })
                ->get();

            // Get the base URL from the environment variable
            $baseURL = env('AWS_URL', config('filesystems.disks.s3.url')); // Fallback to S3 URL if AWS_URL is not set

            // Add document URL to each individual client
            $individualClientsWithDocumentUrl = $individualClients->map(function ($individualClient) use ($baseURL) {
                $individualClient->document_url = $individualClient->document
                    ? $baseURL . '/uploads/individual_clients/' . $individualClient->document
                    : null;
                return $individualClient;
            });

            return response()->json([
                'message' => 'Individual clients retrieved successfully',
                'data' => $individualClientsWithDocumentUrl,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve individual clients', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve individual clients'], 500);
        }
    }

    public function getIndividualClientsByID($id)
    {
        try {
            // Retrieve the individual client by ID with associated client details
            $individualClient = Individual_clients::with('client')->where('client_id', $id)->first();

            if (!$individualClient) {
                return response()->json(['message' => 'Individual client not found'], 404);
            }

            // Get the base URL from the environment variable
            $baseURL = env('AWS_URL', config('filesystems.disks.s3.url')); // Fallback to S3 URL if AWS_URL is not set

            // Add document URL to the individual client
            $individualClient->document_url = $individualClient->document
                ? $baseURL . '/uploads/individual_clients/' . $individualClient->document
                : null;

            return response()->json([
                'message' => 'Individual client retrieved successfully',
                'data' => $individualClient,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve individual client by ID', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve individual client'], 500);
        }
    }

    public function generateOTP()
    {
        do {
            $otp = random_int(100000, 999999);
        } while (Client::where("otp", "=", $otp)->first());

        return $otp;
    }

    //Store individual client details
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            Log::error('Unauthorized access attempt to store individual client.');
            return response(['message' => 'Unauthorized'], 403);
        }

         // Validate client and individual client details
    try {
        $request->validate([
            'email' => 'required|email|unique:clients,email',
            'phone' => 'required|string|max:15|unique:clients,phone',
            'client_type' => 'required|string|exists:customer_types,name',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'gps_address' => 'nullable|string|max:255',
            'document_type' => 'required|string|in:PASSPORT,NATIONAL_ID',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);
        Log::info('Validation passed successfully.', ['request_data' => $request->all()]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Return all validation errors as a flat array of messages
        $messages = [];
        foreach ($e->validator->errors()->messages() as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }
        Log::error('Validation failed.', ['errors' => $messages]);
        return response()->json(['message' => 'Validation failed', 'errors' => $messages], 422);
    }
        
        
        \DB::beginTransaction();
        Log::info('Starting transaction to store individual client.');

        try {
           
            

            $otp = $this->generateOTP();
            $email_verification = Str::uuid()->toString();

            // Create the client
            $client = Client::create([
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make(Str::random(8)), // Generate a random password
                'client_type' => $request->client_type,
                'OTP' => $otp,
                'email_token' => $email_verification,
                'created_by' => $user->id,
                'created_by_type' => get_class($user), // Store the type of user who created the client
            ]);

            if (!$client) {
                Log::error('Failed to create client record.', ['request_data' => $request->all()]);
                \DB::rollBack();
                return response()->json(['message' => 'Failed to create client record'], 500);
            }

            Log::info('Client record created successfully.', ['client_id' => $client->id]);

            // Check if client_type is individual
            if ($request->client_type !== 'INDIVIDUAL') {
                Log::error('Client type is not INDIVIDUAL.', ['client_type' => $request->client_type]);
                \DB::rollBack();
                return response()->json(['message' => 'Client type must be INDIVIDUAL'], 422);
            }

            // Handle file uploads for document
            $documentFileName = null;
            $documentUrl = null;

            if ($request->hasFile('document')) {
                Log::info('Document file detected. Processing upload.');

                $file = $request->file('document');
                $documentFileName = strtolower($request->document_type) . '_upload_' . $client->id . '_' . Str::slug($request->first_name . ' ' . $request->last_name) . '_' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();

                $file->storeAs('uploads/individual_clients', $documentFileName, 's3');
                $documentUrl = env('AWS_URL') . '/uploads/individual_clients/' . $documentFileName;

                Log::info('Document uploaded successfully.', ['document_url' => $documentUrl]);
            } else {
                Log::info('No document file uploaded.');
            }

            // Store individual client details
            $individualClient = Individual_clients::create([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'address' => $request->address,
                'gps_address' => $request->gps_address,
                'document_type' => $request->document_type,
                'document' => $documentFileName ?? 'No upload',
                'client_id' => $client->id,
            ]);

            if (!$individualClient) {
                Log::error('Failed to create individual client record.', ['client_id' => $client->id]);
                \DB::rollBack();
                return response()->json(['message' => 'Failed to create individual client record'], 500);
            }

            Log::info('Individual client record created successfully.', ['client_id' => $client->id]);

            \DB::commit();

            Log::info('Transaction committed successfully.');

            return response()->json([
                'message' => 'Individual client created successfully',
            ], 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Failed to create individual client.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to create individual client'], 500);
        }
    }

  
}
