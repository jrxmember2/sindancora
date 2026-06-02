<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Rules\CpfCnpj;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PersonController extends ApiController
{
    #[OA\Get(
        path: '/v1/persons',
        summary: 'Lista pessoas',
        security: [['apiKey' => []]],
        tags: ['Pessoas'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Person::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('name');

        return $this->paginated($query->paginate($this->perPage($request)), PersonResource::class);
    }

    #[OA\Get(
        path: '/v1/persons/{id}',
        summary: 'Detalha uma pessoa',
        security: [['apiKey' => []]],
        tags: ['Pessoas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function show(string $id): JsonResponse
    {
        return $this->item(new PersonResource(Person::findOrFail($id)));
    }

    #[OA\Post(
        path: '/v1/persons',
        summary: 'Cria uma pessoa',
        security: [['apiKey' => []]],
        tags: ['Pessoas'],
        responses: [new OA\Response(response: 201, description: 'Criada'), new OA\Response(response: 422, description: 'Validação')],
    )]
    public function store(Request $request): JsonResponse
    {
        $person = Person::create($this->validateData($request));

        return $this->item(new PersonResource($person), 201);
    }

    #[OA\Put(
        path: '/v1/persons/{id}',
        summary: 'Atualiza uma pessoa',
        security: [['apiKey' => []]],
        tags: ['Pessoas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $person = Person::findOrFail($id);
        $person->update($this->validateData($request, $person->id));

        return $this->item(new PersonResource($person));
    }

    private function validateData(Request $request, ?string $excludeId = null): array
    {
        $tenantId = app('tenant')->id;

        // Normaliza CPF/CNPJ para só dígitos antes de validar (consistência com o painel).
        $request->merge([
            'cpf' => preg_replace('/\D/', '', (string) $request->input('cpf')) ?: null,
        ]);

        return $request->validate([
            'name' => 'required|string|max:150',
            'cpf' => ['nullable', 'string', 'max:14', new CpfCnpj, "unique:persons,cpf,{$excludeId},id,tenant_id,{$tenantId}"],
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'zip_code' => 'nullable|string|max:9',
            'street' => 'nullable|string|max:200',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            'notes' => 'nullable|string|max:1000',
        ]);
    }
}
