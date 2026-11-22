<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tree;

use Rylacdang\Formcast\Rules\NormalizedRule;

final class RuleTreeBuilder
{
    /**
     * Build a deterministic nested FieldNode tree from normalized dot/wildcard rules.
     *
     * @param  array<string, list<NormalizedRule>>  $normalizedRules
     */
    public function build(array $normalizedRules): FieldNode
    {
        /** @var array<string, mixed> $root */
        $root = $this->createDraft('', '');

        foreach ($normalizedRules as $path => $rules) {
            $segments = array_values(array_filter(explode('.', $path), static fn (string $s): bool => $s !== ''));

            if ($segments === []) {
                continue;
            }

            $this->insert($root, $segments, $rules);
        }

        return $this->freeze($root);
    }

    /**
     * @return array{name: string, path: string, rules: list<NormalizedRule>, children: array<string, mixed>, element: mixed, implicit: bool}
     */
    private function createDraft(string $name, string $path, bool $implicit = true): array
    {
        return [
            'name' => $name,
            'path' => $path,
            'rules' => [],
            'children' => [],
            'element' => null,
            'implicit' => $implicit,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $segments
     * @param  list<NormalizedRule>  $rules
     */
    private function insert(array &$node, array $segments, array $rules): void
    {
        $cursor = &$node;
        $builtPath = '';

        foreach ($segments as $segment) {
            $builtPath = $builtPath === '' ? $segment : $builtPath.'.'.$segment;

            if ($segment === '*') {
                if (! is_array($cursor['element'])) {
                    $cursor['element'] = $this->createDraft('*', $builtPath, implicit: true);
                }

                $cursor = &$cursor['element'];

                continue;
            }

            if (! is_array($cursor['children'])) {
                $cursor['children'] = [];
            }

            if (! isset($cursor['children'][$segment]) || ! is_array($cursor['children'][$segment])) {
                $cursor['children'][$segment] = $this->createDraft($segment, $builtPath, implicit: true);
            }

            $cursor = &$cursor['children'][$segment];
        }

        /** @var list<NormalizedRule> $existing */
        $existing = is_array($cursor['rules']) ? $cursor['rules'] : [];
        $cursor['rules'] = [...$existing, ...$rules];
        $cursor['implicit'] = false;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function freeze(array $draft): FieldNode
    {
        $children = [];

        if (is_array($draft['children'])) {
            /** @var array<string, array<string, mixed>> $rawChildren */
            $rawChildren = $draft['children'];
            ksort($rawChildren, SORT_STRING);

            foreach ($rawChildren as $key => $childDraft) {
                $children[$key] = $this->freeze($childDraft);
            }
        }

        $element = is_array($draft['element'])
            ? $this->freeze($draft['element'])
            : null;

        /** @var string $name */
        $name = $draft['name'];

        /** @var string $path */
        $path = $draft['path'];

        /** @var list<NormalizedRule> $rules */
        $rules = is_array($draft['rules']) ? $draft['rules'] : [];

        /** @var bool $implicit */
        $implicit = (bool) $draft['implicit'];

        return new FieldNode(
            name: $name,
            path: $path,
            rules: $rules,
            children: $children,
            element: $element,
            implicit: $implicit,
        );
    }
}
