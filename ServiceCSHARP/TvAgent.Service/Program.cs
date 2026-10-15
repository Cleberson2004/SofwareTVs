using Microsoft.Extensions.Options;
using TvAgent.Commands;
using TvAgent.Domain;
using TvAgent.NvApi;
using TvAgent.Service;
using TvAgent.SessionHost;

var builder = Host.CreateApplicationBuilder(args);
builder.Services.AddWindowsService(options => options.ServiceName = "TV Wall Agent");
builder.Services.Configure<AgentOptions>(builder.Configuration.GetSection("Agent"));
builder.Services.Configure<DisplayOptions>(builder.Configuration.GetSection("Display"));
builder.Services.Configure<VlcOptions>(builder.Configuration.GetSection("Vlc"));
builder.Services.AddSingleton(serviceProvider => serviceProvider.GetRequiredService<IOptions<AgentOptions>>().Value);
builder.Services.AddSingleton(serviceProvider => serviceProvider.GetRequiredService<IOptions<DisplayOptions>>().Value);
builder.Services.AddSingleton(serviceProvider => serviceProvider.GetRequiredService<IOptions<VlcOptions>>().Value);
builder.Services.AddSingleton<ICredentialStore, DpapiCredentialStore>();
builder.Services.AddHttpClient<IAgentApiClient, AgentApiClient>((serviceProvider, client) =>
{
    var options = serviceProvider.GetRequiredService<AgentOptions>();
    client.BaseAddress = new Uri(options.ApiBaseUrl.EndsWith('/') ? options.ApiBaseUrl : options.ApiBaseUrl + '/');
    client.Timeout = TimeSpan.FromSeconds(60);
});
builder.Services.AddSingleton<IVlcManager, VlcManager>();
builder.Services.AddSingleton<IDisplayController>(serviceProvider =>
{
    var options = serviceProvider.GetRequiredService<DisplayOptions>();
    return options.Provider.Equals("NvApi", StringComparison.OrdinalIgnoreCase)
        ? new NvApiDisplayController()
        : new MockDisplayController(options);
});
builder.Services.AddSingleton<IdempotencyStore>();
builder.Services.AddSingleton<ICommandHandler, CampaignDownloadHandler>();
builder.Services.AddSingleton<ICommandHandler, CampaignActivateHandler>();
foreach (var commandType in new[] { "vlc.status", "vlc.start", "vlc.stop", "vlc.restart", "vlc.reload_playlist" })
{
    builder.Services.AddSingleton<ICommandHandler>(serviceProvider => new VlcCommandHandler(commandType, serviceProvider.GetRequiredService<IVlcManager>()));
}
foreach (var commandType in new[] { "mosaic.inspect", "mosaic.validate_profile", "mosaic.apply_profile", "mosaic.restore_snapshot", "mosaic.enable_current", "mosaic.disable_current" })
{
    builder.Services.AddSingleton<ICommandHandler>(serviceProvider => new MosaicCommandHandler(commandType, serviceProvider.GetRequiredService<IDisplayController>(), serviceProvider.GetRequiredService<IVlcManager>(), serviceProvider.GetRequiredService<AgentOptions>()));
}
foreach (var commandType in new[] { "device.collect_diagnostics", "device.restart_agent", "device.restart_computer" })
{
    builder.Services.AddSingleton<ICommandHandler>(serviceProvider => new DeviceCommandHandler(commandType, serviceProvider.GetRequiredService<AgentOptions>()));
}
builder.Services.AddSingleton<ICommandDispatcher, CommandDispatcher>();
builder.Services.AddHostedService<AgentWorker>();

await builder.Build().RunAsync();
