{{-- Page messages for the staff portal: one look and one dismiss button.

     Only a success message may hide itself, and only where the page asks
     for it ('autohide' => true). An error or a warning stays until it is
     dismissed: the frontdesk is a shared monitor that is not always being
     watched, and a refusal that vanishes after five seconds reads as
     "nothing happened".

     'validation' => false leaves out the first validation error, for a page
     that already prints each error under its own field. --}}
@foreach ([
    'success' => ['alert-success', 'check-circle', 'status'],
    'warning' => ['alert-warning', 'exclamation-triangle', 'alert'],
    'error' => ['alert-danger', 'exclamation-circle', 'alert'],
] as $flashKey => [$flashClass, $flashIcon, $flashRole])
    @if (session($flashKey))
        <div class="alert {{ $flashClass }}" role="{{ $flashRole }}"
            @if ($flashKey === 'success' && ($autohide ?? false)) data-alert-autohide @endif>
            <i class="bi bi-{{ $flashIcon }}" aria-hidden="true"></i>
            <span class="alert-text">{{ session($flashKey) }}</span>
            <button type="button" class="alert-close" data-alert-close aria-label="Dismiss message">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    @endif
@endforeach
@if (($validation ?? true) && $errors->any())
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <span class="alert-text">{{ $errors->first() }}</span>
        <button type="button" class="alert-close" data-alert-close aria-label="Dismiss message">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
@endif
