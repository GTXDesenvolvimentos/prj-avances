<?php

namespace App\Http\Controllers;

use App\Models\MovementTypeModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MovementTypeController extends Controller
{
    /**
     * List all movement types (index)
     */
    public function index()
    {
        try {
            $movementTypes = MovementTypeModel::with('company')->get();
            return response()->json([
                'success' => true,
                'data' => $movementTypes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while listing movement types.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new movement type (store)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'type'      => 'required|string|max:50',
            'company_id'  => 'required|integer|exists:company,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $movementType = MovementTypeModel::create($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Movement type successfully created!',
                'data'    => $movementType
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while creating movement type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific movement type (show)
     */
    public function show($id)
    {
        try {
            $movementType = MovementTypeModel::with('company')->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $movementType
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Movement type not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while fetching movement type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a movement type (update)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type'      => 'sometimes|required|string|max:50',
            'company_id'  => 'sometimes|required|integer|exists:company,id',
            'status'      => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $movementType = MovementTypeModel::findOrFail($id);
            $movementType->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Movement type successfully updated!',
                'data'    => $movementType
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Movement type not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while updating movement type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete a movement type (destroy)
     */
    public function destroy($id)
    {
        try {
            $movementType = MovementTypeModel::findOrFail($id);
            $movementType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Movement type successfully deleted!'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Movement type not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while deleting movement type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
