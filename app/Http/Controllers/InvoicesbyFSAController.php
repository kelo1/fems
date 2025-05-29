<?php

namespace App\Http\Controllers;

use App\Models\InvoicesbyFSA;
use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\Invoicing;
use App\Models\Client;
use App\Models\Corporate_clients;
use App\Models\Individual_clients;
use App\Models\FireServiceAgent;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class InvoicesbyFSAController extends Controller
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
                $invoicings = InvoicesbyFSA::with(['client', 'fireServiceAgent'])->get();

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


    public function generateInvoice(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                \Log::error('Unauthorized access attempt');
                return response(['message' => 'Unauthorized'], 403);
            }

            // Check if the user is a Fire Service Agent
            $user = FireServiceAgent::find($user->id);
            if (!$user) {
                \Log::error('User not found', ['user_id' => $user->id]);
                return response(['message' => 'User not found'], 404);
            }

            $client_id = $request->client_id;
            $Invoice_items = $request->InvoiceItems;

            if (!$client_id || !$Invoice_items) {
                \Log::error('Missing required parameters', [
                    'client_id' => $client_id,
                    'InvoiceItems' => $Invoice_items,
                ]);
                return response(['message' => 'Missing required parameters'], 400);
            }

            // Validate the request data
            $request->validate([
                'client_id' => 'required|integer',
                'InvoiceItems' => 'required|array',
            ]);

            \Log::info('Request validated successfully');

            // Get client type
            $client_type = Client::where('id', $client_id)->value('client_type');
            if (!$client_type) {
                \Log::error('Client not found for client_id', ['client_id' => $client_id]);
                return response(['message' => 'Client not found'], 404);
            }
            \Log::info('Client type retrieved', ['client_type' => $client_type]);

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
                'name' => $user->name,
                'phone' => $user->phone,
                'custom_fields' => [
                    'email' => $user->email,
                    'address' => $user->address,
                ],
            ]);
            \Log::info('Seller party created', ['seller' => $seller]);

            $buyer = new Party([
                'name' => $client_type == 'CORPORATE' ? $client_name . ' (' . $client_branch . ')' : $client_name,
                'phone' => $client_phone,
                'custom_fields' => [
                    'email' => $client_email,
                    'address' => $client_address,
                ],
            ]);
            \Log::info('Buyer party created', ['buyer' => $buyer]);

            // Use InvoiceItems directly as an array
            $InvoiceDecoded = $Invoice_items;
            \Log::info('Invoice items decoded', ['InvoiceDecoded' => $InvoiceDecoded]);

            $items = [];
            $totalAmount = 0;
            $totalWithholdingTax = 0;
            $totalDiscount = 0;

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

                $vatRate = $isVatApplicable ? ($billingItem->VAT_RATE ?? 0.0) : 0.0;
                $withholdingRate = $isWithholdingApplicable ? ($billingItem->WITH_HOLDING_RATE ?? 0.0) : 0.0;

                $pricePerUnit = $invoiceitem['amount_' . $key];
                $quantity = $invoiceitem['quantity_' . $key];

                // Support per-item discount (default to 0 if not present)
                $itemDiscount = isset($invoiceitem['discount_' . $key]) ? floatval($invoiceitem['discount_' . $key]) : 0;

                $subtotal = $pricePerUnit * $quantity;
                $vatAmount = round($subtotal * ($vatRate / 100), 2);
                $withholdingAmount = round($subtotal * ($withholdingRate / 100), 2);

                // Apply discount per item
                $itemTotal = $subtotal + $vatAmount - $itemDiscount;

                $totalAmount += $itemTotal;
                $totalWithholdingTax += $withholdingAmount;
                $totalDiscount += $itemDiscount;

                $item = (new InvoiceItem())
                    ->title($itemDescription)
                    ->pricePerUnit($pricePerUnit)
                    ->quantity($quantity)
                    ->taxByPercent($vatRate);

                // Add discount info to the item description if present
                if ($itemDiscount > 0) {
                    $item->description('Discount: GH₵' . number_format($itemDiscount, 2));
                }

                $items[] = $item;

                \Log::info('Invoice item processed', [
                    'description' => $itemDescription,
                    'quantity' => $quantity,
                    'amount' => $pricePerUnit,
                    'subtotal' => $subtotal,
                    'vat_amount' => $vatAmount,
                    'withholding_tax' => $withholdingAmount,
                    'item_discount' => $itemDiscount,
                ]);
            }

            // Deduct total withholding tax from the total amount
            $totalAmount -= $totalWithholdingTax;

            // Prepare notes for the invoice
            $notes = "Withholding tax (not deducted from payable amount): GH₵" . number_format($totalWithholdingTax, 2);
            if ($totalDiscount > 0) {
                $notes .= "\nTotal Discount applied: GH₵" . number_format($totalDiscount, 2);
            }

            $invoice_number = $this->generateRandomSequence();

            // Generate the invoice
            $invoice = Invoice::make()
                ->series('FSA')
                ->sequence($invoice_number)
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
                ->filename(Str::slug($client_name, '_') . '_' . 'FSA' . $invoice_number . '_fsa_invoice')
                ->addItems($items)
                ->notes($notes)
                ->logo(public_path('storage/fems/logo.jpg'))
                ->template('custom')
                ->save('invoices');

            \Log::info('FSA Invoice generated', ['filename' => $invoice->filename]);

            // Save invoice details to the database
            DB::table('invoices_by_fsa')->insert([
                'fsa_id' => $user->id,
                'invoice_number' => $invoice->getSerialNumber(),
                'client_id' => $client_id,
                'invoice_details' => json_encode($Invoice_items), // Save as JSON string
                'invoice' => $invoice->filename,
                'payment_amount' => $totalAmount,
                'created_by' => $user->id,
                'created_by_type' => get_class($user),
                'payment_status' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            \Log::info('FSA Invoice record inserted into database');

            return response([
                'message' => 'FSA Invoice generated successfully!',
                'url' => Storage::disk('s3')->url('invoices/' . $invoice->filename),
                'filename' => $invoice->filename,
                'total_withholding_tax' => $totalWithholdingTax,
                'total_discount' => $totalDiscount,
                'total_amount' => $totalAmount,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Exception occurred in generateInvoice (FSA)', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response(['message' => 'An error occurred while generating the FSA invoice'], 500);
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

   
    public function show($id)
    {
    try {
        // Retrieve the invoice by ID
        $invoice = InvoicesbyFSA::find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

       
        $invoice->url = Storage::disk('s3')->url('invoices/'. $invoice->invoice);

        return response()->json(['message' => 'Invoice retrieved successfully', 
        "invoice_number" => $invoice->invoice_number,
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


    public function edit($id)
    {
        try {
            // Find the invoice by ID
            $invoice = InvoicesbyFSA::find($id);

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


            
public function getInvoiceByFSA($fsaId)
{
    try {
        // Retrieve invoices for the given FSA ID
        $invoices = InvoicesbyFSA::where('fsa_id', $fsaId)
            ->with('client') // Eager load the client relationship
            ->get();

        if ($invoices->isEmpty()) {
            \Log::info('No invoices found for the FSA', ['fsa_id' => $fsaId]);
            return response(['message' => 'No invoices found for the FSA', 'invoices' => []], 200);
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

        \Log::info('Invoices retrieved for FSA', ['fsa_id' => $fsaId, 'invoices' => $invoices]);

        return response(['invoices' => $invoices], 200);
    } catch (\Exception $e) {
        \Log::error('Exception occurred in getInvoiceByFSA', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response(['message' => 'An error occurred while retrieving invoices'], 500);
    }
}


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\InvoicesbyFSA  $invoicesbyFSA
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            // Find the invoice by ID
            $invoice = InvoicesbyFSA::find($id);

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

    public function destroy($id)
    {
        try {
            // Find the invoice by ID
            $invoice = InvoicesbyFSA::find($id);

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
}
