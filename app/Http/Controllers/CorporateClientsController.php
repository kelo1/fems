<?php

namespace App\Http\Controllers;
use App\Models\Client;
use App\Models\Corporate_clients;
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

    //Store Corporate client details
    public function store(Request $request)
    { 
        $client_id = $request->id;
        $company_email = $request->email;   
        $company_phone = $request->phone;

        //Check if the Company exists
        $check_client = Client::where('company_email',$company_email)->orWhere('company_phone',$company_phone)->first();

        if($check_client){
                
            return response()->json(['message' => 'Client already exist'], 404);
        }
        
        else{

            //Store corporate client details
        
        DB::table('corporate_clients')->insert([
            'company_name' => $request->company_name,
            'company_address' => $request->company_address,
            'company_email' => $company_email,
            'company_phone' => $company_phone,
            'certificate_of_incorporation'=> $request->certificate_of_incorporation,
            'company_registration'=> $request->company_registration,
            'client_id' => $client_id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        //Check if document type is certificate of incorporation
        if($request->certificate_of_incorporation){
            $file = $request->file;
            $filePath = $client_id.'/'.'certificate_of_incorporation_upload_'.$currentTime->toDateTimeString().'_'.$file->getClientOriginalName();
            Storage::disk('s3')->put($filePath, file_get_contents($file));

            DB::table('corporate_clients')
            ->where('id', $client_id)
            ->update([
                'document' =>$filePath
            ]);

            }

        //Check if document type is business registration
        if($request->company_registration){
            $file = $request->file;
            $filePath = $client_id.'/'.'company_registration_upload_'.$currentTime->toDateTimeString().'_'.$file->getClientOriginalName();
            Storage::disk('s3')->put($filePath, file_get_contents($file));

            DB::table('corporate_clients')
            ->where('id', $client_id)
            ->update([
                'document' =>$filePath
            ]);

            }    

        }
    }
}
