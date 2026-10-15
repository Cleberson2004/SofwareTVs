using TvAgent.Domain;

namespace TvAgent.Commands;

public sealed class CampaignActivateHandler : ICommandHandler
{
    private readonly IVlcManager _vlc;

    public CampaignActivateHandler(IVlcManager vlc) => _vlc = vlc;

    public string CommandType => "campaign.activate";

    public async Task<CommandResult> ExecuteAsync(DeviceCommand command, CancellationToken cancellationToken)
    {
        var path = command.Payload.GetProperty("path").GetString();
        if (string.IsNullOrWhiteSpace(path) || !File.Exists(path))
        {
            return CommandResult.Failure("media_not_found", "A mídia solicitada para ativação não existe na área ativa.");
        }

        return CommandResult.Success(await _vlc.ActivateAsync(path, cancellationToken));
    }
}
