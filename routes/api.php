<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CorporateClientsController;
use App\Http\Controllers\IndividualClientsController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ValidatePhoneNumber;
use App\Http\Controllers\ValidateEmailController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\ServiceProviderController;
use App\Http\Controllers\FireServiceAgentController;
use App\Http\Controllers\FEMSAdminController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GRAController;
use App\Http\Controllers\UserTypeController;
use App\Http\Controllers\LicenseTypeController;
use App\Http\Controllers\CorporateTypeController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CustomerTypeController;


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

//Authentication Routes
Route::post('/user/register', [AuthController::class, 'signUp']);
Route::post('/user/login', [AuthController::class, 'signIn']);

//FEMS Admin Routes
Route::post('/fems_admin/login', [FEMSAdminController::class, 'login'])->name('login_fems_admin');

//Client Routes

Route::post('client/login', [ClientController::class, 'login'])->name('login_client');

Route::post('client/otp/validate', [ValidatePhoneNumber::class, 'validateOTP'])->name('validate_client_otp');
Route::post('client/resend/otp', [ValidatePhoneNumber::class, 'resendOTP'])->name('resend_otp');
Route::post('client/email/validate', [ValidateEmailController::class, 'validateEmail'])->name('validate_client_email');
Route::post('client/fems/email/resend', [ValidateEmailController::class, 'resendemail'])->name('resend_email');

//Password Reset - Client
Route::post('client/password/forgotpassword', [ForgotPasswordController::class, 'submitForgetPasswordForm'])->name('client_forgot_password');
Route::post('client/password/resetpassword', [ForgotPasswordController::class, 'submitResetPasswordForm'])->name('client_reset_password');


// User Type Routes
Route::get('user_type/all', [UserTypeController::class, 'index'])->name('user_type_index');

// License Type Routes
Route::get('license_type/all', [LicenseTypeController::class, 'index'])->name('license_type_index');

// Corporate Type Routes
Route::get('corporate_type/all', [CorporateTypeController::class, 'index'])->name('corporate_type_index');

// Customer Type Routes
Route::get('customer_type/all', [CustomerTypeController::class, 'index'])->name('customer_type_index');

// Validate User Phone Number and Email Routes
Route::post('/user/validate_email', [AuthController::class, 'validateEmail'])->name('validate_email');
Route::post('/user/validate_phone', [AuthController::class, 'validatePhone'])->name('validate_phone_number');

//---------------------------------------------------------------------------------------------------------//

//Protected routes which require authentication
Route::group(['middleware'=>['auth:sanctum']], function(){

  //Protected User Routes
  Route::post('/user/logout', [AuthController::class, 'logout'])->name('user_logout');
  Route::put('/user/activate/{id}', [AuthController::class, 'activate'])->name('activate_user');
  Route::put('/user/deactivate/{id}', [AuthController::class, 'deactivate'])->name('deactivate_user');
  Route::get('/user/all', [AuthController::class, 'index'])->name('all_users');
  Route::put('/user/update_user/{id}', [AuthController::class, 'update'])->name('user_update');
  Route::delete('/user/delete_user/{id}', [AuthController::class, 'destroy'])->name('user_delete');
  Route::post('/user/show_by_user_type', [AuthController::class, 'showbyUserType'])->name('user_show');
  
  //Protected Client Routes
  Route::post('/client/create_corporate_client', [ClientController::class, 'createCorporateClient'])->name('register_corporate_client');
  Route::post('/client/create_individual_client', [ClientController::class, 'createIndividualClient'])->name('register_individual_client');
  Route::get('/client/all', [ClientController::class, 'index'])->name('all_clients');
  Route::post('/client/bulk_upload', [ClientController::class, 'bulkUpload'])->name('upload_clients');
  Route::put('/client/update_client/{id}', [ClientController::class, 'update'])->name('update_client');
  Route::delete('/client/delete_client/{id}', [ClientController::class, 'destroy'])->name('delete_client');
  Route::get('/client/show/{id}', [ClientController::class, 'show'])->name('client_show');
  Route::post('/client/getclientbycorporatetype/', [ClientController::class, 'getClientByCorporateType'])->name('clients_by_corporate_type');
  Route::post('/client/getClientByIndividual/', [ClientController::class, 'getClientByIndividual'])->name('clients_by_individual_type');
  Route::post('/client/logout', [ClientController::class, 'logout'])->name('client_logout'); 
  Route::get('/client/uploads/{id}', [ClientController::class, 'getClientUploads'])->name('client_uploads');
  Route::delete('/client/delete_upload/{id}', [ClientController::class, 'deleteClientUpload'])->name('delete_client_upload');
  
  //Protected Corporate Client Routes
  Route::get('/client/corporate_clients', [CorporateClientsController::class, 'getCorporateClients'])->name('all_corporate_clients');
  Route::get('/client/corporate_clients/{id}', [CorporateClientsController::class, 'getCorporateClientByID'])->name('corporate_client_by_id');  
  

  // Individual Clients Routes
  Route::get('/client/individual_clients', [IndividualClientsController::class, 'getIndividualClients'])->name('all_individual_clients');
  Route::get('/client/individual_clients/{id}', [IndividualClientsController::class, 'getIndividualClientsByID'])->name('individual_client_by_id');


  
  //Protected FEMS Admin Routes
  Route::get('/fems_admin/all', [FEMSAdminController::class, 'index'])->name('all_fire_service_admins');
  Route::post('/fems_admin/store', [FEMSAdminController::class, 'store'])->name('register_fire_service_admin');
  Route::post('/fems_admin/logout', [FEMSAdminController::class, 'logout'])->name('fire_service_admin_logout');
  Route::get('/fems_admin/{id}', [FEMSAdminController::class, 'show'])->name('fire_service_admin_show');
  Route::put('/fems_admin/update/{id}', [FEMSAdminController::class, 'update'])->name('fire_service_admin_update');
  Route::delete('/fems_admin/delete/{id}', [FEMSAdminController::class, 'destroy'])->name('fire_service_admin_delete');


  //Protected ServiceProvider Routes
  Route::get('/service_provider/{id}', [ServiceProviderController::class, 'show'])->name('service_provider_show');

  //Protected Fire Service Agent Routes
  Route::get('/fire_service_agent/{id}', [FireServiceAgentController::class, 'show'])->name('fire_service_agent_show');

  //Protected GRAs Routes
  Route::get('/gra/{id}', [GRAController::class, 'show'])->name('gra_show');


  //Protected Equipment Routes
  Route::post('/equipment/register', [EquipmentController::class, 'store'])->name('register_equipment');
  Route::post('/equipment/massupload', [EquipmentController::class, 'massUpload'])->name('register_equipment_in _mass');
  Route::get('/equipment/{id}', [EquipmentController::class, 'show'])->name('equipment_show');
  //Route::get('/equipment/{id}/decode_qr_code', [EquipmentController::class, 'decodeQrCode'])->name('equipment_decode_qr_code');
  Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment');
  Route::put('/equipment/{id}', [EquipmentController::class, 'update'])->name('equipment_update');
  Route::delete('/equipment/{id}', [EquipmentController::class, 'destroy'])->name('equipment_delete');
  Route::get('/equipment/service_provider/{service_provider_id}', [EquipmentController::class, 'getEquipmentByServiceProvider'])->name('equipment_by_service_provider');
  Route::get('/equipment/client/{client_id}', [EquipmentController::class, 'getEquipmentByClient'])->name('equipment_by_client');
  Route::get('/equipment/check_expired', [EquipmentController::class, 'checkExpiredEquipment'])->name('checkExpiredEquipment');
  Route::get('/equipment/check_expiring_soon', [EquipmentController::class, 'checkExpiringSoonEquipment'])->name('checkExpiringSoonEquipment');
  Route::post('/equipment/updateClientOrServiceProvider/{equipment_id}', [EquipmentController::class, 'updateClientOrServiceProvider'])->name('updateClientOrServiceProvider');
  Route::get('/equipment/details/{id}', [EquipmentController::class, 'getEquipmentByID'])->name('equipment_details');
  Route::get('/equipment/serial_number/{serial_number}', [EquipmentController::class, 'getEquipmentBySerialNumber'])->name('equipment_by_serial_number');

  //Protected QR Code Routes
  Route::post('/qrcode/generate/{serial_number}', [QRCodeController::class, 'generateQrCode'])->name('generate_qr_code');
  Route::put('/qrcode/update/{serial_number}', [QRCodeController::class, 'updateQrCode'])->name('decode_qr_code');
  Route::get('/qrcode/decode/{serial_number}', [QRCodeController::class, 'decodeQrCode'])->name('auth:sanctum');

  
  //Protected User Type Routes
  Route::post('/user_type/create', [UserTypeController::class, 'store'])->name('user_type_store');
  Route::delete('/user_type/delete/{id}', [UserTypeController::class, 'destroy'])->name('user_type_delete');

  //Protected License Type Routes
  Route::post('/license_type/create', [LicenseTypeController::class, 'store'])->name('license_type_store');
  Route::delete('/license_type/delete/{id}', [LicenseTypeController::class, 'destroy'])->name('license_type_delete');

  //Protected Billing Routes
  Route::get('/billing/service_provider/{serviceProviderId}', [BillingController::class, 'billingByServiceProvider'])->name('billing_by_service_provider');
  Route::post('/billing/create', [BillingController::class, 'store'])->name('setup_billing');
  Route::put('/billing/update/{id}', [BillingController::class, 'update'])->name('update_billing_items');
  Route::post('/billing/delete', [BillingController::class, 'destroy'])->name('delete_billing_items');
  Route::get('/billing/show/{id}', [BillingController::class, 'show'])->name('show_billitem');
  Route::get('/billing/activebills', [BillingController::class, 'ActiveBillItems'])->name('active_billing');
  Route::get('/billing/search/{search}', [BillingController::class, 'search'])->name('search_billitems');
  

});

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
