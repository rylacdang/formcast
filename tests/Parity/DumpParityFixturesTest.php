<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Validator;
use Rylacdang\Formcast\Emitters\ManifestEmitter;
use Rylacdang\Formcast\Emitters\TypeEmitter;
use Rylacdang\Formcast\Emitters\ZodEmitter;
use Rylacdang\Formcast\Messages\MessageResolver;
use Rylacdang\Formcast\Rules\RuleNormalizer;
use Rylacdang\Formcast\Tree\RuleTreeBuilder;
use Rylacdang\Formcast\Writing\TsCode;

/**
 * Simulate Laravel's TrimStrings + ConvertEmptyStringsToNull HTTP middleware.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function simulateLaravelMiddleware(array $data): array
{
    $result = [];

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $result[$key] = simulateLaravelMiddleware($value);

            continue;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $result[$key] = $trimmed === '' ? null : $trimmed;

            continue;
        }

        $result[$key] = $value;
    }

    return $result;
}

it('dumps Laravel Validator expectations and generated Zod casts for all parity payloads', function (): void {
    $payloadFiles = glob(__DIR__.'/payloads/*.php') ?: [];
    sort($payloadFiles, SORT_STRING);

    expect($payloadFiles)->not->toBeEmpty();

    $fixturesDir = dirname(__DIR__, 2).'/js/tests/parity/fixtures';

    if (! is_dir($fixturesDir)) {
        mkdir($fixturesDir, 0755, true);
    }

    /** @var Translator $translator */
    $translator = $this->app->make('translator');
    $normalizer = new RuleNormalizer;
    $treeBuilder = new RuleTreeBuilder;
    $zodEmitter = new ZodEmitter(new MessageResolver($translator));
    $manifestEmitter = new ManifestEmitter;
    $typeEmitter = new TypeEmitter;

    foreach ($payloadFiles as $payloadFile) {
        /** @var array{name: string, rules: array<string, mixed>, messages: array<string, string>, attributes: array<string, string>, cases: list<array{label: string, payload: array<string, mixed>}>} $suite */
        $suite = require $payloadFile;
        $slug = basename($payloadFile, '.php');

        $tree = $treeBuilder->build($normalizer->normalizeAll($suite['rules']));
        $emitResult = $zodEmitter->emit(
            root: $tree,
            customMessages: $suite['messages'],
            customAttributes: $suite['attributes'],
        );

        $tsContent = TsCode::composeCastFile(
            sourcePath: 'tests/Parity/payloads/'.$slug.'.php',
            exportName: $suite['name'],
            schemaExpression: $emitResult->schema,
            manifestBlock: $manifestEmitter->emit(
                exportName: $suite['name'],
                serverFields: $emitResult->serverFields,
                dependencies: $emitResult->dependencies,
            ),
            typesBlock: $typeEmitter->emit($suite['name']),
            messages: $emitResult->messages,
        );

        // Point generated fixture import to local JS core source for Vitest execution.
        $tsContent = str_replace("'@rylacdang/formcast'", "'../../../src'", $tsContent);

        file_put_contents($fixturesDir.'/'.$slug.'.ts', $tsContent);

        $dumpedCases = [];

        foreach ($suite['cases'] as $case) {
            $processed = simulateLaravelMiddleware($case['payload']);
            $validator = Validator::make(
                $processed,
                $suite['rules'],
                $suite['messages'],
                $suite['attributes'],
            );

            $errors = [];

            foreach ($validator->errors()->toArray() as $field => $fieldMessages) {
                if (isset($fieldMessages[0]) && is_string($fieldMessages[0])) {
                    $errors[$field] = $fieldMessages[0];
                }
            }

            ksort($errors, SORT_STRING);

            $dumpedCases[] = [
                'label' => $case['label'],
                'payload' => $case['payload'],
                'expected' => [
                    'valid' => ! $validator->fails(),
                    'errors' => $errors === [] ? (object) [] : $errors,
                ],
            ];
        }

        $fixtureJson = json_encode(
            [
                'name' => $suite['name'],
                'slug' => $slug,
                'cases' => $dumpedCases,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        file_put_contents($fixturesDir.'/'.$slug.'.json', $fixtureJson."\n");
    }

    expect(glob($fixturesDir.'/*.json'))->toHaveCount(count($payloadFiles));
})->group('parity');
