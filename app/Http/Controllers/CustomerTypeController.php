<?php

namespace App\Http\Controllers;

use App\Models\CustomerType;
use Illuminate\Http\Request;

class CustomerTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $customerTypes = CustomerType::all();
        return response()->json(['message' => 'Customer types retrieved successfully', 'data' => $customerTypes], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
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
        $request->validate([
            'name' => 'required|string|max:255|unique:customer_types,name',
        ]);

        $customerType = CustomerType::create($request->all());

        return response()->json(['message' => 'Customer type created successfully', 'data' => $customerType], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $customerType = CustomerType::find($id);

        if (!$customerType) {
            return response()->json(['message' => 'Customer type not found'], 404);
        }

        return response()->json(['message' => 'Customer type retrieved successfully', 'data' => $customerType], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:customer_types,name,' . $id,
        ]);

        $customerType = CustomerType::find($id);

        if (!$customerType) {
            return response()->json(['message' => 'Customer type not found'], 404);
        }

        $customerType->update($request->all());

        return response()->json(['message' => 'Customer type updated successfully', 'data' => $customerType], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $customerType = CustomerType::find($id);

        if (!$customerType) {
            return response()->json(['message' => 'Customer type not found'], 404);
        }

        $customerType->delete();

        return response()->json(['message' => 'Customer type deleted successfully'], 200);
    }
}
