using TvAgent.Domain;

namespace TvAgent.Commands;

public sealed class CommandDispatcher : ICommandDispatcher
{
    private readonly IReadOnlyDictionary<string, ICommandHandler> _handlers;

    public CommandDispatcher(IEnumerable<ICommandHandler> handlers)
    {
        _handlers = handlers.ToDictionary(handler => handler.CommandType, StringComparer.Ordinal);
    }

    public Task<CommandResult> DispatchAsync(DeviceCommand command, CancellationToken cancellationToken)
    {
        if (!_handlers.TryGetValue(command.Type, out var handler))
        {
            return Task.FromResult(CommandResult.Failure("unsupported_command", $"O comando '{command.Type}' não está na lista permitida do agente."));
        }

        if (command.ExpiresAt is not null && command.ExpiresAt <= DateTimeOffset.UtcNow)
        {
            return Task.FromResult(CommandResult.Failure("command_expired", "O comando expirou antes da execução."));
        }

        return handler.ExecuteAsync(command, cancellationToken);
    }
}
