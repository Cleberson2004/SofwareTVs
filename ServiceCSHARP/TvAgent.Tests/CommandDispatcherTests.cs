using System.Text.Json;
using TvAgent.Commands;
using TvAgent.Domain;

namespace TvAgent.Tests;

public sealed class CommandDispatcherTests
{
    [Fact]
    public async Task UnknownCommandIsRejectedWithoutExecution()
    {
        var dispatcher = new CommandDispatcher([]);
        var command = new DeviceCommand(Guid.NewGuid(), "system.shell", "device", "key", null, JsonDocument.Parse("{}").RootElement.Clone());

        var result = await dispatcher.DispatchAsync(command, CancellationToken.None);

        Assert.False(result.Succeeded);
        Assert.Equal("unsupported_command", result.ErrorCode);
    }

    [Fact]
    public async Task ExpiredCommandIsRejectedBeforeHandler()
    {
        var handler = new RecordingHandler();
        var dispatcher = new CommandDispatcher([handler]);
        var command = new DeviceCommand(Guid.NewGuid(), handler.CommandType, "device", "key", DateTimeOffset.UtcNow.AddMinutes(-1), JsonDocument.Parse("{}").RootElement.Clone());

        var result = await dispatcher.DispatchAsync(command, CancellationToken.None);

        Assert.False(result.Succeeded);
        Assert.Equal("command_expired", result.ErrorCode);
        Assert.False(handler.Executed);
    }

    private sealed class RecordingHandler : ICommandHandler
    {
        public string CommandType => "vlc.status";
        public bool Executed { get; private set; }

        public Task<CommandResult> ExecuteAsync(DeviceCommand command, CancellationToken cancellationToken)
        {
            Executed = true;
            return Task.FromResult(CommandResult.Success());
        }
    }
}
