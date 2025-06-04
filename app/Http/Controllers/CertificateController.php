<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\CertificateType;
use App\Models\FireServiceAgent;
use App\Models\Client;
use App\Models\FEMSAdmin;
use Illuminate\Support\Facades\Auth;


class CertificateController extends Controller
{
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

        $certificates = Certificate::with('certificateType', 'fireServiceAgent', 'client')->get();

        // Add client name based on client_type
        $certificates = $certificates->map(function ($certificate) {
            if ($certificate->client) {
                if ($certificate->client->client_type === 'INDIVIDUAL') {
                    $individualClient = \DB::table('individual_clients')
                        ->where('client_id', $certificate->client->id)
                        ->first(['first_name', 'middle_name', 'last_name']);
                    $certificate->client->name = $individualClient
                        ? trim("{$individualClient->first_name} {$individualClient->middle_name} {$individualClient->last_name}")
                        : null;
                } elseif ($certificate->client->client_type === 'CORPORATE') {
                    $certificate->client->name = \DB::table('corporate_clients')
                        ->where('client_id', $certificate->client->id)
                        ->value('company_name');
                }
             $certificate->invoice_status = $this->isCertificateInvoiced($certificate->invoice_status);
    
            }
            return $certificate;
        });

        return response()->json(['data' => $certificates], 200);
    }

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
            // Validate the request
            $request->validate([
                'certificate_id' => 'required|exists:certificate_types,id',
                'client_id' => 'required|exists:clients,id',
                'fsa_id' => 'required|exists:fire_service_agents,id',
                'issued_date' => 'required|date',
                'expiry_date' => 'required|date|after:issued_date',
                'certificate_upload' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            ]);

            // Handle the file upload
            if ($request->hasFile('certificate_upload')) {
                $file = $request->file('certificate_upload');

                // Fetch the certificate type name
                $certificateTypeName = CertificateType::where('id', $request->certificate_id)->value('certificate_name');
                $clientType = Client::where('id', $request->client_id)->value('client_type');

                // Determine the client name
                if ($clientType === 'INDIVIDUAL') {
                    $clientName = \DB::table('individual_clients')->where('client_id', $request->client_id)->value('first_name');
                } elseif ($clientType === 'CORPORATE') {
                    $clientName = \DB::table('corporate_clients')->where('client_id', $request->client_id)->value('company_name');
                } else {
                    $clientName = 'unknown_client';
                }

                // Generate the file name
                $fileName = $certificateTypeName . '_' . $clientName . '_' . $request->client_id . '_' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();

                // Store the file in S3
                $filePath = $file->storeAs('certificates', $fileName, 's3');

                // Generate the full URL for the uploaded file
                $baseURL = env('AWS_URL', config('filesystems.disks.s3.url'));
                $documentUrl = $baseURL . '/certificates/' . $fileName;
            } else {
                return response()->json(['message' => 'Certificate upload is required'], 400);
            }

            // Check if certificate_id already exists for the client
            $existingCertificate = Certificate::where('client_id', $request->client_id)
                ->where('certificate_id', $request->certificate_id)
                ->first();
            if ($existingCertificate) {
                return response()->json(['message' => 'This type of certificate already exists for this client'], 400);
            }

            // Create the certificate record
            $certificate = Certificate::create([
                'certificate_id' => $request->certificate_id,
                'client_id' => $request->client_id,
                'fsa_id' => $request->fsa_id,
                'issued_date' => $request->issued_date,
                'expiry_date' => $request->expiry_date,
                'created_by' => $user->id,
                'created_by_type' => get_class($user),
                'certificate_upload' => $documentUrl,
            ]);

            // After creating the equipment and related records
            if ($user instanceof \App\Models\FireServiceAgent) {
                // Find all FEMSAdmin users
                $admins = \App\Models\FEMSAdmin::all();
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\CertificateCreatedNotification($certificate, $user));
                }
            }

            return response()->json([
                'message' => 'Certificate created successfully',
                'data' => $certificate,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in CertificatesController@store', [
                'errors' => $e->errors(),
            ]);
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Error in CertificatesController@store', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while creating the certificate', 'error' => $e->getMessage()], 500);
        }
    }

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
            // Find the certificate
            $certificate = Certificate::findOrFail($id);

            // Validate the request
            $request->validate([
                'certificate_id' => 'sometimes|exists:certificate_types,id',
                'client_id' => 'sometimes|exists:clients,id',
                'fsa_id' => 'nullable|exists:fire_service_agents,id',
                'issued_date' => 'sometimes|date',
                'expiry_date' => 'sometimes|date|after:issued_date',
                'certificate_upload' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:2048', // File upload validation
            ]);

            // Handle the file upload if provided
            if ($request->hasFile('certificate_upload')) {
                $file = $request->file('certificate_upload');

                // Fetch the certificate type name
                $certificateTypeName = CertificateType::where('id', $request->certificate_id ?? $certificate->certificate_id)->value('certificate_name') ?? 'certificate';

                // Determine the client name based on client type
                $clientType = Client::where('id', $request->client_id ?? $certificate->client_id)->value('client_type');
                if ($clientType === 'INDIVIDUAL') {
                    $clientName = \DB::table('individual_clients')->where('client_id', $request->client_id ?? $certificate->client_id)->value('first_name');
                } elseif ($clientType === 'CORPORATE') {
                    $clientName = \DB::table('corporate_clients')->where('client_id', $request->client_id ?? $certificate->client_id)->value('company_name');
                } else {
                    $clientName = 'unknown_client';
                }

                // Generate the file name
                $fileName = $certificateTypeName . '_' . $clientName . '_' . ($request->client_id ?? $certificate->client_id) . '_' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();

                // Store the file in S3
                $filePath = $file->storeAs('certificates', $fileName, 's3');

                // Generate the full URL for the uploaded file
                $baseURL = env('AWS_URL', config('filesystems.disks.s3.url'));
                $documentUrl = $baseURL . '/certificates/' . $fileName;

                // Delete the old file from S3 if it exists
                if ($certificate->certificate_upload) {
                    $oldFilePath = str_replace($baseURL . '/', '', $certificate->certificate_upload);
                    Storage::disk('s3')->delete($oldFilePath);
                }

                // Update the certificate record with the new file path
                $certificate->update([
                    'certificate_upload' => $documentUrl,
                ]);
            }

            // Update the certificate record with other fields
            $certificate->update([
                'certificate_id' => $request->certificate_id ?? $certificate->certificate_id,
                'client_id' => $request->client_id ?? $certificate->client_id,
                'fsa_id' => $request->fsa_id ?? $certificate->fsa_id,
                'issued_date' => $request->issued_date ?? $certificate->issued_date,
                'expiry_date' => $request->expiry_date ?? $certificate->expiry_date,
            ]);

            return response()->json([
                'message' => 'Certificate updated successfully',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            \Log::error('Validation error in CertificatesController@update', [
                'errors' => $e->errors(),
            ]);
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // Handle general errors
            \Log::error('Error in CertificatesController@update', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while updating the certificate', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Find the certificate
            $certificate = Certificate::findOrFail($id);

            // Soft delete the certificate
            $certificate->delete();

            return response()->json(['message' => 'Certificate soft deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Certificate not found in destroy method', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Certificate not found', 'error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            \Log::error('Error in CertificatesController@destroy', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while deleting the certificate', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCertificateByClientID($client_id)
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
            // Retrieve certificates for the given client_id
            $certificates = Certificate::with('certificateType', 'fireServiceAgent', 'client')
                ->where('client_id', $client_id)
                ->get();

            if ($certificates->isEmpty()) {
                return response()->json(['message' => 'No certificates found for the specified client'], 200);
            }

            // Add client name based on client_type
            $certificates = $certificates->map(function ($certificate) {
                if ($certificate->client) {
                    if ($certificate->client->client_type === 'INDIVIDUAL') {
                        $individualClient = \DB::table('individual_clients')
                            ->where('client_id', $certificate->client->id)
                            ->first(['first_name', 'middle_name', 'last_name']);
                        $certificate->client->name = $individualClient
                            ? trim("{$individualClient->first_name} {$individualClient->middle_name} {$individualClient->last_name}")
                            : null;
                    } elseif ($certificate->client->client_type === 'CORPORATE') {
                        $certificate->client->name = \DB::table('corporate_clients')
                            ->where('client_id', $certificate->client->id)
                            ->value('company_name');
                    }
            $certificate->isVerified = $this->isCertificateVerified($certificate->isVerified);
            $certificate->invoice_status = $this->isCertificateInvoiced($certificate->invoice_status);

                }
                return $certificate;
            });

            return response()->json(['message' => 'Certificates retrieved successfully', 'data' => $certificates], 200);
        } catch (\Exception $e) {
            \Log::error('Error in CertificatesController@getCertificateByClientID', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while retrieving certificates', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCertificateByID($id)
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
            // Retrieve the certificate by ID
            $certificate = Certificate::with('certificateType', 'fireServiceAgent', 'client')
                ->findOrFail($id);

            // Check if the certificate has expired and update the status if necessary
            if (now()->greaterThan($certificate->expiry_date)) {
                $certificate->status = 'expired';
                $certificate->save(); // Update the status in the database
            }

            // Add client name based on client_type
            if ($certificate->client) {
                if ($certificate->client->client_type === 'INDIVIDUAL') {
                    $individualClient = \DB::table('individual_clients')
                        ->where('client_id', $certificate->client->id)
                        ->first(['first_name', 'middle_name', 'last_name']);
                    $certificate->client->name = $individualClient
                        ? trim("{$individualClient->first_name} {$individualClient->middle_name} {$individualClient->last_name}")
                        : null;
                } elseif ($certificate->client->client_type === 'CORPORATE') {
                    $certificate->client->name = \DB::table('corporate_clients')
                        ->where('client_id', $certificate->client->id)
                        ->value('company_name');
                }
            }

            $certificate->isVerified = $this->isCertificateVerified($certificate->isVerified);
            $certificate->invoice_status = $this->isCertificateInvoiced($certificate->invoice_status);

            
            // Add QR code URL if user is FEMSAdmin
            if (($user instanceof \App\Models\FEMSAdmin) || ($user instanceof \App\Models\FireServiceAgent)) {
                $qrCode = \App\Models\QRCode::where('serial_number', $certificate->serial_number)->first();
                if ($qrCode && $qrCode->qr_code_path) {
                    $qrCodeUrl = Storage::disk('s3')->url($qrCode->qr_code_path);
                    $certificate->qr_code_url = $qrCodeUrl;
                } else {
                    $certificate->qr_code_url = null;
                }
            }


            return response()->json(['message' => 'Certificate retrieved successfully', 'data' => $certificate], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Certificate not found in getCertificateByID method', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Certificate not found', 'error' => $e->getMessage()], 200);
        } catch (\Exception $e) {
            \Log::error('Error in CertificatesController@getCertificateByID', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while retrieving the certificate', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCertificateBySerialNumber($serial_number){

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
            // Retrieve the certificate by ID
            $certificate = Certificate::with('certificateType', 'fireServiceAgent', 'client')
                ->where('serial_number', $serial_number)
                ->firstOrFail();

            // Check if the certificate has expired and update the status if necessary
            if (now()->greaterThan($certificate->expiry_date)) {
                $certificate->status = 'expired';
                $certificate->save(); // Update the status in the database
            }

            // Add client name based on client_type
            if ($certificate->client) {
                if ($certificate->client->client_type === 'INDIVIDUAL') {
                    $individualClient = \DB::table('individual_clients')
                        ->where('client_id', $certificate->client->id)
                        ->first(['first_name', 'middle_name', 'last_name']);
                    $certificate->client->name = $individualClient
                        ? trim("{$individualClient->first_name} {$individualClient->middle_name} {$individualClient->last_name}")
                        : null;
                } elseif ($certificate->client->client_type === 'CORPORATE') {
                    $certificate->client->name = \DB::table('corporate_clients')
                        ->where('client_id', $certificate->client->id)
                        ->value('company_name');
                }
            }

            $certificate->isVerified = $this->isCertificateVerified($certificate->isVerified);
            $certificate->invoice_status = $this->isCertificateInvoiced($certificate->invoice_status);


            return response()->json(['message' => 'Certificate retrieved successfully', 'data' => $certificate], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Certificate not found in getCertificateByID method', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Certificate not found', 'error' => $e->getMessage()], 200);
        } catch (\Exception $e) {
            \Log::error('Error in CertificatesController@getCertificateByID', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while retrieving the certificate', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCertificateByCertificateType($certificate_type_id)
    {
        try {
            // Verify if the user is authenticated
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Check if the user has the required role
            if (get_class($user) != "App\Models\FireServiceAgent" && get_class($user) != "App\Models\FEMSAdmin") {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Retrieve certificates for the given certificate_type_id
            $certificates = Certificate::with('certificateType', 'fireServiceAgent', 'client')
                ->where('certificate_id', $certificate_type_id)
                ->get();

            if ($certificates->isEmpty()) {
                return response()->json(['message' => 'No certificates found for the specified certificate type'], 200);
            }

            // Add isVerified status to each certificate
            $certificates = $certificates->map(function ($certificate) {
                $certificate->isVerified = $this->isCertificateVerified($certificate->isVerified);
                $certificate->invoice_status = $this->isCertificateInvoiced($certificate->invoice_status);
                return $certificate;
            });

            return response()->json(['message' => 'Certificates retrieved successfully', 'data' => $certificates], 200);
        } catch (\Exception $e) {
            \Log::error('Error in CertificatesController@getCertificateByCertificateType', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while retrieving certificates', 'error' => $e->getMessage()], 500);
        }
    }

    private function isCertificateVerified($isVerified)
    {
        return $isVerified == 1 ? 'verified' : 'not verified';
    }

    private function isCertificateInvoiced($invoice_status)
    {
        switch ($invoice_status) {
            case 0:
                return 'not invoiced';
            case 1:
                return 'invoiced';
            default:
                return 'unknown status';
        }
    }
}
