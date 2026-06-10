<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Rules\CpfCnpj;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantProfileController extends Controller
{
    public function __construct(private readonly StorageService $storage) {}

    public function edit(): Response
    {
        $tenant = app('tenant');
        $profile = $tenant->getReportProfile();

        return Inertia::render('Settings/TenantProfile', [
            'profile' => [
                'person_type' => $profile['person_type'],
                'legal_name' => $profile['legal_name'],
                'trade_name' => $profile['trade_name'],
                'document' => $profile['document'],
                'email' => $profile['email'],
                'phone' => $profile['phone'],
                'primary_color' => $tenant->getPrimaryColor(),
                'logo_url' => $tenant->getLogoUrl(),
                'address' => [
                    'zip_code' => data_get($profile, 'address.zip_code'),
                    'street' => data_get($profile, 'address.street'),
                    'number' => data_get($profile, 'address.number'),
                    'complement' => data_get($profile, 'address.complement'),
                    'neighborhood' => data_get($profile, 'address.neighborhood'),
                    'city' => data_get($profile, 'address.city'),
                    'state' => data_get($profile, 'address.state'),
                ],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $request->merge(['document' => preg_replace('/\D/', '', (string) $request->input('document')) ?: null]);

        $data = $request->validate([
            'person_type' => 'required|in:individual,company',
            'legal_name' => 'required|string|max:180',
            'trade_name' => 'nullable|string|max:180',
            'document' => ['nullable', 'string', 'max:18', new CpfCnpj],
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:25',
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'zip_code' => 'nullable|string|max:9',
            'street' => 'nullable|string|max:200',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            // Aceita SVG além de raster; limite 3 MB. Não usa a regra "image" porque ela bloqueia SVG.
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:3072',
            'remove_logo' => 'nullable|boolean',
        ]);

        $settings = $tenant->settings ?? [];
        $displayName = $data['trade_name'] ?: $data['legal_name'];

        data_set($settings, 'brand.name', $displayName);
        data_set($settings, 'brand.primary_color', $data['primary_color'] ?: '#1e40af');
        data_set($settings, 'profile.person_type', $data['person_type']);
        data_set($settings, 'profile.legal_name', $data['legal_name']);
        data_set($settings, 'profile.trade_name', $data['trade_name'] ?: null);
        data_set($settings, 'profile.document', $data['document'] ?? null);
        data_set($settings, 'profile.email', $data['email'] ?? null);
        data_set($settings, 'profile.phone', $data['phone'] ?? null);
        data_set($settings, 'profile.address', [
            'zip_code' => $data['zip_code'] ?? null,
            'street' => $data['street'] ?? null,
            'number' => $data['number'] ?? null,
            'complement' => $data['complement'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => isset($data['state']) ? strtoupper($data['state']) : null,
        ]);

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            if ($logo = $tenant->logoObject()) {
                $this->storage->delete($logo);
            }

            data_forget($settings, 'brand.logo_storage_object_id');
            data_forget($settings, 'brand.logo_url');
        }

        if ($request->hasFile('logo')) {
            $logo = $this->storage->upload(
                file: $request->file('logo'),
                tenant: $tenant,
                entityType: Tenant::LOGO_ENTITY,
                entityId: $tenant->id,
                visibility: 'tenant',
            );

            data_set($settings, 'brand.logo_storage_object_id', $logo->id);
        }

        $tenant->update([
            'name' => $displayName,
            'document' => $data['document'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'settings' => $settings,
        ]);

        // O ResolveTenant carrega o tenant fresco a cada request (só o mapeamento domínio→id é
        // cacheado), então marca/logo/cor já refletem no próximo request sem invalidar nada.
        return back()->with('success', 'Dados do tenant atualizados.');
    }
}
