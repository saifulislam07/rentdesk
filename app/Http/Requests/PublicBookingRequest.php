<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\Product;
use App\Services\AvailabilityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class PublicBookingRequest extends FormRequest
{
    /** Fastest a human could plausibly fill in the form, in seconds. */
    private const MIN_FILL_SECONDS = 3;

    private const MAX_DAYS = 90;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'qty' => ['required', 'integer', 'min:1', 'max:100'],
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'message' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string'],
            'started' => ['required', 'string'],
        ];
    }

    /**
     * Rejects forms submitted faster than a person can type, and then checks the stock.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filledInByAPerson()) {
                    $validator->errors()->add('started', __('Please take a moment to check your details and submit again.'));
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = $this->startAt();
                $end = $this->endAt();

                if ($start->diffInDays($end) > self::MAX_DAYS) {
                    $validator->errors()->add('end_at', __('Online requests are limited to :days days.', ['days' => self::MAX_DAYS]));

                    return;
                }

                $product = Product::with('rates')->findOrFail($this->integer('product_id'));

                if ($product->rates->isEmpty()) {
                    $validator->errors()->add('product_id', __('This product cannot be booked online.'));

                    return;
                }

                $available = app(AvailabilityChecker::class)->availableQuantity(
                    $product,
                    $start,
                    $end,
                    Location::find($this->integer('location_id'))?->id,
                );

                if ($available < $this->integer('qty')) {
                    $validator->errors()->add('product_id', __('Not enough of this product is free for those dates. Try other dates or a smaller quantity.'));
                }
            },
        ];
    }

    public function startAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input('start_at'), 'UTC');
    }

    public function endAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->input('end_at'), 'UTC');
    }

    /**
     * Whether the hidden decoy field was filled in, which only automated clients do.
     */
    public function isBot(): bool
    {
        return $this->filled('website');
    }

    /**
     * The token the form embeds when it is rendered.
     */
    public static function startedToken(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    private function filledInByAPerson(): bool
    {
        try {
            $startedAt = (int) Crypt::decryptString((string) $this->input('started'));
        } catch (DecryptException) {
            return false;
        }

        return now()->timestamp - $startedAt >= self::MIN_FILL_SECONDS;
    }
}
