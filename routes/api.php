<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CorporateClientsController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ValidatePhoneNumber;
use App\Http\Controllers\ValidateEmailController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


//Client Routes
Route::post('client/fems/register', [ClientController::class, 'store'])->name('register_client');

Route::post('client/login', [ClientController::class, 'login'])->name('login_client');

Route::post('client/otp/validate', [ValidatePhoneNumber::class, 'validateOTP'])->name('validate_client_otp');
Route::post('client/resend/otp', [ValidatePhoneNumber::class, 'resendOTP'])->name('resend_otp');
Route::post('client/email/validate', [ValidateEmailController::class, 'validateEmail'])->name('validate_client_email');
Route::post('client/fems/email/resend', [ValidateEmailController::class, 'resendemail'])->name('resend_email');

//Password Reset - Client
Route::post('client/password/forgotpassword', [ForgotPasswordController::class, 'submitForgetPasswordForm'])->name('client_forgot_password');
Route::post('client/password/resetpassword', [ForgotPasswordController::class, 'submitResetPasswordForm'])->name('client_reset_password');
Route::get('/client/all', [ClientController::class, 'index'])->name('all_clients');

//Route::get('/client/corporate/all', [CorporateClientsController::class, 'index'])->name('all_corporate_clients');

  //Password Reset - User
//Route::post('user/password/forget_password', [ForgotUserPasswordController::class, 'submitForgetPasswordForm'])->name('user_forgot_password');
//Route::post('user/password/reset_password', [ForgotUserPasswordController::class, 'submitResetPasswordForm'])->name('user_reset_password');



//---------------------------------------------------------------------------------------------------------//

//Protected routes which require authentication
Route::group(['middleware'=>['auth:sanctum']],function(){
//   Route::get('/client/all', [ClientController::class, 'index'])->name('all_clients');
//   Route::get('/client/all/{id}', [ClientController::class, 'getAllClients'])->name('client');
//   Route::delete('/client/{id}', [ClientController::class, 'destroy'])->name('delete_client');
// //    Route::get('/client/searchbytype/{search}', [ClientController::class, 'search'])->name('search_client');
//   Route::get('/client/searchbytype/{search}/{id}', [ClientController::class, 'search'])->name('search_client');
//   Route::get('/client/{id}', [ClientController::class, 'show'])->name('show_client');

});


// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
