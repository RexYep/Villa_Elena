{{--
    The villa's Pricing card, shared by the create and edit forms.

    Expects $initialIsVilla, and $property on the edit form (null on create).

    Two groups, one per pair of price columns (Property::priceColumnsFor()):
    Day & Night, and both 22-hour slots. The slot names and times come from
    Booking::SLOTS, so a renamed or added slot shows up here without an edit.

    The amounts are text fields, not type="number", so they can show
    "10,000". PropertyController::normalizePrices() strips the commas before
    validation, so the form still works if the script below never runs.
--}}
@php
    $property = $property ?? null;

    $slotList = fn (bool $is22h) => collect(\App\Models\Booking::SLOTS)
        ->filter(fn ($slot) => (($slot['rate'] ?? null) === '22h') === $is22h)
        ->pluck('label')
        ->join(', ', ' and ');

    // "4000.00" → "4,000"; centavos are kept only when there are some. A value
    // that is not a number (a typo sent back after a failed save) is shown as
    // typed, so the admin can see what was refused.
    $shown = function (string $field) use ($property) {
        $value = old($field, $property?->{$field});
        if ($value === null || $value === '') {
            return '';
        }
        $plain = str_replace([',', '₱', ' '], '', (string) $value);
        if (! is_numeric($plain)) {
            return $value;
        }

        return number_format((float) $plain, fmod((float) $plain, 1) == 0 ? 0 : 2);
    };

    $groups = [
        [
            'title' => 'Day & Night',
            'slots' => $slotList(false),
            'fields' => [
                ['name' => 'base_price', 'label' => 'Regular Rate', 'required' => true,
                    'hint' => 'Mon–Thu, and Sun after 6PM', 'placeholder' => 'e.g. 4,000'],
                ['name' => 'weekend_price', 'label' => 'Peak Rate', 'required' => false,
                    'hint' => 'Fri, Sat, and Sun before 6PM', 'placeholder' => 'Same as Regular'],
            ],
            'note' => 'Leave Peak blank to charge the Regular rate every day.',
        ],
        [
            'title' => '22 Hours',
            'slots' => $slotList(true),
            'fields' => [
                ['name' => 'base_price_22h', 'label' => 'Regular Rate', 'required' => false,
                    'hint' => 'Mon–Thu, and Sun after 6PM', 'placeholder' => 'Not offered'],
                ['name' => 'weekend_price_22h', 'label' => 'Peak Rate', 'required' => false,
                    'hint' => 'Fri, Sat, and Sun before 6PM', 'placeholder' => 'Same as Regular'],
            ],
            'note' => null, // written out below, because it carries a link
        ],
    ];
@endphp

@push('styles')
    <style>
        .rate-intro {
            margin: 0 0 18px;
        }

        /* Reset Bootstrap's fieldset/legend so the group title reads as a
           small heading, not a 1.5rem caption floated left. */
        .rate-group {
            min-width: 0;
            margin: 0;
            padding: 0;
            border: 0;
        }

        .rate-group legend {
            float: none;
            width: auto;
            margin: 0 0 2px;
            padding: 0;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
        }

        .rate-group-slots {
            margin: 0 0 14px;
        }

        /* Each field spans four shared rows (label, hint, box, error), so
           both boxes in a pair start at the same height even when one hint
           wraps further than the other or only one field has an error. */
        .rate-pair {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 16px;
        }

        .rate-field {
            display: grid;
            grid-row: span 4;
            grid-template-rows: subgrid;
            align-content: start;
        }

        .rate-group-note {
            margin-top: 12px !important;
            margin-bottom: 0;
        }

        .rate-footnote {
            margin: 18px 0 0;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }

        .rate-footnote a,
        .rate-group-note a {
            color: var(--terracotta);
            font-weight: 500;
        }

        .input-prefix {
            position: relative;
        }

        .input-prefix span {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
            pointer-events: none;
        }

        .input-prefix input {
            padding-left: 28px;
        }

        @media (max-width: 560px) {
            .rate-pair {
                grid-template-columns: minmax(0, 1fr);
            }

            .rate-field {
                display: block;
            }

            .rate-field + .rate-field {
                margin-top: 14px;
            }
        }
    </style>
@endpush

<div class="form-card" id="villaOnlyPricing" style="display:{{ $initialIsVilla ? 'block' : 'none' }};">
    <div class="form-card-header">
        <div class="card-icon tag-amber"><i class="bi bi-tag"></i></div>
        <h3>Pricing</h3>
    </div>
    <div class="form-card-body">
        <p class="note-text-lg rate-intro">
            Each rate is a flat price for the whole slot. The day and time the stay starts decides
            whether the Regular or the Peak rate applies.
        </p>

        @foreach ($groups as $group)
            {{-- The divider is on a wrapper: a fieldset draws its own top
                 border through its legend. --}}
            <div class="{{ $loop->first ? '' : 'section-divider' }}">
            <fieldset class="rate-group">
                <legend>{{ $group['title'] }}</legend>
                <p class="note-text rate-group-slots">{{ $group['slots'] }}</p>

                <div class="rate-pair">
                    @foreach ($group['fields'] as $field)
                        @php
                            $id = 'f_'.$field['name'];
                            $hasError = $errors->has($field['name']);
                        @endphp
                        <div class="rate-field">
                            <label for="{{ $id }}" class="form-label">
                                {{ $field['label'] }}
                                @if ($field['required'])
                                    <span class="req" aria-hidden="true">*</span>
                                @endif
                            </label>
                            <small class="field-caption" id="{{ $id }}_hint">{{ $field['hint'] }}</small>
                            <div class="input-prefix">
                                <span aria-hidden="true">₱</span>
                                <input id="{{ $id }}" type="text" inputmode="decimal" autocomplete="off"
                                    name="{{ $field['name'] }}" data-peso-amount
                                    class="form-control {{ $hasError ? 'is-invalid' : '' }}"
                                    value="{{ $shown($field['name']) }}" placeholder="{{ $field['placeholder'] }}"
                                    aria-describedby="{{ $id }}_hint{{ $hasError ? ' '.$id.'_error' : '' }}"
                                    @if ($field['required']) aria-required="true" @endif
                                    @if ($hasError) aria-invalid="true" @endif>
                            </div>
                            @if ($hasError)
                                <div class="invalid-feedback" id="{{ $id }}_error">{{ $errors->first($field['name']) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($group['note'])
                    <p class="field-hint rate-group-note">{{ $group['note'] }}</p>
                @else
                    <p class="field-hint rate-group-note">
                        Offered only on dates you pick in
                        <a href="{{ route('admin.calendar.index') }}">Calendar</a> → 22-Hour Date. Day and Night are
                        not offered on those dates. Leave both rates blank to stop offering 22-hour stays, or leave
                        Peak blank to charge the Regular rate every day.
                    </p>
                @endif
            </fieldset>
            </div>
        @endforeach

        <p class="note-text rate-footnote">
            A <a href="{{ route('admin.promotions.index') }}">promo</a> or a holiday rate from
            <a href="{{ route('admin.prescriptive.index') }}">Recommendations</a> can change what a guest pays
            on particular dates.
        </p>
    </div>
</div>

@push('scripts')
    <script>
        // Show amounts with thousands separators once the admin leaves a box.
        // Display only: the server strips the commas again before saving.
        document.querySelectorAll('[data-peso-amount]').forEach((input) => {
            input.addEventListener('blur', () => {
                const plain = input.value.replace(/[,\s₱]/g, '');
                if (plain === '' || !/^\d+(\.\d+)?$/.test(plain)) return;
                input.value = Number(plain).toLocaleString('en-US', { maximumFractionDigits: 2 });
            });
        });
    </script>
@endpush
