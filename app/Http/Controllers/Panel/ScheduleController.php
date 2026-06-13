<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\ScheduleEventBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cronograma consolidado do painel. A montagem dos eventos vive em
 * ScheduleEventBuilder (compartilhado com a API do app móvel).
 */
class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleEventBuilder $builder) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Schedule/Index', $this->builder->build($request));
    }
}
