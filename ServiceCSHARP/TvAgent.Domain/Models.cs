using System.Text.Json;
using System.Text.Json.Serialization;

namespace TvAgent.Domain;

public sealed record DisplayProfile(
    [property: JsonPropertyName("profile_id")] long? ProfileId,
    [property: JsonPropertyName("rows")] int Rows,
    [property: JsonPropertyName("columns")] int Columns,
    [property: JsonPropertyName("display_width")] int DisplayWidth,
    [property: JsonPropertyName("display_height")] int DisplayHeight,
    [property: JsonPropertyName("total_width")] int TotalWidth,
    [property: JsonPropertyName("total_height")] int TotalHeight,
    [property: JsonPropertyName("refresh_rate")] double RefreshRate,
    [property: JsonPropertyName("overlap_x")] int OverlapX,
    [property: JsonPropertyName("overlap_y")] int OverlapY);

public sealed record DisplayInfo(
    [property: JsonPropertyName("position")] int Position,
    [property: JsonPropertyName("display_id")] string DisplayId,
    [property: JsonPropertyName("rotation")] int Rotation);

public sealed record DisplayState(
    [property: JsonPropertyName("enabled")] bool Enabled,
    [property: JsonPropertyName("rows")] int Rows,
    [property: JsonPropertyName("columns")] int Columns,
    [property: JsonPropertyName("display_width")] int DisplayWidth,
    [property: JsonPropertyName("display_height")] int DisplayHeight,
    [property: JsonPropertyName("total_width")] int TotalWidth,
    [property: JsonPropertyName("total_height")] int TotalHeight,
    [property: JsonPropertyName("refresh_rate")] double RefreshRate,
    [property: JsonPropertyName("overlap_x")] int OverlapX,
    [property: JsonPropertyName("overlap_y")] int OverlapY,
    [property: JsonPropertyName("displays")] IReadOnlyList<DisplayInfo> Displays);

public sealed record DisplayValidationResult(bool IsValid, string? ErrorCode = null, string? Message = null);
public sealed record DisplayApplyResult(bool Succeeded, DisplayState State, bool RollbackAttempted = false, bool RollbackSucceeded = false, string? ErrorCode = null, string? Message = null);
public sealed record DisplaySnapshot(DisplayState State, string? NativePayload = null, DateTimeOffset CapturedAt = default);

public sealed record DeviceCommand(
    [property: JsonPropertyName("id")] Guid Id,
    [property: JsonPropertyName("type")] string Type,
    [property: JsonPropertyName("device_id")] string DeviceId,
    [property: JsonPropertyName("idempotency_key")] string IdempotencyKey,
    [property: JsonPropertyName("expires_at")] DateTimeOffset? ExpiresAt,
    [property: JsonPropertyName("payload")] JsonElement Payload);

public sealed record CommandResult(bool Succeeded, object? Data = null, string? ErrorCode = null, string? ErrorMessage = null)
{
    public static CommandResult Success(object? data = null) => new(true, data);
    public static CommandResult Failure(string code, string message, object? data = null) => new(false, data, code, message);
}

public sealed record VlcState(bool Running, int? Pid, string? ActiveMedia, bool Responding);

public sealed class AgentOptions
{
    public string DeviceId { get; set; } = Guid.NewGuid().ToString();
    public string DeviceName { get; set; } = Environment.MachineName;
    public string ApiBaseUrl { get; set; } = "http://localhost:8000/api/v1/agent/";
    public string EnrollmentKey { get; set; } = "change-this-enrollment-key";
    public int HeartbeatSeconds { get; set; } = 30;
    public int CommandPollingSeconds { get; set; } = 5;
    public string WorkingDirectory { get; set; } = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "TvAgent");
    public bool AllowComputerRestart { get; set; }
}

public sealed class DisplayOptions
{
    public string Provider { get; set; } = "Mock";
    public MockDisplayOptions Mock { get; set; } = new();
}

public sealed class MockDisplayOptions
{
    public bool Enabled { get; set; } = true;
    public int Rows { get; set; } = 1;
    public int Columns { get; set; } = 4;
    public int DisplayWidth { get; set; } = 1920;
    public int DisplayHeight { get; set; } = 1080;
    public double RefreshRate { get; set; } = 60;
    public int OverlapX { get; set; }
    public int OverlapY { get; set; }
    public int SimulatedApplyDelayMilliseconds { get; set; } = 250;
    public string FailureMode { get; set; } = "None";
}

public sealed class VlcOptions
{
    public string Path { get; set; } = @"C:\Program Files\VideoLAN\VLC\vlc.exe";
    public bool TestMode { get; set; } = true;
    public bool Fullscreen { get; set; }
    public int WindowWidth { get; set; } = 1280;
    public int WindowHeight { get; set; } = 720;
}
