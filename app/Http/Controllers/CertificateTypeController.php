<?php

namespace App\Http\Controllers;

use App\Models\CertificateType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\FireServiceAgent;
use App\Models\FEMSAdmin;


class CertificateTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
         // Verify if the user is authenticated
         $user = Auth::user();
         if (!$user) {
             return response()->json(['message' => 'Unauthorized'], 401);
         }
 
         // Check if the user has the required role
         if (get_class($user) != "App\Models\FireServiceAgent" && get_class($user) != "App\Models\FEMSAdmin") {
             return response()->json(['message' => 'Unauthorized'], 403);
         }
         

        try {
            $certificateTypes = CertificateType::all();
            return response()->json(['message' => 'Certificate types retrieved successfully', 'data' => $certificateTypes], 200);
        } catch (\Exception $e) {
            \Log::error('Error in CertificateTypeController@index', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while retrieving certificate types', 'error' => $e->getMessage()], 500);
        }
    }

   

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
         // Verify if the user is authenticated
         $user = Auth::user();
         if (!$user) {
             return response()->json(['message' => 'Unauthorized'], 401);
         }
 
         // Check if the user has the required role
         if (get_class($user) != "App\Models\FireServiceAgent" && get_class($user) != "App\Models\FEMSAdmin") {
             return response()->json(['message' => 'Unauthorized'], 403);
         }
         

        try {
            $request->validate([
                'certificate_name' => 'required|string|unique:certificate_types,certificate_name|max:255',
              
            ]);

            $certificateType = CertificateType::create([
                'certificate_name' => $request->certificate_name,
                'created_by' => $user->id,
                'created_by_type' => get_class($user),
            ]);

            return response()->json(['message' => 'Certificate type created successfully', 'data' => $certificateType], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in CertificateTypeController@store', [
                'errors' => $e->errors(),
            ]);
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Error in CertificateTypeController@store', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while creating the certificate type', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CertificateType  $certificateType
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
         // Verify if the user is authenticated
         $user = Auth::user();
         if (!$user) {
             return response()->json(['message' => 'Unauthorized'], 401);
         }
 
         // Check if the user has the required role
         if (get_class($user) != "App\Models\FireServiceAgent" && get_class($user) != "App\Models\FEMSAdmin") {
             return response()->json(['message' => 'Unauthorized'], 403);
         }
         

        try {
            $certificateType = CertificateType::findOrFail($id);
            $fireServiceAgentName = null;
            if ($certificateType->created_by_type === "App\Models\FireServiceAgent") {
                $fireServiceAgent = FireServiceAgent::find($certificateType->created_by);
                $fireServiceAgentName = $fireServiceAgent ? $fireServiceAgent->name : null;
            }
            if ($certificateType->created_by_type === "App\Models\FEMSAdmin") {
                $femsAdmin = FEMSAdmin::find($certificateType->created_by);
                $fireServiceAgentName = $femsAdmin ? $femsAdmin->name : null;
            }

            return response()->json([
                'message' => 'Certificate type retrieved successfully',
                'data' => $certificateType,
                'created_by_name' => $fireServiceAgentName,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Certificate type not found in CertificateTypeController@show', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Certificate type not found', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            \Log::error('Error in CertificateTypeController@show', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while retrieving the certificate type', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CertificateType  $certificateType
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {   

         // Verify if the user is authenticated
         $user = Auth::user();
         if (!$user) {
             return response()->json(['message' => 'Unauthorized'], 401);
         }
 
         // Check if the user has the required role
         if (get_class($user) != "App\Models\FireServiceAgent" && get_class($user) != "App\Models\FEMSAdmin") {
             return response()->json(['message' => 'Unauthorized'], 403);
         }
         
        try {
            $certificateType = CertificateType::findOrFail($id);

            $request->validate([
                'certificate_name' => 'sometimes|string|unique:certificate_types,certificate_name,' . $id . '|max:255',
               
            ]);

            $certificateType->update([
                'certificate_name' => $request->input('certificate_name', $certificateType->certificate_name),
                'created_by' => $user->id,
                'created_by_type' => get_class($user),
            ]);
           
            

            return response()->json(['message' => 'Certificate type updated successfully', 'data' => $certificateType], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in CertificateTypeController@update', [
                'errors' => $e->errors(),
            ]);
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Certificate type not found in CertificateTypeController@update', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Certificate type not found', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            \Log::error('Error in CertificateTypeController@update', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while updating the certificate type', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CertificateType  $certificateType
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {       
         // Verify if the user is authenticated
         $user = Auth::user();
         if (!$user) {
             return response()->json(['message' => 'Unauthorized'], 401);
         }
 
         // Check if the user has the required role
         if (get_class($user) != "App\Models\FireServiceAgent" && get_class($user) != "App\Models\FEMSAdmin") {
             return response()->json(['message' => 'Unauthorized'], 403);
         }
         
        try {
            $certificateType = CertificateType::findOrFail($id);
            $certificateType->delete();

            return response()->json(['message' => 'Certificate type deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Certificate type not found in CertificateTypeController@destroy', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Certificate type not found', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            \Log::error('Error in CertificateTypeController@destroy', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while deleting the certificate type', 'error' => $e->getMessage()], 500);
        }
    }
}
