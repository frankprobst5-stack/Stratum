<?php

declare(strict_types=1);

/**
 * Base/fallback language — always loaded first, regardless of the
 * active site language, so a key missing from a translated file still
 * renders real English rather than a raw key name. See
 * core/services/Translator.php for how this is used.
 *
 * `_language_name` is a self-describing convention every language file
 * follows (its own display name, in its own language) — the Settings
 * page's language dropdown reads this rather than maintaining a
 * separate hardcoded English-name-per-code map that could drift out of
 * sync with which files actually exist.
 */
return [
    '_language_name' => 'English',

    'login.title' => 'Log in',
    'login.field_login' => 'Username or email',
    'login.field_password' => 'Password',
    'login.submit' => 'Log in',
];
