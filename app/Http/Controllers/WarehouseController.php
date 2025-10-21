<?php

namespace App\Http\Controllers;

use App\Models\WarehouseModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="Warehouse",
 *     title="Warehouse",
 *     description="Warehouse resource model",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=10),
 *     @OA\Property(property="address_id", type="integer", example=3),
 *     @OA\Property(property="name", type="string", example="Main Warehouse"),
 *     @OA\Property(property="note", type="string", example="Central distribution hub"),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-21T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-21T10:30:00Z")
 * )
 */

class WarehouseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/warehouses",
     *     summary="List all warehouses",
     *     description="Retrieve a paginated list of warehouses with optional search and sorting.",
     *     tags={"Warehouses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=25)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering warehouses by name or note",
     *         required=false,
     *         @OA\Schema(type="string", example="Central Warehouse")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", example="name")
     *     ),
     *     @OA\Parameter(
     *         name="sort_dir",
     *         in="query",
     *         description="Sort direction (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", example="asc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of warehouses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Warehouse")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="page", type="integer", example=1),
     *                 @OA\Property(property="limit", type="integer", example=25),
     *                 @OA\Property(property="page_count", type="integer", example=5),
     *                 @OA\Property(property="total_count", type="integer", example=100)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = WarehouseModel::query();
        $limit = (int) $request->query('limit', 25);

        $search = trim($request->query('search', ''), '"\'');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('note', 'LIKE', "%{$search}%");
            });
        }

        $query->when($request->sort_by, function ($q, $sortBy) use ($request) {
            $direction = $request->get('sort_dir', 'asc');
            $q->orderBy($sortBy, $direction);
        }, function ($q) {
            $q->orderByDesc('id');
        });

        $warehouses = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $warehouses->items(),
            'pagination' => [
                'page' => $warehouses->currentPage(),
                'limit' => $warehouses->perPage(),
                'page_count' => $warehouses->lastPage(),
                'total_count' => $warehouses->total(),
            ],
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/warehouses",
     *     summary="Create a new warehouse",
     *     description="Add a new warehouse to the system.",
     *     tags={"Warehouses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"address_id", "name"},
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Central Storage"),
     *             @OA\Property(property="note", type="string", example="Main warehouse for distribution")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Warehouse successfully created",
     *         @OA\JsonContent(ref="#/components/schemas/Warehouse")
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Database error")
     * )
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $request->merge(['company_id' => $user->company_id]);

        $validator = Validator::make($request->all(), [
            'address_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'note' => 'nullable|string',
            'company_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $warehouse = WarehouseModel::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Warehouse successfully created!',
                'data' => $warehouse
            ], 201);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error while creating warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/warehouses/{id}",
     *     summary="Show a specific warehouse",
     *     description="Retrieve details of a specific warehouse by ID.",
     *     tags={"Warehouses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Response(response=200, description="Warehouse found", @OA\JsonContent(ref="#/components/schemas/Warehouse")),
     *     @OA\Response(response=404, description="Warehouse not found")
     * )
     */
    public function show($id)
    {
        try {
            $warehouse = WarehouseModel::find($id);

            if (!$warehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $warehouse
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while retrieving warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/warehouses/{id}",
     *     summary="Update an existing warehouse",
     *     description="Modify the details of an existing warehouse.",
     *     tags={"Warehouses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="address_id", type="integer", example=2),
     *             @OA\Property(property="name", type="string", example="Updated Warehouse"),
     *             @OA\Property(property="note", type="string", example="Updated description"),
     *             @OA\Property(property="status", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Warehouse successfully updated"),
     *     @OA\Response(response=404, description="Warehouse not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $warehouse = WarehouseModel::find($id);

            if (!$warehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse not found.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'address_id' => 'sometimes|required|integer',
                'name' => 'sometimes|required|string|max:255',
                'note' => 'nullable|string',
                'status' => 'sometimes|required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $warehouse->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Warehouse successfully updated!',
                'data' => $warehouse
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error while updating warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/warehouses/{id}",
     *     summary="Delete a warehouse",
     *     description="Soft delete a warehouse by ID.",
     *     tags={"Warehouses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Warehouse ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Warehouse successfully deleted"),
     *     @OA\Response(response=404, description="Warehouse not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $warehouse = WarehouseModel::find($id);

            if (!$warehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse not found.'
                ], 404);
            }

            $warehouse->delete();

            return response()->json([
                'success' => true,
                'message' => 'Warehouse successfully deleted.'
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while deleting warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
