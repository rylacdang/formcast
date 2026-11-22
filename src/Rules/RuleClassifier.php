<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Rules;

use RuntimeException;
use Rylacdang\Formcast\Formcast;

final class RuleClassifier
{
    /** @var list<string> */
    private const META_RULES = [
        'bail',
        'exclude',
        'exclude_if',
        'exclude_unless',
        'exclude_with',
        'exclude_without',
    ];

    /** @var list<string> */
    private const SERVER_ONLY_RULES = [
        'unique',
        'exists',
        'current_password',
        'active_url',
        'dimensions',
        'encoding',
        'password_uncompromised',
        'closure',
        'conditional',
        'timezone',
    ];

    /** @var list<string> */
    private const CLIENT_RULES = [
        // Presence
        'required',
        'filled',
        'present',
        'nullable',
        'sometimes',
        'missing',
        'prohibited',
        // Conditional presence
        'required_if',
        'required_unless',
        'required_with',
        'required_with_all',
        'required_without',
        'required_without_all',
        'required_if_accepted',
        'prohibited_if',
        'prohibited_unless',
        'missing_if',
        // Types
        'string',
        'integer',
        'numeric',
        'boolean',
        'array',
        'list',
        'decimal',
        'json',
        // Size
        'min',
        'max',
        'size',
        'between',
        // Digits
        'digits',
        'digits_between',
        'min_digits',
        'max_digits',
        'multiple_of',
        // Format
        'email',
        'url',
        'uuid',
        'ulid',
        'ip',
        'ipv4',
        'ipv6',
        'mac_address',
        'hex_color',
        'alpha',
        'alpha_num',
        'alpha_dash',
        'ascii',
        'lowercase',
        'uppercase',
        'starts_with',
        'ends_with',
        'doesnt_start_with',
        'doesnt_end_with',
        // Regex
        'regex',
        'not_regex',
        // Sets
        'in',
        'not_in',
        'enum',
        // Cross-field
        'confirmed',
        'same',
        'different',
        'gt',
        'gte',
        'lt',
        'lte',
        'in_array',
        // Dates
        'date',
        'date_format',
        'date_equals',
        'after',
        'after_or_equal',
        'before',
        'before_or_equal',
        // Acceptance
        'accepted',
        'accepted_if',
        'declined',
        'declined_if',
        // Files
        'file',
        'image',
        'mimes',
        'mimetypes',
        'extensions',
        // Password
        'password',
    ];

    /** @var list<string> */
    private const EMAIL_SERVER_MODES = [
        'dns',
        'spoof',
        'strict',
        'filter_unicode',
    ];

    /**
     * @param  list<string>  $serverOnly
     */
    public function __construct(
        private readonly RegexTranslator $regexTranslator = new RegexTranslator,
        private readonly ?Formcast $registry = null,
        private readonly array $serverOnly = [],
        private readonly string $unsupportedMode = 'server',
    ) {}

    public function classify(NormalizedRule $rule): RuleCategory
    {
        $name = $rule->name;

        if (in_array($name, $this->serverOnly, true)) {
            return RuleCategory::Server;
        }

        if ($rule->forceServer) {
            if ($this->registry !== null && $this->registry->hasMapper($name)) {
                return RuleCategory::Client;
            }

            return RuleCategory::Server;
        }

        if (in_array($name, self::META_RULES, true)) {
            return RuleCategory::Meta;
        }

        if ($this->registry !== null && $this->registry->hasMapper($name)) {
            return RuleCategory::Client;
        }

        if (in_array($name, self::SERVER_ONLY_RULES, true)) {
            return RuleCategory::Server;
        }

        if ($name === 'email' && $this->hasServerEmailMode($rule)) {
            return RuleCategory::Server;
        }

        if (($name === 'regex' || $name === 'not_regex') && ! $this->isSafeRegex($rule)) {
            return RuleCategory::Server;
        }

        if (in_array($name, self::CLIENT_RULES, true)) {
            return RuleCategory::Client;
        }

        if ($this->unsupportedMode === 'fail') {
            throw new RuntimeException("Unsupported Formcast rule: {$name}");
        }

        return RuleCategory::Server;
    }

    /**
     * Determine whether a rule is unknown/unsupported (as opposed to explicitly known server-only).
     */
    public function isUnsupported(NormalizedRule $rule): bool
    {
        $name = $rule->name;

        if ($rule->forceServer || in_array($name, $this->serverOnly, true)) {
            return false;
        }

        if ($this->registry !== null && $this->registry->hasMapper($name)) {
            return false;
        }

        if (
            in_array($name, self::META_RULES, true)
            || in_array($name, self::SERVER_ONLY_RULES, true)
            || in_array($name, self::CLIENT_RULES, true)
        ) {
            return false;
        }

        return true;
    }

    private function hasServerEmailMode(NormalizedRule $rule): bool
    {
        foreach ($rule->parameters as $param) {
            if (in_array(strtolower(trim($param)), self::EMAIL_SERVER_MODES, true)) {
                return true;
            }
        }

        return false;
    }

    private function isSafeRegex(NormalizedRule $rule): bool
    {
        $pattern = $rule->parameters[0] ?? '';

        return $this->regexTranslator->translate($pattern) !== null;
    }
}
