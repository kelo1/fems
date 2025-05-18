<?php

namespace App\Http\Controllers;
use App\Models\Client;
use App\Models\Corporate_clients;
use App\Models\CorporateType;
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
use Exception;
use Illuminate\Support\Facades\Log;

class CorporateClientsController extends Controller
{
     /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    //Display all Corporate clients
     public function index()
     {

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
            $corporateClients = Client::where('client_type', 'CORPORATE')
                ->with([
                    'corporateDetails', // Relationship to the corporate_clients table
                    'createdBy',       // Relationship to the service_provider who created the client
                ])
                ->get();
    
            // Return the response
            return response()->json([
                'message' => 'Corporate clients retrieved successfully',
                'data' => $corporateClients,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve corporate clients', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve corporate clients'], 500);
        }
     }

      // Get all corporate clients
    public function getCorporateClients()
    {   
        // Check if the user is authenticated
        $user = Auth::user();
        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }

        try {
            $corporateClients = Corporate_clients::with('client', 'corporateType')
                ->whereHas('client', function ($query) use ($user) {
                    $query->where('created_by', $user->id) // Filter by created_by in the clients table
                          ->where('created_by_type', get_class($user));
                })
                ->get();

            // Get the base URL from the environment variable
            $baseURL = env('AWS_URL', config('filesystems.disks.s3.url')); // Fallback to S3 URL if AWS_URL is not set

            // Add certificate and registration URLs to each corporate client
            $corporateClientsWithUrls = $corporateClients->map(function ($corporateClient) use ($baseURL) {
                $corporateClient->certificate_url = $corporateClient->certificate_of_incorporation
                    ? $baseURL . '/uploads/corporate_clients/' . $corporateClient->certificate_of_incorporation
                    : null;

                $corporateClient->registration_url = $corporateClient->company_registration
                    ? $baseURL . '/uploads/corporate_clients/' . $corporateClient->company_registration
                    : null;

                return $corporateClient;
            });

            return response()->json([
                'message' => 'Corporate clients retrieved successfully',
                'data' => $corporateClientsWithUrls,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve corporate clients', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve corporate clients'], 500);
        }
    }

    public function getCorporateClientByID($id)
    {
        try {
            // Retrieve the corporate client by ID with associated client and corporate type details
            $corporateClient = Corporate_clients::with('client', 'corporateType')->where('client_id', $id)->first();

            if (!$corporateClient) {
                return response()->json(['message' => 'Corporate client not found'], 404);
            }

            // Get the base URL from the environment variable
            $baseURL = env('AWS_URL', config('filesystems.disks.s3.url')); // Fallback to S3 URL if AWS_URL is not set

            // Add certificate and registration URLs to the corporate client
            $corporateClient->certificate_url = $corporateClient->certificate_of_incorporation
                ? $baseURL . '/uploads/corporate_clients/' . $corporateClient->certificate_of_incorporation
                : null;

            $corporateClient->registration_url = $corporateClient->company_registration
                ? $baseURL . '/uploads/corporate_clients/' . $corporateClient->company_registration
                : null;

            return response()->json([
                'message' => 'Corporate client retrieved successfully',
                'data' => $corporateClient,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve corporate client by ID', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve corporate client'], 500);
        }
    }
    
    public function generateOTP()
    {
        do {
            $otp = random_int(100000, 999999);
        } while (Client::where("otp", "=", $otp)->first());

        return $otp;
    }

    //Store Corporate client details
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }

        // Catch all validation errors for the request
    try {
        $request->validate([
            'email' => 'required|email|unique:clients,email',
            'phone' => 'required|string|max:15|unique:clients,phone',
            'client_type' => 'required|string|exists:customer_types,name',
            'company_name' => 'required|string|max:255',
            'branch_name' => 'sometimes|string|max:255',
            'company_address' => 'required|string|max:255',
            'certificate_of_incorporation' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'company_registration' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'gps_address' => 'nullable|string|max:255',
            'corporate_type_id' => 'required|integer|exists:corporate_types,id',
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Return all validation errors as a flat array of messages
        $messages = [];
        foreach ($e->validator->errors()->messages() as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $messages
        ], 422);
    }

        try {
            \DB::beginTransaction();

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

            // Handle file uploads
            $certificateFileName = null;
            $registrationFileName = null;
            $certificateUrl = null;
            $registrationUrl = null;

            if ($request->hasFile('certificate_of_incorporation')) {
                $certificateFile = $request->file('certificate_of_incorporation');
                $certificateFileName = 'certificate_' . $client->id . '_' . Str::slug($request->company_name) . '_' . now()->format('YmdHis') . '.' . $certificateFile->getClientOriginalExtension();
                $certificateFile->storeAs('uploads/corporate_clients', $certificateFileName, 's3');
                $certificateUrl = env('AWS_URL') . '/uploads/corporate_clients/' . $certificateFileName;
            }

            if ($request->hasFile('company_registration')) {
                $registrationFile = $request->file('company_registration');
                $registrationFileName = 'registration_' . $client->id . '_' . Str::slug($request->company_name) . '_' . now()->format('YmdHis') . '.' . $registrationFile->getClientOriginalExtension();
                $registrationFile->storeAs('uploads/corporate_clients', $registrationFileName, 's3');
                $registrationUrl = env('AWS_URL') . '/uploads/corporate_clients/' . $registrationFileName;
            }

            // Store corporate client details
            Corporate_clients::create([
                'company_name' => $request->company_name,
                'branch_name' => $request->branch_name ?? 'No branch name',
                'company_address' => $request->company_address,
                'company_email' => $request->email,
                'company_phone' => $request->phone,
                'certificate_of_incorporation' => $certificateFileName ?? 'No upload',
                'company_registration' => $registrationFileName ?? 'No upload',
                'gps_address' => $request->gps_address,
                'corporate_type_id' => $request->corporate_type_id,
                'client_id' => $client->id,
            ]);

            \DB::commit();

            return response()->json([
                'message' => 'Corporate client created successfully',
            ], 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Failed to create corporate client', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create corporate client'], 500);
        }
    }
}
