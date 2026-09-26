<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Task 10 — F1, F2, F4 and the chatbot renderer.
 *
 * WHAT THESE CAN AND CANNOT PROVE. The header tests are real end-to-end
 * assertions: a response goes through the whole middleware stack and the
 * headers are read off it. The XSS tests split in two:
 *
 *   - F2 is genuinely behavioural. The fix is a Blade expression, so the
 *     expression can be rendered here with a hostile value and the result
 *     checked against the invariant that matters.
 *   - F1 and the chatbot renderer are JavaScript. PHPUnit cannot execute
 *     them, so those tests assert the SOURCE — that the unsafe interpolation
 *     is gone and the escaper is applied. That is a regression guard, not a
 *     proof. The proof was done by rendering the real templates with payloads
 *     and executing the result in node; the measured before/after is in
 *     project.md v7.37.
 */
class XssAndHeadersTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════
    // F4 — the headers, now set by the application
    // ══════════════════════════════════════════════════════════════

    private function headerRoute(): string
    {
        Route::get('/_test_security_headers', fn () => 'ok');

        return '/_test_security_headers';
    }

    public function test_every_response_carries_the_security_headers(): void
    {
        $response = $this->get($this->headerRoute());

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin');
    }

    /**
     * The two that were measured ABSENT in production while the nginx config
     * claimed them. If either disappears again, this is what says so.
     */
    public function test_the_two_headers_that_were_missing_in_production_are_set(): void
    {
        $path = $this->headerRoute();

        $this->get($path)->assertHeader('Referrer-Policy', 'strict-origin');

        // secure_url(), never a hardcoded https://localhost. TrustHosts pins
        // Symfony's trusted-host list in a STATIC on the Request class, so
        // once any earlier test has run a request with env=production that
        // pin survives for the whole PHP process. A request to a host outside
        // it is a 400 raised BEFORE this middleware runs — which is exactly
        // how the first version of this test passed alone and failed after
        // CsrfCookieSecurityTest, with the pinned pattern being
        // {^(.+\.)?127\.0\.0\.1$} and my hardcoded host being localhost.
        $this->get(secure_url($path))
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    /**
     * HSTS over plain http is ignored by browsers, and pinning localhost in a
     * developer's browser is a nuisance to undo by hand.
     */
    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        // assertOk() first, on purpose: assertHeaderMissing also passes on a
        // 400 that never reached the middleware, which is how the earlier
        // version of this test passed for the wrong reason.
        $this->get(url($this->headerRoute()))
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    /**
     * A duplicated X-Frame-Options is treated as a conflict and ignored
     * outright, so "set twice" is strictly worse than "set once". nginx no
     * longer adds these; if it is ever put back alongside the middleware,
     * the live response grows a second value. This catches the application
     * half of that.
     */
    public function test_no_security_header_is_sent_twice(): void
    {
        $response = $this->get($this->headerRoute());

        foreach (['x-content-type-options', 'x-frame-options', 'referrer-policy'] as $header) {
            $values = $response->headers->all()[$header] ?? [];
            $this->assertCount(1, $values, "{$header} must be sent exactly once, got: ".implode(' | ', $values));
        }
    }

    public function test_the_cron_routes_get_the_headers_too(): void
    {
        // routes/cron.php is deliberately outside the `web` group, which is
        // why the middleware is registered globally rather than on `web`.
        $this->get('/cron/run-schedule/definitely-not-the-secret')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    // ══════════════════════════════════════════════════════════════
    // F2 — a guest name interpolated into an inline event handler
    // ══════════════════════════════════════════════════════════════

    /**
     * The invariant, stated precisely. `Js::from` emits a SINGLE-quoted
     * JavaScript literal — not JSON — with `'`, `"`, `<`, `>`, `/` and
     * newlines all \u-escaped, e.g.
     *
     *     '+alert('XSS')+'   ->   ''+alert('XSS')+''
     *
     * Two things have to hold. First, the interior contains no raw quote,
     * angle bracket or newline, so there is nothing left that could end the
     * literal once the browser has decoded HTML entities in the attribute —
     * which it does BEFORE the JS parser runs, and which is exactly why
     * `{{ }}` was not enough here. Second, because of that, swapping the
     * outer quotes for double ones yields valid JSON, so the value can be
     * round-tripped back and compared to the original name.
     */
    public function test_a_hostile_guest_name_cannot_escape_the_javascript_string(): void
    {
        $payloads = [
            "'+alert('XSS')+'",            // the one that actually fired
            "');alert('XSS');//",
            "\\'+alert('XSS')+'",
            '"+alert(\'XSS\')+"',
            "Bob\n');alert('XSS');//",     // broke the button even with addslashes
            "</script><img src=x onerror=alert('XSS')>",
            "O'Brien",                     // and an ordinary name with an apostrophe
        ];

        foreach ($payloads as $payload) {
            $rendered = Blade::render(
                '{{ Illuminate\Support\Js::from($v) }}',
                ['v' => $payload]
            );

            $asTheJsEngineSeesIt = html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $this->assertMatchesRegularExpression("/^'.*'$/s", $asTheJsEngineSeesIt);
            $interior = substr($asTheJsEngineSeesIt, 1, -1);

            foreach (["'", '"', '<', '>', "\n", "\r"] as $terminator) {
                $this->assertStringNotContainsString($terminator, $interior,
                    'Nothing that can end the literal may survive, in: '.$payload);
            }

            $this->assertSame($payload, json_decode('"'.$interior.'"', true),
                'And the value must still be exactly the name: '.$payload);
        }
    }

    public function test_the_checkout_button_no_longer_pastes_the_name_into_the_handler(): void
    {
        $source = file_get_contents(resource_path('views/staff/partials/_today_list.blade.php'));

        $this->assertStringNotContainsString(
            "confirm('Check out {{ \$booking->user->full_name }}?')",
            $source,
            'This exact expression was measured executing alert() from a guest-set name.'
        );
        $this->assertStringContainsString('Js::from($booking->user->full_name)', $source);
    }

    /**
     * F3 — the sweep, rather than seven separate assertions.
     *
     * A quoted Blade echo inside an inline handler is the shape that was
     * measured executing, so none should exist anywhere. This catches the next
     * one somebody writes, which is the part a per-file test cannot do.
     */
    public function test_no_inline_handler_pastes_a_blade_echo_into_a_javascript_string(): void
    {
        $offenders = [];

        foreach (\Illuminate\Support\Facades\File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            // on<something>="…'{{ … }}'…"
            preg_match_all('/\bon[a-z]+="[^"]*\'\{\{[^}]*\}\}\'[^"]*"/i', $source, $matches);

            foreach ($matches[0] as $hit) {
                // route()/url() build the value themselves and take no user
                // input; everything else is data and must go through Js::from.
                if (preg_match('/\{\{\s*(route|url|asset|secure_url)\s*\(/', $hit)) {
                    continue;
                }

                $offenders[] = str_replace((string) resource_path('views'), '', $file->getPathname())
                    .' -> '.trim($hit);
            }
        }

        $this->assertSame([], $offenders,
            "An inline handler is pasting a Blade echo into a JS string. Use Js::from():\n".implode("\n", $offenders));
    }

    // ══════════════════════════════════════════════════════════════
    // F8 / F9 — what the production container tells a visitor
    //
    // A FILE ASSERTION, and weaker than it looks: it proves the directive is
    // written, not that the image loads it. That was verified separately by
    // running `php:8.2-fpm-alpine` with this exact file mounted at
    // conf.d/99-villa-elena.ini and reading the values back — before: '1',
    // '1', '1', '0'; after: Off, Off, On, Off. See project.md v7.38.
    // ══════════════════════════════════════════════════════════════

    public function test_the_production_php_ini_does_not_print_errors_to_the_visitor(): void
    {
        $ini = file_get_contents(base_path('docker/php.ini'));

        // The image activates no main php.ini, so PHP's compiled defaults
        // apply and they are display_errors=1, display_startup_errors=1,
        // log_errors=0, expose_php=1. Every one of these has to be stated.
        $this->assertMatchesRegularExpression('/^display_errors\s*=\s*Off$/mi', $ini);
        $this->assertMatchesRegularExpression('/^display_startup_errors\s*=\s*Off$/mi', $ini);
        $this->assertMatchesRegularExpression('/^log_errors\s*=\s*On$/mi', $ini,
            'An error shown to nobody and logged nowhere is the worst of both.');
        $this->assertMatchesRegularExpression('/^expose_php\s*=\s*Off$/mi', $ini);
    }

    /**
     * conf.d is the only directory scanned when there is no main php.ini, so a
     * Dockerfile that stopped copying the file there would silently restore
     * every compiled default.
     */
    public function test_the_php_ini_is_installed_where_php_will_read_it(): void
    {
        $this->assertStringContainsString(
            'COPY docker/php.ini /usr/local/etc/php/conf.d/',
            file_get_contents(base_path('Dockerfile'))
        );
    }

    // ══════════════════════════════════════════════════════════════
    // F1 — the admin global search (source guard; see the class note)
    // ══════════════════════════════════════════════════════════════

    public function test_the_admin_search_escapes_every_value_it_renders(): void
    {
        $source = file_get_contents(resource_path('views/admin/partials/topbar_features.blade.php'));

        $this->assertStringContainsString('function escapeHtml(', $source,
            'The search renderer had no escaper at all.');

        foreach ([
            '${g.name}', '${g.email}', '${b.guest}', '${b.property}',
            '${b.booking_ref}', '${p.name}', '${p.type}', '${p.status}',
            '${query}',
        ] as $raw) {
            $this->assertStringNotContainsString($raw, $source,
                "Raw interpolation into innerHTML: {$raw}");
        }
    }

    // ══════════════════════════════════════════════════════════════
    // The chatbot renderer
    // ══════════════════════════════════════════════════════════════

    public function test_the_chatbot_escapes_the_reply_and_the_card_fields(): void
    {
        $source = file_get_contents(resource_path('views/partials/chatbot.blade.php'));

        $this->assertStringContainsString('function escapeHtml(', $source);
        $this->assertStringContainsString('${escapeHtml(text).replace(/\n/g, \'<br>\')}', $source,
            'The reply is the model\'s output and is attacker-influenced.');

        foreach (['${p.name}', '${p.promo}', '${p.book_url}', '${p.slot_label}', '${a}'] as $raw) {
            $this->assertStringNotContainsString($raw, $source,
                "Raw interpolation into innerHTML: {$raw}");
        }
    }

    /**
     * escapeHtml() escapes & < > and NOTHING ELSE — measured against a real
     * DOM, a double quote comes back unchanged. Three card fields sit inside
     * src="", alt="" and href="", where that is an attribute breakout and
     * then an onerror= of the attacker's choosing. The first version of this
     * fix used escapeHtml in all three and was wrong.
     */
    public function test_attribute_position_uses_the_quote_escaping_variant(): void
    {
        $source = file_get_contents(resource_path('views/partials/chatbot.blade.php'));

        $this->assertStringContainsString('function escapeAttr(', $source);

        foreach (['src="${escapeAttr(p.image)}"', 'alt="${escapeAttr(p.name)}"', 'href="${escapeAttr(p.book_url)}"'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }

        $this->assertStringNotContainsString('src="${escapeHtml(', $source,
            'escapeHtml leaves quotes intact; an attribute needs escapeAttr.');
        $this->assertStringNotContainsString('href="${escapeHtml(', $source);
        $this->assertStringNotContainsString('alt="${escapeHtml(', $source);
    }
}
