using TvAgent.Domain;

namespace TvAgent.Service;

public sealed class AgentWorker : BackgroundService
{
    private readonly IAgentApiClient _api;
    private readonly ICommandDispatcher _dispatcher;
    private readonly IDisplayController _display;
    private readonly IVlcManager _vlc;
    private readonly IdempotencyStore _idempotency;
    private readonly AgentOptions _options;
    private readonly ILogger<AgentWorker> _logger;
    private DateTimeOffset _nextHeartbeat = DateTimeOffset.MinValue;
    private Guid? _lastCommandId;

    public AgentWorker(IAgentApiClient api, ICommandDispatcher dispatcher, IDisplayController display, IVlcManager vlc, IdempotencyStore idempotency, AgentOptions options, ILogger<AgentWorker> logger)
    {
        _api = api;
        _dispatcher = dispatcher;
        _display = display;
        _vlc = vlc;
        _idempotency = idempotency;
        _options = options;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        Directory.CreateDirectory(_options.WorkingDirectory);
        _logger.LogInformation("TV Agent iniciado. Dispositivo {DeviceId}, provedor {Provider}", _options.DeviceId, _display.ProviderName);

        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                await _api.EnsureRegisteredAsync(stoppingToken);
                if (DateTimeOffset.UtcNow >= _nextHeartbeat)
                {
                    await SendTelemetryAsync(stoppingToken);
                    _nextHeartbeat = DateTimeOffset.UtcNow.AddSeconds(Math.Max(5, _options.HeartbeatSeconds));
                }

                var command = await _api.GetNextCommandAsync(stoppingToken);
                if (command is not null)
                {
                    await ProcessCommandAsync(command, stoppingToken);
                }
            }
            catch (OperationCanceledException) when (stoppingToken.IsCancellationRequested)
            {
                break;
            }
            catch (HttpRequestException exception)
            {
                _logger.LogWarning("API indisponível; a campanha atual continuará ativa. {Message}", exception.Message);
            }
            catch (Exception exception)
            {
                _logger.LogError(exception, "Falha não tratada no ciclo do agente.");
            }

            await Task.Delay(TimeSpan.FromSeconds(Math.Max(1, _options.CommandPollingSeconds)), stoppingToken);
        }
    }

    private async Task SendTelemetryAsync(CancellationToken cancellationToken)
    {
        var displayState = await _display.GetCurrentStateAsync(cancellationToken);
        var vlcState = await _vlc.GetStatusAsync(cancellationToken);
        await _api.SendDisplayStateAsync(displayState, cancellationToken);
        await _api.SendHeartbeatAsync(displayState, vlcState, _lastCommandId, cancellationToken);
    }

    private async Task ProcessCommandAsync(DeviceCommand command, CancellationToken cancellationToken)
    {
        _lastCommandId = command.Id;
        if (await _idempotency.ContainsAsync(command.IdempotencyKey, cancellationToken))
        {
            _logger.LogInformation("Comando duplicado {CommandId} ignorado por idempotência.", command.Id);
            await _api.MarkCompletedAsync(command.Id, new { duplicate = true, recovered_from_local_state = true }, cancellationToken);
            return;
        }

        await _api.MarkStartedAsync(command.Id, cancellationToken);
        CommandResult result;
        try
        {
            result = await _dispatcher.DispatchAsync(command, cancellationToken);
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception exception)
        {
            _logger.LogError(exception, "Falha ao executar comando {CommandId} ({CommandType}).", command.Id, command.Type);
            result = CommandResult.Failure("handler_exception", exception.Message);
        }

        if (result.Succeeded)
        {
            await _idempotency.AddAsync(command.IdempotencyKey, cancellationToken);
            await _api.MarkCompletedAsync(command.Id, result.Data, cancellationToken);
            _logger.LogInformation("Comando {CommandId} ({CommandType}) concluído.", command.Id, command.Type);
        }
        else
        {
            await _api.MarkFailedAsync(command.Id, result.ErrorCode ?? "command_failed", result.ErrorMessage ?? "Falha sem detalhes.", result.Data, cancellationToken);
            _logger.LogWarning("Comando {CommandId} ({CommandType}) falhou: {ErrorCode}", command.Id, command.Type, result.ErrorCode);
        }
    }
}
