<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $logs = AuditLog::where('tenant_id', $tenant->id)
            ->with('user:id,name,email')
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->entity, fn ($q, $v) => $q->where('entity', $v))
            ->when($request->action, fn ($q, $v) => $q->where('action', $v))
            ->when($request->date_from, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $users = User::where('tenant_id', $tenant->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $entities = AuditLog::where('tenant_id', $tenant->id)
            ->distinct()
            ->pluck('entity')
            ->sort()
            ->values();

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'users' => $users,
            'entities' => $entities,
            'filters' => $request->only(['user_id', 'entity', 'action', 'date_from', 'date_to']),
        ]);
    }
}
