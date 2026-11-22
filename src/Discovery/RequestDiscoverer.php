<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Discovery;

use Illuminate\Foundation\Http\FormRequest;
use PhpToken;
use ReflectionClass;
use Rylacdang\Formcast\Attributes\Formcast;
use Rylacdang\Formcast\Attributes\FormcastIgnore;
use Symfony\Component\Finder\Finder;

final class RequestDiscoverer
{
    /**
     * Discover concrete FormRequest classes from the given directories or files.
     *
     * @param  list<string>  $paths
     * @return list<DiscoveredRequest>
     */
    public function discover(array $paths, string $mode = 'all'): array
    {
        /** @var array<class-string<FormRequest>, DiscoveredRequest> $discovered */
        $discovered = [];

        foreach ($this->resolveFiles($paths) as $filePath) {
            foreach ($this->extractClassNames($filePath) as $className) {
                if (! class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                if (
                    $reflection->isAbstract()
                    || $reflection->isInterface()
                    || $reflection->isTrait()
                    || $reflection->isEnum()
                    || ! $reflection->isSubclassOf(FormRequest::class)
                ) {
                    continue;
                }

                if ($reflection->getAttributes(FormcastIgnore::class) !== []) {
                    continue;
                }

                $formcastAttributes = $reflection->getAttributes(Formcast::class);

                if ($mode === 'attribute' && $formcastAttributes === []) {
                    continue;
                }

                $customName = $formcastAttributes !== []
                    ? $formcastAttributes[0]->newInstance()->name
                    : null;

                $exportName = ($customName !== null && $customName !== '')
                    ? $customName
                    : $reflection->getShortName();

                /** @var class-string<FormRequest> $fqcn */
                $fqcn = $reflection->getName();

                $discovered[$fqcn] = new DiscoveredRequest(
                    class: $fqcn,
                    filePath: $filePath,
                    exportName: $exportName,
                );
            }
        }

        ksort($discovered, SORT_STRING);

        return array_values($discovered);
    }

    /**
     * Resolve all PHP file paths from the configured directories or files.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function resolveFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path) && str_ends_with($path, '.php')) {
                $realPath = realpath($path);
                $files[] = $realPath !== false ? $realPath : $path;

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            $finder = (new Finder)
                ->files()
                ->in($path)
                ->name('*.php')
                ->sortByName();

            foreach ($finder as $file) {
                $realPath = $file->getRealPath();
                $files[] = $realPath !== false ? $realPath : $file->getPathname();
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Extract declared class FQCNs from a PHP file using PhpToken.
     *
     * @return list<string>
     */
    private function extractClassNames(string $filePath): array
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return [];
        }

        $tokens = array_values(PhpToken::tokenize($contents));
        $count = count($tokens);
        $namespace = '';
        $classes = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->id === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $i);

                continue;
            }

            if ($token->id !== T_CLASS) {
                continue;
            }

            $previous = $this->previousSignificantToken($tokens, $i);

            // Skip `Foo::class` references and `new class` anonymous classes.
            if ($previous !== null && in_array($previous->id, [T_DOUBLE_COLON, T_NEW], true)) {
                continue;
            }

            $next = $this->nextSignificantToken($tokens, $i);

            if ($next === null || $next->id !== T_STRING) {
                continue;
            }

            $classes[] = $namespace !== ''
                ? $namespace.'\\'.$next->text
                : $next->text;
        }

        return $classes;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private function readNamespace(array $tokens, int $startIndex): string
    {
        $parts = [];
        $count = count($tokens);

        for ($i = $startIndex + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->text === ';' || $token->text === '{') {
                break;
            }

            if (in_array($token->id, [T_NAME_QUALIFIED, T_STRING, T_NS_SEPARATOR], true)) {
                $parts[] = $token->text;
            }
        }

        return trim(implode('', $parts), '\\');
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private function previousSignificantToken(array $tokens, int $index): ?PhpToken
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (! $tokens[$i]->isIgnorable()) {
                return $tokens[$i];
            }
        }

        return null;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private function nextSignificantToken(array $tokens, int $index): ?PhpToken
    {
        $count = count($tokens);

        for ($i = $index + 1; $i < $count; $i++) {
            if (! $tokens[$i]->isIgnorable()) {
                return $tokens[$i];
            }
        }

        return null;
    }
}
