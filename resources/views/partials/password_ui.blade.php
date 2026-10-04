{{-- Behaviour for password fields, included once by each layout that has a
     password form (auth, admin, staff, customer).

     1. Show / hide: `<button type="button" data-pw-toggle="fieldId">`.
     2. Live checklist: partials/password_rules.blade.php.

     Both are delegated from `document`, so a field added later needs
     nothing extra. --}}
<script>
    (function() {
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-pw-toggle]');
            if (!btn) return;
            const input = document.getElementById(btn.getAttribute('data-pw-toggle'));
            if (!input) return;

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', !show);
                icon.classList.toggle('bi-eye-slash', show);
            }
        });

        function check(box, value) {
            // Count characters, not UTF-16 units, the way the server does.
            const length = Array.from(value).length;
            box.querySelectorAll('[data-pw-rule]').forEach(function(item) {
                const min = item.getAttribute('data-min');
                const met = min ?
                    length >= parseInt(min, 10) :
                    new RegExp(item.getAttribute('data-pattern'), 'u').test(value);

                item.classList.toggle('is-met', met);
                const icon = item.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-circle', !met);
                    icon.classList.toggle('bi-check-circle-fill', met);
                }
                const state = item.querySelector('.pw-rule-state');
                if (state) state.textContent = met ? ' (met)' : ' (not met yet)';
            });
        }

        document.querySelectorAll('[data-pw-rules-for]').forEach(function(box) {
            const input = document.getElementById(box.getAttribute('data-pw-rules-for'));
            if (!input) return;
            if (!input.hasAttribute('aria-describedby')) input.setAttribute('aria-describedby', box.id);
            input.addEventListener('input', function() {
                check(box, input.value);
            });
            check(box, input.value);
        });
    })();
</script>
