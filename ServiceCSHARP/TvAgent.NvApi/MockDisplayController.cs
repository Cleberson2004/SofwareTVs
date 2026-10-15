using TvAgent.Domain;

namespace TvAgent.NvApi;

public sealed class MockDisplayController : IDisplayController
{
    private readonly MockDisplayOptions _options;
    private readonly SemaphoreSlim _gate = new(1, 1);
    private DisplayState _state;

    public MockDisplayController(DisplayOptions options)
    {
        _options = options.Mock;
        _state = CreateState(_options.Enabled, _options.Rows, _options.Columns, _options.DisplayWidth, _options.DisplayHeight, _options.RefreshRate, _options.OverlapX, _options.OverlapY);
    }

    public string ProviderName => "Mock";

    public Task<DisplayState> GetCurrentStateAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        return Task.FromResult(_state);
    }

    public Task<DisplayValidationResult> ValidateProfileAsync(DisplayProfile profile, CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();

        if (_options.FailureMode.Equals("InvalidProfile", StringComparison.OrdinalIgnoreCase))
        {
            return Task.FromResult(new DisplayValidationResult(false, "mock_invalid_profile", "Falha de validação simulada."));
        }

        if (_options.FailureMode.Equals("ScreenDisconnected", StringComparison.OrdinalIgnoreCase) || profile.Rows * profile.Columns > _state.Displays.Count)
        {
            return Task.FromResult(new DisplayValidationResult(false, "display_disconnected", "Não há telas suficientes para o perfil."));
        }

        if (profile.Rows < 1 || profile.Columns < 1 || profile.DisplayWidth < 1 || profile.DisplayHeight < 1)
        {
            return Task.FromResult(new DisplayValidationResult(false, "invalid_dimensions", "As dimensões e a topologia devem ser positivas."));
        }

        return Task.FromResult(new DisplayValidationResult(true));
    }

    public async Task<DisplayApplyResult> ApplyProfileAsync(DisplayProfile profile, CancellationToken cancellationToken)
    {
        await _gate.WaitAsync(cancellationToken);
        try
        {
            var validation = await ValidateProfileAsync(profile, cancellationToken);
            if (!validation.IsValid)
            {
                return new DisplayApplyResult(false, _state, ErrorCode: validation.ErrorCode, Message: validation.Message);
            }

            if (_options.FailureMode.Equals("DriverTimeout", StringComparison.OrdinalIgnoreCase))
            {
                await Task.Delay(Timeout.InfiniteTimeSpan, cancellationToken);
            }

            await Task.Delay(_options.SimulatedApplyDelayMilliseconds, cancellationToken);
            if (_options.FailureMode.Equals("ApplyFailure", StringComparison.OrdinalIgnoreCase))
            {
                return new DisplayApplyResult(false, _state, ErrorCode: "mock_apply_failed", Message: "Falha de aplicação simulada.");
            }

            _state = CreateState(true, profile.Rows, profile.Columns, profile.DisplayWidth, profile.DisplayHeight, profile.RefreshRate, profile.OverlapX, profile.OverlapY);
            if (_options.FailureMode.Equals("DivergentResolution", StringComparison.OrdinalIgnoreCase))
            {
                _state = _state with { TotalWidth = _state.TotalWidth - 1 };
            }

            return new DisplayApplyResult(true, _state);
        }
        finally
        {
            _gate.Release();
        }
    }

    public Task<DisplaySnapshot> CaptureSnapshotAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        return Task.FromResult(new DisplaySnapshot(_state, CapturedAt: DateTimeOffset.UtcNow));
    }

    public async Task<DisplayApplyResult> RestoreSnapshotAsync(DisplaySnapshot snapshot, CancellationToken cancellationToken)
    {
        await Task.Delay(_options.SimulatedApplyDelayMilliseconds, cancellationToken);
        if (_options.FailureMode.Equals("RollbackFailure", StringComparison.OrdinalIgnoreCase))
        {
            return new DisplayApplyResult(false, _state, true, false, "mock_rollback_failed", "Falha de rollback simulada.");
        }

        _state = snapshot.State;
        return new DisplayApplyResult(true, _state, true, true);
    }

    public Task<DisplayApplyResult> EnableCurrentAsync(bool enabled, CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        _state = _state with { Enabled = enabled };
        return Task.FromResult(new DisplayApplyResult(true, _state));
    }

    private static DisplayState CreateState(bool enabled, int rows, int columns, int width, int height, double refreshRate, int overlapX, int overlapY)
    {
        var displays = Enumerable.Range(1, rows * columns)
            .Select(position => new DisplayInfo(position, $"MOCK-{position:00}", 0))
            .ToArray();
        var totalWidth = width * columns - overlapX * Math.Max(0, columns - 1);
        var totalHeight = height * rows - overlapY * Math.Max(0, rows - 1);
        return new DisplayState(enabled, rows, columns, width, height, totalWidth, totalHeight, refreshRate, overlapX, overlapY, displays);
    }
}
