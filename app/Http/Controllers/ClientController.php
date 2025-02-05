<?php

namespace App\Http\Controllers;
use App\Models\Client;
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
    //

     //Enable middleware route for authentication
    /*public function __construct()
    {
        $this->middleware('auth');

    }*/

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

     public function index()
    {
        return Client::all();


    }


    //OTP Generation Function
    public function generateOTP()
    {
        do {
            $otp = random_int(100000, 999999);
        } while (Client::where("otp", "=", $otp)->first());

        return $otp;
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

      //Validate request
       $request->validate([
        'email'=>'required|string',
        'phone'=>'required|string|unique:clients,phone',
        'password'=>'required|string|confirmed',
        'client_type'=>'required|string',
        
    ]);


    //Generate OTP
   $otp = $this->generateOTP();

   $email_verification = Str::uuid()->toString();

   $client = Client::create([
        'email'=>$request->email,
        'phone'=>$request->phone,
        'password'=>Hash::make($request->password),
        'client_type'=>$request->client_type,
        'OTP'=>$otp,
        'email_token'=>$email_verification
      ]);
    
      //Get the last inserted item
      $itemId = DB::getPdo()->lastInsertId();
      
      //Get email and phone number
      $client_email = Client::where('id',$itemId)->value('email');
      $client_phone = Client::where('id',$itemId)->value('phone');

      //Return response 
      response([
        'message'=>'Client created successfully',
        'email'=>$client_email,
        'phone'=>$client_phone,
        'id'=>$itemId   
        ], 201);


    $client_id = $itemId;
    $client_type = Client::where('id',$client_id)->value('client_type');
   
    //Check if client type is individual or corporate

if(strtoupper($client_type)=='INDIVIDUAL'){
    //Store individual client details
     app('App\Http\Controllers\IndividualClientsController')-> store($request);
   // app('App\Http\Controllers\IndividualClientsController')-> store($request);

}

elseif(strtoupper($client_type)=='CORPORATE'){
    //Store Corporate client details
     app('App\Http\Controllers\CorporateClientsController')-> store($request);


}

else{

    return response(["Message" => "The set customer type does not exist"], 404);

}

//Send OTP to client

//prepend country code to phone number

$new_client_phone = preg_replace('~^(?:0|\+?233)?~', '+233', $client_phone);


    $params = array(
        'credentials' => [
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
        'region' => env('AWS_DEFAULT_REGION'),
        'version' => 'latest'
    );
    $sns = new \Aws\Sns\SnsClient($params);

    try {
    $args = array(
            "MessageAttributes" =>[



            'AWS.SNS.SMS.SMSType'=>[
                'DataType' => 'String',
                'StringValue' =>'Transactional'
                ]

            ],
        "Message" => "This is your OTP: ".$otp,
        "PhoneNumber" => $new_client_phone
    );


   $sns->publish($args);

   if($sns->publish($args)[
    'message'
   ]){

    throw new Exception('SNS failed.');

   }

}

catch(Exception $e){


    DB::table('clients')->where('id',$client_id)->delete();

    $response =([
        'message' =>"Something went wrong, please contact Administrator ",
        'snsError' => $e->getMessage() . PHP_EOL
    ]);

    return response($response, 400);

}

//Send email verification to client

$toAddress = Client::where('id', $client_id)
        ->value('email');

//Get client name
if($client_type=='INDIVIDUAL'){
    $client_name = Individual_clients::where('client_id', $client_id)
        ->value('first_name');
}
else{
    $client_name = Corporate_clients::where('client_id', $client_id)
        ->value('company_name');
}

$client_name = Client::where('id', $client_id)
        ->value('first_name');


$client = Client::findOrFail($client_id);
$client->notify(new VerifyEmailNotification($client, $client_name, $toAddress, $email_verification));



    }


    public function login(Request $request){
        $fields = $request->validate([
            'email'=>'required|email',
            'password'=>'required|string'
        ]);

        //Check Email
        $client = Client::where('email',$fields['email'])->first();

        if(!$client){

            return response([
                'message'=>'Client not found!'
            ], 404);
        }


        //Check Password
        if(!$client || !Hash::check($fields['password'], $client->password)){
            return response([
                'message'=>'Invalid Credentials!'
            ], 401);

        }


        $email_verified= Client::where('id',$client->id)->value('email_verified_at');
        $phone_verified= Client::where('id',$client->id)->value('sms_verified');



        //Check Client Validation Status

        if($email_verified==null){
            return response([
                'message'=>'Kindly verify your email!'
            ], 401);
        }

        if($phone_verified==null){
            return response([
                'message'=>'Kindly verify your phone number!'
            ], 401);
        }

       $client_token =  $client->createToken($client->first_name)->plainTextToken;
       
       
        
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
                'ghanapost_gps' => $individual_details->ghanapost_gps,
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
                'ghanapost_gps' => $corporate_details->ghanapost_gps,
                'phone' => $client->phone,
                'session_id' => session()->get('client_id'),
                'client_type' => $client->client_type,
                'token' => $client_token
            ];
        } else {
            return response([
                'message'=>'Client does not belong to a client_type!'
            ], 401);
        }

        return response($response, 200);
    }

     /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

     //Client Logout
    public function logout(Request $request){

        $request->bearerToken();

        $client_id = $request->id;

        $client = Client::find($client_id);

         $tokenId = $client_id;


       $client->tokens()->where('tokenable_id', $tokenId)->delete();


         return response([
            'message'=>'Logout Successful'
        ], 200);
    }




}
