using TvAgent.Domain;

namespace TvAgent.Commands;

public sealed class VlcCommandHandler : ICommandHandler
{
    private readonly IVlcManager _vlc;

    public VlcCommandHandler(string commandType, IVlcManager vlc)
    {
        CommandType = commandType;
        _vlc = vlc;
    }

    public string CommandType { get; }

    public async Task<CommandResult> ExecuteAsync(DeviceCommand command, CancellationToken cancellationToken)
    {
        return CommandType switch
        {
            "vlc.status" => CommandResult.Success(await _vlc.GetStatusAsync(cancellationToken)),
            "vlc.start" => CommandResult.Success(await _vlc.StartAsync(null, cancellationToken)),
            "vlc.stop" => await StopAsync(cancellationToken),
            "vlc.restart" or "vlc.reload_playlist" => CommandResult.Success(await _vlc.RestartAsync(cancellationToken)),
            _ => CommandResult.Failure("unsupported_vlc_command", $"Comando VLC desconhecido: {CommandType}"),
        };
    }

    private async Task<CommandResult> StopAsync(CancellationToken cancellationToken)
    {
        await _vlc.StopAsync(cancellationToken);
        return CommandResult.Success(await _vlc.GetStatusAsync(cancellationToken));
    }
}
