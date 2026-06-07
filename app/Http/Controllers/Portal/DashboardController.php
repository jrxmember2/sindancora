<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\Announcement;
use App\Models\Charge;
use App\Models\Document;
use App\Models\Occurrence;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithResident;

    public function index(): Response
    {
        $person = $this->resident();
        $condominiumIds = $this->condominiumIds();
        $userId = Auth::id();
        $plan = app('tenant')->activePlan();
        $hasFinancial = (bool) $plan?->hasModule('financial');

        $readIds = Announcement::query()
            ->whereIn('condominium_id', $condominiumIds ?: ['-'])
            ->visible()
            ->whereHas('reads', fn ($q) => $q->where('user_id', $userId))
            ->pluck('id');

        $unreadAnnouncements = Announcement::query()
            ->whereIn('condominium_id', $condominiumIds ?: ['-'])
            ->visible()
            ->whereNotIn('id', $readIds)
            ->count();

        $recentAnnouncements = Announcement::query()
            ->whereIn('condominium_id', $condominiumIds ?: ['-'])
            ->visible()
            ->orderByDesc('published_at')
            ->take(3)
            ->get(['id', 'title', 'category', 'urgency', 'published_at']);

        $openOccurrences = Occurrence::where('created_by', $userId)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        $reservations = Reservation::where('requested_by', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->where('date', '>=', now()->toDateString())
            ->with('commonArea:id,name')
            ->orderBy('date')
            ->orderBy('start_time')
            ->take(3)
            ->get(['id', 'common_area_id', 'date', 'start_time', 'end_time', 'status']);

        $documentsCount = Document::query()
            ->whereIn('condominium_id', $condominiumIds ?: ['-'])
            ->where('visibility', 'residents')
            ->count();

        $openCharges = $hasFinancial
            ? Charge::query()
                ->whereIn('unit_id', $this->unitIds() ?: ['-'])
                ->whereIn('status', ['pending', 'overdue'])
                ->sum('amount')
            : 0;

        return Inertia::render('Portal/Dashboard', [
            'resident' => [
                'name' => $person->name,
                'units' => $this->activeLinks()->map(fn ($l) => [
                    'id' => $l->unit->id,
                    'number' => $l->unit->number,
                    'block' => $l->unit->block?->name,
                    'condominium' => $l->unit->condominium?->name,
                    'type' => $l->type,
                ]),
            ],
            'stats' => [
                'unread_announcements' => $unreadAnnouncements,
                'open_occurrences' => $openOccurrences,
                'documents' => $documentsCount,
                'open_charges' => (float) $openCharges,
            ],
            'recentAnnouncements' => $recentAnnouncements,
            'upcomingReservations' => $reservations,
            'reservationStatuses' => Reservation::STATUSES,
            'announcementCategories' => Announcement::CATEGORIES,
        ]);
    }
}
