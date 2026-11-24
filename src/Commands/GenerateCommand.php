<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;
use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Discovery\RequestDiscoverer;
use Rylacdang\Formcast\Discovery\RouteScanner;
use Rylacdang\Formcast\Emitters\EnumEmitter;
use Rylacdang\Formcast\Emitters\ManifestEmitter;
use Rylacdang\Formcast\Emitters\TypeEmitter;
use Rylacdang\Formcast\Emitters\ZodEmitter;
use Rylacdang\Formcast\Extraction\FormRequestExtractor;
use Rylacdang\Formcast\Formcast;
use Rylacdang\Formcast\Messages\MessageResolver;
use Rylacdang\Formcast\Rules\RuleClassifier;
use Rylacdang\Formcast\Rules\RuleNormalizer;
use Rylacdang\Formcast\Tree\RuleTreeBuilder;
use Rylacdang\Formcast\Writing\FileWriter;
use Rylacdang\Formcast\Writing\TsCode;
use UnitEnum;

final class GenerateCommand extends Command
{
    protected $signature = 'formcast:generate
        {--path=* : Limit generation to one or more paths}
        {--locale= : Resolve validation messages for a specific locale}
        {--dry-run : Preview changes without writing files}
        {--check : Exit with a non-zero status if generated files are out of date}';

    protected $description = 'Generate typed Zod schemas and manifests from Laravel FormRequest classes';

    public function handle(
        RequestDiscoverer $discoverer,
        FormRequestExtractor $extractor,
        RuleNormalizer $normalizer,
        RuleTreeBuilder $treeBuilder,
        EnumEmitter $enumEmitter,
        TypeEmitter $typeEmitter,
        ManifestEmitter $manifestEmitter,
        FileWriter $writer,
        Formcast $registry,
        Translator $translator,
        Router $router,
    ): int {
        /** @var list<string> $optionPaths */
        $optionPaths = array_values(array_filter(
            (array) $this->option('path'),
            static fn (mixed $p): bool => is_string($p) && $p !== '',
        ));

        /** @var list<string> $configPaths */
        $configPaths = array_values(array_filter(
            (array) config('formcast.paths', []),
            is_string(...),
        ));

        $paths = $optionPaths !== [] ? $optionPaths : $configPaths;
        $outputDir = (string) config('formcast.output', resource_path('js/formcast'));
        $discoveryMode = (string) config('formcast.discovery', 'all');
        $unsupportedMode = (string) config('formcast.unsupported', 'server');
        $routesEnabled = (bool) config('formcast.routes.enabled', true);

        /** @var list<string> $serverOnly */
        $serverOnly = array_values(array_filter(
            (array) config('formcast.server_only', []),
            is_string(...),
        ));

        /** @var array<string, list<string>> $redactConfig */
        $redactConfig = (array) config('formcast.redact', []);

        /** @var array<string, class-string<RuleMapper>> $configMappers */
        $configMappers = (array) config('formcast.mappers', []);

        $localeOption = $this->option('locale');
        $locale = is_string($localeOption) && $localeOption !== ''
            ? $localeOption
            : $this->defaultLocale();

        $dryRun = (bool) $this->option('dry-run');
        $check = (bool) $this->option('check');

        $classifier = new RuleClassifier(
            registry: $registry,
            serverOnly: $serverOnly,
            unsupportedMode: $unsupportedMode,
        );

        $zodEmitter = new ZodEmitter(
            messageResolver: new MessageResolver($translator),
            classifier: $classifier,
            registry: $registry,
            configMappers: $configMappers,
        );

        $discovered = $discoverer->discover($paths, $discoveryMode);
        $extraction = $extractor->extractAll($discovered);
        $routeMap = $routesEnabled ? (new RouteScanner($router))->scan() : [];

        /** @var array<string, string> $files */
        $files = [];

        /** @var array<string, string> $barrelEntries */
        $barrelEntries = [];

        /** @var array<string, array<string, string>> $namedRouteFiles */
        $namedRouteFiles = [];

        /** @var array<class-string<UnitEnum>, string> $emittedEnums */
        $emittedEnums = [];

        /** @var list<string> $warnings */
        $warnings = [];

        foreach ($extraction['extracted'] as $extracted) {
            if ($extracted->hasPrepareForValidation) {
                $warnings[] = "{$extracted->class} overrides prepareForValidation(); input mutations are not replicated on the client.";
            }

            $rules = $this->applyRedactions($extracted->rules, $redactConfig[$extracted->class] ?? []);
            $normalized = $normalizer->normalizeAll($rules);

            if ($unsupportedMode === 'warn') {
                foreach ($normalized as $field => $fieldRules) {
                    foreach ($fieldRules as $rule) {
                        if ($classifier->isUnsupported($rule)) {
                            $warnings[] = "Unsupported rule '{$rule->name}' on {$extracted->class}::{$field} (treated as server-only).";
                        }
                    }
                }
            }

            $tree = $treeBuilder->build($normalized);

            try {
                $emitResult = $zodEmitter->emit(
                    root: $tree,
                    customMessages: $extracted->messages,
                    customAttributes: $extracted->attributes,
                    locale: $locale,
                );
            } catch (RuntimeException $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            $castRelativePath = TsCode::classToRelativePath($extracted->class);

            /** @var array<string, string> $enumImports */
            $enumImports = [];

            foreach ($emitResult->enumClasses as $enumClass) {
                $enumRelativePath = TsCode::classToRelativePath($enumClass);

                if (! isset($emittedEnums[$enumClass])) {
                    $emittedEnums[$enumClass] = $enumRelativePath;
                    $files[$enumRelativePath] = $enumEmitter->emit($enumClass);
                }

                $shortName = (new ReflectionClass($enumClass))->getShortName();
                $enumImports[$shortName] = TsCode::relativeImportPath($castRelativePath, $enumRelativePath);
            }

            $manifestBlock = $manifestEmitter->emit(
                exportName: $extracted->exportName,
                serverFields: $emitResult->serverFields,
                dependencies: $emitResult->dependencies,
                usedBy: $routeMap[$extracted->class] ?? [],
            );

            $typesBlock = $typeEmitter->emit($extracted->exportName);

            $files[$castRelativePath] = TsCode::composeCastFile(
                sourcePath: $this->relativeSourcePath($extracted->sourcePath),
                exportName: $extracted->exportName,
                schemaExpression: $emitResult->schema,
                manifestBlock: $manifestBlock,
                typesBlock: $typesBlock,
                messages: $emitResult->messages,
                enumImports: $enumImports,
            );

            $barrelKey = $this->uniqueBarrelAlias($extracted->exportName, $extracted->class, $barrelEntries);
            $barrelEntries[$barrelKey] = $castRelativePath;

            foreach ($routeMap[$extracted->class] ?? [] as $routeUsage) {
                $routeName = $routeUsage['name'];

                if ($routeName === null || $routeName === '') {
                    continue;
                }

                [$routeIndexFile, $routeExportName] = $this->namedRouteTarget($routeName);

                if ($routeExportName !== '') {
                    $namedRouteFiles[$routeIndexFile][$routeExportName] = $castRelativePath;
                }
            }
        }

        foreach ($namedRouteFiles as $routeIndexFile => $routeExports) {
            $files[$routeIndexFile] = TsCode::composeNamedRouteIndex($routeIndexFile, $routeExports);
        }

        $files['index.ts'] = TsCode::composeBarrelIndex($barrelEntries);

        $writeResult = $writer->write($outputDir, $files, dryRun: $dryRun || $check);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Written', (string) count($writeResult->written)],
                ['Unchanged', (string) count($writeResult->unchanged)],
                ['Removed', (string) count($writeResult->removed)],
                ['Skipped', (string) count($extraction['skipped'])],
                ['Warnings', (string) count($warnings)],
            ],
        );

        foreach ($extraction['skipped'] as $skippedMessage) {
            $this->components->warn($skippedMessage);
        }

        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($check) {
            if ($writeResult->hasChanges()) {
                $this->components->error('Generated Formcast files are out of date. Run "php artisan formcast:generate".');

                return self::FAILURE;
            }

            $this->components->info('Formcast files are up to date.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->components->info('Dry run complete (no files were modified).');

            return self::SUCCESS;
        }

        $this->components->info('Formcast generation complete.');

        return self::SUCCESS;
    }

    private function defaultLocale(): ?string
    {
        $locales = config('formcast.messages.locales', [null]);

        if (is_array($locales) && array_key_exists(0, $locales) && is_string($locales[0])) {
            return $locales[0];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $redactedFields
     * @return array<string, mixed>
     */
    private function applyRedactions(array $rules, array $redactedFields): array
    {
        if ($redactedFields === []) {
            return $rules;
        }

        foreach ($redactedFields as $field) {
            unset($rules[$field]);
        }

        return $rules;
    }

    private function relativeSourcePath(string $absolutePath): string
    {
        $base = str_replace('\\', '/', base_path());
        $normalized = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalized, $base.'/')) {
            return substr($normalized, strlen($base) + 1);
        }

        return $normalized;
    }

    /**
     * Map a Laravel route name (e.g. `orders.store`, `admin.orders.update`, `checkout`)
     * to a Wayfinder-style `routes/<group>/index.ts` file and export identifier.
     *
     * @return array{0: string, 1: string}
     */
    private function namedRouteTarget(string $routeName): array
    {
        $segments = array_values(array_filter(
            explode('.', $routeName),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return ['routes/index.ts', ''];
        }

        $exportRaw = array_pop($segments);
        $exportName = Str::camel(preg_replace('/[^a-zA-Z0-9_]+/', '_', $exportRaw) ?? $exportRaw);

        if ($segments === []) {
            return ['routes/index.ts', $exportName];
        }

        return ['routes/'.implode('/', $segments).'/index.ts', $exportName];
    }

    /**
     * @param  array<string, string>  $existing
     */
    private function uniqueBarrelAlias(string $exportName, string $fqcn, array $existing): string
    {
        if (! array_key_exists($exportName, $existing)) {
            return $exportName;
        }

        return str_replace('\\', '', ltrim($fqcn, '\\'));
    }
}
