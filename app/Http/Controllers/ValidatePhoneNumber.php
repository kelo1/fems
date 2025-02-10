<?php

namespace App\Http\Controllers;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;


class ValidatePhoneNumber extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

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
    public function validateOTP(Request $request)
    {
        //$client_id = $request->session()->get('id');

        $client_id= $request->client_id;

        $fields = $request->validate([
            'OTP'=>'required|integer'
        ]);

         $client = Client::where('OTP',$fields['OTP'])->first();


        if(!$client){
            return response([
                'message'=>'Invalid OTP Entered!'
            ], 401);

        }
        else{
            DB::table('clients')
            ->where('id', $client_id)
            ->update(['sms_verified' => 1, ]);

            return response("Kindly Confirm Email", 200);
            //return View('validate_email');
        }


    }


    public function resendOTP(Request $request)
    {
        //$client_id= $request->id;

        $fields = $request->validate([ 'phone'=>'required|string'  ]);

        $client_phone = Client::where('phone',$fields['phone'])->value('phone');

        //Send OTP to client phone number

$new_client_phone = $client_phone;

//new_client_phone = ltrim($new_client_phone, "0");

//prepend country code
if($request->country=='Ghana'||$request->country=='GHANA'||$request->country=='ghana'){
    $new_client_phone = preg_replace('~^(?:0|\+?233)?~', '+233', $client_phone);
}

if($request->country=='UK'||$request->country=='Uk'||$request->country=='uk'){
$new_client_phone = preg_replace('~^(?:0|\+?44)?~', '+44', $client_phone);

}

    $otp = $this->generateOTP();


    $params = array(
        'credentials' => [
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
        'region' => env('AWS_DEFAULT_REGION'),
        'version' => 'latest'
    );
    $sns = new \Aws\Sns\SnsClient($params);

    $args = array(
            "MessageAttributes" =>[

            /*
                'AWS.SNS.SMS.SenderID'=>[
                'DataType' => 'String',
                'StringValue' =>'Transactional'
                ]
            */

            'AWS.SNS.SMS.SMSType'=>[
                'DataType' => 'String',
                'StringValue' =>'Transactional'
                ]

            ],
        "Message" => "This is your OTP: ".$otp,
        "PhoneNumber" => $new_client_phone
    );

    $result = $sns->publish($args);
    DB::table('clients')
    ->where('phone', $new_client_phone)
    ->update(['OTP' => $otp, ]);

    return response("Kindly Confirm OTP", 200);
    //return View('validate_number');

    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
