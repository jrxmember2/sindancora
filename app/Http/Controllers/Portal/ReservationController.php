<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\ReservationConflictException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
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
    use InteractsWithResident;

    public function __construct(private readonly ReservationService $service) {}

    public function index(Request $request): Response
    {
        $condominiumIds = $this->condominiumIds() ?: ['-'];

        $reservations = Reservation::where('requested_by', Auth::id())
            ->with(['commonArea:id,name', 'condominium:id,name'])
            ->orderByDesc('date')
            ->orderBy('start_time')
            ->paginate(15);

        // Disponibilidade do mês: todas as reservas confirmadas/pendentes das áreas dos meus condomínios.
        $month = $this->parseMonth($request->month);
        $monthReservations = Reservation::whereIn('condominium_id', $condominiumIds)
            ->with('commonArea:id,name')
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('start_time')
            ->get(['id', 'common_area_id', 'date', 'start_time', 'end_time', 'status']);

        return Inertia::render('Portal/Reservations/Index', [
            'reservations' => $reservations,
            'monthReservations' => $monthReservations,
            'month' => $month->format('Y-m'),
            'areas' => $this->areaOptions($condominiumIds),
            'statuses' => Reservation::STATUSES,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Portal/Reservations/Create', [
            'areas' => $this->areaDetails($this->condominiumIds() ?: ['-']),
            'selectedArea' => $request->area,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $condominiumIds = $this->condominiumIds() ?: ['-'];

        $data = $request->validate([
            'common_area_id' => 'required|uuid',
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:1000',
        ]);

        // A área precisa pertencer a um condomínio do morador e estar ativa.
        $area = CommonArea::whereIn('condominium_id', $condominiumIds)
            ->where('active', true)
            ->findOrFail($data['common_area_id']);

        $this->assertWithinAreaRules($area, $data);

        try {
            $reservation = $this->service->request($area, $data, Auth::id());
        } catch (ReservationConflictException $e) {
            return back()->withErrors(['start_time' => $e->getMessage()])->withInput();
        }

        return redirect()->route('portal.reservations.show', $reservation)
            ->with('success', $reservation->status === 'approved'
                ? 'Reserva confirmada!'
                : 'Solicitação enviada. Você será avisado quando for analisada.');
    }

    public function show(Reservation $reservation): Response
    {
        $this->authorizeOwner($reservation);
        $reservation->load(['commonArea', 'condominium:id,name', 'decider:id,name']);

        return Inertia::render('Portal/Reservations/Show', [
            'reservation' => $reservation,
            'statuses' => Reservation::STATUSES,
        ]);
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeOwner($reservation);

        abort_unless(in_array($reservation->status, ['pending', 'approved'], true), 422, 'Esta reserva não pode ser cancelada.');

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->service->cancel($reservation, $data['reason'] ?? null);

        return back()->with('success', 'Reserva cancelada.');
    }

    /** A reserva precisa ser do tenant e ter sido solicitada pelo próprio morador. */
    private function authorizeOwner(Reservation $reservation): void
    {
        abort_unless($reservation->tenant_id === app('tenant')->id, 403);
        abort_unless($reservation->requested_by === Auth::id(), 403);
    }

    /** Valida antecedência mínima e janela de horário da área (espelha o painel). */
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

    private function areaOptions(array $condominiumIds): \Illuminate\Support\Collection
    {
        return CommonArea::whereIn('condominium_id', $condominiumIds)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($a) => ['value' => $a->id, 'label' => $a->name]);
    }

    private function areaDetails(array $condominiumIds): \Illuminate\Support\Collection
    {
        return CommonArea::whereIn('condominium_id', $condominiumIds)
            ->where('active', true)
            ->with('attachments')
            ->orderBy('name')
            ->get(['id', 'name', 'condominium_id', 'requires_approval', 'min_advance_days', 'opening_time', 'closing_time', 'fee', 'deposit', 'rules', 'capacity'])
            ->map(fn (CommonArea $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'condominium_id' => $a->condominium_id,
                'requires_approval' => $a->requires_approval,
                'min_advance_days' => $a->min_advance_days,
                'opening_time' => $a->opening_time,
                'closing_time' => $a->closing_time,
                'fee' => $a->fee,
                'deposit' => $a->deposit,
                'rules' => $a->rules,
                'capacity' => $a->capacity,
                'photos' => $a->attachmentsPayload(),
            ]);
    }
}
