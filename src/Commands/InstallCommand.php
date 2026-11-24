<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class InstallCommand extends Command
{
    protected $signature = 'formcast:install
        {--agents= : Comma-separated AI tools to install the skill for (antigravity,claude,cursor,copilot)}
        {--force : Overwrite existing config and AI skill files}';

    protected $description = 'Install Formcast configuration, setup snippets, and optional AI skills';

    /** @var array<string, string> */
    private const AGENT_DESTINATIONS = [
        'antigravity' => '.agents/skills/formcast/SKILL.md',
        'claude' => '.claude/skills/formcast/SKILL.md',
        'cursor' => '.cursor/rules/formcast.mdc',
        'copilot' => '.github/instructions/formcast.instructions.md',
    ];

    public function handle(Filesystem $files): int
    {
        $force = (bool) $this->option('force');

        $this->call('vendor:publish', [
            '--tag' => 'formcast-config',
            '--force' => $force,
        ]);

        $this->installAiSkills($files, $force);
        $this->printNextSteps();

        $this->components->info('Formcast installed successfully.');

        return self::SUCCESS;
    }

    private function installAiSkills(Filesystem $files, bool $force): void
    {
        $agentsOption = $this->option('agents');
        $hasBoost = $this->hasLaravelBoost($files);

        if (! is_string($agentsOption) || trim($agentsOption) === '') {
            if ($hasBoost) {
                $this->components->info('Laravel Boost detected — Formcast guidelines and skill are discovered automatically via php artisan boost:install.');
            } else {
                $this->components->info('Tip: Run "php artisan formcast:install --agents=antigravity,claude,cursor,copilot" to install the Formcast AI skill.');
            }

            return;
        }

        $agents = array_values(array_unique(array_filter(
            array_map(
                static fn (string $agent): string => strtolower(trim($agent)),
                explode(',', $agentsOption),
            ),
            static fn (string $agent): bool => $agent !== '',
        )));

        $skillSourceDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'boost'.DIRECTORY_SEPARATOR.'skills'.DIRECTORY_SEPARATOR.'formcast';
        $skillSourceFile = $skillSourceDir.DIRECTORY_SEPARATOR.'SKILL.md';

        if (! $files->exists($skillSourceFile)) {
            $this->components->warn('Formcast skill template could not be found.');

            return;
        }

        $skillContent = $files->get($skillSourceFile);

        foreach ($agents as $agent) {
            if ($agent === 'boost') {
                $this->components->info('Laravel Boost discovers Formcast skills automatically — no copy needed.');

                continue;
            }

            if (! array_key_exists($agent, self::AGENT_DESTINATIONS)) {
                $this->components->warn("Unknown agent '{$agent}'. Supported: antigravity, claude, cursor, copilot.");

                continue;
            }

            $relativeDest = self::AGENT_DESTINATIONS[$agent];
            $fullDest = base_path($relativeDest);

            if ($files->exists($fullDest) && ! $force) {
                $this->components->warn("Skipped {$relativeDest} (already exists; pass --force to overwrite).");

                continue;
            }

            $files->ensureDirectoryExists(dirname($fullDest));
            $files->put($fullDest, $this->formatForAgent($agent, $skillContent));

            if ($agent === 'antigravity' || $agent === 'claude') {
                $this->copyReferences($files, $skillSourceDir, dirname($fullDest), $force);
            }

            $this->components->info("Installed Formcast skill for {$agent} at {$relativeDest}.");
        }
    }

    private function copyReferences(
        Filesystem $files,
        string $sourceSkillDir,
        string $destSkillDir,
        bool $force,
    ): void {
        $sourceRefs = $sourceSkillDir.DIRECTORY_SEPARATOR.'references';

        if (! $files->isDirectory($sourceRefs)) {
            return;
        }

        $destRefs = $destSkillDir.DIRECTORY_SEPARATOR.'references';
        $files->ensureDirectoryExists($destRefs);

        foreach ($files->files($sourceRefs) as $refFile) {
            $target = $destRefs.DIRECTORY_SEPARATOR.$refFile->getFilename();

            if ($files->exists($target) && ! $force) {
                continue;
            }

            $files->copy($refFile->getPathname(), $target);
        }
    }

    private function formatForAgent(string $agent, string $skillContent): string
    {
        if ($agent === 'cursor') {
            $body = $this->stripFrontmatter($skillContent);

            return "---\ndescription: Formcast validation schema generation and useFormcast patterns for Laravel + Inertia\nalwaysApply: false\n---\n\n".$body;
        }

        if ($agent === 'copilot') {
            $body = $this->stripFrontmatter($skillContent);

            return "---\napplyTo: \"**/*\"\n---\n\n".$body;
        }

        return $skillContent;
    }

    private function stripFrontmatter(string $markdown): string
    {
        return (string) preg_replace('/\A---\R.*?\R---\R+/s', '', $markdown);
    }

    private function hasLaravelBoost(Filesystem $files): bool
    {
        return class_exists('Laravel\\Boost\\BoostServiceProvider')
            || $files->exists(base_path('vendor/laravel/boost/composer.json'));
    }

    private function printNextSteps(): void
    {
        $this->newLine();
        $this->line('<comment>1. Add the Vite plugin in vite.config.ts:</comment>');
        $this->line("   import { formcast } from '@rylacdang/formcast/vite';");
        $this->line('   plugins: [..., formcast()],');
        $this->newLine();
        $this->line('<comment>2. Add a path alias in tsconfig.json (if not already covered by @/*):</comment>');
        $this->line('   "@/formcast/*": ["./resources/js/formcast/*"]');
        $this->newLine();
        $this->line('<comment>3. Generate your casts:</comment>');
        $this->line('   php artisan formcast:generate');
        $this->newLine();
    }
}
