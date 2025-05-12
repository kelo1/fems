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
use App\Http\Controllers\InvoicingController;
use App\Http\Controllers\InvoicesbyFSAController;
use App\Http\Controllers\EquipmentActivityController;
use App\Http\Controllers\ServiceProviderDevicesController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTypeController;



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

Route::get('/csrf-test', function () {
  return response()->json(['message' => 'Hello without middleware!']);
});

Route::get('/test-session', function () {
  return response()->json([
      'session_id' => session()->getId(),
      'session_data' => session()->all(),
  ]);
});


Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->json(['message' => 'CSRF cookie set']);
});

//Authentication Routes
Route::post('/user/register', [AuthController::class, 'signUp']);
Route::post('/user/login', [AuthController::class, 'signIn']);

//FEMS Admin Routes
Route::post('/fems_admin/login', [FEMSAdminController::class, 'login'])->name('login_fems_admin');

//Client Routes

Route::post('client/login', [ClientController::class, 'login'])->name('login_client');

// Validate User Phone Number and Email Routes
Route::post('user/otp/validate', [ValidatePhoneNumber::class, 'validateOTP'])->name('validate_user_otp');
Route::post('user/resend/otp', [ValidatePhoneNumber::class, 'resendOTP'])->name('resend_otp');
Route::post('user/email/validate', [ValidateEmailController::class, 'validateEmail'])->name('validate_user_email');
Route::post('user/email/resend', [ValidateEmailController::class, 'resendEmail'])->name('resend_email');

//Password Reset - User
Route::post('user/password/forgotpassword', [ForgotPasswordController::class, 'submitForgetPasswordForm'])->name('client_forgot_password');
Route::post('user/password/resetpassword', [ForgotPasswordController::class, 'submitResetPasswordForm'])->name('client_reset_password');


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
Route::group(['middleware'=>['web','auth:sanctum']], function(){

  //Protected User Routes
  Route::post('/user/logout', [AuthController::class, 'logout'])->name('user_logout');
  Route::put('/user/activate/{id}', [AuthController::class, 'activate'])->name('activate_user');
  Route::put('/user/deactivate/{id}', [AuthController::class, 'deactivate'])->name('deactivate_user');
  Route::get('/user/all', [AuthController::class, 'index'])->name('all_users');
  Route::put('/user/update_user/{id}', [AuthController::class, 'update'])->name('user_update');
  Route::delete('/user/delete_user/{id}', [AuthController::class, 'destroy'])->name('user_delete');
  Route::post('/user/show_by_user_type', [AuthController::class, 'showbyUserType'])->name('user_show');
  Route::post('/user/user_by_id/{id}', [AuthController::class, 'getUserByID'])->name('user_by_id');


  //Protected User Dashboard Routes
  Route::get('/FEMSAdmin/dashboard', [AuthController::class, 'adminDashboard'])->name('user_dashboard');
  Route::get('/service_provider/dashboard', [AuthController::class, 'serviceProviderDashboard'])->name('service_provider_dashboard');
  Route::get('/fire_service_agent/dashboard', [AuthController::class, 'fireServiceAgentDashboard'])->name('fire_service_agent_dashboard');
  Route::get('/gra/dashboard', [AuthController::class, 'graDashboard'])->name('gra_dashboard');

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
  Route::post('/client/uploads/update', [ClientController::class, 'updateClientUploads'])->name('update_client_uploads');
  Route::get('/client/history/{client_id}', [ClientController::class, 'getClientHistory']);

  //Protected Corporate Client Routes
  Route::get('/client/corporate_clients', [CorporateClientsController::class, 'getCorporateClients'])->name('all_corporate_clients');
  Route::get('/client/corporate_clients/{id}', [CorporateClientsController::class, 'getCorporateClientByID'])->name('corporate_client_by_id');  
  Route::get('/corporate_clients', [CorporateClientsController::class, 'index'])->name('all_corporate_clients_admin');


  // Individual Clients Routes
  Route::get('/client/individual_clients', [IndividualClientsController::class, 'getIndividualClients'])->name('all_individual_clients');
  Route::get('/client/individual_clients/{id}', [IndividualClientsController::class, 'getIndividualClientsByID'])->name('individual_client_by_id');

  Route::get('/individual_clients', [IndividualClientsController::class, 'index'])->name('all_individual_clients_admin');

  
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
  Route::get('/equipment/history/{equipment_id}', [EquipmentController::class, 'getEquipmentHistory'])->name('equipment_history');
  Route::post('/equipment/createIndividualClient/{serial_number}', [EquipmentController::class, 'createIndividualClientEquipment'])->name('create_individualclient_equipment');
  Route::post('/equipment/createCorporateClient/{serial_number}', [EquipmentController::class, 'createCorporateClientEquipment'])->name('create_individualclient_equipment');




  //Protected QR Code Routes
  Route::post('/qrcode/equipment/{serial_number}', [QRCodeController::class, 'generateEquipmentQrCode'])->name('generate_qr_code');
  Route::post('/qrcode/certificate/{serial_number}', [QRCodeController::class, 'generateCertificateQrCode'])->name('decode_qr_code');
  Route::get('/qrcode/decode/{serial_number}', [QRCodeController::class, 'decodeQrCode'])->name('auth:sanctum');

  
  //Protected User Type Routes
  Route::post('/user_type/create', [UserTypeController::class, 'store'])->name('user_type_store');
  Route::delete('/user_type/delete/{id}', [UserTypeController::class, 'destroy'])->name('user_type_delete');

  //Protected License Type Routes
  Route::post('/license_type/create', [LicenseTypeController::class, 'store'])->name('license_type_store');
  Route::delete('/license_type/delete/{id}', [LicenseTypeController::class, 'destroy'])->name('license_type_delete');

  //Protected Billing Routes
  Route::get('/billing/service_provider/{serviceProviderId}', [BillingController::class, 'billingByServiceProvider'])->name('billing_by_service_provider');
  Route::get('/billing/fsa/{fsaID}', [BillingController::class, 'billingByFSA'])->name('billing_by_FSA');
  Route::post('/billing/create', [BillingController::class, 'store'])->name('setup_billing');
  Route::put('/billing/update/{id}', [BillingController::class, 'update'])->name('update_billing_items');
  Route::delete('/billing/delete', [BillingController::class, 'destroy'])->name('delete_billing_items');
  Route::get('/billing/show/{id}', [BillingController::class, 'show'])->name('show_billitem');
  Route::get('/billing/activebills', [BillingController::class, 'ActiveBillItems'])->name('active_billing');
  Route::get('/billing/search/{search}', [BillingController::class, 'search'])->name('search_billitems');
  Route::put('/billing/service_provider/vat_rate/{serviceProviderId}', [BillingController::class, 'updateVATRate']);
  
  //Protected Invoicing Routes
  Route::post('/invoicing/all', [InvoicingController::class, 'index'])->name('invoice_all');
  Route::post('/invoicing/create', [InvoicingController::class, 'generateInvoice'])->name('create_invoicing');
  Route::post('/invoicing/generate_invoice_pdf', [InvoicingController::class, 'generateInvoicePDF']);
  Route::get('/invoicing/{id}', [InvoicingController::class, 'show'])->name('invoicing_show');
  Route::get('/invoicing/service_provider/{serviceProviderId}', [InvoicingController::class, 'getInvoiceByServiceProvider'])->name('invoicing_by_service_provider');
  Route::get('/invoicing/client/{clientId}', [InvoicingController::class, 'getInvoiceByClient'])->name('invoicing_by_client');
  Route::put('/invoicing/update/{id}', [InvoicingController::class, 'update'])->name('update_invoicing');
  Route::delete('/invoicing/delete/{id}', [InvoicingController::class, 'destroy'])->name('delete_invoicing');
  Route::get('/invoices/edit/{id}', [InvoicingController::class, 'edit'])->name('edit_invoicing');

  //Protected FSA Invoicing Routes
  Route::post('/fsa/invoice/all', [InvoicesbyFSAController::class, 'index'])->name('fsa_invoices_all');
  Route::post('/fsa/invoice/generate', [InvoicesbyFSAController::class, 'generateInvoice'])->name('fsa_generate_invoice');
  Route::get('/fsa/invoice_by_fsa/{fsaId}', [InvoicesbyFSAController::class, 'getInvoiceByFSA'])->name('fsa_invoicing_by_FSA');
  Route::get('/fsa/invoice/{id}', [InvoicesbyFSAController::class, 'show'])->name('fsa_invoice_show');
  Route::put('/fsa/invoice/update/{id}', [InvoicesbyFSAController::class, 'update'])->name('update_fsa_invoice');
  Route::delete('/fsa/invoice/{id}', [InvoicesbyFSAController::class, 'destroy'])->name('delete_fsa_invoice');
  Route::get('/fsa/invoices/edit/{id}', [InvoicesbyFSAController::class, 'edit'])->name('edit_fsa_invoice');

  //Protected Equipment Activity Routes
  Route::get('/equipment_activity/all', [EquipmentActivityController::class, 'index'])->name('all_equipment_activities');
  Route::post('/equipment_activity/create', [EquipmentActivityController::class, 'store'])->name('create_equipment_activity');
  
  //Protected Service Provider Devices Routes
  Route::get('/service_provider_device/all', [ServiceProviderDevicesController::class, 'index'])->name('all_service_provider_devices');
  Route::post('/service_provider_device/create', [ServiceProviderDevicesController::class, 'store'])->name('create_service_provider_device');
  Route::put('/service_provider_device/update/{id}', [ServiceProviderDevicesController::class, 'update'])->name('update_service_provider_device');
  Route::get('/service_provider_device/{id}', [ServiceProviderDevicesController::class, 'show'])->name('service_provider_devices_show');

  //Protected Certificate Types Routes
  Route::get('/certificate_type/all', [CertificateTypeController::class, 'index'])->name('all_certificate_types');
  Route::post('/certificate_type/create', [CertificateTypeController::class, 'store'])->name('create_certificate_type');
  Route::put('/certificate_type/update/{id}', [CertificateTypeController::class, 'update'])->name('update_certificate_type');
  Route::delete('/certificate_type/{id}', [CertificateTypeController::class, 'destroy'])->name('delete_certificate_type');
  Route::get('/certificate_type/{id}', [CertificateTypeController::class, 'show'])->name('certificate_type_show');

  //Proteceted Certificate Routes
  Route::get('/certificate/all', [CertificateController::class, 'index'])->name('all_certificates');
  Route::post('/certificate/create', [CertificateController::class, 'store'])->name('create_certificate');
  Route::put('/certificate/update/{id}', [CertificateController::class, 'update'])->name('update_certificate');
  Route::delete('/certificate/{id}', [CertificateController::class, 'destroy'])->name('delete_certificate');
  Route::get('/certificate/{id}', [CertificateController::class, 'getCertificateByID'])->name('certificate_show');
  Route::get('/certificate/client/{client_id}', [CertificateController::class, 'getCertificateByClientID'])->name('certificate_by_client');
  Route::get('/certificates/type/{certificate_type_id}', [CertificateController::class, 'getCertificateByCertificateType']);

});

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
