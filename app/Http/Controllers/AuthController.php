<?php

namespace App\Http\Controllers;

use App\Models\ServiceProvider;
use App\Models\FireServiceAgent;
use App\Models\GRA;
use App\Models\LicenseType;
use App\Models\FEMSAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class AuthController extends Controller
{
    // Show all users
    public function index()
    {
         // Get the authenticated admin
         $admin = Auth::user();
    
         if (!$admin) {
             return response()->json(['message' => 'Unauthorized: Admin user not found'], 403);
         }
 
         // Get the authenticated user
         $admin = Auth::user();
     
         // Ensure the authenticated user is a FEMSAdmin
         if (!$admin instanceof FEMSAdmin) {
             \Log::warning('Unauthorized attempt to update isActive', ['auth_user' => $admin]);
             return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 403);
         }

        // Check authorization using policy
        if (!Gate::allows('update-isActive', $admin)) {
            \Log::warning('Unauthorized attempt to update isActive', ['auth_user' => $admin, 'target_id' => $id, 'user_type' => $request->user_type]);
            return response()->json(['message' => 'Unauthorized'], 403);
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


    /**
     * Sign up a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */ 

     public function signUp(Request $request)
    {
    \Log::info("SignUp method called", $request->all());

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

        DB::commit();
        return response()->json(['message' => 'User registered successfully', 'user' => $user], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error("Error creating user: " . $e->getMessage());
        return response()->json(['error' => 'An error occurred while creating user'], 500);
    }
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
        \Log::info('SignIn method called', ['request' => $request->all()]);

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

        // Generate API token
        $token = $user->createToken('auth_token')->plainTextToken;

        \Log::info('User signed in successfully', ['user_id' => $user->id, 'user_type' => $userType]);

        return response()->json([
            'message' => 'User signed in successfully',
            'token' => $token,
            'user' => $user,
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
            \Log::info('updateIsActive method called', ['id' => $id, 'request_data' => $request->all(), 'isActive' => $isActive]);
    
            $request->validate([
                'user_type' => ['required', Rule::in(['SERVICE_PROVIDER', 'FSA_AGENT', 'GRA_PERSONNEL'])],
            ]);
    
            // Get the authenticated user
            $admin = Auth::user();
    
            // Ensure the authenticated user is a FEMSAdmin
            if (!$admin instanceof FEMSAdmin) {
                \Log::warning('Unauthorized attempt to update isActive', ['auth_user' => $admin]);
                return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 403);
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
            \Log::info('update method called', ['id' => $id, 'request_data' => $request->all()]);
    
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
            \Log::info('showbyUserType method called', ['request_data' => $request->all()]);
    
            // Validate the request
            $request->validate([
                'user_type' => ['required', Rule::in(['SERVICE_PROVIDER', 'FSA_AGENT', 'GRA_PERSONNEL'])],
            ]);
    
            // Get the authenticated admin
            $admin = Auth::user();
    
            // Ensure the authenticated user is a FEMSAdmin
            if (!$admin instanceof FEMSAdmin) {
                \Log::warning('Unauthorized attempt to access showbyUserType', ['auth_user' => $admin]);
                return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 403);
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
            \Log::info('destroy method called', ['id' => $id, 'request_data' => $request->all()]);
    
            $request->validate([
                'user_type' => ['required', Rule::in(['SERVICE_PROVIDER', 'FSA_AGENT', 'GRA_PERSONNEL'])],
            ]);
    
            // Get the authenticated admin
            $admin = Auth::user();
    
            if (!$admin) {
                return response()->json(['message' => 'Unauthorized: Admin user not found'], 403);
            }
    
            // Get the authenticated user
            $admin = Auth::user();
        
            // Ensure the authenticated user is a FEMSAdmin
            if (!$admin instanceof FEMSAdmin) {
                \Log::warning('Unauthorized attempt to update isActive', ['auth_user' => $admin]);
                return response()->json(['message' => 'You\'re Unauthorized to perform this action'], 403);
            }

            // Determine the user type and find the user
            $user = match (strtoupper($request->user_type)) {
                'SERVICE_PROVIDER' => ServiceProvider::findOrFail($id),
                'FSA_AGENT' => FireServiceAgent::findOrFail($id),
                'GRA_PERSONNEL' => GRA::findOrFail($id),
            };
    
            // Delete the user
            $user->delete();
    
            \Log::info('Successfully deleted user', ['user' => $user]);
    
            return response()->json(['message' => 'User deleted successfully']);
    
        } catch (\Exception $e) {
            \Log::error('Error deleting user', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }


}
