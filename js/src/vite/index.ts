import { exec } from 'node:child_process';
import { relative, resolve } from 'node:path';
import { promisify } from 'node:util';
import { type Plugin, type ViteDevServer } from 'vite';

const execAsync = promisify(exec);

export interface FormcastViteOptions {
  /**
   * Command executed to regenerate Formcast casts.
   * @default 'php artisan formcast:generate'
   */
  readonly command?: string;

  /**
   * Relative glob patterns watched in dev mode.
   */
  readonly patterns?: readonly string[];

  /**
   * Debounce duration in milliseconds for file-watch regenerations.
   * @default 150
   */
  readonly debounce?: number;

  /**
   * Whether to run generation during `vite build`.
   * @default true
   */
  readonly runOnBuild?: boolean;

  /**
   * Working directory for executing the command (defaults to Vite root).
   */
  readonly cwd?: string;

  /**
   * Custom command runner (primarily useful for testing).
   */
  readonly runner?: (command: string, cwd: string) => Promise<void>;
}

const DEFAULT_PATTERNS: readonly string[] = [
  'app/Http/Requests/**/*.php',
  'app/Enums/**/*.php',
  'lang/**/validation.php',
  'resources/lang/**/validation.php',
];

function globToRegExp(glob: string): RegExp {
  const normalized = glob.replace(/\\/g, '/').replace(/^\.\//, '');
  let regex = '^';
  let i = 0;

  while (i < normalized.length) {
    const char = normalized[i];

    if (char === '*') {
      if (normalized[i + 1] === '*') {
        if (normalized[i + 2] === '/') {
          regex += '(?:.*/)?';
          i += 3;
          continue;
        }
        regex += '.*';
        i += 2;
        continue;
      }
      regex += '[^/]*';
      i += 1;
      continue;
    }

    if (char === '?') {
      regex += '[^/]';
      i += 1;
      continue;
    }

    if (char !== undefined && /[.+^${}()|[\]\\]/.test(char)) {
      regex += `\\${char}`;
    } else if (char !== undefined) {
      regex += char;
    }

    i += 1;
  }

  return new RegExp(`${regex}$`);
}

export function matchesWatchPatterns(
  filePath: string,
  root: string,
  patterns: readonly string[],
): boolean {
  const rel = relative(resolve(root), resolve(filePath)).replace(/\\/g, '/');
  if (rel.startsWith('..') || rel === '') {
    return false;
  }

  return patterns.some((pattern) => globToRegExp(pattern).test(rel));
}

function isSkippedByEnv(): boolean {
  const value = process.env['FORMCAST_SKIP'];
  return value === '1' || value === 'true';
}

async function defaultRunner(command: string, cwd: string): Promise<void> {
  await execAsync(command, { cwd });
}

export function formcast(options: FormcastViteOptions = {}): Plugin {
  const command = options.command ?? 'php artisan formcast:generate';
  const patterns = options.patterns ?? DEFAULT_PATTERNS;
  const debounceMs = options.debounce ?? 150;
  const runOnBuild = options.runOnBuild ?? true;
  const runner = options.runner ?? defaultRunner;

  let root = options.cwd ?? process.cwd();
  let isBuild = false;
  let timer: ReturnType<typeof setTimeout> | null = null;
  let running = false;
  let queued = false;

  const execute = async (server?: ViteDevServer): Promise<void> => {
    if (isSkippedByEnv()) {
      return;
    }

    if (running) {
      queued = true;
      return;
    }

    running = true;
    try {
      await runner(command, options.cwd ?? root);
    } catch (error: unknown) {
      const err = error instanceof Error ? error : new Error(String(error));

      if (server) {
        server.config.logger.error(`[formcast] ${err.message}`);
        server.ws.send({
          type: 'error',
          err: {
            message: `[formcast] ${err.message}`,
            stack: err.stack ?? '',
          },
        });
      } else {
        throw err;
      }
    } finally {
      running = false;
      if (queued) {
        queued = false;
        void execute(server);
      }
    }
  };

  const schedule = (server: ViteDevServer): void => {
    if (timer !== null) {
      clearTimeout(timer);
    }

    timer = setTimeout(() => {
      timer = null;
      void execute(server);
    }, debounceMs);
  };

  return {
    name: 'formcast',

    configResolved(config) {
      root = config.root;
      isBuild = config.command === 'build';
    },

    async buildStart() {
      if (isSkippedByEnv()) {
        return;
      }

      if (isBuild && !runOnBuild) {
        return;
      }

      await execute();
    },

    configureServer(server) {
      const onFileEvent = (filePath: string): void => {
        if (isSkippedByEnv()) {
          return;
        }

        if (matchesWatchPatterns(filePath, root, patterns)) {
          schedule(server);
        }
      };

      server.watcher.on('change', onFileEvent);
      server.watcher.on('add', onFileEvent);
      server.watcher.on('unlink', onFileEvent);
    },

    buildEnd() {
      if (timer !== null) {
        clearTimeout(timer);
        timer = null;
      }
    },
  };
}

export default formcast;
