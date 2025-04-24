<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderVAT;

class BillingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */


    public function billingByServiceProvider($serviceProviderId)
    {
        $billings = Billing::where('created_by', $serviceProviderId)->get();

        if ($billings->isEmpty()) {
            return response()->json(['message' => 'No billings found for this service provider'], 404);
        }

        return response()->json(['message' => 'Billings retrieved successfully', 'data' => $billings], 200);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Check if the user is authenticated
        $user = Auth::user();

        if (!$user) {
            return response(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'DESCRIPTION' => 'nullable|string|max:255',
            'VAT_APPLICABLE' => 'nullable|boolean',
            'VAT_RATE' => 'required_if:VAT_APPLICABLE,1|nullable|numeric|min:0|max:100',
            'isACTIVE' => 'nullable|boolean',
            'WITH_HOLDING_APPLICABLE' => 'nullable|boolean',
            'WITH_HOLDING_RATE' => 'required_if:WITH_HOLDING_APPLICABLE,1|nullable|numeric|min:0|max:100',
           
        ]);

        $billing = Billing::create([
            'DESCRIPTION' => $request->DESCRIPTION,
            'VAT_APPLICABLE' => $request->VAT_APPLICABLE,
            'VAT_RATE' => $request->VAT_RATE,
            'WITH_HOLDING_APPLICABLE' => $request->WITH_HOLDING_APPLICABLE,
            'WITH_HOLDING_RATE' => $request->WITH_HOLDING_RATE,
            'isACTIVE' => $request->isACTIVE,
            'created_by' => $user->id,
            'created_by_type' => get_class($user),
        ]);

        // Log the creation of the billing
        \Log::info('Billing created', ['billing' => $billing]);

        // Check if VAT is applicable
        // if ($request->has('VAT_APPLICABLE') && ($request->VAT_APPLICABLE === true || $request->VAT_APPLICABLE === 1)) {
        //     $request->validate([
        //         'VAT_RATE' => 'required|numeric|min:0|max:100',
        //     ]);

        //     // // Check if a VAT record already exists for this service provider
        //     // $existingVAT = ServiceProviderVAT::where('service_provider_id', $user->id)->first();

        //     // if (!$existingVAT) {
        //     //     // Create a new VAT record
        //     //     ServiceProviderVAT::create([
        //     //         'service_provider_id' => $user->id,
        //     //         'VAT_RATE' => $request->VAT_RATE,
        //     //         'created_by' => $user->id,
        //     //         'created_by_type' => get_class($user),
        //     //     ]);
        //     // }
        // }

        return response()->json(['message' => 'Billing created successfully', 'data' => $billing], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $billing = Billing::find($id);

        if (!$billing) {
            return response()->json(['message' => 'Billing not found'], 404);
        }

        return response()->json(['message' => 'Billing retrieved successfully', 'data' => $billing], 200);
    }

    /**
     * Search for a specific resource.
     *
     * @param  string  $search
     * @return \Illuminate\Http\JsonResponse
     */
    
    
    public function search($search)
    {

        $billings = Billing::where('DESCRIPTION', 'like', '%' . $search . '%')->get();

        if ($billings->isEmpty()) {
            return response()->json(['message' => 'No billings found'], 404);
        }

        return response()->json(['message' => 'Billings retrieved successfully', 'data' => $billings], 200);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ActiveBillItems()
    {
        // Fetch all active billings
        $activeBillings = Billing::where('isActive', 1)->get();
        return response()->json(['message' => 'Active billings retrieved successfully', 'data' => $activeBillings], 200);
       
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
         // Check if the user is authenticated
         $user = Auth::user();

         if (!$user) {
             return response(['message' => 'Unauthorized'], 403);
         }

        $request->validate([
            'DESCRIPTION' => 'sometimes|string|max:255',
            'VAT_APPLICABLE' => 'sometimes|boolean',
            'VAT_RATE' => 'sometimes|numeric|min:0|max:100',
            'WITH_HOLDING_APPLICABLE' => 'sometimes|boolean',
            'WITH_HOLDING_RATE' => 'sometimes|numeric|min:0|max:100',
            'isACTIVE' => 'sometimes|boolean',

        ]);

        $billing = Billing::find($id);

        if (!$billing) {
            return response()->json(['message' => 'Billing not found'], 404);
        }

        $billing->update($request->all());

        // Check if VAT is applicable
        // if ($request->has('VAT_APPLICABLE') && ($request->VAT_APPLICABLE === true || $request->VAT_APPLICABLE === 1)) {
        //     $request->validate([
        //         'VAT_RATE' => 'required|numeric|min:0|max:100',
        //     ]);

        //     // Check if a VAT record already exists for this service provider
        //     $existingVAT = ServiceProviderVAT::where('service_provider_id', $billing->created_by)->first();

        //     if (!$existingVAT) {
        //         // Create a new VAT record
        //         ServiceProviderVAT::create([
        //             'service_provider_id' => $billing->created_by,
        //             'VAT_RATE' => $request->VAT_RATE,
        //             'created_by' => $billing->created_by,
        //             'created_by_type' => get_class(Auth::user()),
        //         ]);
        //     }
        // }

        // Log the update of the billing
        \Log::info('Billing updated', ['billing' => $billing]);

        return response()->json(['message' => 'Billing updated successfully', 'data' => $billing], 200);
    }

    /**
     * Update the VAT rate for a specific service provider.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $serviceProviderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateVATRate(Request $request, $serviceProviderId)
    {   
         // Check if the user is authenticated
         $user = Auth::user();

         if (!$user) {
             return response(['message' => 'Unauthorized'], 403);
         }

        $request->validate([
            'VAT_RATE' => 'required|numeric|min:0|max:100',
        ]);

        $existingVAT = ServiceProviderVAT::where('service_provider_id', $serviceProviderId)->first();

        if ($existingVAT) {
            // Update the existing VAT record
            $existingVAT->update([
                'VAT_RATE' => $request->VAT_RATE,
                'created_by' => $user->id,
                'created_by_type' => get_class($user),
            ]);
        } else {
            // Create a new VAT record
            $existingVAT = ServiceProviderVAT::create([
                'service_provider_id' => $user->id,
                'VAT_RATE' => $request->VAT_RATE,
                'created_by' => $user->id,
                'created_by_type' => get_class($user),
            ]);
        }

        return response()->json(['message' => 'VAT rate updated successfully', 'data' => $existingVAT], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $billing = Billing::find($id);

        if (!$billing) {
            return response()->json(['message' => 'Billing not found'], 404);
        }

        $billing->delete();
        //also delete the associated VAT record
        

        return response()->json(['message' => 'Billing deleted successfully'], 200);
    }
}
