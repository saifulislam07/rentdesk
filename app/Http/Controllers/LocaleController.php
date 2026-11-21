<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Remember the language the visitor chose for the rest of their session.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', Rule::in(array_keys(config('rentdesk.locales')))],
        ])['locale'];

        $request->session()->put('locale', $locale);

        return back();
    }
}
