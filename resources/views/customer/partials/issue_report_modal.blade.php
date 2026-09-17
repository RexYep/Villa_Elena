{{--
    "Report an Issue" popup — pinagsasaluhan ng booking details page at ng
    dashboard (v7.11). Iisang kopya ng form, para hindi magkaiba ang dalawa.

    Buksan gamit ang anumang button na may
    data-bs-toggle="modal" data-bs-target="#issueReportModal".

    @param \App\Models\Booking $booking  ang booking na naka-check-in
--}}
@php $issueErrors = $errors->issueReport; @endphp

<div class="modal fade" id="issueReportModal" tabindex="-1" aria-labelledby="issueReportTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content issue-modal">
            <form method="POST" action="{{ route('customer.bookings.issues.store', $booking) }}" id="issueForm">
                @csrf
                <div class="issue-modal-head">
                    <div>
                        <h2 id="issueReportTitle">Report an Issue</h2>
                        <p>Goes straight to our staff on duty — no need to look for someone in person.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="issue-modal-body">
                    <fieldset>
                        <legend class="issue-label">What's the problem?</legend>
                        <div class="issue-pick">
                            @foreach (\App\Models\IssueReport::CATEGORIES as $key => $cat)
                                <label>
                                    <input type="radio" name="category" value="{{ $key }}" required
                                        @checked(old('category') === $key)>
                                    <i class="bi bi-{{ $cat['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $cat['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @if ($issueErrors->has('category'))
                        <div class="issue-error">{{ $issueErrors->first('category') }}</div>
                    @endif

                    <label for="issueDescription" class="issue-label" style="margin-top:16px;">
                        Tell us a bit more <span class="issue-optional">(required for "Other")</span>
                    </label>
                    <textarea name="description" id="issueDescription" class="form-control" rows="3"
                        maxlength="{{ \App\Models\IssueReport::DESCRIPTION_MAX }}"
                        placeholder="e.g. The aircon in the second bedroom is blowing warm air">{{ old('description') }}</textarea>
                    @if ($issueErrors->has('description'))
                        <div class="issue-error">{{ $issueErrors->first('description') }}</div>
                    @endif

                    <div class="issue-modal-actions">
                        <button type="button" class="btn-issue-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-issue">
                            <i class="bi bi-send me-1"></i> Send to staff
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>
            .btn-report-issue {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 7px;
                background: #dc2626;
                color: #fff;
                border: none;
                border-radius: 10px;
                padding: 11px 18px;
                min-height: 44px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: background .2s;
            }

            .btn-report-issue:hover {
                background: #b91c1c;
                color: #fff;
            }

            .issue-modal {
                border: none;
                border-radius: 16px;
            }

            .issue-modal-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                padding: 20px 22px 14px;
                border-bottom: 1px solid var(--border);
            }

            .issue-modal-head h2 {
                font-family: 'Playfair Display', serif;
                font-size: 20px;
                font-weight: 600;
                margin: 0;
                color: var(--stone);
            }

            .issue-modal-head p {
                font-size: 13px;
                color: var(--muted);
                margin: 4px 0 0;
                line-height: 1.5;
            }

            .issue-modal-body {
                padding: 18px 22px 22px;
            }

            .issue-modal fieldset {
                border: none;
                padding: 0;
                margin: 0;
            }

            .issue-label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: var(--stone);
                margin-bottom: 8px;
            }

            .issue-optional {
                font-weight: 400;
                color: var(--muted);
            }

            .issue-pick {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
            }

            .issue-pick label {
                position: relative;
                border: 1.5px solid var(--border);
                border-radius: 10px;
                padding: 10px 4px;
                min-height: 44px;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                font-size: 13px;
                cursor: pointer;
                text-align: center;
            }

            .issue-pick label i {
                font-size: 18px;
            }

            .issue-pick input {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }

            .issue-pick label:has(input:checked) {
                border-color: #dc2626;
                background: #fef2f2;
                color: #b91c1c;
                font-weight: 600;
            }

            .issue-pick label:has(input:focus-visible) {
                outline: 2px solid #dc2626;
                outline-offset: 2px;
            }

            .issue-error {
                color: #dc2626;
                font-size: 13px;
                margin-top: 6px;
            }

            .issue-modal-actions {
                display: flex;
                gap: 10px;
                margin-top: 18px;
            }

            .issue-modal-actions .btn-issue {
                flex: 2;
                background: #dc2626;
                color: #fff;
                border: none;
                border-radius: 10px;
                padding: 12px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
            }

            .issue-modal-actions .btn-issue:hover {
                background: #b91c1c;
            }

            .btn-issue-cancel {
                flex: 1;
                background: #f1f5f9;
                color: #374151;
                border: none;
                border-radius: 10px;
                padding: 12px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
            }

            @media (max-width:420px) {
                .issue-pick {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function() {
                const form = document.getElementById('issueForm');
                if (!form) return;

                // Pinipigilan ang dobleng pag-send ng ulat.
                form.addEventListener('submit', function(e) {
                    const btn = form.querySelector('button[type="submit"]');
                    if (btn.disabled) {
                        e.preventDefault();
                        return;
                    }
                    btn.disabled = true;
                    btn.textContent = 'Sending…';
                });

                @if ($issueErrors->any())
                    // Validation error — buksan muli ang popup, kasama ang inilagay na.
                    document.addEventListener('DOMContentLoaded', () =>
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('issueReportModal')).show());
                @endif
            })();
        </script>
    @endpush
@endonce
