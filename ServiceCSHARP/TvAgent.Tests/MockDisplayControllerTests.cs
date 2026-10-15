using TvAgent.Domain;
using TvAgent.NvApi;

namespace TvAgent.Tests;

public sealed class MockDisplayControllerTests
{
    [Fact]
    public async Task ApplyProfileProducesExpectedVideowallState()
    {
        var controller = CreateController();
        var profile = new DisplayProfile(3, 2, 2, 1920, 1080, 3840, 2160, 60, 0, 0);

        var result = await controller.ApplyProfileAsync(profile, CancellationToken.None);

        Assert.True(result.Succeeded);
        Assert.Equal(3840, result.State.TotalWidth);
        Assert.Equal(2160, result.State.TotalHeight);
        Assert.Equal(4, result.State.Displays.Count);
    }

    [Fact]
    public async Task InvalidProfileFailureModeDoesNotChangeState()
    {
        var controller = CreateController("InvalidProfile");
        var before = await controller.GetCurrentStateAsync(CancellationToken.None);
        var profile = new DisplayProfile(3, 2, 2, 1920, 1080, 3840, 2160, 60, 0, 0);

        var result = await controller.ApplyProfileAsync(profile, CancellationToken.None);
        var after = await controller.GetCurrentStateAsync(CancellationToken.None);

        Assert.False(result.Succeeded);
        Assert.Equal("mock_invalid_profile", result.ErrorCode);
        Assert.Equal(before, after);
    }

    [Fact]
    public async Task SnapshotRestoresPreviousState()
    {
        var controller = CreateController();
        var snapshot = await controller.CaptureSnapshotAsync(CancellationToken.None);
        var profile = new DisplayProfile(3, 2, 2, 1920, 1080, 3840, 2160, 60, 0, 0);
        await controller.ApplyProfileAsync(profile, CancellationToken.None);

        var restored = await controller.RestoreSnapshotAsync(snapshot, CancellationToken.None);

        Assert.True(restored.Succeeded);
        Assert.Equal(snapshot.State, restored.State);
    }

    private static MockDisplayController CreateController(string failureMode = "None") => new(new DisplayOptions
    {
        Mock = new MockDisplayOptions
        {
            Rows = 1,
            Columns = 4,
            DisplayWidth = 1920,
            DisplayHeight = 1080,
            SimulatedApplyDelayMilliseconds = 1,
            FailureMode = failureMode,
        },
    });
}
