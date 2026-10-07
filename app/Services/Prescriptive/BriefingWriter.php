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
            ->orderBy('target_start')
            ->orderBy('id')
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

        // Nagbabalik ng NULL ang `GeminiService::ask()` kapag hindi tumugon
        // ang AI, sa halip na mag-throw — kailangan iyon ng fail-open na
        // review moderation. Dito, walang briefing na ipinapakita kaysa sa
        // isang mensaheng mukhang sinabi mismo ng sistema.
        if ($reply === null || trim($reply) === '') {
            Log::warning('Prescriptive briefing unavailable — the AI returned no reply.');
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
                "- [%s] %s | dates: %s | %s\n",
                $rec->action_label,
                $rec->title,
                $rec->window_label,
                $rec->summary
            );
        }

        return <<<PROMPT
        You are writing a short morning briefing for the owner of Villa Elena, a single
        exclusive-use private pool villa in the Philippines rented as a whole to one group
        at a time.

        A separate rule-based engine has ALREADY worked out the recommendations below from the
        resort's own bookings. They are listed soonest first. Your ONLY job is to summarise
        and prioritise them in prose.

        HARD RULES — breaking any of these makes the output unusable:
        - Do NOT invent, estimate, or adjust any number. Use only the figures given below.
        - Do NOT promise or estimate any revenue, profit or peso gain. None was computed.
        - Do NOT suggest any action that is not in the list. No new discounts, dates, or ideas.
        - Do NOT mention multiple rooms or units — there is only ONE bookable villa. "Occupancy"
          here means booked slots out of offered slots for that one villa.
        - Do NOT use markdown, headings, bullets, or bold. Plain sentences only.
        - Write 2 to 3 sentences, maximum 70 words total.
        - Say which one deserves attention first and why, in the owner's terms (timing and guests).

        RECOMMENDATIONS:
        {$lines}
        Write the briefing now. Plain prose, nothing else.
        PROMPT;
    }
}
