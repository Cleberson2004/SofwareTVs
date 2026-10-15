using System.Security.Cryptography;
using TvAgent.Domain;
using TvAgent.SessionHost;

namespace TvAgent.Commands;

public sealed class CampaignDownloadHandler : ICommandHandler
{
    private readonly IAgentApiClient _api;
    private readonly IVlcManager _vlc;
    private readonly AgentOptions _options;

    public CampaignDownloadHandler(IAgentApiClient api, IVlcManager vlc, AgentOptions options)
    {
        _api = api;
        _vlc = vlc;
        _options = options;
    }

    public string CommandType => "campaign.download";

    public async Task<CommandResult> ExecuteAsync(DeviceCommand command, CancellationToken cancellationToken)
    {
        var payload = command.Payload;
        var downloadPath = payload.GetProperty("download_path").GetString() ?? throw new InvalidDataException("download_path ausente.");
        var expectedHash = payload.GetProperty("sha256").GetString() ?? throw new InvalidDataException("sha256 ausente.");
        var expectedWidth = payload.GetProperty("width").GetInt32();
        var expectedHeight = payload.GetProperty("height").GetInt32();
        var filename = Path.GetFileName(payload.GetProperty("filename").GetString() ?? "campaign.png");
        var activate = !payload.TryGetProperty("activate", out var activateElement) || activateElement.GetBoolean();
        var activeDirectory = Path.Combine(_options.WorkingDirectory, "active");
        var stagingDirectory = Path.Combine(_options.WorkingDirectory, "staging");
        Directory.CreateDirectory(activeDirectory);
        Directory.CreateDirectory(stagingDirectory);
        var temporaryPath = Path.Combine(stagingDirectory, $"{command.Id:N}.download");
        var activePath = Path.Combine(activeDirectory, filename);
        var backupPath = Path.Combine(activeDirectory, "previous.png");

        try
        {
            await _api.DownloadAsync(downloadPath, temporaryPath, cancellationToken);
            var hash = await CalculateSha256Async(temporaryPath, cancellationToken);
            if (!hash.Equals(expectedHash, StringComparison.OrdinalIgnoreCase))
            {
                return CommandResult.Failure("hash_mismatch", "O SHA-256 do download diverge do valor publicado.", new { expectedHash, actualHash = hash });
            }

            var dimensions = ImageDimensions.ReadPng(temporaryPath);
            if (dimensions.Width != expectedWidth || dimensions.Height != expectedHeight)
            {
                return CommandResult.Failure("dimension_mismatch", "As dimensões do arquivo baixado divergem da campanha.", new { expectedWidth, expectedHeight, actualWidth = dimensions.Width, actualHeight = dimensions.Height });
            }

            if (File.Exists(activePath))
            {
                File.Replace(temporaryPath, activePath, backupPath, true);
            }
            else
            {
                File.Move(temporaryPath, activePath);
            }

            var vlcState = activate ? await _vlc.ActivateAsync(activePath, cancellationToken) : await _vlc.GetStatusAsync(cancellationToken);
            return CommandResult.Success(new { activePath, sha256 = hash, dimensions.Width, dimensions.Height, vlc = vlcState });
        }
        finally
        {
            if (File.Exists(temporaryPath))
            {
                File.Delete(temporaryPath);
            }
        }
    }

    private static async Task<string> CalculateSha256Async(string path, CancellationToken cancellationToken)
    {
        await using var stream = File.OpenRead(path);
        var hash = await SHA256.HashDataAsync(stream, cancellationToken);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }
}
