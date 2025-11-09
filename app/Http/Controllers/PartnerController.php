<?php

namespace App\Http\Controllers;

use App\Models\AddressPartnerModel;
use App\Models\ContactsModel;
use App\Traits\ApiResponser;
use App\Models\AddressModel;
use App\Models\PartnerModel;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
{
    use ApiResponser;

    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $companyId = $user->company_id;

            // 🔹 Parâmetros de busca e paginação
            $search = trim($request->query('search', ''), '"\'');
            $limit = (int) $request->query('limit', 25);
            $status = $request->query('status');
            $partnerType = $request->query('partner_type');
            $personType = $request->query('person_type');

            // 🔹 Query base com relacionamentos
            $query = PartnerModel::with(['contacts', 'addresses'])
                ->where('company_id', $companyId);

            // 🔹 Filtro de busca geral
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('tax_id', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%")
                        ->orWhere('partner_type', 'like', "%{$search}%")
                        ->orWhere('person_type', 'like', "%{$search}%");
                });
            }

            // 🔹 Filtros adicionais (opcionais)
            if (!empty($status)) {
                $query->where('status', $status);
            }

            if (!empty($partnerType)) {
                $query->where('partner_type', $partnerType);
            }

            if (!empty($personType)) {
                $query->where('person_type', $personType);
            }

            // 🔹 Ordenação
            $query->orderByDesc('id');

            // 🔹 Paginação
            $partners = $query->paginate($limit);

            return $this->paginatedResponse($partners, 'Partners retrieved successfully');
        });
    }

    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {

            $data = $request->json()->all();
            $user = $request->user();
            $companyId = $user->company_id;
            $userId = $user->id;

            // 🔍 Validação
            $validator = Validator::make($data, [
                'name' => 'required|string|min:1|max:255',
                'tax_id' => 'required|string|max:20|unique:partners,tax_id',
                'partner_type' => 'nullable|array',
                'partner_type.*' => 'in:customer,supplier',
                'person_type' => ['required', 'string', Rule::in(['legal', 'individual'])],
                'status' => 'boolean',
                'note' => 'nullable|string|max:1000',

                // Contatos
                'contacts' => 'nullable|array',
                'contacts.*.name' => 'required|string|max:255',
                'contacts.*.note' => 'nullable|string|max:200',
                'contacts.*.type' => 'nullable|string|max:200',
                'contacts.*.contact' => 'nullable|string|max:200',

                // Endereços
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

            // 🔐 Transação segura
            $partner = DB::transaction(function () use ($data, $companyId, $userId) {

                $partner_type = $data['partner_type'] ?? [];
                if (is_array($partner_type)) {
                    $partner_type = implode(',', $partner_type);
                }

                // 🔹 Cria o parceiro
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

                /**
                 * 🔹 Contatos (N:N)
                 * Cria cada contato (se não existir) e faz attach na pivot contacts_partners
                 */
                if (!empty($data['contacts'])) {
                    foreach ($data['contacts'] as $contactData) {

                        $contact = ContactsModel::create([
                            'name' => $contactData['name'],
                            'company_id' => $companyId,
                            'type' => $contactData['type'] ?? null,
                            'contact' => $contactData['contact'] ?? null,
                            'note' => $contactData['note'] ?? null,
                            'created_by' => $userId,
                        ]);

                        // Cria o vínculo na pivot
                        $partner->contacts()->attach($contact->id, [
                            'created_by' => $userId,
                            'updated_by' => $userId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                /**
                 * 🔹 Endereços (1:N)
                 */
                if (!empty($data['addresses'])) {
                    foreach ($data['addresses'] as $address) {
                        $addressModel = AddressModel::create([
                            'company_id' => $companyId,
                            'zip_code' => $address['zip_code'],
                            'street' => $address['street'],
                            'complement' => $address['complement'] ?? null,
                            'neighborhood' => $address['neighborhood'] ?? null,
                            'city' => $address['city'],
                            'state' => $address['state'],
                            'status' => $address['status'] ?? 'A',
                            'active' => $address['active'] ?? true,
                            'created_by' => $userId,
                        ]);

                        // cria o vínculo N:N na pivot
                        $partner->addresses()->attach($addressModel->id, [
                            'number' => $address['number'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                    }
                }

                return $partner;
            }); // 👈 Rollback automático em caso de exceção

            // 🔹 Carrega relações
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
                // 🔒 tax_id não pode ser alterado
                // portanto, removemos do validation

                'partner_type' => 'nullable|array',
                'partner_type.*' => ['string', Rule::in(['customer', 'supplier', 'distributor', 'reseller', 'partner'])],

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

            // 🔎 Busca o parceiro garantindo que pertence à empresa do usuário
            $partner = PartnerModel::where('company_id', $companyId)
                ->where('id', $id)
                ->first();

            if (!$partner) {
                return $this->errorResponse('Partner not found.', 'NOT_FOUND', 404);
            }

            DB::beginTransaction();

            $partner_type = $data['partner_type'] ?? [];
            if (is_array($partner_type)) {
                $partner_type = implode(',', $partner_type);
            }

            /**
             * 🔹 Atualiza apenas campos permitidos
             * company_id e tax_id NÃO são alterados
             */
            $partner->update([
                'name' => $data['name'],
                'partner_type' => $partner_type,
                'status' => $data['status'] ?? $partner->status,
                'note' => $data['note'] ?? $partner->note,
                'updated_by' => $user->id,
            ]);

            /**
             * 🔹 Atualiza contatos (N:N)
             */
            if (isset($data['contacts'])) {
                $contactIds = [];

                foreach ($data['contacts'] as $contactData) {
                    $contact = ContactsModel::where('company_id', $companyId)
                        ->where('name', $contactData['name'])
                        ->first();

                    if (!$contact) {
                        $contact = ContactsModel::create([
                            'company_id' => $companyId,
                            'name' => $contactData['name'],
                            'type' => $contactData['type'] ?? null,
                            'contact' => $contactData['contact'] ?? null,
                            'note' => $contactData['note'] ?? null,
                            'created_by' => $user->id,
                        ]);
                    } else {
                        $contact->update([
                            'type' => $contactData['type'] ?? $contact->type,
                            'contact' => $contactData['contact'] ?? $contact->contact,
                            'note' => $contactData['note'] ?? $contact->note,
                            'updated_by' => $user->id,
                        ]);
                    }

                    $contactIds[$contact->id] = [
                        'updated_at' => now(),
                        'updated_by' => $user->id,
                    ];
                }

                $partner->contacts()->sync($contactIds);
            }

            /**
             * 🔹 Atualiza endereços (N:N)
             */
            if (isset($data['addresses'])) {
                $addressIds = [];

                foreach ($data['addresses'] as $addrData) {
                    $address = AddressModel::where('company_id', $companyId)
                        ->where('zip_code', $addrData['zip_code'])
                        ->where('street', $addrData['street'])
                        ->first();

                    if (!$address) {
                        $address = AddressModel::create([
                            'company_id' => $companyId,
                            'zip_code' => $addrData['zip_code'],
                            'street' => $addrData['street'],
                            'complement' => $addrData['complement'] ?? null,
                            'neighborhood' => $addrData['neighborhood'] ?? null,
                            'city' => $addrData['city'],
                            'state' => $addrData['state'],
                            'status' => $addrData['status'] ?? 'A',
                            'active' => $addrData['active'] ?? true,
                            'created_by' => $user->id,
                        ]);
                    } else {
                        $address->update([
                            'complement' => $addrData['complement'] ?? $address->complement,
                            'neighborhood' => $addrData['neighborhood'] ?? $address->neighborhood,
                            'city' => $addrData['city'] ?? $address->city,
                            'state' => $addrData['state'] ?? $address->state,
                            'status' => $addrData['status'] ?? $address->status,
                            'active' => $addrData['active'] ?? $address->active,
                            'updated_by' => $user->id,
                        ]);
                    }

                    $addressIds[$address->id] = [
                        'number' => $addrData['number'] ?? null,
                        'updated_at' => now(),
                        'updated_by' => $user->id,
                    ];
                }

                $partner->addresses()->sync($addressIds);
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
            $companyId = $user->company_id;

            // 🔹 Busca o parceiro com os relacionamentos N:N
            $partner = PartnerModel::with(['contacts', 'addresses'])
                ->where('company_id', $companyId)
                ->find($id);

            if (!$partner) {
                return $this->errorResponse(
                    'Partner not found.',
                    'NOT_FOUND',
                    404
                );
            }

            // 🔹 Monta a resposta formatada
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
                        // 🔹 Pega o número da tabela pivô address_partner
                        'number' => $a->pivot->number ?? null,
                        'neighborhood' => $a->neighborhood,
                        'city' => $a->city,
                        'state' => $a->state,
                        'complement' => $a->complement,
                        'status' => $a->status,
                        'active' => (bool) $a->active,
                        'company_id' => $a->company_id,
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
            $companyId = $user->company_id;

            // 🔹 Busca o parceiro dentro da empresa do usuário logado
            $partner = PartnerModel::where('company_id', $companyId)
                ->with(['contacts', 'addresses'])
                ->where('id', $id)
                ->first();

            if (!$partner) {
                return $this->errorResponse(
                    'Partner not found or does not belong to your company.',
                    'NOT_FOUND',
                    404
                );
            }

            DB::beginTransaction();

            // 🔹 Atualiza deleted_by e faz soft delete dos contatos
            if ($partner->contacts && $partner->contacts->isNotEmpty()) {
                foreach ($partner->contacts as $contact) {
                    $contact->update(['deleted_by' => $user->id]);
                    $contact->delete();
                }
            }

            // 🔹 Atualiza deleted_by e faz soft delete dos endereços
            if ($partner->addresses && $partner->addresses->isNotEmpty()) {
                foreach ($partner->addresses as $address) {
                    $address->update(['deleted_by' => $user->id]);
                    $address->delete();
                }
            }

            // 🔹 Marca o partner como excluído (soft delete)
            $partner->update(['deleted_by' => $user->id]);
            $partner->delete();

            DB::commit();

            return $this->deletedResponse(
                'Partner and all related records successfully soft deleted!',
                [
                    'id' => $partner->id,
                    'deleted_at' => $partner->deleted_at,
                    'deleted_by' => $user->id,
                ]
            );
        });
    }

}