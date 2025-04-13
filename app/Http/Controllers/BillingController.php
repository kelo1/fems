<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;

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
            'isACTIVE' => 'nullable|boolean',
        ]);



        $billing = Billing::create([
            'DESCRIPTION' => $request->DESCRIPTION,
            'VAT_APPLICABLE' => $request->VAT_APPLICABLE,
            'isACTIVE' => $request->isACTIVE,
            'created_by' => $user->id,
            'created_by_type' => get_class($user),
        ]);
        // Log the creation of the billing
        \Log::info('Billing created', ['billing' => $billing]);
        // Return a success response

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
        $request->validate([
            'DESCRIPTION' => 'sometimes|string|max:255',
            'VAT_APPLICABLE' => 'sometimes|boolean',
            'isACTIVE' => 'sometimes|boolean',
        ]);

        $billing = Billing::find($id);

        if (!$billing) {
            return response()->json(['message' => 'Billing not found'], 404);
        }

       $billing->update($request->all());
        // Log the update of the billing
        \Log::info('Billing updated', ['billing' => $billing]);
        // Return a success response
        return response()->json(['message' => 'Billing updated successfully', 'data' => $billing], 200);
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

        return response()->json(['message' => 'Billing deleted successfully'], 200);
    }
}
