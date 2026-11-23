import { EventEmitter } from 'node:events';
import { resolve } from 'node:path';
import { type ResolvedConfig, type ViteDevServer } from 'vite';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { formcast, matchesWatchPatterns } from '../../src/vite';

function createMockServer(root: string): {
  server: ViteDevServer;
  watcher: EventEmitter;
  wsSend: ReturnType<typeof vi.fn>;
  loggerError: ReturnType<typeof vi.fn>;
} {
  const watcher = new EventEmitter();
  const wsSend = vi.fn();
  const loggerError = vi.fn();

  const server = {
    watcher,
    ws: { send: wsSend },
    config: {
      root,
      logger: { error: loggerError },
    },
  } as unknown as ViteDevServer;

  return { server, watcher, wsSend, loggerError };
}

describe('formcast Vite plugin', () => {
  afterEach(() => {
    delete process.env['FORMCAST_SKIP'];
    vi.useRealTimers();
  });

  it('matches FormRequests, Enums, and validation lang files by default', () => {
    const root = resolve('/project');
    const defaults = [
      'app/Http/Requests/**/*.php',
      'app/Enums/**/*.php',
      'lang/**/validation.php',
      'resources/lang/**/validation.php',
    ];

    expect(
      matchesWatchPatterns(
        resolve(root, 'app/Http/Requests/StoreOrderRequest.php'),
        root,
        defaults,
      ),
    ).toBe(true);
    expect(
      matchesWatchPatterns(
        resolve(root, 'app/Http/Requests/Billing/PayRequest.php'),
        root,
        defaults,
      ),
    ).toBe(true);
    expect(matchesWatchPatterns(resolve(root, 'app/Enums/PaymentMethod.php'), root, defaults)).toBe(
      true,
    );
    expect(matchesWatchPatterns(resolve(root, 'lang/en/validation.php'), root, defaults)).toBe(
      true,
    );
    expect(matchesWatchPatterns(resolve(root, 'app/Models/User.php'), root, defaults)).toBe(false);
  });

  it('runs on buildStart and respects runOnBuild and FORMCAST_SKIP', async () => {
    const runner = vi.fn().mockResolvedValue(undefined);
    const plugin = formcast({ runner, cwd: '/project' });

    const configResolved = plugin.configResolved as (config: ResolvedConfig) => void;
    const buildStart = plugin.buildStart as () => Promise<void>;

    configResolved({ root: '/project', command: 'build' } as ResolvedConfig);
    await buildStart();
    expect(runner).toHaveBeenCalledTimes(1);
    expect(runner).toHaveBeenCalledWith('php artisan formcast:generate', '/project');

    // FORMCAST_SKIP=1 skips execution
    process.env['FORMCAST_SKIP'] = '1';
    await buildStart();
    expect(runner).toHaveBeenCalledTimes(1);

    // runOnBuild: false skips during vite build
    delete process.env['FORMCAST_SKIP'];
    const skipBuildPlugin = formcast({ runner, runOnBuild: false });
    (skipBuildPlugin.configResolved as (config: ResolvedConfig) => void)({
      root: '/project',
      command: 'build',
    } as ResolvedConfig);
    await (skipBuildPlugin.buildStart as () => Promise<void>)();
    expect(runner).toHaveBeenCalledTimes(1);
  });

  it('debounces watched file changes and sends errors to the Vite dev overlay', async () => {
    vi.useFakeTimers();

    const root = resolve('/project');
    const runner = vi
      .fn()
      .mockRejectedValueOnce(new Error('Syntax error in StoreOrderRequest.php'));
    const plugin = formcast({ runner, debounce: 100 });

    const { server, watcher, wsSend, loggerError } = createMockServer(root);

    (plugin.configResolved as (config: ResolvedConfig) => void)({
      root,
      command: 'serve',
    } as ResolvedConfig);
    (plugin.configureServer as (s: ViteDevServer) => void)(server);

    // Unrelated file is ignored
    watcher.emit('change', resolve(root, 'app/Models/Order.php'));
    await vi.advanceTimersByTimeAsync(150);
    expect(runner).not.toHaveBeenCalled();

    // Rapid edits debounce into a single run
    watcher.emit('change', resolve(root, 'app/Http/Requests/StoreOrderRequest.php'));
    watcher.emit('change', resolve(root, 'app/Http/Requests/StoreOrderRequest.php'));
    await vi.advanceTimersByTimeAsync(120);

    expect(runner).toHaveBeenCalledTimes(1);
    expect(loggerError).toHaveBeenCalledWith('[formcast] Syntax error in StoreOrderRequest.php');
    expect(wsSend).toHaveBeenCalledTimes(1);
    const payload = wsSend.mock.calls[0]?.[0] as { type: string; err: { message: string } };
    expect(payload.type).toBe('error');
    expect(payload.err.message).toBe('[formcast] Syntax error in StoreOrderRequest.php');
  });
});
