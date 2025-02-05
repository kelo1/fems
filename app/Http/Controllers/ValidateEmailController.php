<?php

namespace App\Http\Controllers;
use App\Models\Client;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
/*require 'vendor/autoload.php';
use Aws\Ses\SesClient;
use Aws\Exception\AwsException;
*/


class ValidateEmailController extends Controller
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validateEmail(Request $request)
    {
       // $client = Client::findOrFail($email_token);
       $email_token = $request->email_token;

      $client_id =  $request->id;

      $email_token_query = Client::where('id',$client_id)->value('email_token');

      $email_verified = Client::where('id',$client_id)->value('email_verified_at');

      if($email_verified!=NULL){
        return response([
            'message'=>'Email already verified!'
        ], 200);
      }
      else{
           if($email_token_query==$email_token){

        DB::table('clients')
        ->where('email_token', $email_token)
        ->update(['email_verified_at' => Carbon::now(), ]);

        return response([
            'message'=>'Email Verification successful!'
        ], 200);
        }
     }

    }


    public function resendEmail(Request $request)
    {
       $fields = $request->validate([ 'email'=>'required|string'  ]);

        $toAddress = Client::where('email',$fields['email'])->value('email');

        $client_id=$request->id;

        $email_verification = Str::uuid()->toString();

        $client_name = Client::where('id', $client_id)
                ->value('first_name');


        $client = Client::findOrFail($client_id);
        $client->notify(new VerifyEmailNotification($client, $client_name, $toAddress, $email_verification));


         DB::table('clients')
    ->where('email', $toAddress)
    ->update(['email_token' => $email_verification, ]);

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
