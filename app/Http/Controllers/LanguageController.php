<?php

namespace App\Http\Controllers;

use App\Http\Requests\LanguageRequest;
use App\Models\Language;
use App\Services\Languages;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Authorize('settings.manage')]
class LanguageController extends Controller
{
    public function __construct(private readonly Languages $languages) {}

    public function index(): View
    {
        $languages = Language::query()->orderByRaw('code = ? desc', [Language::BASE])->orderBy('name')->get();

        return view('languages.index', [
            'languages' => $languages,
            'progress' => $languages->mapWithKeys(fn (Language $language): array => [$language->code => $this->languages->progress($language)]),
        ]);
    }

    public function create(): View
    {
        return view('languages.form', ['language' => new Language(['dir' => 'ltr', 'is_active' => true])]);
    }

    public function store(LanguageRequest $request): RedirectResponse
    {
        $imported = null;

        DB::transaction(function () use ($request, &$imported): void {
            $language = Language::create($request->safe()->only(['code', 'name', 'dir', 'is_active']));
            $imported = $this->importTranslations($request, $language);
        });

        return $this->saved(__('Language added.'), $imported);
    }

    public function edit(Language $language): View
    {
        return view('languages.form', ['language' => $language, 'progress' => $this->languages->progress($language)]);
    }

    public function update(LanguageRequest $request, Language $language): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'dir', 'is_active']);

        if ($language->isProtected()) {
            $data['is_active'] = true;
        }

        DB::transaction(function () use ($request, $language, $data, &$imported): void {
            $language->update($data);
            $imported = $this->importTranslations($request, $language);
        });

        return $this->saved(__('Language updated.'), $imported);
    }

    public function destroy(Language $language): RedirectResponse
    {
        if ($language->isProtected()) {
            return back()->with('error', __('The base language and the default language cannot be removed. Choose another default language in Settings first.'));
        }

        $language->delete();
        $this->languages->remove($language);

        return $this->saved(__('Language removed.'));
    }

    /**
     * The language's strings as a JSON file to translate and upload again.
     */
    public function export(Language $language): StreamedResponse
    {
        $json = json_encode($this->languages->export($language), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return response()->streamDownload(function () use ($json): void {
            echo $json."\n";
        }, "{$language->code}.json", ['Content-Type' => 'application/json']);
    }

    private function importTranslations(LanguageRequest $request, Language $language): ?int
    {
        if (! $request->hasFile('translations') || $language->isBase()) {
            return null;
        }

        return $this->languages->import($language, $request->file('translations'));
    }

    private function saved(string $message, ?int $imported = null): RedirectResponse
    {
        $this->languages->forget();

        return to_route('languages.index')->with('status', $imported === null ? $message : $message.' '.trans_choice(':count translation imported.|:count translations imported.', $imported));
    }
}
