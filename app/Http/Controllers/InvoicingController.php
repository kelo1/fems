<?php

namespace App\Http\Controllers;

use App\Models\Invoicing;
use Illuminate\Http\Request;
use App\Models\Equipment;
use App\Models\Billing;
use App\Models\ServiceProvider;
use App\Models\InvoicesbyFSA;
use App\Models\ServiceProviderVAT;
use App\Models\Client;
use App\Models\Corporate_clients;
use App\Models\Individual_clients;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class InvoicingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response(['message' => 'Unauthorized'], 403);
            }

            $user_type = $request->user_type;

            if ($user_type == 'FEMS_ADMIN' || $user_type == 'GRA_PERSONNEL') {
                // Retrieve all invoices with associated client and service provider details
                $invoicings = Invoicing::with(['client', 'serviceProvider'])->get();

                // Add client details dynamically based on client_type
                foreach ($invoicings as $invoice) {
                    $client = Client::find($invoice->client_id);

                    if ($client->client_type == 'CORPORATE') {
                        $invoice->client_details = Corporate_clients::where('client_id', $client->id)->first();
                    } else {
                        $invoice->client_details = Individual_clients::where('client_id', $client->id)->first();
                    }
                }
            } 
             else {
                return response(['message' => 'Unauthorized'], 403);
            }

            \Log::info('Invoices retrieved successfully', ['user_type' => $user_type, 'invoices' => $invoicings]);

            return response(['invoices' => $invoicings], 200);
        } catch (\Exception $e) {
            \Log::error('Exception occurred in index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['message' => 'An error occurred while retrieving invoices'], 500);
        }
    }

    public function generateRandomSequence()
    {
        do {
            $random_sequence = random_int(100000, 999999);
        } while (
            InvoicesbyFSA::where("invoice_number", "=", $random_sequence)->first() || 
            Invoicing::where("invoice_number", "=", $random_sequence)->first()
        );
        
        return $random_sequence;
    }


    /**
     * Show the form for creating a new resource.
     *@param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generateInvoice(Request $request)
    {
        try {
           
            $user = Auth::user();
            if (!$user) {
                \Log::error('Unauthorized access attempt');
                return response(['message' => 'Unauthorized'], 403);
            }

            $client_id = $request->client_id;
            $serial_number = $request->serial_number;
            $Invoice_items = $request->InvoiceItems;
             // Accept discount as an optional field
            $discount = $request->input('discount', 0);

            if (!$client_id || !$serial_number || !$Invoice_items) {
                \Log::error('Missing required parameters', [
                    'client_id' => $client_id,
                    'serial_number' => $serial_number,
                    'InvoiceItems' => $Invoice_items,
                ]);
                return response(['message' => 'Missing required parameters'], 400);
            }

            // Validate the request data
            $request->validate([
                'client_id' => 'required|integer',
                'serial_number' => 'required|string',
                'InvoiceItems' => 'required|array', // Updated to expect an array
            ]);

            \Log::info('Request validated successfully');

            // Check if the equipment exists
            $equipment = Equipment::where('serial_number', $serial_number)->first();
            if (!$equipment) {
                \Log::error('Equipment not found for serial number', ['serial_number' => $serial_number]);
                return response(['message' => 'Equipment not found'], 404);
            }
            \Log::info('Equipment found', ['equipment' => $equipment]);

            // Get client type
            $client_type = Client::where('id', $client_id)->value('client_type');
            if (!$client_type) {
                \Log::error('Client not found for client_id', ['client_id' => $client_id]);
                return response(['message' => 'Client not found'], 404);
            }
            \Log::info('Client type retrieved', ['client_type' => $client_type]);

            // Get service provider ID
            $service_provider_id = DB::table('equipment_service_providers')
                ->where('serial_number', $serial_number)
                ->where('status_service_provider', 1)
                ->value('service_provider_id');
            if (!$service_provider_id) {
                \Log::error('Service provider not found for serial number', ['serial_number' => $serial_number]);
                return response(['message' => 'Service provider not found'], 404);
            }
            \Log::info('Service provider ID retrieved', ['service_provider_id' => $service_provider_id]);

            // Find the service provider
            $service_provider = ServiceProvider::find($service_provider_id);
            if (!$service_provider) {
                \Log::error('Service provider not found', ['service_provider_id' => $service_provider_id]);
                return response(['message' => 'Service provider not found'], 404);
            }
            \Log::info('Service provider found', ['service_provider' => $service_provider]);

            // Get client details
            $client = null;
            if ($client_type == 'CORPORATE') {
                $client = Corporate_clients::with('client')->where('client_id', $client_id)->first();
            } else {
                $client = Individual_clients::with('client')->where('client_id', $client_id)->first();
            }
            if (!$client) {
                \Log::error('Client details not found', ['client_id' => $client_id]);
                return response(['message' => 'Client details not found'], 404);
            }
            \Log::info('Client details retrieved', ['client' => $client]);

            $client_name = $client_type == 'CORPORATE' ? $client->company_name : $client->first_name . ' ' . $client->last_name;
            $client_email = $client_type == 'CORPORATE' ? $client->company_email : $client->email;
            $client_phone = $client_type == 'CORPORATE' ? $client->company_phone : $client->phone;
            $client_address = $client_type == 'CORPORATE' ? $client->company_address : $client->address;
            $client_branch = $client_type == 'CORPORATE' ? $client->branch_name : null;

            \Log::info('Client information processed', [
                'client_name' => $client_name,
                'client_email' => $client_email,
                'client_phone' => $client_phone,
                'client_branch' => $client_branch,
            ]);

            // Create seller and buyer parties
            $seller = new Party([
                'name' => $service_provider->name,
                'phone' => $service_provider->phone,
                'custom_fields' => [
                    'email' => $service_provider->email,
                    'address' => $service_provider->address,
                ],
            ]);
            \Log::info('Seller party created', ['seller' => $seller]);

            $buyer = new Party([
                'name' => $client_type == 'CORPORATE' ? $client_name . ' (' . $client_branch . ')' : $client_name,
                'phone' => $client_phone,
                'custom_fields' => [
                    'email' => $client_email,
                    'address' => $client_address,
                    'Equipment serial number' => $equipment->serial_number,
                ],
            ]);
            \Log::info('Buyer party created', ['buyer' => $buyer]);

            // Use InvoiceItems directly as an array
            $InvoiceDecoded = $Invoice_items; // No need for json_decode
            \Log::info('Invoice items decoded', ['InvoiceDecoded' => $InvoiceDecoded]);

            $items = [];
            $totalAmount = 0;
            $totalWithholdingTax = 0; // Track total withholding tax

            foreach ($InvoiceDecoded as $key => $invoiceitem) {
                $billingItemId = $invoiceitem['billingitem_' . $key];
            
                // Fetch the billing item once
                $billingItem = Billing::find($billingItemId);
            
                if (!$billingItem) {
                    \Log::warning("Billing item not found", ['billing_item_id' => $billingItemId]);
                    continue;
                }
            
                // Extract necessary data from billing item
                $itemDescription = $billingItem->DESCRIPTION;
                $isVatApplicable = $billingItem->VAT_APPLICABLE == 1;
                $isWithholdingApplicable = $billingItem->WITH_HOLDING_APPLICABLE == 1;
            
                // Use rates only if applicable, otherwise default to 0.0
                $vatRate = $isVatApplicable ? ($billingItem->VAT_RATE ?? 0.0) : 0.0;
                $withholdingRate = $isWithholdingApplicable ? ($billingItem->WITH_HOLDING_RATE ?? 0.0) : 0.0;
            
                // Log the extracted values
                \Log::info('Billing values retrieved', [
                    'description' => $itemDescription,
                    'vat_applicable' => $isVatApplicable,
                    'withholding_applicable' => $isWithholdingApplicable,
                    'vat_rate' => $vatRate,
                    'withholding_rate' => $withholdingRate,
                    'service_provider_id' => $service_provider_id,
                ]);
            
                // Prices and quantities from decoded invoice input
                $pricePerUnit = $invoiceitem['amount_' . $key];
                $quantity = $invoiceitem['quantity_' . $key];
            
                // Calculate subtotal and taxes
                $subtotal = $pricePerUnit * $quantity;
                $vatAmount = round($subtotal * ($vatRate / 100), 2);
                $withholdingAmount = round($subtotal * ($withholdingRate / 100), 2);
            
                // Update totals
                $totalAmount += $subtotal + $vatAmount;
                $totalWithholdingTax += $withholdingAmount;
            
                // Create and push the invoice item
                $item = (new InvoiceItem())
                    ->title($itemDescription)
                    ->pricePerUnit($pricePerUnit)
                    ->quantity($quantity)
                    ->taxByPercent($vatRate); // Laravel Daily Invoice tax field
            
                $items[] = $item;
            
                // Log processed invoice item
                \Log::info('Invoice item processed', [
                    'description' => $itemDescription,
                    'quantity' => $quantity,
                    'amount' => $pricePerUnit,
                    'subtotal' => $subtotal,
                    'vat_amount' => $vatAmount,
                    'withholding_tax' => $withholdingAmount,
                ]);
            }
            

            // Deduct total withholding tax from the total amount
            $totalAmount -= $totalWithholdingTax;

             // Apply discount if provided and greater than 0
            $finalAmount = $totalAmount;
            $discountNote = '';
            if ($discount && $discount > 0) {
                $finalAmount -= $discount;
                $discountNote = "Discount applied: GH₵" . number_format($discount, 2);
            }

            // Prepare notes for the invoice
            $notes = "Withholding tax (not deducted from payable amount): GH₵" . number_format($totalWithholdingTax, 2);
            if ($discountNote) {
                $notes .= "\n" . $discountNote;
            }

            

            // Generate the invoice
            $invoice = Invoice::make()
                ->series('PP')
                ->sequence($this->generateRandomSequence())
                ->serialNumberFormat('{SERIES}{SEQUENCE}')
                ->seller($seller)
                ->buyer($buyer)
                ->date(now())
                ->dateFormat('d/m/Y')
                ->payUntilDays(14)
                ->currencySymbol('GH₵')
                ->currencyCode('GHS')
                ->currencyFormat('{SYMBOL}{VALUE}')
                ->currencyThousandsSeparator(',')
                ->currencyDecimalPoint('.')
                ->filename(Str::slug($client_name, '_') . '_' . $serial_number . '_invoice')
                ->addItems($items)
                //->notes("Withholding tax (not deducted from payable amount): GH₵" . number_format($totalWithholdingTax, 2))
                ->notes($notes)
                ->logo(public_path('storage/fems/logo.jpg'))
                ->save('invoices');

            \Log::info('Invoice generated', ['filename' => $invoice->filename]);

            // Save invoice details to the database
            DB::table('invoicings')->insert([
                'service_provider_id' => $service_provider_id,
                'invoice_number' => $invoice->getSerialNumber(),
                'equipment_serial_number' => $serial_number,
                'client_id' => $client_id,
                'invoice_details' => json_encode($Invoice_items), // Save as JSON string
                'invoice' => $invoice->filename,
                'payment_amount' => $finalAmount,
                'created_by' => $user->id,
                'created_by_type' => get_class($user),
                'payment_status' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            \Log::info('Invoice record inserted into database');

            return response([
                'message' => 'Invoice generated successfully!',
                'url' => Storage::disk('s3')->url('invoices/'. $invoice->filename),
               // 'url' => Storage::url($invoice->filename),  // Use this for s3 storage
                'filename' => $invoice->filename,
                'total_withholding_tax' => $totalWithholdingTax,
    'total_amount' => $finalAmount,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Exception occurred in generateInvoice', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['message' => 'An error occurred while generating the invoice'], 500);
        }
    }

    public function show($id)
{
    try {
        // Retrieve the invoice by ID
        $invoice = Invoicing::find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        $invoice->url = Storage::disk('s3')->url('invoices/'. $invoice->invoice);
        return response()->json(['message' => 'Invoice retrieved successfully', 
        "invoice_number" => $invoice->invoice_number,
        "equipment_serial_number" => $invoice->equipment_serial_number,
        "invoice_url"=>$invoice->url], 200);
    } catch (\Exception $e) {
        // Log the error
        \Log::error('Error in show method for invoices', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json(['message' => 'An error occurred while retrieving the invoice'], 500);
    }
}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Invoicing  $invoicing
     * @return \Illuminate\Http\Response
     */
  
    public function edit($id)
    {
        try {
            // Find the invoice by ID
            $invoice = Invoicing::find($id);

            if (!$invoice) {
                \Log::error('Invoice not found', ['id' => $id]);
                return response(['message' => 'Invoice not found'], 404);
            }

            \Log::info('Invoice retrieved for editing', ['invoice' => $invoice]);

            return response(['invoice' => $invoice], 200);
        } catch (\Exception $e) {
            \Log::error('Exception occurred in edit', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['message' => 'An error occurred while retrieving the invoice'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Invoicing  $invoicing
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            // Find the invoice by ID
            $invoice = Invoicing::find($id);

            if (!$invoice) {
                \Log::error('Invoice not found', ['id' => $id]);
                return response(['message' => 'Invoice not found'], 404);
            }

            // Validate the request data
            $request->validate([
                'invoice_details' => 'sometimes|array',
                'payment_status' => 'sometimes|integer|in:0,1', // 0 = unpaid, 1 = paid
            ]);

            // Update the invoice
            $invoice->update([
                'invoice_details' => $request->invoice_details ? json_encode($request->invoice_details) : $invoice->invoice_details,
                'payment_status' => $request->payment_status ?? $invoice->payment_status,
                'updated_at' => Carbon::now(),
            ]);

            \Log::info('Invoice updated successfully', ['invoice' => $invoice]);

            return response(['message' => 'Invoice updated successfully', 'invoice' => $invoice], 200);
        } catch (\Exception $e) {
            \Log::error('Exception occurred in update', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['message' => 'An error occurred while updating the invoice'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Invoicing  $invoicing
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            // Find the invoice by ID
            $invoice = Invoicing::find($id);
    
            if (!$invoice) {
                \Log::error('Invoice not found', ['id' => $id]);
                return response(['message' => 'Invoice not found'], 404);
            }
    
            // Delete the invoice file from the S3 bucket if it exists
            $s3FilePath = 'invoices/' . $invoice->invoice;
            if (Storage::disk('s3')->exists($s3FilePath)) {
                Storage::disk('s3')->delete($s3FilePath);
                \Log::info('Invoice file deleted from S3', ['s3FilePath' => $s3FilePath]);
            } else {
                \Log::warning('Invoice file not found on S3', ['s3FilePath' => $s3FilePath]);
            }
    
            // Delete the invoice record from the database
            $invoice->delete();
    
            \Log::info('Invoice deleted successfully', ['id' => $id]);
    
            return response(['message' => 'Invoice deleted successfully'], 200);
        } catch (\Exception $e) {
            \Log::error('Exception occurred in destroy', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['message' => 'An error occurred while deleting the invoice'], 500);
        }
    }


    public function getInvoiceByServiceProvider($serviceProviderId)
    {
        try {
            // Retrieve invoices for the given service provider ID
            $invoices = Invoicing::where('service_provider_id', $serviceProviderId)
                ->with('client') // Eager load the client relationship
                ->get();

            if ($invoices->isEmpty()) {
                \Log::info('No invoices found for the service provider', ['service_provider_id' => $serviceProviderId]);
                return response(['message' => 'No invoices found for the service provider'], 200);
            }

            // Add client details dynamically based on client_type
            foreach ($invoices as $invoice) {
                $client = Client::find($invoice->client_id);

                if ($client->client_type == 'CORPORATE') {
                    $invoice->client_details = Corporate_clients::where('client_id', $client->id)->first();
                } else {
                    $invoice->client_details = Individual_clients::where('client_id', $client->id)->first();
                }
            }

            \Log::info('Invoices retrieved for service provider', ['service_provider_id' => $serviceProviderId, 'invoices' => $invoices]);

            return response(['invoices' => $invoices], 200);
        } catch (\Exception $e) {
            \Log::error('Exception occurred in getInvoiceByServiceProvider', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['message' => 'An error occurred while retrieving invoices'], 500);
        }
    }


    public function getInvoiceByClient($clientId)
    {
        try {
            // Retrieve invoices for the given client ID
            $invoices = Invoicing::where('client_id', $clientId)
                ->with('serviceProvider') // Eager load the service provider relationship
                ->get();

            if ($invoices->isEmpty()) {
                \Log::info('No invoices found for the client', ['client_id' => $clientId]);
                return response(['message' => 'No invoices found for the client'], 200);
            }

            // Add client details dynamically based on client_type
            foreach ($invoices as $invoice) {
                $client = Client::find($invoice->client_id);

                if ($client->client_type == 'CORPORATE') {
                    $invoice->client_details = Corporate_clients::where('client_id', $client->id)->first();
                } else {
                    $invoice->client_details = Individual_clients::where('client_id', $client->id)->first();
                }
            }

            \Log::info('Invoices retrieved for client', ['client_id' => $clientId, 'invoices' => $invoices]);

            return response(['invoices' => $invoices], 200);
        } catch (\Exception $e) {
            \Log::error('Exception occurred in getInvoiceByClient', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['message' => 'An error occurred while retrieving invoices'], 500);
        }
    }
}
