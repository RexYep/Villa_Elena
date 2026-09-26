<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Task 7 — file upload security.
 *
 * The validation half is asserted against the real rule with real hostile
 * bytes. The deployment half (nginx, php.ini) is asserted against the config
 * files, because there is nothing else to interrogate from PHPUnit — the
 * behavioural proof for those lives in project.md v7.30, measured against a
 * real nginx + php-fpm pair.
 */
class FileUploadSecurityTest extends TestCase
{
    /** The rule all three upload sites share. */
    private const RULE = 'nullable|image|mimes:jpg,jpeg,png,webp|max:3048';

    /** Laravel's per-file ceiling, in KB. */
    private const MAX_FILE_KB = 3048;

    /** Images accepted per request by Admin\PropertyController. */
    private const MAX_BATCH = 10;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->dir = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'ve_upload_test_'.bin2hex(random_bytes(4));
        @mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    // ── What may be uploaded ───────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function hostileFileProvider(): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $gif = 'GIF89a'.str_repeat("\x00", 10);
        $php = "<?php system(\$_GET['c']); ?>";
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
            .'<script>alert(document.cookie)</script></svg>';

        return [
            'plain PHP named .php' => ['shell.php', $php, 'application/x-php'],
            'plain PHP renamed .jpg' => ['shell.jpg', $php, 'image/jpeg'],
            'PHP with double extension' => ['shell.php.png', $php, 'image/png'],
            'GIF/PHP polyglot named .php' => ['x.php', $gif.$php, 'application/x-php'],
            'GIF/PHP polyglot named .gif' => ['x.gif', $gif.$php, 'image/gif'],
            'GIF/PHP polyglot named .png' => ['x.png', $gif.$php, 'image/png'],
            'scripted SVG named .svg' => ['x.svg', $svg, 'image/svg+xml'],
            'scripted SVG named .png' => ['x.png', $svg, 'image/png'],
            'HTML named .png' => ['x.png', '<html><script>alert(1)</script></html>', 'image/png'],
            'empty file named .png' => ['x.png', '', 'image/png'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hostileFileProvider')]
    public function test_hostile_uploads_are_rejected(string $name, string $bytes, string $claimedMime): void
    {
        $file = $this->makeUpload($name, $bytes, $claimedMime);

        $this->requireWorkingMimeDetection($file);

        $this->assertTrue(
            Validator::make(['f' => $file], ['f' => self::RULE])->fails(),
            "{$name} was accepted — it must not be."
        );
    }

    /**
     * Skip when the local libmagic cannot classify a PHP file at all.
     *
     * The Windows build behind finfo returns false for some byte sequences —
     * a bare `<?php system(...) ?>` among them — with "Failed to open stream:
     * Invalid argument". A null MIME then reaches
     * Symfony\...\File::guessExtension(), which passes it to a string
     * parameter and throws a TypeError; and because Symfony caches the finfo
     * handle in a static, the first bad call poisons every later one in the
     * process, so unrelated data sets start failing too.
     *
     * That is the harness, not the application. Verified inside the deployed
     * image (php:8.2-fpm-alpine): finfo returns text/x-php, no TypeError, and
     * all ten hostile files are rejected by this exact rule — the run is in
     * project.md v7.30. Skipping keeps the local suite honest instead of
     * green-by-accident, and CI on Linux runs the whole matrix.
     */
    private function requireWorkingMimeDetection(UploadedFile $file): void
    {
        /*
         * Checked against THIS file's bytes, not a stand-in. Whether libmagic
         * copes is content-specific: a PHP tag calling system(1) classifies
         * fine here, while the same tag calling system($_GET['c']) does not.
         * A generic probe therefore reports "works" and the test dies anyway.
         *
         * The check runs BEFORE the validator so Symfony's guesser is never
         * handed the bad file — once it is, the poisoned static handle takes
         * every later case in the process down with it.
         *
         * (Block comment on purpose: a PHP closing tag inside a // comment
         * ends PHP mode, which is how the first version of this broke.)
         */
        if (is_string(@(new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname()))) {
            return;
        }

        $this->markTestSkipped(
            'This platform\'s libmagic cannot classify these bytes at all, which breaks the mime guesser itself. '
            .'Verified on the deployment platform instead — see project.md v7.30.'
        );
    }

    /** SVG is the classic stored-XSS route into an image field. Closed twice. */
    public function test_svg_is_rejected_by_the_image_rule_alone(): void
    {
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->assertTrue(
            Validator::make(['f' => $this->makeUpload('x.svg', $svg, 'image/svg+xml')], ['f' => 'image'])->fails(),
            'If `image` ever starts allowing SVG again, only the mimes list stands between us and stored XSS.'
        );
    }

    public function test_a_real_image_is_still_accepted(): void
    {
        $this->assertFalse(
            Validator::make(['f' => $this->makeUpload('ok.png', $this->png(), 'image/png')], ['f' => self::RULE])->fails()
        );
    }

    // ── What it is stored as ───────────────────────────────────────

    /**
     * The client filename is attacker-controlled and is never used. The stored
     * name is random, and the extension comes from the file's CONTENT — so a
     * hostile name cannot survive the trip to disk even if the bytes pass.
     */
    public function test_the_stored_name_is_random_and_the_extension_comes_from_content(): void
    {
        $file = $this->makeUpload('../../evil name.PNG', $this->png(), 'image/png');

        $stored = $file->hashName();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.png$/', $stored);
        $this->assertStringNotContainsString('evil', $stored);
        $this->assertStringNotContainsString('..', $stored);
        $this->assertSame('png', $file->guessExtension());
    }

    // ── Size limits agree across all four layers ───────────────────

    /**
     * The bug this guards was not a rejection, it was a misleading one. PHP's
     * compiled defaults (2M/8M) sat below Laravel's own rule, so a file the
     * app said was fine died with "The avatar failed to upload." — and a big
     * enough batch blew past post_max_size, taking `_token` with it and
     * surfacing as "419 Page Expired".
     */
    public function test_php_accepts_a_file_larger_than_laravel_allows(): void
    {
        $ini = $this->phpIni();

        $this->assertGreaterThan(
            self::MAX_FILE_KB * 1024,
            $this->toBytes($ini['upload_max_filesize']),
            'upload_max_filesize must sit ABOVE Laravel\'s max:, so Laravel is the layer that explains the rejection.'
        );
    }

    public function test_a_full_batch_fits_inside_post_max_size(): void
    {
        $ini = $this->phpIni();
        $batchBytes = self::MAX_BATCH * self::MAX_FILE_KB * 1024;

        $this->assertGreaterThan(
            $batchBytes,
            $this->toBytes($ini['post_max_size']),
            self::MAX_BATCH.' images of '.self::MAX_FILE_KB.'KB must fit, or the admin gets a 419 instead of a validation error.'
        );
    }

    public function test_nginx_accepts_at_least_what_php_does(): void
    {
        $nginx = file_get_contents(base_path('docker/nginx.conf.template'));

        $this->assertMatchesRegularExpression('/client_max_body_size\s+(\d+)([KMG]);/', $nginx);
        preg_match('/client_max_body_size\s+(\d+)([KMG]);/', $nginx, $m);

        $this->assertGreaterThanOrEqual(
            $this->toBytes($this->phpIni()['post_max_size']),
            $this->toBytes($m[1].$m[2]),
            'nginx below post_max_size makes nginx the binding limit, and it answers 413 with no explanation.'
        );
    }

    /** The controller's batch cap is what the other three numbers were sized for. */
    public function test_the_controller_caps_the_batch_at_the_documented_number(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/PropertyController.php'));

        $this->assertSame(
            2,
            substr_count($source, "'images'   => 'nullable|array|max:".self::MAX_BATCH."'"),
            'Both store() and update() must cap the batch, and at the number php.ini was sized for.'
        );
        $this->assertStringNotContainsString("'nullable|array|max:20'", $source);
    }

    // ── Uploaded files are never executable ────────────────────────

    /**
     * Measured behaviour is in project.md v7.30: before this block, a request
     * for /storage/evil.php ran it, and so did /storage/evil.php/x.php. The
     * `^~` modifier is the load-bearing character — it stops nginx consulting
     * regex locations at all for URIs underneath, which is what keeps
     * `location ~ \.php$` away from the upload directory.
     */
    public function test_nginx_never_hands_the_upload_directory_to_php(): void
    {
        $nginx = file_get_contents(base_path('docker/nginx.conf.template'));

        $this->assertMatchesRegularExpression(
            '/location\s+\^~\s+\/storage\/\s*\{/',
            $nginx,
            'The ^~ prefix match on /storage/ is what stops `location ~ \.php$` reaching uploaded files.'
        );
    }

    public function test_the_php_location_only_runs_scripts_that_exist(): void
    {
        $nginx = file_get_contents(base_path('docker/nginx.conf.template'));

        $phpBlock = substr($nginx, strpos($nginx, 'location ~ \.php$'));

        $this->assertStringContainsString('try_files $uri =404;', $phpBlock);
    }

    /** The limits only exist if the Dockerfile actually installs them. */
    public function test_the_php_ini_is_installed_into_the_image(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertStringContainsString('docker/php.ini', $dockerfile);
        $this->assertStringContainsString('/usr/local/etc/php/conf.d/', $dockerfile);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function makeUpload(string $name, string $bytes, string $claimedMime): UploadedFile
    {
        // tempnam() rather than string concatenation: finfo (which the `mimes`
        // rule goes through) rejects the mixed \ and / separators you get from
        // gluing sys_get_temp_dir() to a '/'-joined name on Windows, and fails
        // with a bare "Invalid argument" that looks nothing like a path bug.
        $path = tempnam($this->dir, 'up');
        file_put_contents($path, $bytes);

        // $test: true builds the object outside a real POST. The name and mime
        // are attacker-controlled in reality, so they are passed as given.
        return new UploadedFile($path, $name, $claimedMime, null, true);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    /** @return array<string, string> */
    private function phpIni(): array
    {
        $raw = file_get_contents(base_path('docker/php.ini'));
        $out = [];

        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            $out[$k] = $v;
        }

        foreach (['upload_max_filesize', 'post_max_size'] as $required) {
            $this->assertArrayHasKey($required, $out, "docker/php.ini must set {$required}.");
        }

        return $out;
    }

    private function toBytes(string $shorthand): int
    {
        $unit = strtoupper(substr($shorthand, -1));
        $value = (int) $shorthand;

        return match ($unit) {
            'G' => $value * 1024 ** 3,
            'M' => $value * 1024 ** 2,
            'K' => $value * 1024,
            default => $value,
        };
    }
}
