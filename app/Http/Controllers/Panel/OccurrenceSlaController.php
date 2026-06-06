<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Occurrence;
use App\Models\OccurrenceSlaSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OccurrenceSlaController extends Controller
{
    public function edit(): Response
    {
        $tenant = app('tenant');
        $setting = OccurrenceSlaSetting::where('tenant_id', $tenant->id)->first();

        // Mescla os dias salvos com os padrões, garantindo todas as prioridades no form.
        $days = [];
        foreach (array_keys(Occurrence::PRIORITIES) as $priority) {
            $days[$priority] = $setting->days_by_priority[$priority] ?? Occurrence::SLA_DEFAULT_DAYS[$priority] ?? 5;
        }

        return Inertia::render('Settings/OccurrenceSla', [
            'days' => $days,
            'priorities' => Occurrence::PRIORITIES,
            'defaults' => Occurrence::SLA_DEFAULT_DAYS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $rules = [];
        foreach (array_keys(Occurrence::PRIORITIES) as $priority) {
            $rules["days.{$priority}"] = 'required|integer|min:0|max:365';
        }
        $data = $request->validate($rules);

        OccurrenceSlaSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['days_by_priority' => $data['days']],
        );

        return back()->with('success', 'SLA atualizado. Vale para novas ocorrências.');
    }
}
