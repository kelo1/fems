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

            return Individual_clients::all();
    }

    //Store individual client details
    public function store(Request $request)
    {
        $client_id = $request->client_id;

        // Check if the client exists
        $check_individual_client = Individual_clients::where('id', $client_id)->first();

        if ($check_individual_client) {
            return response()->json(['message' => 'Individual Client already exist'], 404);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'ghanapost_gps' => 'nullable|string|max:255',
            'document_type' => 'required|string|max:255',
            'document' => 'nullable|string|max:255',
            'client_id' => 'required|integer|unique:individual_clients,client_id',
        ]);

        // Store individual client details
        DB::table('individual_clients')->insert([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'address' => $request->address,
            'ghanapost_gps' => $request->ghanapost_gps,
            'document_type' => $request->document_type,
            'document' => $request->document ?? 'No upload',
            'client_id' => $client_id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        // Check if document type is passport
        if ($request->hasFile('file') && strtoupper($request->document_type) == 'PASSPORT') {
            $file = $request->file('file');
            $filePath = $client_id . '/' . 'passport_upload_' . Carbon::now()->toDateTimeString() . '_' . $file->getClientOriginalName();
            Storage::disk('s3')->put($filePath, file_get_contents($file));

            DB::table('individual_clients')
                ->where('client_id', $client_id)
                ->update([
                    'document' => $filePath
                ]);
        }

        // Check if document type is national id
        if ($request->hasFile('file') && strtoupper($request->document_type) == 'NATIONAL_ID') {
            $file = $request->file('file');
            $filePath = $client_id . '/' . 'national_id_upload_' . Carbon::now()->toDateTimeString() . '_' . $file->getClientOriginalName();
            Storage::disk('s3')->put($filePath, file_get_contents($file));

            DB::table('individual_clients')
                ->where('client_id', $client_id)
                ->update([
                    'document' => $filePath
                ]);
        }

    }

  
}
