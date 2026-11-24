<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

afterEach(function (): void {
    $files = new Filesystem;

    foreach ([
        config_path('formcast.php'),
        base_path('.agents/skills/formcast'),
        base_path('.claude/skills/formcast'),
        base_path('.cursor/rules/formcast.mdc'),
        base_path('.github/instructions/formcast.instructions.md'),
    ] as $path) {
        if ($files->isDirectory($path)) {
            $files->deleteDirectory($path);
        } elseif ($files->exists($path)) {
            $files->delete($path);
        }
    }
});

it('publishes config and prints Vite and tsconfig setup instructions', function (): void {
    $this->artisan('formcast:install', ['--force' => true])
        ->expectsOutputToContain('@rylacdang/formcast/vite')
        ->expectsOutputToContain('@/formcast/*')
        ->expectsOutputToContain('Formcast installed successfully.')
        ->assertSuccessful();

    expect(is_file(config_path('formcast.php')))->toBeTrue();
});

it('installs the Formcast AI skill for antigravity, claude, cursor, and copilot', function (): void {
    $this->artisan('formcast:install', [
        '--agents' => 'antigravity,claude,cursor,copilot',
        '--force' => true,
    ])
        ->expectsOutputToContain('Installed Formcast skill for antigravity')
        ->expectsOutputToContain('Installed Formcast skill for claude')
        ->expectsOutputToContain('Installed Formcast skill for cursor')
        ->expectsOutputToContain('Installed Formcast skill for copilot')
        ->assertSuccessful();

    expect(is_file(base_path('.agents/skills/formcast/SKILL.md')))->toBeTrue();
    expect(is_file(base_path('.agents/skills/formcast/references/rule-support.md')))->toBeTrue();
    expect(is_file(base_path('.claude/skills/formcast/SKILL.md')))->toBeTrue();
    expect(is_file(base_path('.claude/skills/formcast/references/vue-patterns.md')))->toBeTrue();

    $cursorContent = (string) file_get_contents(base_path('.cursor/rules/formcast.mdc'));
    expect($cursorContent)->toContain('alwaysApply: false');
    expect($cursorContent)->toContain('# Formcast');

    $copilotContent = (string) file_get_contents(base_path('.github/instructions/formcast.instructions.md'));
    expect($copilotContent)->toContain('applyTo: "**/*"');
    expect($copilotContent)->toContain('# Formcast');
});

it('warns on unknown agent names without failing installation', function (): void {
    $this->artisan('formcast:install', [
        '--agents' => 'unknown_tool',
        '--force' => true,
    ])
        ->expectsOutputToContain("Unknown agent 'unknown_tool'")
        ->assertSuccessful();
});
