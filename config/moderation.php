<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blocked Words
    |--------------------------------------------------------------------------
    | Substrings matched case-insensitively against submitted review title +
    | content. Kept short and specific to avoid false positives on ordinary
    | guest feedback — this is a first-pass filter, not the only line of
    | defense (the LLM classification step in ReviewModerationService
    | handles nuance like hate speech and harassment).
    */
    'blocked_words' => [
        // English profanity/slurs
        'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'cunt', 'whore', 'slut',
        'nigger', 'faggot', 'retard',
        // Tagalog/Filipino profanity
        'putangina', 'putang ina', 'gago', 'gaga', 'tangina', 'tarantado',
        'ulol', 'bobo', 'tanga', 'leche', 'pakyu', 'kupal', 'peste',
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal Info / Spam Patterns
    |--------------------------------------------------------------------------
    | Regexes checked against the same combined text. A match holds the
    | review for manual review — guests sharing phone numbers/emails/links
    | in a public review is usually either spam or unwanted PII exposure.
    */
    'patterns' => [
        'PH mobile number'  => '/(?:\+?63|0)9\d{9}/',
        'email address'     => '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
        'URL / link'        => '/(https?:\/\/|www\.)\S+/i',
    ],

];
