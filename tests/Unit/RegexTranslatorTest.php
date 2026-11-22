<?php

declare(strict_types=1);

use Rylacdang\Formcast\Rules\RegexTranslator;

it('translates basic pcre patterns, flags, alternate delimiters, and named groups', function (): void {
    $translator = new RegexTranslator;

    expect($translator->translate('/^(09|\+639)\d{9}$/i'))->toBe('/^(09|\+639)\d{9}$/i');
    expect($translator->translate('#^https://example\.com/\d+$#iu'))->toBe('/^https:\/\/example\.com\/\d+$/iu');
    expect($translator->translate('/\A[a-z]+\z/'))->toBe('/^[a-z]+$/');
    expect($translator->translate('/^(?P<code>[A-Z]{3})-\d{4}$/'))->toBe('/^(?<code>[A-Z]{3})-\d{4}$/');
});

it('rejects unsupported pcre features and falls back to null', function (): void {
    $translator = new RegexTranslator;

    // Possessive quantifiers
    expect($translator->translate('/a++/'))->toBeNull();
    expect($translator->translate('/\d*+/'))->toBeNull();

    // Atomic groups & recursion
    expect($translator->translate('/(?>foo|bar)/'))->toBeNull();
    expect($translator->translate('/\(([^()]+|(?R))*\)/'))->toBeNull();

    // Unsupported flags (x, D, U)
    expect($translator->translate('/foo bar/x'))->toBeNull();
    expect($translator->translate('/foo$/D'))->toBeNull();

    // String boundary anchors combined with multiline flag
    expect($translator->translate('/\Afoo\z/m'))->toBeNull();

    // PCRE-only escapes and POSIX classes
    expect($translator->translate('/foo\Kbar/'))->toBeNull();
    expect($translator->translate('/[[:alpha:]]+/'))->toBeNull();
});
