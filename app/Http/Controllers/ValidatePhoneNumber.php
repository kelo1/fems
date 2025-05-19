<?php

namespace App\Http\Controllers;
use App\Models\ServiceProvider;
use App\Models\FireServiceAgent;
use App\Models\GRA;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Aws\Sns\SnsClient;
use Aws\Exception\AwsException;


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

    public function generateOTP($userType)
    {
        // Map user types to their respective models
        $modelMap = [
            'SERVICE_PROVIDER' => \App\Models\ServiceProvider::class,
            'FSA_AGENT' => \App\Models\FireServiceAgent::class,
            'GRA_PERSONNEL' => \App\Models\GRA::class,
        ];

        $model = $modelMap[strtoupper($userType)] ?? null;

        if (!$model) {
            throw new \Exception("Invalid user type provided for OTP generation.");
        }

        do {
            // Generate a random 6-digit OTP
            $otp = random_int(100000, 999999);
        } while ($model::where('OTP', $otp)->exists());

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
        try {
            // Validate the input fields
            $fields = $request->validate([
                'user_type' => 'required|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
                'id' => 'required|integer',
                'OTP' => 'required|integer',
            ]);

            // Determine the model and table based on the user type
            $modelMap = [
                'SERVICE_PROVIDER' => \App\Models\ServiceProvider::class,
                'FSA_AGENT' => \App\Models\FireServiceAgent::class,
                'GRA_PERSONNEL' => \App\Models\GRA::class,
            ];

            $model = $modelMap[$fields['user_type']] ?? null;

            if (!$model) {
                return response()->json(['message' => 'Invalid user type provided'], 400);
            }

            // Find the user by ID and OTP
            $user = $model::where('id', $fields['id'])
                ->where('OTP', $fields['OTP'])
                ->first();

            if (!$user) {
                return response()->json(['message' => 'Invalid OTP or user not found'], 401);
            }

            // Update the `sms_verified` column to true
            $user->update(['sms_verified' => true]);

            return response()->json(['message' => 'OTP validated successfully. Kindly confirm your email.'], 200);
        } catch (\Exception $e) {
            // Log the exception
            \Log::error('Error validating OTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return a generic error response
            return response()->json(['message' => 'An error occurred while validating OTP. Please try again later.'], 500);
        }
    }


    public function resendOTP(Request $request)
    {
        try {
            // Validate the input fields
            $fields = $request->validate([
                'user_type' => 'required|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
                'phone' => 'required|string',
            ]);

            // Determine the model and table based on the user type
            $modelMap = [
                'SERVICE_PROVIDER' => \App\Models\ServiceProvider::class,
                'FSA_AGENT' => \App\Models\FireServiceAgent::class,
                'GRA_PERSONNEL' => \App\Models\GRA::class,
            ];

            $model = $modelMap[$fields['user_type']] ?? null;

            if (!$model) {
                return response()->json(['message' => 'Invalid user type provided'], 400);
            }

            // Find the user by phone number
            $user = $model::where('phone', $fields['phone'])->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Generate a new OTP
            $otp = $this->generateOTP($fields['user_type']);
        
            $formattedPhone = preg_replace('~^(?:0|\+?233)?~', '+233', $phone);

            // Twilio client configuration
            $twilioSid = env('TWILIO_ACCOUNT_SID');
            $twilioToken = env('TWILIO_AUTH_TOKEN');

            $twilio = new \Twilio\Rest\Client($twilioSid, $twilioToken);

            // Send the SMS
            $twilio->messages->create(
                $formattedPhone,
                [
                    'messagingServiceSid' => env('TWILIO_MESSAGING_SERVICE_SID'),
                    'body' => "Your Guardian Safety OTP is: $otp",
                ]
            );
   

            // Update the OTP in the database
            $user->update(['OTP' => $otp]);

            return response()->json(['message' => 'OTP resent successfully. Kindly confirm OTP.'], 200);
        } catch (\Exception $e) {
            // Log the exception
            \Log::error('Failed to resend OTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return a generic error response
            return response()->json(['message' => 'Failed to resend OTP. Please try again later.'], 500);
        }
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
