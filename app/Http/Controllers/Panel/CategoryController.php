<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        $categories = Category::where('tenant_id', $tenant->id)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'type', 'name', 'slug', 'color', 'sort_order', 'is_active']);

        return Inertia::render('Settings/Categories', [
            'categories' => $categories,
            'types' => Category::TYPES,
            'defaults' => [
                'occurrence' => \App\Models\Occurrence::CATEGORIES,
                'document' => \App\Models\Document::CATEGORIES,
                'supplier' => \App\Models\Supplier::CATEGORIES,
                'maintenance' => \App\Models\MaintenancePlan::CATEGORIES,
                'quotation' => \App\Models\Quotation::CATEGORIES,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request);

        Category::create([
            'tenant_id' => $tenant->id,
            'type' => $data['type'],
            'name' => $data['name'],
            'slug' => Category::makeSlug($tenant->id, $data['type'], $data['name']),
            'color' => $data['color'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Categoria criada.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category = $this->authorizeTenant($category);
        $data = $this->validated($request, withType: false);

        // O slug (valor armazenado nos registros) não muda na edição, para não órfãos.
        $category->update([
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Categoria atualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category = $this->authorizeTenant($category);
        $category->delete();

        return back()->with('success', 'Categoria removida. Os registros já classificados são preservados.');
    }

    private function validated(Request $request, bool $withType = true): array
    {
        $rules = [
            'name' => 'required|string|max:60',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'boolean',
        ];

        if ($withType) {
            $rules['type'] = 'required|in:'.implode(',', array_keys(Category::TYPES));
        }

        return $request->validate($rules);
    }

    private function authorizeTenant(Category $category): Category
    {
        abort_unless($category->tenant_id === app('tenant')->id, 403);

        return $category;
    }
}
