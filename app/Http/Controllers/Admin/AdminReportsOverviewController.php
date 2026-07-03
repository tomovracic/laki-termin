<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportsOverviewController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('admin/reports/index');
    }
}
