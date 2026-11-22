<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Rules;

final class RegexTranslator
{
    /**
     * Translate a PCRE regex pattern into a JavaScript regex literal, or return null if unsafe.
     */
    public function translate(string $pcre): ?string
    {
        $trimmed = trim($pcre);

        if (strlen($trimmed) < 2) {
            return null;
        }

        $openDelimiter = $trimmed[0];

        if (ctype_alnum($openDelimiter) || $openDelimiter === '\\' || ctype_space($openDelimiter)) {
            return null;
        }

        $closeDelimiter = match ($openDelimiter) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $openDelimiter,
        };

        $closingPos = $this->findClosingDelimiter($trimmed, $closeDelimiter);

        if ($closingPos === null || $closingPos === 0) {
            return null;
        }

        $body = substr($trimmed, 1, $closingPos - 1);
        $rawFlags = substr($trimmed, $closingPos + 1);

        $flags = $this->translateFlags($rawFlags);

        if ($flags === null) {
            return null;
        }

        if ($this->containsUnsupportedPcreSyntax($body)) {
            return null;
        }

        $hasMultiline = str_contains($flags, 'm');

        if ($hasMultiline && $this->containsStringBoundaryAnchors($body)) {
            return null;
        }

        $body = $this->translateNamedGroups($body);
        $body = $this->translateAnchors($body);
        $body = $this->normalizeSlashes($body, $openDelimiter, $closeDelimiter);

        return '/'.$body.'/'.$flags;
    }

    private function findClosingDelimiter(string $pattern, string $closeDelimiter): ?int
    {
        for ($i = strlen($pattern) - 1; $i >= 1; $i--) {
            if ($pattern[$i] !== $closeDelimiter) {
                continue;
            }

            if (! $this->isEscaped($pattern, $i)) {
                return $i;
            }
        }

        return null;
    }

    private function translateFlags(string $rawFlags): ?string
    {
        if ($rawFlags === '') {
            return '';
        }

        $allowed = ['i', 'm', 's', 'u'];
        $collected = [];

        foreach (str_split($rawFlags) as $flag) {
            if (! in_array($flag, $allowed, true)) {
                return null;
            }

            $collected[$flag] = true;
        }

        ksort($collected);

        return implode('', array_keys($collected));
    }

    private function containsUnsupportedPcreSyntax(string $body): bool
    {
        // Possessive quantifiers (*+, ++, ?+, }+)
        if (preg_match('/(?<!\\\\)(?:\\\\{2})*[*+?}]\+/', $body) === 1) {
            return true;
        }

        // Atomic groups, recursion/subroutines, conditionals, inline modifiers, and PCRE verbs
        if (preg_match('/(?<!\\\\)(?:\\\\{2})*\(\?(?:>|R|\d|&\w|P>|\(|[imsxUJn-]+[:)]|\*)/', $body) === 1) {
            return true;
        }

        // PCRE-only escapes: \K, \G, \Q, \E, \R, \X, \C
        if (preg_match('/(?<!\\\\)(?:\\\\{2})*\\\\[KGQERXC]/', $body) === 1) {
            return true;
        }

        // POSIX character classes like [:alpha:]
        if (preg_match('/\[:[a-z]+:\]/', $body) === 1) {
            return true;
        }

        return false;
    }

    private function containsStringBoundaryAnchors(string $body): bool
    {
        return preg_match('/(?<!\\\\)(?:\\\\{2})*\\\\[AzZ]/', $body) === 1;
    }

    private function translateNamedGroups(string $body): string
    {
        return (string) preg_replace('/(?<!\\\\)((?:\\\\{2})*)\(\?P<(\w+)>/', '$1(?<$2>', $body);
    }

    private function translateAnchors(string $body): string
    {
        $body = (string) preg_replace('/(?<!\\\\)((?:\\\\{2})*)\\\\A/', '$1^', $body);

        return (string) preg_replace('/(?<!\\\\)((?:\\\\{2})*)\\\\[zZ]/', '$1$', $body);
    }

    private function normalizeSlashes(string $body, string $openDelimiter, string $closeDelimiter): string
    {
        if ($openDelimiter === '/' && $closeDelimiter === '/') {
            return $body;
        }

        $result = '';
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($char === '/' && ! $this->isEscaped($body, $i)) {
                $result .= '\\/';

                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    private function isEscaped(string $subject, int $index): bool
    {
        $backslashes = 0;

        for ($i = $index - 1; $i >= 0 && $subject[$i] === '\\'; $i--) {
            $backslashes++;
        }

        return $backslashes % 2 === 1;
    }
}
