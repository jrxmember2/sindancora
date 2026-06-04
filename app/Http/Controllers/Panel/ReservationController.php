<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\ReservationConflictException;
use App\Http\Controllers\Controller;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $service)
    {
    }

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $reservations = Reservation::where('tenant_id', $tenant->id)
            ->with(['commonArea:id,name', 'condominium:id,name', 'requester:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->common_area_id, fn ($q, $id) => $q->where('common_area_id', $id))
            ->orderByDesc('date')
            ->orderBy('start_time')
            ->paginate(20)
            ->withQueryString();

        // Calendário: visão mensal (?month=YYYY-MM) ou semanal (?view=week&week=YYYY-MM-DD), por área.
        $view = $request->view === 'week' ? 'week' : 'month';

        if ($view === 'week') {
            $anchor = $this->parseDate($request->week);
            $start = $anchor->copy()->startOfWeek(Carbon::SUNDAY);
            $end = $anchor->copy()->endOfWeek(Carbon::SATURDAY);
        } else {
            $anchor = $this->parseMonth($request->month);
            $start = $anchor->copy()->startOfMonth();
            $end = $anchor->copy()->endOfMonth();
        }

        $calendarReservations = Reservation::where('tenant_id', $tenant->id)
            ->with('commonArea:id,name')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($request->common_area_id, fn ($q, $id) => $q->where('common_area_id', $id))
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get(['id', 'common_area_id', 'date', 'start_time', 'end_time', 'status'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'common_area_id' => $r->common_area_id,
                'common_area' => $r->commonArea?->name,
                'date' => $r->date instanceof Carbon ? $r->date->toDateString() : substr((string) $r->date, 0, 10),
                'start_time' => $r->start_time,
                'end_time' => $r->end_time,
                'status' => $r->status,
            ]);

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'calendar' => [
                'view' => $view,
                'month' => $anchor->copy()->startOfMonth()->format('Y-m'),
                'week_start' => $start->toDateString(),
                'reservations' => $calendarReservations,
            ],
            'areas' => $this->areaOptions($tenant->id),
            'statuses' => Reservation::STATUSES,
            'filters' => $request->only(['status', 'common_area_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Reservations/Create', [
            'areas' => $this->areaDetails($tenant->id),
            'selectedArea' => $request->area,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'common_area_id' => "required|uuid|exists:common_areas,id,tenant_id,{$tenant->id}",
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:1000',
        ]);

        $area = CommonArea::where('tenant_id', $tenant->id)->findOrFail($data['common_area_id']);
        $this->assertWithinAreaRules($area, $data);

        try {
            $reservation = $this->service->request($area, $data, Auth::id());
        } catch (ReservationConflictException $e) {
            return back()->withErrors(['start_time' => $e->getMessage()])->withInput();
        }

        return redirect()->route('reservations.show', $reservation)
            ->with('success', $reservation->status === 'approved' ? 'Reserva confirmada.' : 'Solicitação enviada para aprovação.');
    }

    public function show(Reservation $reservation): Response
    {
        $reservation = $this->authorizeTenant($reservation);
        $reservation->load(['commonArea', 'condominium:id,name', 'requester:id,name', 'decider:id,name']);

        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation,
            'statuses' => Reservation::STATUSES,
            'can' => [
                'approve' => Auth::user()->hasPermission('reservations:approve'),
                'reject' => Auth::user()->hasPermission('reservations:reject'),
                'cancel' => Auth::user()->hasPermission('reservations:cancel'),
            ],
        ]);
    }

    public function approve(Reservation $reservation): RedirectResponse
    {
        $reservation = $this->authorizeTenant($reservation);

        try {
            $this->service->approve($reservation);
        } catch (ReservationConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Reserva aprovada.');
    }

    public function reject(Request $request, Reservation $reservation): RedirectResponse
    {
        $reservation = $this->authorizeTenant($reservation);
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->service->reject($reservation, $data['reason'] ?? null);

        return back()->with('success', 'Reserva recusada.');
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $reservation = $this->authorizeTenant($reservation);
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->service->cancel($reservation, $data['reason'] ?? null);

        return back()->with('success', 'Reserva cancelada.');
    }

    /** Valida antecedência mínima e janela de horário da área. */
    private function assertWithinAreaRules(CommonArea $area, array $data): void
    {
        $errors = [];

        if ($area->min_advance_days > 0) {
            $minDate = Carbon::today()->addDays($area->min_advance_days);
            if (Carbon::parse($data['date'])->lt($minDate)) {
                $errors['date'] = "Esta área exige antecedência mínima de {$area->min_advance_days} dia(s).";
            }
        }

        if ($area->opening_time && $data['start_time'] < substr($area->opening_time, 0, 5)) {
            $errors['start_time'] = 'Horário antes da abertura da área ('.substr($area->opening_time, 0, 5).').';
        }
        if ($area->closing_time && $data['end_time'] > substr($area->closing_time, 0, 5)) {
            $errors['end_time'] = 'Horário após o fechamento da área ('.substr($area->closing_time, 0, 5).').';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function parseMonth(?string $month): Carbon
    {
        try {
            return $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::now()->startOfMonth();
        }
    }

    private function parseDate(?string $date): Carbon
    {
        try {
            return $date ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay() : Carbon::now()->startOfDay();
        } catch (\Throwable) {
            return Carbon::now()->startOfDay();
        }
    }

    private function authorizeTenant(Reservation $reservation): Reservation
    {
        abort_unless($reservation->tenant_id === app('tenant')->id, 403);

        return $reservation;
    }

    private function areaOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return CommonArea::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($a) => ['value' => $a->id, 'label' => $a->name]);
    }

    private function areaDetails(string $tenantId): \Illuminate\Support\Collection
    {
        return CommonArea::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'condominium_id', 'requires_approval', 'min_advance_days', 'opening_time', 'closing_time', 'fee', 'deposit', 'rules', 'capacity']);
    }
}
