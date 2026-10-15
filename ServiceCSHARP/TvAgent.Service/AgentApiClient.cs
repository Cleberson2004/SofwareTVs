using System.Diagnostics;
using System.Net;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Text.Json;
using TvAgent.Domain;

namespace TvAgent.Service;

public sealed class AgentApiClient : IAgentApiClient
{
    private static readonly JsonSerializerOptions JsonOptions = new(JsonSerializerDefaults.Web)
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
    };
    private readonly HttpClient _http;
    private readonly ICredentialStore _credentials;
    private readonly AgentOptions _options;
    private readonly IDisplayController _display;

    public AgentApiClient(HttpClient http, ICredentialStore credentials, AgentOptions options, IDisplayController display)
    {
        _http = http;
        _credentials = credentials;
        _options = options;
        _display = display;
    }

    public async Task EnsureRegisteredAsync(CancellationToken cancellationToken)
    {
        var credential = await _credentials.ReadAsync(cancellationToken);
        if (!string.IsNullOrWhiteSpace(credential))
        {
            _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", credential);
            return;
        }

        using var request = new HttpRequestMessage(HttpMethod.Post, "register");
        request.Headers.Add("X-Enrollment-Key", _options.EnrollmentKey);
        request.Content = JsonContent.Create(new
        {
            device_uuid = _options.DeviceId,
            name = _options.DeviceName,
            agent_version = Version,
            windows_version = RuntimeInformation.OSDescription,
            gpu_name = (string?)null,
            driver_version = (string?)null,
            display_provider = _display.ProviderName,
            simulation_mode = _display.ProviderName == "Mock",
        }, options: JsonOptions);
        using var response = await _http.SendAsync(request, cancellationToken);
        response.EnsureSuccessStatusCode();
        var registration = await response.Content.ReadFromJsonAsync<RegistrationResponse>(JsonOptions, cancellationToken)
            ?? throw new InvalidDataException("A API retornou um registro vazio.");
        await _credentials.WriteAsync(registration.Token, cancellationToken);
        _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", registration.Token);
    }

    public async Task SendHeartbeatAsync(DisplayState displayState, VlcState vlcState, Guid? lastCommandId, CancellationToken cancellationToken)
    {
        var process = Process.GetCurrentProcess();
        var drive = new DriveInfo(Path.GetPathRoot(_options.WorkingDirectory)!);
        var payload = new
        {
            agent_version = Version,
            windows_version = RuntimeInformation.OSDescription,
            gpu_name = (string?)null,
            driver_version = (string?)null,
            display_provider = _display.ProviderName,
            simulation_mode = _display.ProviderName == "Mock",
            vlc_status = vlcState,
            mosaic_status = displayState,
            desktop_width = displayState.TotalWidth,
            desktop_height = displayState.TotalHeight,
            screens_detected = displayState.Displays.Count,
            cpu_usage = (double?)null,
            memory_usage = (double?)null,
            free_disk_space = drive.AvailableFreeSpace,
            last_command_id = lastCommandId,
            diagnostics = new { process_id = process.Id, process_memory_bytes = process.WorkingSet64, uptime_seconds = Environment.TickCount64 / 1000 },
        };
        using var response = await _http.PostAsJsonAsync("heartbeat", payload, JsonOptions, cancellationToken);
        response.EnsureSuccessStatusCode();
    }

    public async Task SendDisplayStateAsync(DisplayState displayState, CancellationToken cancellationToken)
    {
        using var response = await _http.PostAsJsonAsync("display-state", displayState, JsonOptions, cancellationToken);
        response.EnsureSuccessStatusCode();
    }

    public async Task<DeviceCommand?> GetNextCommandAsync(CancellationToken cancellationToken)
    {
        using var response = await _http.GetAsync("commands/next", cancellationToken);
        if (response.StatusCode == HttpStatusCode.NoContent)
        {
            return null;
        }

        response.EnsureSuccessStatusCode();
        return await response.Content.ReadFromJsonAsync<DeviceCommand>(JsonOptions, cancellationToken);
    }

    public Task MarkStartedAsync(Guid commandId, CancellationToken cancellationToken) => PostStatusAsync($"commands/{commandId}/started", null, cancellationToken);
    public Task MarkCompletedAsync(Guid commandId, object? result, CancellationToken cancellationToken) => PostStatusAsync($"commands/{commandId}/completed", new { result }, cancellationToken);
    public Task MarkFailedAsync(Guid commandId, string errorCode, string errorMessage, object? result, CancellationToken cancellationToken) => PostStatusAsync($"commands/{commandId}/failed", new { error_code = errorCode, error_message = errorMessage, result }, cancellationToken);

    public async Task DownloadAsync(string path, string destinationPath, CancellationToken cancellationToken)
    {
        var authority = new Uri(_http.BaseAddress!.GetLeftPart(UriPartial.Authority));
        var uri = Uri.TryCreate(path, UriKind.Absolute, out var absolute) ? absolute : new Uri(authority, path);
        using var response = await _http.GetAsync(uri, HttpCompletionOption.ResponseHeadersRead, cancellationToken);
        response.EnsureSuccessStatusCode();
        await using var source = await response.Content.ReadAsStreamAsync(cancellationToken);
        await using var destination = new FileStream(destinationPath, FileMode.Create, FileAccess.Write, FileShare.None, 81920, FileOptions.Asynchronous | FileOptions.WriteThrough);
        await source.CopyToAsync(destination, cancellationToken);
        await destination.FlushAsync(cancellationToken);
    }

    private async Task PostStatusAsync(string path, object? payload, CancellationToken cancellationToken)
    {
        using var response = await _http.PostAsJsonAsync(path, payload ?? new { }, JsonOptions, cancellationToken);
        response.EnsureSuccessStatusCode();
    }

    private static string Version => Assembly.GetExecutingAssembly().GetName().Version?.ToString(3) ?? "1.0.0";
    private sealed record RegistrationResponse(string DeviceId, string Token);
}
