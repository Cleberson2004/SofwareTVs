using System.Diagnostics;
using System.Runtime.InteropServices;
using TvAgent.Domain;

namespace TvAgent.Commands;

public sealed class DeviceCommandHandler : ICommandHandler
{
    private readonly AgentOptions _options;

    public DeviceCommandHandler(string commandType, AgentOptions options)
    {
        CommandType = commandType;
        _options = options;
    }

    public string CommandType { get; }

    public Task<CommandResult> ExecuteAsync(DeviceCommand command, CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        return CommandType switch
        {
            "device.collect_diagnostics" => Task.FromResult(CommandResult.Success(new
            {
                machineName = Environment.MachineName,
                os = RuntimeInformation.OSDescription,
                architecture = RuntimeInformation.OSArchitecture.ToString(),
                processArchitecture = RuntimeInformation.ProcessArchitecture.ToString(),
                uptimeSeconds = Environment.TickCount64 / 1000,
                workingSetBytes = Environment.WorkingSet,
                freeDiskSpace = new DriveInfo(Path.GetPathRoot(_options.WorkingDirectory)!).AvailableFreeSpace,
                utc = DateTimeOffset.UtcNow,
                local = DateTimeOffset.Now,
            })),
            "device.restart_agent" => Task.FromResult(CommandResult.Failure("restart_requires_service_manager", "O Service Control Manager deve reiniciar o agente após seu encerramento.")),
            "device.restart_computer" => RestartComputer(),
            _ => Task.FromResult(CommandResult.Failure("unsupported_device_command", $"Comando de dispositivo desconhecido: {CommandType}")),
        };
    }

    private Task<CommandResult> RestartComputer()
    {
        if (!_options.AllowComputerRestart)
        {
            return Task.FromResult(CommandResult.Failure("computer_restart_disabled", "A reinicialização remota está desabilitada na configuração local."));
        }

        if (!OperatingSystem.IsWindows())
        {
            return Task.FromResult(CommandResult.Failure("platform_not_supported", "A reinicialização remota só é suportada no Windows."));
        }

        var info = new ProcessStartInfo("shutdown.exe") { UseShellExecute = false, CreateNoWindow = true };
        info.ArgumentList.Add("/r");
        info.ArgumentList.Add("/t");
        info.ArgumentList.Add("30");
        info.ArgumentList.Add("/c");
        info.ArgumentList.Add("Reinicialização solicitada pelo painel TV Wall Control");
        Process.Start(info);
        return Task.FromResult(CommandResult.Success(new { scheduledInSeconds = 30 }));
    }
}
