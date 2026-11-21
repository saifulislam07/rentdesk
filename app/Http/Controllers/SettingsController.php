<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('settings.manage')]
class SettingsController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function edit(): View
    {
        return view('settings.edit', [
            'values' => $this->settings->current(),
            'dateFormats' => UpdateSettingsRequest::DATE_FORMATS,
            'timeFormats' => UpdateSettingsRequest::TIME_FORMATS,
            'sample' => now('UTC')->setDate(2026, 12, 25)->setTime(15, 30),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->validated());

        return redirect()->route('settings.edit')->with('status', __('Settings saved.'));
    }
}
