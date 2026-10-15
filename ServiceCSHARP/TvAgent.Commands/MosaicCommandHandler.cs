using System.Text.Json;
using TvAgent.Domain;

namespace TvAgent.Commands;

public sealed class MosaicCommandHandler : ICommandHandler
{
    private readonly IDisplayController _display;
    private readonly IVlcManager _vlc;
    private readonly AgentOptions _options;

    public MosaicCommandHandler(string commandType, IDisplayController display, IVlcManager vlc, AgentOptions options)
    {
        CommandType = commandType;
        _display = display;
        _vlc = vlc;
        _options = options;
    }

    public string CommandType { get; }

    public async Task<CommandResult> ExecuteAsync(DeviceCommand command, CancellationToken cancellationToken)
    {
        return CommandType switch
        {
            "mosaic.inspect" => CommandResult.Success(await _display.GetCurrentStateAsync(cancellationToken)),
            "mosaic.validate_profile" => await ValidateAsync(command, cancellationToken),
            "mosaic.apply_profile" => await ApplyAsync(command, cancellationToken),
            "mosaic.restore_snapshot" => await RestoreAsync(cancellationToken),
            "mosaic.enable_current" => CommandResult.Success(await _display.EnableCurrentAsync(true, cancellationToken)),
            "mosaic.disable_current" => CommandResult.Success(await _display.EnableCurrentAsync(false, cancellationToken)),
            _ => CommandResult.Failure("unsupported_mosaic_command", $"Comando Mosaic desconhecido: {CommandType}"),
        };
    }

    private async Task<CommandResult> ValidateAsync(DeviceCommand command, CancellationToken cancellationToken)
    {
        var profile = DeserializeProfile(command);
        var validation = await _display.ValidateProfileAsync(profile, cancellationToken);
        return validation.IsValid ? CommandResult.Success(validation) : CommandResult.Failure(validation.ErrorCode ?? "invalid_profile", validation.Message ?? "Perfil inválido.", validation);
    }

    private async Task<CommandResult> ApplyAsync(DeviceCommand command, CancellationToken cancellationToken)
    {
        var profile = DeserializeProfile(command);
        var validation = await _display.ValidateProfileAsync(profile, cancellationToken);
        if (!validation.IsValid)
        {
            return CommandResult.Failure(validation.ErrorCode ?? "invalid_profile", validation.Message ?? "Perfil inválido.");
        }

        var snapshot = await _display.CaptureSnapshotAsync(cancellationToken);
        await SaveSnapshotAsync(snapshot, cancellationToken);
        var vlcBefore = await _vlc.GetStatusAsync(cancellationToken);
        if (vlcBefore.Running)
        {
            await _vlc.StopAsync(cancellationToken);
        }

        var apply = await _display.ApplyProfileAsync(profile, cancellationToken);
        if (!apply.Succeeded || apply.State.TotalWidth != profile.TotalWidth || apply.State.TotalHeight != profile.TotalHeight)
        {
            var rollback = apply.RollbackAttempted
                ? apply
                : await _display.RestoreSnapshotAsync(snapshot, cancellationToken);
            if (vlcBefore.Running)
            {
                await _vlc.StartAsync(vlcBefore.ActiveMedia, cancellationToken);
            }

            return CommandResult.Failure(apply.ErrorCode ?? "mosaic_result_mismatch", apply.Message ?? "O perfil aplicado diverge do esperado.", new { apply, rollback });
        }

        if (vlcBefore.Running)
        {
            await _vlc.StartAsync(vlcBefore.ActiveMedia, cancellationToken);
        }

        return CommandResult.Success(apply);
    }

    private async Task<CommandResult> RestoreAsync(CancellationToken cancellationToken)
    {
        if (!File.Exists(SnapshotPath))
        {
            return CommandResult.Failure("snapshot_not_found", "Não existe snapshot local para restaurar.");
        }

        var json = await File.ReadAllTextAsync(SnapshotPath, cancellationToken);
        var snapshot = JsonSerializer.Deserialize<DisplaySnapshot>(json) ?? throw new InvalidDataException("Snapshot local inválido.");
        var result = await _display.RestoreSnapshotAsync(snapshot, cancellationToken);
        return result.Succeeded ? CommandResult.Success(result) : CommandResult.Failure(result.ErrorCode ?? "rollback_failed", result.Message ?? "Falha ao restaurar o snapshot.", result);
    }

    private async Task SaveSnapshotAsync(DisplaySnapshot snapshot, CancellationToken cancellationToken)
    {
        Directory.CreateDirectory(Path.GetDirectoryName(SnapshotPath)!);
        var temporaryPath = SnapshotPath + ".tmp";
        await File.WriteAllTextAsync(temporaryPath, JsonSerializer.Serialize(snapshot), cancellationToken);
        File.Move(temporaryPath, SnapshotPath, true);
    }

    private DisplayProfile DeserializeProfile(DeviceCommand command) => JsonSerializer.Deserialize<DisplayProfile>(command.Payload.GetRawText())
        ?? throw new InvalidDataException("Payload do perfil Mosaic inválido.");

    private string SnapshotPath => Path.Combine(_options.WorkingDirectory, "snapshots", "latest-mosaic.json");
}
