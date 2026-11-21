<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Http\Requests\CompleteMaintenanceRequest;
use App\Http\Requests\MaintenanceTaskRequest;
use App\Models\MaintenanceTask;
use App\Models\Unit;
use App\Services\MaintenanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Validation\Rule;

class MaintenanceTaskController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance) {}

    #[Authorize('viewAny', MaintenanceTask::class)]
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(MaintenanceStatus::class)]])['status'] ?? MaintenanceStatus::Open->value;

        return view('maintenance.index', [
            'status' => $status,
            'tasks' => MaintenanceTask::query()
                ->with(['unit.product', 'unit.location', 'plan'])
                ->where('status', $status)
                ->orderBy('due_at')
                ->paginate($this->perPage())
                ->withQueryString(),
        ]);
    }

    #[Authorize('create', MaintenanceTask::class)]
    public function store(MaintenanceTaskRequest $request, Unit $unit): RedirectResponse
    {
        $this->maintenance->open($unit, notes: $request->validated('notes'));

        return back()->with('status', __('Maintenance scheduled and the unit is blocked.'));
    }

    #[Authorize('update', 'task')]
    public function complete(CompleteMaintenanceRequest $request, MaintenanceTask $task): RedirectResponse
    {
        $this->maintenance->complete($task, $request->validated('cost'), $request->validated('notes'));

        return back()->with('status', __('Maintenance completed.'));
    }

    #[Authorize('update', 'task')]
    public function cancel(MaintenanceTask $task): RedirectResponse
    {
        $this->maintenance->cancel($task);

        return back()->with('status', __('Maintenance cancelled.'));
    }
}
