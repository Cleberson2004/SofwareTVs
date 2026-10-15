using System.Diagnostics;
using TvAgent.Domain;

namespace TvAgent.SessionHost;

public sealed class VlcManager : IVlcManager, IDisposable
{
    private readonly VlcOptions _options;
    private readonly AgentOptions _agentOptions;
    private readonly SemaphoreSlim _gate = new(1, 1);
    private Process? _process;
    private string? _activeMedia;

    public VlcManager(VlcOptions options, AgentOptions agentOptions)
    {
        _options = options;
        _agentOptions = agentOptions;
        RestoreOwnedProcess();
    }

    public Task<VlcState> GetStatusAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        var running = _process is { HasExited: false };
        return Task.FromResult(new VlcState(running, running ? _process!.Id : null, _activeMedia, running && _process!.Responding));
    }

    public async Task<VlcState> StartAsync(string? mediaPath, CancellationToken cancellationToken)
    {
        await _gate.WaitAsync(cancellationToken);
        try
        {
            if (_process is { HasExited: false })
            {
                return await GetStatusAsync(cancellationToken);
            }

            if (!File.Exists(_options.Path))
            {
                throw new FileNotFoundException("Executável do VLC não encontrado.", _options.Path);
            }

            var startInfo = new ProcessStartInfo(_options.Path)
            {
                UseShellExecute = false,
                CreateNoWindow = !_options.TestMode,
                WorkingDirectory = _agentOptions.WorkingDirectory,
            };
            startInfo.ArgumentList.Add("--no-video-title-show");
            startInfo.ArgumentList.Add("--loop");
            startInfo.ArgumentList.Add("--one-instance");
            startInfo.ArgumentList.Add("--no-osd");
            if (_options.TestMode)
            {
                startInfo.ArgumentList.Add($"--width={_options.WindowWidth}");
                startInfo.ArgumentList.Add($"--height={_options.WindowHeight}");
                startInfo.ArgumentList.Add("--no-fullscreen");
            }
            else if (_options.Fullscreen)
            {
                startInfo.ArgumentList.Add("--fullscreen");
            }

            if (!string.IsNullOrWhiteSpace(mediaPath))
            {
                if (!File.Exists(mediaPath))
                {
                    throw new FileNotFoundException("Mídia ativa não encontrada.", mediaPath);
                }

                startInfo.ArgumentList.Add(mediaPath);
                _activeMedia = mediaPath;
            }

            _process = Process.Start(startInfo) ?? throw new InvalidOperationException("O VLC não pôde ser iniciado.");
            await File.WriteAllTextAsync(PidPath, _process.Id.ToString(), cancellationToken);
            await Task.Delay(500, cancellationToken);
            if (_process.HasExited)
            {
                throw new InvalidOperationException($"O VLC encerrou imediatamente com código {_process.ExitCode}.");
            }

            return await GetStatusAsync(cancellationToken);
        }
        finally
        {
            _gate.Release();
        }
    }

    public async Task StopAsync(CancellationToken cancellationToken)
    {
        await _gate.WaitAsync(cancellationToken);
        try
        {
            if (_process is not { HasExited: false })
            {
                return;
            }

            if (_process.CloseMainWindow())
            {
                using var timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
                timeout.CancelAfter(TimeSpan.FromSeconds(5));
                try
                {
                    await _process.WaitForExitAsync(timeout.Token);
                }
                catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
                {
                    _process.Kill(true);
                    await _process.WaitForExitAsync(cancellationToken);
                }
            }
            else
            {
                _process.Kill(true);
                await _process.WaitForExitAsync(cancellationToken);
            }

            File.Delete(PidPath);
        }
        finally
        {
            _gate.Release();
        }
    }

    public async Task<VlcState> RestartAsync(CancellationToken cancellationToken)
    {
        var media = _activeMedia;
        await StopAsync(cancellationToken);
        return await StartAsync(media, cancellationToken);
    }

    public async Task<VlcState> ActivateAsync(string mediaPath, CancellationToken cancellationToken)
    {
        _activeMedia = mediaPath;
        await StopAsync(cancellationToken);
        return await StartAsync(mediaPath, cancellationToken);
    }

    public void Dispose()
    {
        _process?.Dispose();
        _gate.Dispose();
    }

    private string PidPath => Path.Combine(_agentOptions.WorkingDirectory, "vlc.pid");

    private void RestoreOwnedProcess()
    {
        try
        {
            if (File.Exists(PidPath) && int.TryParse(File.ReadAllText(PidPath), out var pid))
            {
                var process = Process.GetProcessById(pid);
                if (process.ProcessName.Equals("vlc", StringComparison.OrdinalIgnoreCase))
                {
                    _process = process;
                }
            }
        }
        catch (ArgumentException)
        {
            File.Delete(PidPath);
        }
    }
}
