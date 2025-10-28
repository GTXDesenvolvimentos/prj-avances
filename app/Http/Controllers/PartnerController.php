<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
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
    use ApiResponser;

    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $companyId = $user->company_id;

            $search = trim($request->query('search', ''), '"\'');
            $limit = (int) $request->query('limit', 25);

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

            $query->orderBy('id', 'desc');

            $partners = $query->paginate($limit);

            return $this->paginatedResponse($partners, 'Partners retrieved successfully');
        });
    }

    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
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
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

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
                'created_by' => $userId,
                'updated_by' => $userId,
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

            $partner->load(['contacts', 'addresses']);

            return $this->createdResponse($partner, 'Partner created successfully');
        });
    }

    public function update(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $data = $request->json()->all();
            $user = auth()->user();
            $companyId = $user->company_id;

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
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $partner = PartnerModel::where('company_id', $companyId)
                ->where('id', $id)
                ->first();

            if (!$partner) {
                return $this->errorResponse(
                    'Partner not found.',
                    'NOT_FOUND',
                    404
                );
            }

            DB::beginTransaction();

            $partner->update([
                'name' => $data['name'],
                'tax_id' => $data['tax_id'],
                'partner_type' => $data['partner_type'],
                'status' => $data['status'] ?? $partner->status,
                'note' => $data['note'] ?? $partner->note,
                'updated_by' => $user->id,
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
                        'created_by' => $user->id,
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
                        'created_by' => $user->id,
                    ]);
                }
            }

            DB::commit();

            $partner->load(['contacts', 'addresses']);

            return $this->updatedResponse($partner, 'Partner updated successfully');
        });
    }

    public function show(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            $partner = PartnerModel::with(['contacts', 'addresses'])
                ->where('company_id', $user->company_id)
                ->find($id);

            if (!$partner) {
                return $this->errorResponse(
                    'Partner not found.',
                    'NOT_FOUND',
                    404
                );
            }

            $formatted = [
                'id' => $partner->id,
                'name' => $partner->name,
                'tax_id' => $partner->tax_id,
                'partner_type' => $partner->partner_type,
                'person_type' => $partner->person_type,
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

            return $this->successResponse($formatted, 'Partner retrieved successfully');
        });
    }

    public function destroy(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            $partner = PartnerModel::where('company_id', $user->company_id)
                ->with(['contacts', 'addresses'])
                ->find($id);

            if (!$partner) {
                return $this->errorResponse(
                    'Partner not found.',
                    'NOT_FOUND',
                    404
                );
            }

            DB::beginTransaction();

            // Atualizar deleted_by nos relacionamentos
            if ($partner->contacts) {
                foreach ($partner->contacts as $contact) {
                    $contact->update(['deleted_by' => $user->id]);
                    $contact->delete();
                }
            }

            if ($partner->addresses) {
                foreach ($partner->addresses as $address) {
                    $address->update(['deleted_by' => $user->id]);
                    $address->delete();
                }
            }

            // Atualizar deleted_by no partner
            $partner->update([
                'deleted_by' => $user->id
            ]);

            $partner->delete();

            DB::commit();

            return $this->deletedResponse(
                'Partner and related data successfully marked as deleted!',
                ['id' => $partner->id, 'deleted_at' => $partner->deleted_at]
            );
        });
    }
}