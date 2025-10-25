<?php

namespace App\Http\Controllers;

use App\Models\AddressModel;
use App\Models\ContactEntitiesModel;
use App\Models\PartnerModel;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;

            $search = trim($request->query('search', ''), '"\'');
            //$partnerType = $request->query('partner_type');
            $limit = (int) $request->query('limit', 25);
            //$name = trim($request->query('name', ''), '"\'');

            $query = PartnerModel::with(['contacts', 'addresses'])
                ->where('company_id', $companyId);

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('tax_id', 'like', "%{$search}%")
                        ->orWhere('partner_type', 'like', "%{$search}%")
                        ->orWhere('person_type', 'like', "%{$search}%");
                });
            }

            $query->orderBy('id', 'desc'); // <-- aqui adiciona o orderBy

            $partners = $query->get();

            if (!$partners) {
                return response()->json([
                    'success' => false,
                    'message' => 'No partners found.',
                ], 404);
            }

            $products = $query->paginate($limit);

            return response()->json([
                'success' => true,
                'partner' => $partners,
                'pagination' => [
                    'page' => $products->currentPage(),
                    'limit' => $products->perPage(),
                    'page_count' => $products->lastPage(),
                    'total_count' => $products->total(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error listing partners.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function store(Request $request)
    {
        $data = $request->json()->all();

        $validator = Validator::make($data, [
            'name' => 'required|string|min:1|max:255',
            'tax_id' => 'required|string|max:20|unique:partners,tax_id',
            'partner_type' => 'nullable|array',
            'partner_type.*' => 'in:customer,supplier',
            'person_type' => ['required', 'string', Rule::in(['legal', 'individual'])],
            'status' => 'boolean',
            'note' => 'nullable|string|max:1000',

            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.note' => 'nullable|string|max:200',
            'contacts.*.type' => 'nullable|string|max:200',
            'contacts.*.contact' => 'nullable|string|max:200',

            'addresses' => 'nullable|array',
            'addresses.*.zip_code' => 'required|string|max:10',
            'addresses.*.street' => 'required|string|max:200',
            'addresses.*.number' => 'nullable|string|max:10',
            'addresses.*.complement' => 'nullable|string|max:100',
            'addresses.*.neighborhood' => 'nullable|string|max:100',
            'addresses.*.city' => 'required|string|max:100',
            'addresses.*.state' => 'required|string|max:2',
            'addresses.*.status' => 'nullable|string|max:1',
            'addresses.*.active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $companyId = auth()->user()->company_id;
            $userId = auth()->id();

            DB::beginTransaction();

            $partner_type = $request->partner_type;

            if (is_array($partner_type)) {
                $partner_type = implode(',', $partner_type);
            }

            $partner = PartnerModel::create([
                'name' => $data['name'],
                'tax_id' => $data['tax_id'],
                'partner_type' => $partner_type,
                'person_type' => $data['person_type'],
                'company_id' => $companyId,
                'status' => $data['status'] ?? true,
                'note' => $data['note'] ?? null,
            ]);

            if (!empty($data['contacts'])) {
                foreach ($data['contacts'] as $contact) {
                    ContactEntitiesModel::create([
                        'name' => $contact['name'],
                        'note' => $contact['note'] ?? null,
                        'partner_id' => $partner->id,
                        'partners_id' => $partner->id,
                        'type' => $contact['type'],
                        'contact' => $contact['contact'],
                        'company_id' => $companyId,
                        'created_by' => $userId,
                    ]);
                }
            }

            if (!empty($data['addresses'])) {
                foreach ($data['addresses'] as $address) {
                    AddressModel::create([
                        'company_id' => $companyId,
                        'partner_id' => $partner->id,
                        'zip_code' => $address['zip_code'],
                        'street' => $address['street'],
                        'number' => $address['number'] ?? null,
                        'complement' => $address['complement'] ?? null,
                        'neighborhood' => $address['neighborhood'] ?? null,
                        'city' => $address['city'],
                        'state' => $address['state'],
                        'status' => $address['status'] ?? 'A',
                        'active' => $address['active'] ?? true,
                        'created_by' => $userId,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'partner' => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'tax_id' => $partner->tax_id,
                    'partner_type' => $partner->partner_type,
                    'person_type' => $partner->person_type,
                    'status' => $partner->status,
                    'note' => $partner->note,
                    'contacts' => $partner->contacts,
                    'addresses' => $partner->addresses,
                ]
            ], 201);

        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => [
                    'database' => $e->getMessage(),
                ],
            ], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => [
                    'general' => $e->getMessage(),
                ],
            ], 500);
        }
    }



    public function update(Request $request, $id)
    {
        $data = $request->json()->all();

        $validator = Validator::make($data, [
            'name' => 'required|string|min:1|max:255',
            'tax_id' => [
                'required',
                'string',
                'max:20',
                Rule::unique('partners', 'tax_id')->ignore($id),
            ],
            'partner_type' => ['required', 'string', Rule::in(['customer', 'supplier', 'distributor', 'reseller', 'partner'])],
            'status' => 'boolean',
            'note' => 'nullable|string|max:1000',

            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.note' => 'nullable|string|max:200',
            'contacts.*.type' => 'nullable|string|max:200',
            'contacts.*.contact' => 'nullable|string|max:200',

            'addresses' => 'nullable|array',
            'addresses.*.zip_code' => 'required|string|max:10',
            'addresses.*.street' => 'required|string|max:200',
            'addresses.*.number' => 'nullable|string|max:10',
            'addresses.*.complement' => 'nullable|string|max:100',
            'addresses.*.neighborhood' => 'nullable|string|max:100',
            'addresses.*.city' => 'required|string|max:100',
            'addresses.*.state' => 'required|string|max:2',
            'addresses.*.status' => 'nullable|string|max:1',
            'addresses.*.active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $companyId = auth()->user()->company_id;
            $userId = auth()->id();

            $partner = PartnerModel::where('company_id', $companyId)
                ->where('id', $id)
                ->first();

            if (!$partner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner not found.',
                ], 404);
            }

            DB::beginTransaction();

            $partner->update([
                'name' => $data['name'],
                'tax_id' => $data['tax_id'],
                'partner_type' => $data['partner_type'],
                'status' => $data['status'] ?? $partner->status,
                'note' => $data['note'] ?? $partner->note,
            ]);

            if (isset($data['contacts'])) {
                ContactEntitiesModel::where('partner_id', $partner->id)->delete();

                foreach ($data['contacts'] as $contact) {
                    ContactEntitiesModel::create([
                        'name' => $contact['name'],
                        'note' => $contact['note'] ?? null,
                        'partner_id' => $partner->id,
                        'partners_id' => $partner->id,
                        'type' => $contact['type'] ?? null,
                        'contact' => $contact['contact'] ?? null,
                        'company_id' => $companyId,
                        'created_by' => $userId,
                    ]);
                }
            }

            if (isset($data['addresses'])) {
                AddressModel::where('partner_id', $partner->id)->delete();

                foreach ($data['addresses'] as $address) {
                    AddressModel::create([
                        'company_id' => $companyId,
                        'partner_id' => $partner->id,
                        'zip_code' => $address['zip_code'],
                        'street' => $address['street'],
                        'number' => $address['number'] ?? null,
                        'complement' => $address['complement'] ?? null,
                        'neighborhood' => $address['neighborhood'] ?? null,
                        'city' => $address['city'],
                        'state' => $address['state'],
                        'status' => $address['status'] ?? 'A',
                        'active' => $address['active'] ?? true,
                        'created_by' => $userId,
                    ]);
                }
            }

            DB::commit();

            $partner->load(['contacts', 'addresses']);

            return response()->json([
                'success' => true,
                'partner' => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'tax_id' => $partner->tax_id,
                    'partner_type' => $partner->partner_type,
                    'status' => $partner->status,
                    'note' => $partner->note,
                    'contacts' => $partner->contacts,
                    'addresses' => $partner->addresses,
                ]
            ], 200);

        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => [
                    'database' => $e->getMessage(),
                ],
            ], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => [
                    'general' => $e->getMessage(),
                ],
            ], 500);
        }
    }



    public function show($id)
    {
        try {
            $user = auth()->user();

            $partner = PartnerModel::with(['contacts', 'addresses'])
                ->where('company_id', $user->company_id)
                ->findOrFail($id);

            $formatted = [
                'id' => $partner->id,
                'name' => $partner->name,
                'tax_id' => $partner->tax_id,
                'partner_type' => $partner->partner_type,
                'status' => $partner->status,
                'note' => $partner->note,
                'contacts' => $partner->contacts->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'type' => $c->type,
                        'contact' => $c->contact,
                        'note' => $c->note,
                    ];
                }),
                'addresses' => $partner->addresses->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'zip_code' => $a->zip_code,
                        'street' => $a->street,
                        'number' => $a->number,
                        'district' => $a->district ?? null,
                        'neighborhood' => $a->neighborhood ?? null,
                        'city' => $a->city,
                        'state' => $a->state,
                        'country' => $a->country ?? 'Brazil',
                        'complement' => $a->complement,
                        'note' => $a->note ?? null,
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'partner' => $formatted,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Partner not found.'],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }



    public function destroy($id)
    {
        try {
            $user = auth()->user();

            $partner = PartnerModel::where('company_id', $user->company_id)
                ->with(['contacts', 'addresses'])
                ->findOrFail($id);

            if ($partner->contacts) {
                foreach ($partner->contacts as $contact) {
                    $contact->delete();
                }
            }

            if ($partner->addresses) {
                foreach ($partner->addresses as $address) {
                    $address->delete();
                }
            }

            $partner->delete();

            return response()->json([
                'success' => true,
                'message' => 'Partner and related data successfully marked as deleted!',
                'data' => [
                    'id' => $partner->id,
                    'deleted_at' => $partner->deleted_at,
                ],
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Partner not found.'],
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }



    public function restore($id)
    {
        try {
            $user = auth()->user();
            $partner = PartnerModel::withTrashed()
                ->where('company_id', $user->company_id)
                ->findOrFail($id);

            if (!$partner->trashed()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['general' => 'Partner is not deleted.'],
                ], 400);
            }

            $partner->restore();

            return response()->json([
                'success' => true,
                'message' => 'Partner successfully restored!',
                'data' => $partner,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Partner not found.'],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }

}
