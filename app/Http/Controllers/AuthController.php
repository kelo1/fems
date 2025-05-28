<?php

namespace App\Http\Controllers;

use App\Models\ServiceProvider;
use App\Models\FireServiceAgent;
use App\Models\GRA;
use App\Models\LicenseType;
use App\Models\FEMSAdmin;
use App\Models\Certificate;
use App\Models\Invoicing;
use App\Models\InvoicesbyFSA;
use App\Models\Equipment;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Show all users
    public function index()
    {
         // Get the authenticated admin
         $admin = Auth::user();
    
         if (!$admin) {
             return response()->json(['message' => 'Unauthorized: Admin user not found'], 401);
         }
     
         // Ensure the authenticated user is a FEMSAdmin
         if (!$admin instanceof FEMSAdmin) {
             \Log::warning('Unauthorized attempt to update isActive', ['auth_user' => $admin]);
             return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 403);
         }

        // Check authorization using policy
        if (!Gate::allows('update-isActive', $admin)) {
            \Log::warning('Unauthorized attempt to update isActive', ['auth_user' => $admin, 'target_id' => $id, 'user_type' => $request->user_type]);
            return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 401);
        }

        $serviceProviders = ServiceProvider::all();
        $fireServiceAgents = FireServiceAgent::all();
        $gras = GRA::all();

        return response()->json([
            'service_providers' => $serviceProviders,
            'fire_service_agents' => $fireServiceAgents,
            'gras' => $gras,
        ]);
    }


    public function getUserByID(Request $request, $id){

        // Get the authenticated admin
        $admin = Auth::user();
    
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized: Admin user not found'], 401);
        }

        // Ensure the authenticated user is a FEMSAdmin
        if (get_class($admin) != "App\Models\FEMSAdmin") {
            \Log::warning('Unauthorized attempt to update isActive', ['auth_user' => $admin]);
            return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 403);
        }


        // Validate the user_type field
        $fields = $request->validate([
            'user_type' => 'required|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
        ]);

        $userType = strtoupper($fields['user_type']);

        // Map user types to their respective models
        $modelMap = [
            'SERVICE_PROVIDER' => \App\Models\ServiceProvider::class,
            'FSA_AGENT' => \App\Models\FireServiceAgent::class,
            'GRA_PERSONNEL' => \App\Models\GRA::class,
        ];

        $model = $modelMap[$userType] ?? null;

        if (!$model) {
            return response()->json(['message' => 'Invalid user type provided'], 400);
        }

        // Retrieve the user by email
        $user = $model::where('id', $id)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        } else {
            // If SERVICE_PROVIDER, add license_type description directly to the user object
            if ($userType === 'SERVICE_PROVIDER') {
                $licenseType = \App\Models\LicenseType::find($user->license_id);
                $user->license_type_description = $licenseType ? $licenseType->description : null;
            }

            $response = [
                'user_type' => $fields['user_type'],
                'user' => $user,
            ];

            return response()->json($response);
        }


    }


    /**
     * Sign up a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */ 

     public function signUp(Request $request)
    {
 
    // Validate request
    $validator = \Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'address' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:service_providers|unique:fire_service_agents|unique:gras',
        'phone' => 'required|string|max:15|unique:service_providers|unique:fire_service_agents|unique:gras',
        'gps_address' => 'nullable|string|max:255',
        'password' => 'required|string|min:8|confirmed',
        'user_type' => ['required', Rule::in(['FSA_AGENT', 'SERVICE_PROVIDER', 'GRA_PERSONNEL'])],
        'license_id' => 'required_if:user_type,Service Provider|required_if:user_type,SERVICE_PROVIDER|exists:license_types,id',
    ]);
    
    if ($validator->fails()) {
        \Log::error("Validation failed!", ['errors' => $validator->errors()]);
        return response()->json(['error' => $validator->errors()], 422);
    }
    
    \Log::info("Validation passed successfully.");
    
    // Create user
    $user = null;
    $email_verification = Str::uuid()->toString();

    $otp = $this->generateOTP($request->user_type);

    try {
        DB::beginTransaction();

        //  Log User Type Before Creating
        \Log::info("User Type: " . strtoupper($request->user_type));

        if (strtoupper($request->user_type) === 'SERVICE_PROVIDER') {  
            $licenseType = LicenseType::find($request->license_id);

            if (!$licenseType) {
                \Log::error("LicenseType not found for ID: " . $request->license_id);
                return response()->json(['error' => 'Invalid license ID'], 400);
            }

            \Log::info("Creating Service Provider user...");

            $user = ServiceProvider::create([
                'name' => $request->name,
                'address' => $request->address,
                'email' => $request->email,
                'phone' => $request->phone,
                'OTP' => $otp,
                'email_token' => $email_verification,
                'gps_address' => $request->gps_address,
                'password' => Hash::make($request->password),
                'license_id' => $request->license_id,
            ]);

            \Log::info("Service Provider created successfully", ['user' => $user]);

        } elseif (strtoupper($request->user_type) === 'FSA_AGENT') {
            \Log::info("Creating FSA Agent user...");

            $user = FireServiceAgent::create([
                'name' => $request->name,
                'address' => $request->address,
                'email' => $request->email,
                'phone' => $request->phone,
                'OTP' => $otp,
                'email_token' => $email_verification,
                'gps_address' => $request->gps_address,
                'password' => Hash::make($request->password),
            ]);

            \Log::info("FSA Agent created successfully", ['user' => $user]);

        } elseif (strtoupper($request->user_type) === 'GRA_PERSONNEL') {
            \Log::info("Creating GRA Personnel user...");

            $user = GRA::create([
                'name' => $request->name,
                'address' => $request->address,
                'email' => $request->email,
                'phone' => $request->phone,
                'OTP' => $otp,
                'email_token' => $email_verification,
                'gps_address' => $request->gps_address,
                'password' => Hash::make($request->password),
            ]);

            \Log::info("GRA Personnel created successfully", ['user' => $user]);
        }

        if (!$user) {
            \Log::error("User creation failed");
            DB::rollBack();
            return response()->json(['error' => 'Failed to create user'], 500);
        }

        // Send OTP via AWS SNS
        $this->sendOtpToPhone($user->phone, $otp);

        // Send password notification
      //  $user->notify(new \App\Notifications\SendPasswordNotification($request->password));

        // Send email verification notification
        $user->notify(new \App\Notifications\VerifyEmailNotification($user, $request->name, $request->email, $email_verification, $request->user_type));

        DB::commit();
        return response()->json(['message' => 'User created successfully'
        , 'user' => $user->only('name', 'phone', 'user_type', 'id'),
    ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error("Error creating user: " . $e->getMessage());
        return response()->json(['error' => 'An error occurred while creating user'], 500);
    }
    }

    private function sendOtpToPhone($phone, $otp)
{
    try {
        // Format the phone number (e.g., for Ghana, prepend +233)
        $formattedPhone = preg_replace('~^(?:0|\+?233)?~', '+233', $phone);

        // Twilio client configuration
        $twilioSid = env('TWILIO_ACCOUNT_SID');
        $twilioToken = env('TWILIO_AUTH_TOKEN');

        $twilio = new \Twilio\Rest\Client($twilioSid, $twilioToken);

        // Send the SMS
        $twilio->messages->create(
            $formattedPhone,
            [
                'messagingServiceSid' => env('TWILIO_MESSAGING_SERVICE_SID'),
                'body' => "Your Guardian Safety OTP is: $otp",
            ]
        );

        \Log::info("OTP sent successfully to $formattedPhone");
    } catch (\Exception $e) {
        \Log::error("Failed to send OTP to $phone", ['error' => $e->getMessage()]);
        throw new \Exception("Failed to send OTP. Please try again.");
    }
}

   
     
 // OTP Generation Function
 public function generateOTP($userType)
 {
     // Map user types to their respective models
     $modelMap = [
         'SERVICE_PROVIDER' => \App\Models\ServiceProvider::class,
         'FSA_AGENT' => \App\Models\FireServiceAgent::class,
         'GRA_PERSONNEL' => \App\Models\GRA::class,
     ];

     $model = $modelMap[strtoupper($userType)] ?? null;

     if (!$model) {
         throw new \Exception("Invalid user type provided for OTP generation.");
     }

     do {
         // Generate a random 6-digit OTP
         $otp = random_int(100000, 999999);
     } while ($model::where('OTP', $otp)->exists());

     return $otp;
 }

    /**
     * Sign in an existing user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function signIn(Request $request)
    {
    try {
      
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'user_type' => ['required', Rule::in(['FSA_AGENT', 'SERVICE_PROVIDER', 'GRA_PERSONNEL'])],
        ]);

        // Ensure user_type is present in request
        if (!$request->has('user_type')) {
            \Log::error('Missing user_type in request');
            return response()->json(['message' => 'User type is required'], 400);
        }

        // Convert user_type to uppercase
        $userType = strtoupper($request->user_type);
        $guard = null;

        // Map user_type to the correct guard
        if ($userType === 'SERVICE_PROVIDER') {
            $guard = 'service_provider';
        } elseif ($userType === 'FSA_AGENT') {
            $guard = 'fire_service_agent';
        } elseif ($userType === 'GRA_PERSONNEL') {
            $guard = 'gra';
        }

        if (!$guard) {
            \Log::error('Invalid user type selected', ['user_type' => $userType]);
            return response()->json(['message' => 'Invalid user type'], 400);
        }

        // Attempt authentication
        if (!Auth::guard($guard)->attempt($request->only('email', 'password'))) {
            \Log::warning('Invalid login attempt', ['email' => $request->email, 'user_type' => $userType]);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Retrieve the authenticated user
        $user = Auth::guard($guard)->user();

        // Ensure the account is active before proceeding
        if (!$user->isActive) {
            \Log::warning('Inactive account login attempt', ['email' => $user->email, 'user_type' => $userType]);
            return response()->json(['message' => 'Account is inactive'], 403);
        }

        //Check if sms_verified is true for user
        if ($user->sms_verified !== 1) {
            \Log::warning('SMS verification required', ['email' => $user->email, 'user_type' => $userType]);
            return response()->json(['message' => 'Please verify your phone number'], 403);
        }

        // Check if email_verified_at is null for user
        if ($user->email_verified_at === null) {
            \Log::warning('Email verification required', ['email' => $user->email, 'user_type' => $userType]);
            return response()->json(['message' => 'Please verify your email address'], 403);
        }

        // Generate API token
        $token = $user->createToken('auth_token')->plainTextToken;

        \Log::info('User signed in successfully', ['user_id' => $user->id, 'user_type' => $userType]);

        return response()->json([
            'message' => 'User signed in successfully',
            'token' => $token,
            'user' => $user->only('name'),
            'id' => $user->id,
            'user_type' => $userType,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('SignIn error', ['error' => $e->getMessage()]);
        return response()->json(['message' => 'Something went wrong, please try again later'], 500);
     }
    }



    

  /**
     * Log out the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();

        return response()->json(['message' => 'User logged out successfully'], 200);
    }

   
    /**
     * Update the isActive status of a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateIsActive(Request $request, $id, ?bool $isActive = null)
    {
        try {
         
            $request->validate([
                'user_type' => ['required', Rule::in(['SERVICE_PROVIDER', 'FSA_AGENT', 'GRA_PERSONNEL'])],
            ]);
    
            // Get the authenticated user
            $admin = Auth::user();
    
            // Ensure the authenticated user is a FEMSAdmin
            if (!$admin instanceof FEMSAdmin) {
                \Log::warning('Unauthorized attempt to update isActive', ['auth_user' => $admin]);
                return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 401);
            }
    
            // Determine the user type and find the user
            $user = match (strtoupper($request->user_type)) {
                'SERVICE_PROVIDER' => ServiceProvider::findOrFail($id),
                'FSA_AGENT' => FireServiceAgent::findOrFail($id),
                'GRA_PERSONNEL' => GRA::findOrFail($id),
            };
    
            // If $isActive is explicitly set (e.g., from deactivate method), use it; otherwise, require it in the request
            $newStatus = $isActive ?? $request->boolean('isActive');
    
            // Update isActive status
            $user->update(['isActive' => $newStatus]);
    
            \Log::info('Successfully updated isActive status', ['user' => $user]);
    
            return response()->json(['message' => 'User isActive status updated successfully', 'user' => $user]);
    
        } catch (\Exception $e) {
            \Log::error('Error updating isActive status', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }
    
    
    // Activate a user
    public function activate(Request $request, $id)
    {
        return $this->updateIsActive($request, $id, true);
    }

    // Deactivate a user
    public function deactivate(Request $request, $id)
    {
        return $this->updateIsActive($request, $id, false);
    }

    // Update user details
    public function update(Request $request, $id)
    {
        try {
          
            $request->validate([
                'user_type' => ['required', Rule::in(['SERVICE_PROVIDER', 'FSA_AGENT', 'GRA_PERSONNEL'])],
            ]);
    
            // Determine the user type and find the user
            $user = match (strtoupper($request->user_type)) {
                'SERVICE_PROVIDER' => ServiceProvider::findOrFail($id),
                'FSA_AGENT' => FireServiceAgent::findOrFail($id),
                'GRA_PERSONNEL' => GRA::findOrFail($id),
            };
    
            // Check if isActive is being updated
            if ($request->has('isActive')) {
                \Log::info('isActive field detected in update request', ['isActive' => $request->isActive]);
    
                // Call the updateIsActive method
                return $this->updateIsActive($request, $id, $request->boolean('isActive'));
            }
    
            // Update other user details
            $user->update($request->except('isActive'));
    
            \Log::info('Successfully updated user details', ['user' => $user]);
    
            return response()->json(['message' => 'User details updated successfully', 'user' => $user]);
    
        } catch (\Exception $e) {
            \Log::error('Error updating user details', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }


    //Show by user type

    public function showbyUserType(Request $request)
    {
        try {
          
            // Validate the request
            $request->validate([
                'user_type' => ['required', Rule::in(['SERVICE_PROVIDER', 'FSA_AGENT', 'GRA_PERSONNEL'])],
            ]);
    
            // Get the authenticated admin
            $admin = Auth::user();
    
            // Ensure the authenticated user is a FEMSAdmin
            if (!$admin instanceof FEMSAdmin) {
                \Log::warning('Unauthorized attempt to access showbyUserType', ['auth_user' => $admin]);
                return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 401);
            }
    
            // Determine the user type and fetch the users
            $users = match (strtoupper($request->user_type)) {
                'SERVICE_PROVIDER' => ServiceProvider::all(),
                'FSA_AGENT' => FireServiceAgent::all(),
                'GRA_PERSONNEL' => GRA::all(),
            };
    
            \Log::info('Users retrieved successfully', ['user_type' => $request->user_type, 'count' => count($users)]);
    
            return response()->json([
                'message' => 'Users retrieved successfully',
                'user_type' => $request->user_type,
                'users' => $users,
            ], 200);
    
        } catch (\Exception $e) {
            \Log::error('Error in showbyUserType method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }

    // Delete a user
    public function destroy(Request $request, $id)
    {
        try {
            
            // Validate the request
            $request->validate([
                'user_type' => ['required', Rule::in(['SERVICE_PROVIDER', 'FSA_AGENT', 'GRA_PERSONNEL'])],
            ]);

            // Get the authenticated admin
            $admin = Auth::user();

            if (!$admin) {
                return response()->json(['message' => 'Unauthorized: Admin user not found'], 401);
            }

            // Ensure the authenticated user is a FEMSAdmin
            if (!$admin instanceof FEMSAdmin) {
                \Log::warning('Unauthorized attempt to delete user', ['auth_user' => $admin]);
                return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 401);
            }

            // Determine the user type and find the user
            $user = match (strtoupper($request->user_type)) {
                'SERVICE_PROVIDER' => ServiceProvider::find($id),
                'FSA_AGENT' => FireServiceAgent::find($id),
                'GRA_PERSONNEL' => GRA::find($id),
            };

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Perform soft delete
            $user->delete();

            \Log::info('Successfully soft deleted user', ['user' => $user]);

            return response()->json(['message' => 'User soft deleted successfully'], 200);
        } catch (\Exception $e) {
            \Log::error('Error soft deleting user', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }

    // Validate email
    public function validateEmail(Request $request)
    {
        try {
            \Log::info('validateEmail method called', ['request_data' => $request->all()]);

            // Validate the request
            $request->validate([
                'user_type' => ['required', Rule::in(['SERVICE_PROVIDER', 'FSA_AGENT', 'GRA_PERSONNEL'])],
                'email' => 'required|string|email',
            ]);

            // Determine the user type and check if the email exists
            $emailExists = match (strtoupper($request->user_type)) {
                'SERVICE_PROVIDER' => ServiceProvider::where('email', $request->email)->exists(),
                'FSA_AGENT' => FireServiceAgent::where('email', $request->email)->exists(),
                'GRA_PERSONNEL' => GRA::where('email', $request->email)->exists(),
            };

            if ($emailExists) {
                return response()->json(['message' => 'Email already exists'], 409); // Conflict
            }

            return response()->json(['message' => 'Email is available'], 200);

        } catch (\Exception $e) {
            \Log::error('Error in validateEmail method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }

    // Validate phone
    public function validatePhone(Request $request)
    {
        try {
            \Log::info('validatePhone method called', ['request_data' => $request->all()]);

            // Validate the request
            $request->validate([
                'user_type' => ['required', Rule::in(['SERVICE_PROVIDER', 'FSA_AGENT', 'GRA_PERSONNEL'])],
                'phone' => 'required|string',
            ]);

            // Determine the user type and check if the phone exists
            $phoneExists = match (strtoupper($request->user_type)) {
                'SERVICE_PROVIDER' => ServiceProvider::where('phone', $request->phone)->exists(),
                'FSA_AGENT' => FireServiceAgent::where('phone', $request->phone)->exists(),
                'GRA_PERSONNEL' => GRA::where('phone', $request->phone)->exists(),
            };

            if ($phoneExists) {
                return response()->json(['message' => 'Phone number already exists'], 409); // Conflict
            }

            return response()->json(['message' => 'Phone number is available'], 200);

        } catch (\Exception $e) {
            \Log::error('Error in validatePhone method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }
    
    public function adminDashboard()
    {
        try {
            // Ensure the authenticated user is a FEMSAdmin
            $admin = Auth::user();
            if (get_class($admin) != "App\Models\FEMSAdmin") {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Fetch the required statistics
            $totalEquipments = Equipment::count(); // Total number of equipments
            $activeEquipments = Equipment::where('isActive', true)->count(); // Active equipments
            $inactiveEquipments = Equipment::where('isActive', false)->count(); // Inactive equipments
            $totalServiceProviders = ServiceProvider::count(); // Total number of service providers
            $totalFireServiceAgents = FireServiceAgent::count(); // Total number of fire service agents
            $totalGRAs = GRA::count(); // Total number of GRAs
            $totalCertificates = Certificate::count(); // Total number of certificates
            $verifiedCertificates = Certificate::where('isVerified', true)->count(); // Verified certificates
            $unverifiedCertificates = $totalCertificates - $verifiedCertificates; // Not verified certificates
            $totalInvoices = Invoicing::count(); // Total number of invoices

            // Return the statistics
            return response()->json([
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'total_equipments' => $totalEquipments,
                    'active_equipments' => $activeEquipments,
                    'inactive_equipments' => $inactiveEquipments,
                    'total_certificates' => $totalCertificates,
                    'verified_certificates' => $verifiedCertificates,
                    'unverified_certificates' => $unverifiedCertificates,
                    'total_invoices' => $totalInvoices,
                    'total_service_providers' => $totalServiceProviders,
                    'total_fire_service_agents' => $totalFireServiceAgents,
                    'total_gras' => $totalGRAs,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in dashboard method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred while retrieving dashboard statistics'], 500);
        }
    }

    /**
     * Show the dashboard for GRA personnel.
     *
     * @return \Illuminate\Http\Response
     */

    public function graDashboard()
    {
        try {
            // Ensure the authenticated user is a GRA
            $gra = Auth::user();
            if (get_class($gra) != "App\Models\GRA") {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Get today's date in Y-m-d format
            $today = now()->toDateString();

            // Build queries filtered by today
            $spQuery = Invoicing::whereDate('created_at', $today);
            $fsaQuery = InvoicesbyFSA::whereDate('created_at', $today);

            // Total number of invoices by Service Provider (today)
            $totalSPInvoices = $spQuery->count();

            // Total number of invoices by FSA (today)
            $invoicebyFSA = $fsaQuery->count();

            // Total sum of payment amounts for Service Providers' invoices (today)
            $totalSPPayments = $spQuery->sum('payment_amount');

            // Total sum of payment amounts for FSA Agents' invoices (today)
            $totalFSAPayments = $fsaQuery->sum('payment_amount');

            // Total sum of all payments (Service Providers + FSA Agents)
            $totalAllPayments = $totalSPPayments + $totalFSAPayments;

            // Return the statistics
            return response()->json([
                'message' => 'Dashboard statistics for today retrieved successfully',
                'data' => [
                    'total_service_provider_invoices' => $totalSPInvoices,
                    'total_FSA_invoices' => $invoicebyFSA,
                    'total_service_provider_payments' => $totalSPPayments,
                    'total_FSA_payments' => $totalFSAPayments,
                    'total_all_payments' => $totalAllPayments,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in dashboard method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred while retrieving dashboard statistics'], 500);
        }
    }

    public function serviceProviderDashboard()
    {
        try {
            // Ensure the authenticated user is a Service Provider
            $serviceProvider = Auth::user();
            if (get_class($serviceProvider) != "App\Models\ServiceProvider") {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Fetch the required statistics
            $totalEquipments = Equipment::where('service_provider_id', $serviceProvider->id)->count(); // Total number of equipments by the service provider
            $activeEquipments = Equipment::where('service_provider_id', $serviceProvider->id)->where('isActive', true)->count(); // Active equipments by the service provider
            $inactiveEquipments = Equipment::where('service_provider_id', $serviceProvider->id)->where('isActive', false)->count(); // Inactive equipments by the service provider
            $totalInvoices = Invoicing::where('service_provider_id', $serviceProvider->id)->count(); // Total number of invoices by the service provider
            $totalClients = Client::where('created_by', $serviceProvider->id)->count(); // Total number of clients
            $totalIndividualClients = Client::where('created_by', $serviceProvider->id)->where('client_type', 'INDIVIDUAL')->count(); // Total number of individual clients created by the service provider
            $totalCorporateClients = Client::where('created_by', $serviceProvider->id)->where('client_type', 'CORPORATE')->count(); // Total number of corporate clients created by the service provider

            // Return the statistics
            return response()->json([
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'total_equipments' => $totalEquipments,
                    'active_equipments' => $activeEquipments,
                    'inactive_equipments' => $inactiveEquipments,
                    'total_invoices' => $totalInvoices,
                    'total_clients' => $totalClients,
                    'total_individual_clients' => $totalIndividualClients,
                    'total_corporate_clients' => $totalCorporateClients,

                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in dashboard method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred while retrieving dashboard statistics'], 500);
        }
    }

    public function fireServiceAgentDashboard()
    {
        try {
            // Ensure the authenticated user is a Fire Service Agent
            $fireServiceAgent = Auth::user();
            if (get_class($fireServiceAgent) != "App\Models\FireServiceAgent") {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Fetch the required statistics
            $totalCertificates = Certificate::where('fsa_id', $fireServiceAgent->id)->count(); // Total number of certificates by the fire service agent
            $verifiedCertificates = Certificate::where('isVerified', true)->where('fsa_id', $fireServiceAgent->id)->count(); // Verified certificates by the fire service agent
            $unverifiedCertificates = $totalCertificates - $verifiedCertificates; //  Unverified certificates by the fire service agent
            $totalNewCertificates = Certificate::where('certificate_id', 1)->where('fsa_id', $fireServiceAgent->id)->count(); // Total number of new certificates by the fire service agent
            $FirePermitCertificates = Certificate::where('certificate_id', 2)->where('fsa_id', $fireServiceAgent->id)->count(); // Total number of Fire Permit certificates by the fire service agent
            $RenewalCertificates = Certificate::where('certificate_id', 3)->where('fsa_id', $fireServiceAgent->id)->count(); // Total number of renewal certificates by the fire service agent
            
            // Return the statistics
            return response()->json([
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'total_certificates' => $totalCertificates,
                    'verified_certificates' => $verifiedCertificates,
                    'unverified_certificates' => $unverifiedCertificates,
                    'total_new_certificates' => $totalNewCertificates,
                    'fire_permit_certificates' => $FirePermitCertificates,
                    'renewal_certificates' => $RenewalCertificates,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in dashboard method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred while retrieving dashboard statistics'], 500);
        }
    }

    public function graReport(Request $request)
    {
        try {
            // Ensure the authenticated user is a GRA
            $gra = Auth::user();
            if (get_class($gra) != "App\Models\GRA") {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Get date boundaries from request (optional)
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // Build queries with optional date filtering
            $spQuery = Invoicing::query();
            $fsaQuery = InvoicesbyFSA::query();

            if ($startDate) {
                $spQuery->whereDate('created_at', '>=', $startDate);
                $fsaQuery->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $spQuery->whereDate('created_at', '<=', $endDate);
                $fsaQuery->whereDate('created_at', '<=', $endDate);
            }

            // Total number of invoices by Service Provider (filtered)
            $totalSPInvoices = $spQuery->count() ?? 0;

            // Total number of invoices by FSA (filtered)
            $invoicebyFSA = $fsaQuery->count() ?? 0;

            // Total sum of payment amounts for Service Providers' invoices (filtered)
            $totalSPPayments = $spQuery->sum('payment_amount') ?? 0;

            // Total sum of payment amounts for FSA Agents' invoices (filtered)
            $totalFSAPayments = $fsaQuery->sum('payment_amount') ?? 0;

            // Total sum of all payments (Service Providers + FSA Agents)
            $totalAllPayments = $totalSPPayments + $totalFSAPayments;

            // Return the statistics (always output 0 if no records)
            return response()->json([
                'message' => 'Report statistics retrieved successfully',
                'data' => [
                    'total_service_provider_invoices' => $totalSPInvoices,
                    'total_FSA_invoices' => $invoicebyFSA,
                    'total_service_provider_payments' => $totalSPPayments,
                    'total_FSA_payments' => $totalFSAPayments,
                    'total_all_payments' => $totalAllPayments,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in graReport method', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred while retrieving report statistics'], 500);
        }
    }
}
