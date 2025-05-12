<?php

namespace App\Http\Controllers;
use App\Models\user;
use App\Models\ServiceProvider;
use App\Models\FireServiceAgent;
use App\Models\GRA;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
/*require 'vendor/autoload.php';
use Aws\Ses\Sesuser;
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
        // Validate the input fields
        $fields = $request->validate([
            'user_type' => 'required|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
            'id' => 'required|integer',
            'email_token' => 'required|string',
        ]);

        // Determine the model based on the user type
        $modelMap = [
            'SERVICE_PROVIDER' => \App\Models\ServiceProvider::class,
            'FSA_AGENT' => \App\Models\FireServiceAgent::class,
            'GRA_PERSONNEL' => \App\Models\GRA::class,
        ];

        $model = $modelMap[$fields['user_type']] ?? null;

        if (!$model) {
            return response()->json(['message' => 'Invalid user type provided'], 400);
        }

        // Retrieve the user by ID
        $user = $model::where('id', $fields['id'])->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if the email is already verified
        if ($user->email_verified_at !== null) {
            return response()->json(['message' => 'Email already verified!'], 200);
        }

        // Validate the email token
        if ($user->email_token !== $fields['email_token']) {
            return response()->json(['message' => 'Invalid email token provided'], 401);
        }

        // Update the email_verified_at column to the current timestamp
        $user->update(['email_verified_at' => Carbon::now()]);

        return response()->json(['message' => 'Email verification successful!'], 200);
    }


    public function resendEmail(Request $request)
    {
        // Validate the input fields
        $fields = $request->validate([
            'user_type' => 'required|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
            'id' => 'required|integer',
            'email' => 'required|string|email',
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

        // Retrieve the user by ID and email
        $user = $model::where('id', $fields['id'])
            ->where('email', $fields['email'])
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Generate a new email verification token
        $email_verification = Str::uuid()->toString();

        // Retrieve the user's name
        $user_name = $user->first_name ?? 'User';

        // Send the email notification
        $user->notify(new VerifyEmailNotification($user, $user_name, $fields['email'], $email_verification, $fields['user_type']));

        // Update the email_token in the database
        $user->update(['email_token' => $email_verification]);

        return response()->json(['message' => 'Verification email resent successfully.'], 200);
    }

    // public function resendOTP(Request $request)
    // {
    //     try {
    //         // Validate the input fields
    //         $fields = $request->validate([
    //             'user_type' => 'required|string|in:SERVICE_PROVIDER,FSA_AGENT,GRA_PERSONNEL',
    //             'phone' => 'required|string',
    //             'country' => 'required|string',
    //         ]);

    //         // Determine the model and table based on the user type
    //         $modelMap = [
    //             'SERVICE_PROVIDER' => \App\Models\ServiceProvider::class,
    //             'FSA_AGENT' => \App\Models\FireServiceAgent::class,
    //             'GRA_PERSONNEL' => \App\Models\GRA::class,
    //         ];

    //         $model = $modelMap[$fields['user_type']] ?? null;

    //         if (!$model) {
    //             return response()->json(['message' => 'Invalid user type provided'], 400);
    //         }

    //         // Find the user by phone number
    //         $user = $model::where('phone', $fields['phone'])->first();

    //         if (!$user) {
    //             return response()->json(['message' => 'User not found'], 404);
    //         }

    //         // Generate a new OTP
    //         $otp = $this->generateOTP();

    //         // Format the phone number based on the country
    //         $newUserPhone = $fields['phone'];
    //         if (strtolower($fields['country']) === 'ghana') {
    //             $newUserPhone = preg_replace('~^(?:0|\+?233)?~', '+233', $fields['phone']);
    //         } else {
    //             $newUserPhone = preg_replace('~^(?:0|\+?)~', '+', $fields['phone']);
    //         }

    //         // Send OTP to the user's phone number using AWS SNS
    //         $params = [
    //             'credentials' => [
    //                 'key' => env('AWS_ACCESS_KEY_ID'),
    //                 'secret' => env('AWS_SECRET_ACCESS_KEY'),
    //             ],
    //             'region' => env('AWS_DEFAULT_REGION'),
    //             'version' => 'latest',
    //         ];
    //         $sns = new \Aws\Sns\SnsClient($params);

    //         $args = [
    //             'MessageAttributes' => [
    //                 'AWS.SNS.SMS.SMSType' => [
    //                     'DataType' => 'String',
    //                     'StringValue' => 'Transactional',
    //                 ],
    //             ],
    //             'Message' => "This is your OTP: " . $otp,
    //             'PhoneNumber' => $newUserPhone,
    //         ];

    //         $sns->publish($args);

    //         // Update the OTP in the database
    //         $user->update(['OTP' => $otp]);

    //         return response()->json(['message' => 'OTP resent successfully. Kindly confirm OTP.'], 200);
    //     } catch (\Exception $e) {
    //         // Log the exception
    //         \Log::error('Failed to resend OTP', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);

    //         // Return a generic error response
    //         return response()->json(['message' => 'Failed to resend OTP. Please try again later.'], 500);
    //     }
    // }

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
