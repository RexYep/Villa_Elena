<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\GuestEmail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The resort's name has one source: Admin → Settings → "Resort Name".
 *
 * The table is built by hand: the real migrations are MySQL-only and don't
 * run on the in-memory SQLite test connection.
 */
class ResortNameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['app.name' => 'Fallback Name', 'mail.from.name' => 'Fallback Name']);
    }

    public function test_the_name_and_the_email_sender_come_from_the_setting(): void
    {
        $this->makeSettingsTable();
        Setting::set('resort_name', 'Villa Elena Private Rental Resort');

        $this->assertSame('Villa Elena Private Rental Resort', Setting::resortName());
        $this->assertSame('Villa Elena Private Rental Resort', GuestEmail::resortName());
        $this->assertSame('Villa Elena Private Rental Resort', GuestEmail::sender()->name,
            'An email must be sent under the name it is signed with.');
    }

    public function test_the_wordmark_is_the_first_two_words_then_the_rest(): void
    {
        $this->makeSettingsTable();

        Setting::set('resort_name', 'Villa Elena Private Rental Resort');
        $this->assertSame(['Villa Elena', 'Private Rental Resort'], Setting::resortNameLines());

        Setting::set('resort_name', '  Casa   Blanca ');
        $this->assertSame(['Casa Blanca', ''], Setting::resortNameLines());
    }

    public function test_a_blank_setting_falls_back_to_the_app_name(): void
    {
        $this->makeSettingsTable();
        Setting::set('resort_name', '   ');

        $this->assertSame('Fallback Name', Setting::resortName());
    }

    /**
     * The error pages are printed in the sign-in layout, which shows the
     * name. With no database to read, they must still print.
     */
    public function test_the_name_and_the_sign_in_layout_survive_an_unreadable_database(): void
    {
        $this->assertFalse(Schema::hasTable('settings'));

        $this->assertSame('Fallback Name', Setting::resortName());

        $html = view('errors.404')->render();
        $this->assertStringContainsString('Fallback Name', $html);
    }

    public function test_the_sign_in_layout_prints_the_setting_on_two_lines(): void
    {
        $this->makeSettingsTable();
        Setting::set('resort_name', 'Villa Elena Private Rental Resort');

        $html = view('errors.404')->render();

        $this->assertMatchesRegularExpression('/class="brand-mark">\s*Villa Elena<\/div>/', $html);
        $this->assertStringContainsString('<p>Private Rental Resort</p>', $html);
    }

    private function makeSettingsTable(): void
    {
        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('data_type')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }
}
