<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters\Mappers;

use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\NormalizedRule;

final class FileMappers implements RuleMapper
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'file',
            'image',
            'mimes',
            'mimetypes',
            'extensions',
        ];
    }

    public function map(NormalizedRule $rule, ZodContext $ctx): void
    {
        match ($rule->name) {
            'file' => $ctx->setBase('fc.file('.$ctx->message().')'),
            'image' => $this->mapImage($ctx),
            'mimes' => $this->mapList('fileMimes', $rule->parameters, $ctx),
            'mimetypes' => $this->mapList('fileMimetypes', $rule->parameters, $ctx),
            'extensions' => $this->mapList('fileExtensions', $rule->parameters, $ctx),
            default => null,
        };
    }

    private function mapImage(ZodContext $ctx): void
    {
        if (! $ctx->hasExplicitBase()) {
            $ctx->setBase('fc.file()');
        }

        $ctx->chain('refine', 'fc.isImage', $ctx->message());
    }

    /**
     * @param  list<string>  $values
     */
    private function mapList(string $helper, array $values, ZodContext $ctx): void
    {
        if (! $ctx->hasExplicitBase()) {
            $ctx->setBase('fc.file()');
        }

        $quoted = implode(', ', array_map($ctx->quote(...), $values));
        $ctx->chain('refine', 'fc.'.$helper.'(['.$quoted.'])', $ctx->message());
    }
}
