<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Notifications\ForgotClientPasswordNotification;

class ForgotPasswordController extends Controller
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
    public function store(Request $request)
    {
        //
    }
     /**
     * Send Forgot password Link via email
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    //
    public function submitForgetPasswordForm(Request $request)
      {



        $email=$request->email;

        $client_id = Client::where('email',$email)->value('id');

        $token = Str::random(64);

        $client_type = Client::where('id',$client_id)->value('client_type');

        if($client_type=='INDIVIDUAL'){
            $client_name = Individual_clients::where('client_id', $client_id)
            ->value('first_name');
        }
        else{
            $client_name = Corporate_clients::where('client_id', $client_id)
            ->value('company_name');
        }

            $fields =  $request->validate([
            'email' => 'required|email|exists:clients',
                ]);

          DB::table('password_resets')->insert([
              'email' => $request->email,
              'token' => $token,
              'created_at' => Carbon::now()
            ]);

           //Send Email to view
           $toAddress = Client::where('email',$fields['email'])->value('email');


           $client = Client::findOrFail($client_id);
        $client->notify(new ForgotClientPasswordNotification($client, $client_name, $toAddress, $token));


          $response = [
              'token'=>$token,
            'message'=> 'We have e-mailed your password reset link!',

         ];

         return response($response, 201);
      }

       /**
     * Reset Password
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

      public function submitResetPasswordForm(Request $request)
      {
            //$request->client_id;

          $request->validate([
             // 'email' => 'required|email|exists:clients',
              'password' => 'required|string|confirmed'
          ]);

          $updatePassword = DB::table('password_resets')
                              ->where([
                               // 'email' => $request->email,
                                'token' => $request->token
                              ])
                              ->first();

          if(!$updatePassword){
              return back()->withInput()->with('error', 'Invalid token!');
          }

          $client = Client::where('id', $request->id)
                      ->update(['password' => Hash::make($request->password),
                                 'updated_at' => Carbon::now()]);

          DB::table('password_resets')->where(['email'=> $request->email])->delete();


        $response = [
            'message'=> 'Password changed successfully!',

        ];

        return response($response, 200);

         // return redirect('customer/login')->with('message', 'Your password has been changed!');
      }


}
