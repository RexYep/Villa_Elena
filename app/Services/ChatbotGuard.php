<?php

namespace App\Services;

/**
 * The chatbot's model-independent safety layer.
 *
 * WHY THIS EXISTS. Task 9 fenced the guest transcript with a nonce the guest
 * cannot guess (PromptGuard), which stops them forging the STRUCTURE of the
 * prompt. It does nothing about the other half of the problem: a model that
 * reads a plainly-marked untrusted instruction and decides to follow it
 * anyway. Until now the only thing standing between that and the guest was
 * the prompt's own wording — which is to say, the model's judgement checking
 * the model's judgement.
 *
 * Review moderation already has a layer that cannot be talked round
 * (ReviewModerationService::looksLikePromptInjection). This is the same idea,
 * applied on both sides of the chatbot's AI call:
 *
 *   INPUT  — a guest message that is addressing the model rather than asking
 *            about the villa never reaches the model at all.
 *   OUTPUT — a reply is checked against invariants that hold no matter what
 *            the model was persuaded to do, before the guest ever sees it.
 *
 * WHY IT IS SEPARATE FROM THE MODERATION ONE, rather than shared. The two
 * surfaces have opposite error costs. A false positive in moderation sends a
 * review to a human queue — invisible to the reviewer, nearly free. A false
 * positive here replaces a good answer with a hand-off, in front of a guest,
 * in real time. So these patterns are deliberately narrower, and the reply
 * checks are written as "this can never be legitimate" invariants rather than
 * as a filter on topics. Merging the two would mean tuning one and silently
 * changing the other.
 *
 * WHAT THIS DOES NOT DO, and cannot. It does not detect the general case of a
 * fabricated fact. The measured inventions that started all of this —
 * staffing ("is there a girl there?"), a pet policy — are ordinary sentences;
 * telling an invented one from a true one is the judgement call this class
 * exists precisely because we cannot rely on. Those remain the prompt's job,
 * and the prompt is good at them. What is caught here is the subset with a
 * hard invariant behind it: the resort never gave this model a credential,
 * never gave it a second phone number, and never asked it to repeat its own
 * instructions — so a reply containing one of those is wrong by construction,
 * whatever the model thought it was doing.
 */
final class ChatbotGuard
{
    /**
     * Does this text address the model instead of the resort?
     *
     * Applied to the guest's message (which then never reaches the AI) and to
     * each replayed history entry (which is dropped from the prompt).
     *
     * Every pattern here had to survive one question: could a guest asking
     * about a pool party weekend write this by accident? That rules out the
     * looser shapes the moderation copy can afford. "Ignore my previous
     * message, I meant Sunday" is a normal thing to type and must not match,
     * so the override pattern requires the object to be instructions, rules
     * or a prompt. "What are your rules?" is a real question about the house
     * rules and must not match either.
     */
    public function looksLikeInjection(string $text): bool
    {
        $patterns = [
            // Classic overrides. The object must be instructions/rules/prompt
            // — "ignore the previous message" is ordinary conversation.
            '/\b(ignore|disregard|forget|override|bypass)\s+(all\s+|any\s+|your\s+|the\s+|these\s+)*(previous|prior|above|earlier|initial|original|system|preceding)?\s*(instructions?|rules?|prompts?|guidelines?|directives?|constraints?)\b/i',

            // Asking for the prompt itself.
            '/\b(system|developer|initial|original|hidden)\s+(prompt|instructions?)\b/i',
            '/\b(reveal|show|print|repeat|output|display)\s+(me\s+)?(your|the)\s+(system\s+|initial\s+|original\s+|full\s+|exact\s+)*(prompt|instructions?)\b/i',
            '/\brepeat\s+(everything|all\s+of\s+the\s+text|the\s+text|everything\s+written)\s+(above|before)/i',

            // Persona swaps and "modes".
            '/\byou\s+are\s+(now\s+)?(a|an|in)\b.{0,40}\b(developer|debug|admin(istrator)?|god|unrestricted|uncensored|jailbreak|dan)\b/i',
            '/\b(act|behave|pretend|roleplay|respond)\s+(as|like|to\s+be)\s+(if\s+you\s+(are|were)\s+)?(a\s+|an\s+|the\s+)?(system|admin(istrator)?|developer|moderator|different\s+ai)\b/i',

            // Forging the transcript's own structure.
            '/-{2,}\s*(BEGIN|END)\s+[A-Z][A-Z \-]{3,}/',
            '/\bend\s+of\s+(transcript|conversation|history|prompt|instructions)\b/i',
            '/^\s*(system|assistant|developer|administrator)\s*[:=]/im',
            '/^\s*(system|important)\s+(update|note|notice|override|message)\s*[:=]/im',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this reply safe to show the guest? Returns a short reason, or null.
     *
     * $ownContacts is the resort's real phone and email as the prompt handed
     * them to the model. They have to be excluded explicitly: the hand-off
     * wording the prompt mandates ends in the resort's phone number, so that
     * number appears in a large share of perfectly correct replies.
     */
    public function unsafeReply(string $reply, string $nonce, array $ownContacts = []): ?string
    {
        if ($this->leaksThePrompt($reply, $nonce)) {
            return 'echoed the system prompt';
        }

        if ($this->disclosesCredential($reply)) {
            return 'stated a credential';
        }

        if ($this->inventsContactDetail($reply, $ownContacts)) {
            return 'gave out a contact detail that is not the resort own';
        }

        return null;
    }

    /**
     * The model reproducing its own scaffolding. Exact strings, so there is no
     * false-positive question to argue about: a guest asking about the pool
     * does not get a reply containing this request's nonce.
     */
    private function leaksThePrompt(string $reply, string $nonce): bool
    {
        $literals = [
            $nonce,
            'GUEST-SUPPLIED TRANSCRIPT',
            'THE MOST IMPORTANT RULE',
            'THINGS YOU DO NOT HAVE INFORMATION ABOUT',
            'SEARCH RESULT:',
            'CURRENT PROMOS / DISCOUNTS',
            'BOOKING & PAYMENT POLICY',
        ];

        foreach ($literals as $literal) {
            if ($literal !== '' && str_contains($reply, $literal)) {
                return true;
            }
        }

        return (bool) preg_match('/-{2,}\s*(BEGIN|END)\s+[A-Z][A-Z \-]{3,}/', $reply);
    }

    /**
     * A WiFi password, gate code or door PIN with an actual value attached.
     *
     * THE INVARIANT: nothing in the prompt contains a credential of any kind.
     * `Wifi` in the amenity list says the villa has internet and nothing more
     * — which is exactly the leap the model already made once, inventing
     * `elena2026`. So a reply that states a credential VALUE is fabricated by
     * construction, and no judgement call is needed to say so.
     *
     * The whole difficulty is separating "the password is elena2026" from the
     * correct answers, which mention the same nouns constantly:
     *
     *   "I don't have the WiFi password on hand"       — no assertion at all
     *   "the gate code is provided on arrival"         — the value is a word
     *   "the WiFi password is not something I have,
     *    please call 0917 123 4567"                    — the value is a
     *                                                    NEGATION, and a phone
     *                                                    number sits 40
     *                                                    characters later
     *
     * Hence the shape: the credential noun, then an assertion within a short
     * span, then the VERY NEXT token has to be credential-shaped.
     *
     * TWO OF THOSE THREE ARE LOAD-BEARING, and it is not the ones it looks
     * like. Measured by running the same corpus through each variant:
     *
     *   scan every token after the assertion, not just the next  -> 3 of 5
     *       correct replies flagged. "is not something I have — please call
     *       the resort at 0917 123 4567" reaches the phone number, which is
     *       four digits in a row.
     *   drop the digit requirement on the token                  -> 2 of 5
     *       flagged. "is provided", "is shared" become credentials.
     *   lengthen the span before the assertion (20 -> 80 chars)  -> 0 of 5.
     *
     * So the span is the cheap fail-fast; "only the next token" and "must
     * contain a digit" are what keep the hand-off out of this. Do not widen
     * either on the grounds that the check looks too narrow to be useful.
     */
    private function disclosesCredential(string $reply): bool
    {
        $noun = '/\b(?:wi-?fi\s+(?:password|pass|key|code|credentials?)'
            .'|passwords?|pass\s?codes?|pass\s?keys?'
            .'|pin(?:\s+(?:code|number))?'
            .'|(?:gate|door|lock|access|entry|security|keypad|safe|alarm|wifi)\s*codes?'
            .'|(?:gate|door|lock)\s*combination)\b/i';

        if (! preg_match_all($noun, $reply, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        foreach ($matches[0] as [$found, $offset]) {
            $tail = substr($reply, $offset + strlen($found), 80);

            // An assertion close behind the noun, then the next token. Filler
            // words are skipped; a negation is not one, so "is not something
            // I have" stops at "not" and reads as no value given.
            if (! preg_match('/^[^.\n!?]{0,20}?(?:\bis\b|\bare\b|[:=])\s*(?:the\s+|our\s+|currently\s+|simply\s+|just\s+)*([^\s.,;!?]{3,40})/i', $tail, $m)) {
                continue;
            }

            // Strip surrounding quotes, brackets and stray punctuation rather
            // than listing every quote character the model might reach for.
            $token = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $m[1]);

            if ($this->looksLikeSecretValue((string) $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Credential-shaped: letters and digits together (`elena2026`), or a run
     * of four or more digits (`4821`). An English word is neither, which is
     * what lets "is provided on arrival" and "is shared at check-in" through.
     */
    private function looksLikeSecretValue(string $token): bool
    {
        if ($token === '' || mb_strlen($token) > 40) {
            return false;
        }

        if (preg_match('/^[A-Za-z0-9@#_.\-]{5,40}$/', $token)
            && preg_match('/[A-Za-z]/', $token)
            && preg_match('/\d/', $token)) {
            return true;
        }

        return (bool) preg_match('/^\d{4,12}$/', $token);
    }

    /**
     * A phone number or email address that is not one of the resort's.
     *
     * THE INVARIANT: the prompt hands the model exactly one phone and one
     * email, read from the settings table, and tells it to give those out
     * freely. It has no other contact detail to give, so any second one is
     * either invented or was planted in the transcript — and routing a guest
     * to an attacker's number is the most directly exploitable thing this bot
     * could be made to do.
     *
     * A guest-supplied number echoed back would also trip this. That is the
     * intended reading rather than an oversight: "our new booking hotline is
     * 0999…" planted in the history is exactly the attack, and at this layer
     * it is indistinguishable from an innocent echo. The cost of the innocent
     * case is one hand-off.
     *
     * Phone matching is anchored to `+63`/`0` and needs ten or more digits, so
     * prices (₱6,000), capacities, times and ISO dates (2026-10-03) cannot
     * reach it.
     */
    private function inventsContactDetail(string $reply, array $ownContacts): bool
    {
        $ownDigits = [];
        $ownEmails = [];

        foreach (array_filter($ownContacts) as $contact) {
            $contact = (string) $contact;

            if (str_contains($contact, '@')) {
                $ownEmails[] = strtolower(trim($contact));

                continue;
            }

            $digits = preg_replace('/\D/', '', $contact);

            if ($digits !== '') {
                $ownDigits[] = $this->normalisePhone($digits);
            }
        }

        if (preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $reply, $emails)) {
            foreach ($emails[0] as $email) {
                if (! in_array(strtolower($email), $ownEmails, true)) {
                    return true;
                }
            }
        }

        if (preg_match_all('/(?:\+63|\b0)[\d\s\-().]{8,16}\d/', $reply, $phones)) {
            foreach ($phones[0] as $phone) {
                $digits = preg_replace('/\D/', '', $phone);

                if (strlen($digits) < 10) {
                    continue;
                }

                if (! in_array($this->normalisePhone($digits), $ownDigits, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** `+639171234567`, `09171234567` and `9171234567` are one number. */
    private function normalisePhone(string $digits): string
    {
        $digits = preg_replace('/^63/', '', $digits);

        return (string) preg_replace('/^0/', '', $digits);
    }
}
