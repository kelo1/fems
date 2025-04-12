<?php

namespace App\Http\Controllers;

use App\Models\CorporateType;
use Illuminate\Http\Request;

class CorporateTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $corporateTypes = CorporateType::all();
        return response()->json(['message' => 'Corporate types retrieved successfully', 'data' => $corporateTypes], 200);
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $corporateType = CorporateType::find($id);

        if (!$corporateType) {
            return response()->json(['message' => 'Corporate type not found'], 404);
        }

        return response()->json(['message' => 'Corporate type retrieved successfully', 'data' => $corporateType], 200);
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $corporateType = CorporateType::find($id);

        if (!$corporateType) {
            return response()->json(['message' => 'Corporate type not found'], 404);
        }

        $corporateType->update($request->all());

        return response()->json(['message' => 'Corporate type updated successfully', 'data' => $corporateType], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $corporateType = CorporateType::find($id);

        if (!$corporateType) {
            return response()->json(['message' => 'Corporate type not found'], 404);
        }

        $corporateType->delete();

        return response()->json(['message' => 'Corporate type deleted successfully'], 200);
    }
}
