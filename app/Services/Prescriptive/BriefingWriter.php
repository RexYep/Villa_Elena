<?php

namespace App\Services\Prescriptive;

use App\Models\Recommendation;
use App\Models\Setting;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Log;

/**
 * Ang ISANG lugar kung saan may papel ang AI sa prescriptive na bahagi —
 * at ito ay pagsasalita, hindi pagpapasya.
 *
 * ANG HANGGANAN, NANG MALINAW: ang lahat ng numero ay kinompyut na bago
 * pa marating ang klaseng ito. Hindi pinapayagan ang modelong magmungkahi
 * ng aksyon, magdagdag ng petsa, o umimbento ng halaga. Ang tanging
 * ginagawa nito ay pagsamahin ang mga naibigay nang katotohanan sa isang
 * talatang mababasa ng tao — kung ano ang unahin, at bakit.
 *
 * Kung magbabalik ang modelo ng sarili nitong rekomendasyon, iyon ay
 * eksaktong pagkakamaling inalis sa `ForecastController` sa v6.3: payo na
 * walang pinagmumulan, at hindi kayang isagawa ng sistema.
 *
 * ISANG TAWAG KADA PAGTAKBO — hindi isa kada card at hindi isa kada
 * pagbisita sa page. Kabahagi ang Brevo/Groq na libreng quota sa 2FA,
 * booking confirmation at password reset; ang isang araw-araw na talata
 * ay abot-kaya, ang isang tawag kada refresh ay hindi.
 *
 * FAIL-OPEN. Kapag bumagsak ang Groq, nabubura ang briefing at buo pa rin
 * ang page. Ang mga card ang tunay na produkto; palamuti lang ang talata.
 * Sinasadyang BINUBURA at hindi iniiwan ang luma — ang isang briefing na
 * tumutukoy sa mga mungkahing wala na ay mas masama kaysa sa wala.
 */
class BriefingWriter
{
    public const SETTING_TEXT = 'prescriptive_briefing';

    public const SETTING_AT = 'prescriptive_briefing_at';

    public function __construct(private GeminiService $ai) {}

    public function write(): ?string
    {
        $open = Recommendation::open()
            ->whereDate('target_end', '>=', now()->toDateString())
            ->orderByDesc('expected_impact')
            ->limit(8)
            ->get();

        if ($open->isEmpty()) {
            $this->store('');

            return null;
        }

        try {
            $reply = $this->ai->ask($this->prompt($open), 500);
        } catch (\Throwable $e) {
            Log::error('Prescriptive briefing failed: '.$e->getMessage());
            $this->store('');

            return null;
        }

        // Ibinabalik ng `GeminiService::ask()` ang mismong error bilang
        // string sa halip na mag-throw — kailangan iyon ng fail-open na
        // review moderation. Dito, ang isang error na ipinakitang briefing
        // ay mukhang sinabi ng sistema, kaya hayagan itong sinasala.
        if ($reply === '' || str_starts_with($reply, 'API Error:') || $reply === 'No insights returned.') {
            Log::warning('Prescriptive briefing unavailable: '.$reply);
            $this->store('');

            return null;
        }

        $text = trim($reply);
        $this->store($text);

        return $text;
    }

    private function store(string $text): void
    {
        Setting::set(self::SETTING_TEXT, $text);
        Setting::set(self::SETTING_AT, $text === '' ? '' : now()->toDateTimeString());
    }

    private function prompt($open): string
    {
        $lines = '';

        foreach ($open as $rec) {
            $lines .= sprintf(
                "- %s | window: %s | projected value: PHP %s | confidence: %s (%d past days) | %s\n",
                $rec->title,
                $rec->window_label,
                number_format((float) $rec->expected_impact, 0),
                $rec->confidence_label,
                $rec->sample_size,
                $rec->summary
            );
        }

        return <<<PROMPT
        You are writing a short morning briefing for the owner of Villa Elena, a single
        exclusive-use private pool villa in the Philippines rented as a whole to one group
        at a time.

        A separate analytics engine has ALREADY computed the recommendations below from the
        resort's own booking history. Your ONLY job is to summarise and prioritise them in
        prose.

        HARD RULES — breaking any of these makes the output unusable:
        - Do NOT invent, estimate, or adjust any number. Use only the figures given below.
        - Do NOT suggest any action that is not in the list. No new discounts, dates, or ideas.
        - Do NOT mention 'occupancy rate' or multiple rooms/units — there is only ONE bookable villa.
        - Do NOT use markdown, headings, bullets, or bold. Plain sentences only.
        - Write 2 to 3 sentences, maximum 70 words total.
        - Say which one deserves attention first and why, in the owner's terms (money and timing).
        - If confidence is Low on the top item, say so plainly.

        RECOMMENDATIONS:
        {$lines}
        Write the briefing now. Plain prose, nothing else.
        PROMPT;
    }
}
