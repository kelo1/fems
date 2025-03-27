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
        return Corporate_clients::all();
     }

      // Get all corporate clients
    public function getCorporateClients()
    {
        $corporateClients = Corporate_clients::with('client', 'corporateType')->get();
        return response()->json($corporateClients);
    }

    public function getCorporateClientByID($id)
    {
        $corporateClient = Corporate_clients::with('client', 'corporateType')->where('client_id', $id)->first();
        return response()->json($corporateClient);
    }

    //Store Corporate client details
    public function store(Request $request)
    { 
        $client_id = $request->client_id;
        $company_email = $request->email;   
        $company_phone = $request->phone;

        // Log the initial request data
        Log::info('Corporate client store method called', $request->all());

        //Check if the Company exists
        $check_corporate_client = Corporate_clients::where('id', $client_id)->first();

        if ($check_corporate_client) {
            return response()->json(['message' => 'Corporate client already exist'], 404);
        }

        //Validate Corporate client details    
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_address' => 'required|string|max:255',
            'company_email' => 'required|string|max:255',
            'company_phone' => 'required|string|max:15',
            'certificate_of_incorporation' => 'nullable|string|max:255',           
            'company_registration' => 'nullable|string|max:255',
            'gps_address' => 'nullable|string|max:255',
            'client_id' => 'required|integer|unique:corporate_clients,client_id',
            'corporate_type_id' => 'required|integer|exists:corporate_types,id',
        ]);

        // Log the validated request data
        Log::info('Corporate client request data validated', $request->all());
        
        //Store corporate client details
        try {
            DB::table('corporate_clients')->insert([
                'company_name' => $request->company_name,
                'company_address' => $request->company_address,
                'company_email' => $request->company_email ?? $company_email,
                'company_phone' => $request->company_phone ?? $company_phone,
                'certificate_of_incorporation'=> $request->certificate_of_incorporation ?? 'No upload',
                'company_registration'=> $request->company_registration ?? 'No upload',
                'gps_address' => $request->gps_address,
                'corporate_type_id' => $request->corporate_type_id,
                'client_id' => $client_id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            Log::info('Corporate client details inserted', ['client_id' => $client_id]);

            //Check if document type is certificate of incorporation
            if($request->hasFile('file') && $request->certificate_of_incorporation){
                $file = $request->file('file');
                $filePath = $client_id.'/' . 'certificate_of_incorporation_upload_' . Carbon::now()->toDateTimeString().'_'.$file->getClientOriginalName();
                Storage::disk('s3')->put($filePath, file_get_contents($file));

                DB::table('corporate_clients')
                ->where('client_id', $client_id)
                ->update([
                    'certificate_of_incorporation' =>$filePath
                ]);

                Log::info('Certificate of incorporation uploaded', ['filePath' => $filePath]);
            }

            //Check if document type is business registration
            if($request->hasFile('file') && $request->company_registration){
                $file = $request->file('file');
                $filePath = $client_id.'/' . 'company_registration_upload_' . Carbon::now()->toDateTimeString().'_'.$file->getClientOriginalName();
                Storage::disk('s3')->put($filePath, file_get_contents($file));

                DB::table('corporate_clients')
                ->where('client_id', $client_id)
                ->update([
                    'company_registration' =>$filePath
                ]);

                Log::info('Company registration uploaded', ['filePath' => $filePath]);
            }    

            return response()->json(['message' => 'Corporate client created successfully'], 201);
        } catch (Exception $e) {
            Log::error('Failed to create corporate client details', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create corporate client details'], 500);
        }
    }
}
