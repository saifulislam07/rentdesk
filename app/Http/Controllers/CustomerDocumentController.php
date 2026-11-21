<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerDocumentRequest;
use App\Models\Customer;
use App\Models\CustomerDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerDocumentController extends Controller
{
    public function store(CustomerDocumentRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $file = $request->file('file');

        $customer->documents()->create([
            'type' => $request->validated('type'),
            'file_path' => $file->store('customer-documents/'.$customer->id, 'local'),
            'original_name' => $file->getClientOriginalName(),
        ]);

        return back()->with('status', __('Document uploaded.'));
    }

    /**
     * Stream a document from private storage to authorised staff.
     */
    public function show(CustomerDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document->customer);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    /**
     * Toggle whether staff have verified the document.
     */
    public function update(CustomerDocument $document): RedirectResponse
    {
        Gate::authorize('update', $document->customer);

        $document->update(['verified_at' => $document->isVerified() ? null : now()]);

        return back()->with('status', __('Document updated.'));
    }

    public function destroy(CustomerDocument $document): RedirectResponse
    {
        Gate::authorize('update', $document->customer);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('status', __('Document deleted.'));
    }
}
