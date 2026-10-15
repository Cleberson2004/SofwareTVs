namespace TvAgent.Domain;

public interface IDisplayController
{
    string ProviderName { get; }
    Task<DisplayState> GetCurrentStateAsync(CancellationToken cancellationToken);
    Task<DisplayValidationResult> ValidateProfileAsync(DisplayProfile profile, CancellationToken cancellationToken);
    Task<DisplayApplyResult> ApplyProfileAsync(DisplayProfile profile, CancellationToken cancellationToken);
    Task<DisplaySnapshot> CaptureSnapshotAsync(CancellationToken cancellationToken);
    Task<DisplayApplyResult> RestoreSnapshotAsync(DisplaySnapshot snapshot, CancellationToken cancellationToken);
    Task<DisplayApplyResult> EnableCurrentAsync(bool enabled, CancellationToken cancellationToken);
}

public interface IVlcManager
{
    Task<VlcState> GetStatusAsync(CancellationToken cancellationToken);
    Task<VlcState> StartAsync(string? mediaPath, CancellationToken cancellationToken);
    Task StopAsync(CancellationToken cancellationToken);
    Task<VlcState> RestartAsync(CancellationToken cancellationToken);
    Task<VlcState> ActivateAsync(string mediaPath, CancellationToken cancellationToken);
}

public interface IAgentApiClient
{
    Task EnsureRegisteredAsync(CancellationToken cancellationToken);
    Task SendHeartbeatAsync(DisplayState displayState, VlcState vlcState, Guid? lastCommandId, CancellationToken cancellationToken);
    Task SendDisplayStateAsync(DisplayState displayState, CancellationToken cancellationToken);
    Task<DeviceCommand?> GetNextCommandAsync(CancellationToken cancellationToken);
    Task MarkStartedAsync(Guid commandId, CancellationToken cancellationToken);
    Task MarkCompletedAsync(Guid commandId, object? result, CancellationToken cancellationToken);
    Task MarkFailedAsync(Guid commandId, string errorCode, string errorMessage, object? result, CancellationToken cancellationToken);
    Task DownloadAsync(string path, string destinationPath, CancellationToken cancellationToken);
}

public interface ICommandHandler
{
    string CommandType { get; }
    Task<CommandResult> ExecuteAsync(DeviceCommand command, CancellationToken cancellationToken);
}

public interface ICommandDispatcher
{
    Task<CommandResult> DispatchAsync(DeviceCommand command, CancellationToken cancellationToken);
}

public interface ICredentialStore
{
    Task<string?> ReadAsync(CancellationToken cancellationToken);
    Task WriteAsync(string value, CancellationToken cancellationToken);
}
