using System.Text.Json;
using TvAgent.Domain;
using TvAgent.NvApi.NativeInterop;

namespace TvAgent.NvApi;

public sealed class NvApiDisplayController : IDisplayController, IDisposable
{
    private readonly NvApiNative _native = new();
    private readonly SemaphoreSlim _gate = new(1, 1);

    public string ProviderName => "NvApi";

    public Task<DisplayState> GetCurrentStateAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        var grid = SelectGrid(_native.EnumerateGrids());
        return Task.FromResult(ToState(grid, true));
    }

    public Task<DisplayValidationResult> ValidateProfileAsync(DisplayProfile profile, CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        try
        {
            var proposed = BuildProposedGrid(profile);
            var validation = _native.Validate(proposed);
            return Task.FromResult(validation.IsValid
                ? new DisplayValidationResult(true)
                : new DisplayValidationResult(false, $"nvapi_validation_0x{validation.ErrorFlags:X}", $"O NVAPI rejeitou o perfil. Avisos: 0x{validation.WarningFlags:X}."));
        }
        catch (Exception exception) when (exception is InvalidOperationException or ArgumentException)
        {
            return Task.FromResult(new DisplayValidationResult(false, "nvapi_validation_failed", exception.Message));
        }
    }

    public async Task<DisplayApplyResult> ApplyProfileAsync(DisplayProfile profile, CancellationToken cancellationToken)
    {
        await _gate.WaitAsync(cancellationToken);
        try
        {
            var snapshot = await CaptureSnapshotAsync(cancellationToken);
            var validation = await ValidateProfileAsync(profile, cancellationToken);
            if (!validation.IsValid)
            {
                return new DisplayApplyResult(false, snapshot.State, ErrorCode: validation.ErrorCode, Message: validation.Message);
            }

            _native.Apply(BuildProposedGrid(profile));
            var resultingState = await GetCurrentStateAsync(cancellationToken);
            if (resultingState.TotalWidth == profile.TotalWidth && resultingState.TotalHeight == profile.TotalHeight && resultingState.Rows == profile.Rows && resultingState.Columns == profile.Columns)
            {
                return new DisplayApplyResult(true, resultingState);
            }

            var rollback = await RestoreSnapshotCoreAsync(snapshot, cancellationToken);
            return new DisplayApplyResult(false, rollback.State, true, rollback.Succeeded, "nvapi_result_mismatch", "O estado resultante divergiu do perfil solicitado; o rollback foi executado.");
        }
        catch (Exception exception) when (exception is InvalidOperationException or ArgumentException)
        {
            var state = await GetCurrentStateAsync(cancellationToken);
            return new DisplayApplyResult(false, state, ErrorCode: "nvapi_apply_failed", Message: exception.Message);
        }
        finally
        {
            _gate.Release();
        }
    }

    public Task<DisplaySnapshot> CaptureSnapshotAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        var grid = SelectGrid(_native.EnumerateGrids());
        return Task.FromResult(new DisplaySnapshot(ToState(grid, true), JsonSerializer.Serialize(grid), DateTimeOffset.UtcNow));
    }

    public async Task<DisplayApplyResult> RestoreSnapshotAsync(DisplaySnapshot snapshot, CancellationToken cancellationToken)
    {
        await _gate.WaitAsync(cancellationToken);
        try
        {
            return await RestoreSnapshotCoreAsync(snapshot, cancellationToken);
        }
        finally
        {
            _gate.Release();
        }
    }

    public Task<DisplayApplyResult> EnableCurrentAsync(bool enabled, CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        _native.Enable(enabled);
        var grid = SelectGrid(_native.EnumerateGrids());
        return Task.FromResult(new DisplayApplyResult(true, ToState(grid, enabled)));
    }

    public void Dispose() => _native.Dispose();

    private Task<DisplayApplyResult> RestoreSnapshotCoreAsync(DisplaySnapshot snapshot, CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        if (string.IsNullOrWhiteSpace(snapshot.NativePayload))
        {
            return Task.FromResult(new DisplayApplyResult(false, snapshot.State, true, false, "snapshot_missing_native_payload", "O snapshot não contém a topologia NVAPI original."));
        }

        var grid = JsonSerializer.Deserialize<NvGrid>(snapshot.NativePayload) ?? throw new InvalidOperationException("Snapshot NVAPI inválido.");
        _native.Apply(grid);
        var state = ToState(SelectGrid(_native.EnumerateGrids()), snapshot.State.Enabled);
        var succeeded = state.Rows == snapshot.State.Rows && state.Columns == snapshot.State.Columns && state.TotalWidth == snapshot.State.TotalWidth && state.TotalHeight == snapshot.State.TotalHeight;
        return Task.FromResult(new DisplayApplyResult(succeeded, state, true, succeeded, succeeded ? null : "rollback_mismatch", succeeded ? null : "O estado restaurado diverge do snapshot."));
    }

    private NvGrid BuildProposedGrid(DisplayProfile profile)
    {
        if (profile.Rows is < 1 or > 8 || profile.Columns is < 1 or > 8 || profile.Rows * profile.Columns > 16)
        {
            throw new ArgumentException("A topologia excede os limites suportados pelo NVAPI.", nameof(profile));
        }

        var current = SelectGrid(_native.EnumerateGrids());
        var count = profile.Rows * profile.Columns;
        if (current.Displays.Count < count)
        {
            throw new InvalidOperationException($"O perfil exige {count} telas, mas apenas {current.Displays.Count} foram enumeradas.");
        }

        var displays = current.Displays.Take(count)
            .Select(display => display with { OverlapX = profile.OverlapX, OverlapY = profile.OverlapY })
            .ToArray();
        return new NvGrid(profile.Rows, profile.Columns, current.Flags, displays, profile.DisplayWidth, profile.DisplayHeight, (int)Math.Round(profile.RefreshRate));
    }

    private static NvGrid SelectGrid(IReadOnlyList<NvGrid> grids) => grids.OrderByDescending(grid => grid.Displays.Count).FirstOrDefault()
        ?? throw new InvalidOperationException("Nenhuma grade de display ativa foi retornada pelo NVAPI.");

    private static DisplayState ToState(NvGrid grid, bool enabled)
    {
        var overlapX = grid.Displays.Count == 0 ? 0 : grid.Displays[0].OverlapX;
        var overlapY = grid.Displays.Count == 0 ? 0 : grid.Displays[0].OverlapY;
        var displays = grid.Displays.Select((display, index) => new DisplayInfo(index + 1, display.DisplayId.ToString("X8"), display.Rotation)).ToArray();
        return new DisplayState(enabled, grid.Rows, grid.Columns, grid.Width, grid.Height,
            grid.Width * grid.Columns - overlapX * Math.Max(0, grid.Columns - 1),
            grid.Height * grid.Rows - overlapY * Math.Max(0, grid.Rows - 1),
            grid.RefreshRate, overlapX, overlapY, displays);
    }
}
