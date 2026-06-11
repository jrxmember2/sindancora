<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard modular: monta a página (metadados + dados dos widgets não-lazy),
 * serve dados de widgets sob demanda (lazy/refresh) e persiste preferências.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard/Index', $this->dashboard->buildPage($request));
    }

    /** Dados de um widget específico (JSON) — usado para widgets lazy e atualização. */
    public function widget(Request $request, string $key): JsonResponse
    {
        return response()->json([
            'key' => $key,
            'data' => $this->dashboard->resolveWidget($request, $key),
        ]);
    }

    /** Salva preferências de personalização (widgets ocultos e ordem). */
    public function preferences(Request $request): RedirectResponse
    {
        $this->dashboard->savePreferences($request);

        return back();
    }
}
