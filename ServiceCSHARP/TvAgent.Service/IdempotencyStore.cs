using System.Text.Json;
using TvAgent.Domain;

namespace TvAgent.Service;

public sealed class IdempotencyStore
{
    private readonly AgentOptions _options;
    private readonly SemaphoreSlim _gate = new(1, 1);
    private HashSet<string>? _completed;

    public IdempotencyStore(AgentOptions options) => _options = options;

    public async Task<bool> ContainsAsync(string key, CancellationToken cancellationToken)
    {
        await _gate.WaitAsync(cancellationToken);
        try
        {
            await EnsureLoadedAsync(cancellationToken);
            return _completed!.Contains(key);
        }
        finally
        {
            _gate.Release();
        }
    }

    public async Task AddAsync(string key, CancellationToken cancellationToken)
    {
        await _gate.WaitAsync(cancellationToken);
        try
        {
            await EnsureLoadedAsync(cancellationToken);
            if (!_completed!.Add(key))
            {
                return;
            }

            Directory.CreateDirectory(_options.WorkingDirectory);
            var temporaryPath = StorePath + ".tmp";
            await File.WriteAllTextAsync(temporaryPath, JsonSerializer.Serialize(_completed), cancellationToken);
            File.Move(temporaryPath, StorePath, true);
        }
        finally
        {
            _gate.Release();
        }
    }

    private async Task EnsureLoadedAsync(CancellationToken cancellationToken)
    {
        if (_completed is not null)
        {
            return;
        }

        if (!File.Exists(StorePath))
        {
            _completed = new HashSet<string>(StringComparer.Ordinal);
            return;
        }

        var json = await File.ReadAllTextAsync(StorePath, cancellationToken);
        _completed = JsonSerializer.Deserialize<HashSet<string>>(json) ?? new HashSet<string>(StringComparer.Ordinal);
    }

    private string StorePath => Path.Combine(_options.WorkingDirectory, "completed-commands.json");
}
