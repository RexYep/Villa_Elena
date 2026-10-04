{{-- The password rule, shown under a new-password field and ticked off as
     the person types. Pass the field's id:

         @include('partials.password_rules', ['for' => 'password'])

     The list comes from App\Support\PasswordPolicy, the same class the
     server validates with, so the words here cannot drift from the rule.
     It is plain readable text without JavaScript; the ticking is done by
     partials/password_ui.blade.php, which every layout with a password
     form includes. --}}
<div class="pw-rules" id="{{ $for }}_rules" data-pw-rules-for="{{ $for }}">
    <p class="pw-rules-title">Your password needs:</p>
    <ul>
        @foreach (\App\Support\PasswordPolicy::requirements() as $rule)
            <li data-pw-rule
                @if ($rule['min']) data-min="{{ $rule['min'] }}" @else data-pattern="{{ $rule['pattern'] }}" @endif>
                <i class="bi bi-circle" aria-hidden="true"></i>
                <span>{{ $rule['label'] }}</span>
                <span class="pw-rule-state"></span>
            </li>
        @endforeach
    </ul>
</div>
