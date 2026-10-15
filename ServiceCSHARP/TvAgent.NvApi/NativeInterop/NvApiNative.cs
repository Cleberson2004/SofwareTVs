using System.Runtime.InteropServices;

namespace TvAgent.NvApi.NativeInterop;

internal sealed class NvApiNative : IDisposable
{
    private const int NvapiOk = 0;
    private const int MaxDisplays = 16;
    private const int GridDisplaySize = 28;
    private const int GridSize = 488;
    private const int StatusSize = 272;
    private readonly nint _library;
    private readonly NvApiUnload _unload;
    private bool _disposed;

    [UnmanagedFunctionPointer(CallingConvention.Cdecl)] private delegate nint QueryInterface(uint id);
    [UnmanagedFunctionPointer(CallingConvention.Cdecl)] private delegate int NvApiInitialize();
    [UnmanagedFunctionPointer(CallingConvention.Cdecl)] private delegate int NvApiUnload();
    [UnmanagedFunctionPointer(CallingConvention.Cdecl)] private delegate int EnumDisplayGrids(nint grids, ref uint count);
    [UnmanagedFunctionPointer(CallingConvention.Cdecl)] private delegate int ValidateDisplayGrids(uint flags, nint grids, nint statuses, uint count);
    [UnmanagedFunctionPointer(CallingConvention.Cdecl)] private delegate int SetDisplayGrids(nint grids, uint count, uint flags);
    [UnmanagedFunctionPointer(CallingConvention.Cdecl)] private delegate int EnableCurrentTopo(uint enabled);

    private readonly EnumDisplayGrids _enumDisplayGrids;
    private readonly ValidateDisplayGrids _validateDisplayGrids;
    private readonly SetDisplayGrids _setDisplayGrids;
    private readonly EnableCurrentTopo _enableCurrentTopo;

    public NvApiNative()
    {
        if (!OperatingSystem.IsWindows())
        {
            throw new PlatformNotSupportedException("NVAPI está disponível somente no Windows.");
        }

        _library = NativeLibrary.Load(Environment.Is64BitProcess ? "nvapi64.dll" : "nvapi.dll");
        var query = Marshal.GetDelegateForFunctionPointer<QueryInterface>(NativeLibrary.GetExport(_library, "nvapi_QueryInterface"));
        var initialize = Resolve<NvApiInitialize>(query, 0x0150E828);
        _unload = Resolve<NvApiUnload>(query, 0xD22BDD7E);
        _enumDisplayGrids = Resolve<EnumDisplayGrids>(query, 0xDF2887AF);
        _validateDisplayGrids = Resolve<ValidateDisplayGrids>(query, 0xCF43903D);
        _setDisplayGrids = Resolve<SetDisplayGrids>(query, 0x4D959A89);
        _enableCurrentTopo = Resolve<EnableCurrentTopo>(query, 0x5F1AA66C);
        EnsureSuccess(initialize(), "NvAPI_Initialize");
    }

    public IReadOnlyList<NvGrid> EnumerateGrids()
    {
        uint count = 0;
        EnsureSuccess(_enumDisplayGrids(nint.Zero, ref count), "NvAPI_Mosaic_EnumDisplayGrids(count)");
        if (count == 0)
        {
            return [];
        }

        var buffer = Marshal.AllocHGlobal(checked((int)count * GridSize));
        try
        {
            Zero(buffer, checked((int)count * GridSize));
            for (var index = 0; index < count; index++)
            {
                InitializeGridVersions(buffer + index * GridSize);
            }

            EnsureSuccess(_enumDisplayGrids(buffer, ref count), "NvAPI_Mosaic_EnumDisplayGrids");
            return Enumerable.Range(0, checked((int)count)).Select(index => ReadGrid(buffer + index * GridSize)).ToArray();
        }
        finally
        {
            Marshal.FreeHGlobal(buffer);
        }
    }

    public (bool IsValid, uint ErrorFlags, uint WarningFlags) Validate(NvGrid grid)
    {
        var gridBuffer = Marshal.AllocHGlobal(GridSize);
        var statusBuffer = Marshal.AllocHGlobal(StatusSize);
        try
        {
            Zero(gridBuffer, GridSize);
            Zero(statusBuffer, StatusSize);
            WriteGrid(gridBuffer, grid);
            Marshal.WriteInt32(statusBuffer, MakeVersion(StatusSize, 1));
            var result = _validateDisplayGrids(0, gridBuffer, statusBuffer, 1);
            var errorFlags = unchecked((uint)Marshal.ReadInt32(statusBuffer, 4));
            var warningFlags = unchecked((uint)Marshal.ReadInt32(statusBuffer, 8));
            return (result == NvapiOk && errorFlags == 0, errorFlags, warningFlags);
        }
        finally
        {
            Marshal.FreeHGlobal(gridBuffer);
            Marshal.FreeHGlobal(statusBuffer);
        }
    }

    public void Apply(NvGrid grid)
    {
        var buffer = Marshal.AllocHGlobal(GridSize);
        try
        {
            Zero(buffer, GridSize);
            WriteGrid(buffer, grid);
            EnsureSuccess(_setDisplayGrids(buffer, 1, 0), "NvAPI_Mosaic_SetDisplayGrids");
        }
        finally
        {
            Marshal.FreeHGlobal(buffer);
        }
    }

    public void Enable(bool enabled) => EnsureSuccess(_enableCurrentTopo(enabled ? 1u : 0u), "NvAPI_Mosaic_EnableCurrentTopo");

    public void Dispose()
    {
        if (_disposed)
        {
            return;
        }

        _unload();
        NativeLibrary.Free(_library);
        _disposed = true;
    }

    private static T Resolve<T>(QueryInterface query, uint id) where T : Delegate
    {
        var pointer = query(id);
        if (pointer == nint.Zero)
        {
            throw new InvalidOperationException($"A função NVAPI 0x{id:X8} não está disponível no driver instalado.");
        }

        return Marshal.GetDelegateForFunctionPointer<T>(pointer);
    }

    private static NvGrid ReadGrid(nint pointer)
    {
        var rows = Marshal.ReadInt32(pointer, 4);
        var columns = Marshal.ReadInt32(pointer, 8);
        var displayCount = Marshal.ReadInt32(pointer, 12);
        var flags = unchecked((uint)Marshal.ReadInt32(pointer, 16));
        var displays = new List<NvGridDisplay>(displayCount);
        for (var index = 0; index < displayCount; index++)
        {
            var offset = 20 + index * GridDisplaySize;
            displays.Add(new NvGridDisplay(
                unchecked((uint)Marshal.ReadInt32(pointer, offset + 4)),
                Marshal.ReadInt32(pointer, offset + 8),
                Marshal.ReadInt32(pointer, offset + 12),
                Marshal.ReadInt32(pointer, offset + 16)));
        }

        var settingsOffset = 20 + MaxDisplays * GridDisplaySize;
        return new NvGrid(rows, columns, flags, displays,
            Marshal.ReadInt32(pointer, settingsOffset + 4), Marshal.ReadInt32(pointer, settingsOffset + 8),
            Marshal.ReadInt32(pointer, settingsOffset + 16));
    }

    private static void WriteGrid(nint pointer, NvGrid grid)
    {
        InitializeGridVersions(pointer);
        Marshal.WriteInt32(pointer, 4, grid.Rows);
        Marshal.WriteInt32(pointer, 8, grid.Columns);
        Marshal.WriteInt32(pointer, 12, grid.Displays.Count);
        Marshal.WriteInt32(pointer, 16, unchecked((int)grid.Flags));
        for (var index = 0; index < grid.Displays.Count; index++)
        {
            var display = grid.Displays[index];
            var offset = 20 + index * GridDisplaySize;
            Marshal.WriteInt32(pointer, offset + 4, unchecked((int)display.DisplayId));
            Marshal.WriteInt32(pointer, offset + 8, display.OverlapX);
            Marshal.WriteInt32(pointer, offset + 12, display.OverlapY);
            Marshal.WriteInt32(pointer, offset + 16, display.Rotation);
        }

        var settingsOffset = 20 + MaxDisplays * GridDisplaySize;
        Marshal.WriteInt32(pointer, settingsOffset + 4, grid.Width);
        Marshal.WriteInt32(pointer, settingsOffset + 8, grid.Height);
        Marshal.WriteInt32(pointer, settingsOffset + 12, 32);
        Marshal.WriteInt32(pointer, settingsOffset + 16, grid.RefreshRate);
    }

    private static void InitializeGridVersions(nint pointer)
    {
        Marshal.WriteInt32(pointer, MakeVersion(GridSize, 2));
        for (var index = 0; index < MaxDisplays; index++)
        {
            Marshal.WriteInt32(pointer, 20 + index * GridDisplaySize, MakeVersion(GridDisplaySize, 2));
        }

        Marshal.WriteInt32(pointer, 20 + MaxDisplays * GridDisplaySize, MakeVersion(20, 1));
    }

    private static int MakeVersion(int size, int version) => size | version << 16;

    private static void EnsureSuccess(int status, string operation)
    {
        if (status != NvapiOk)
        {
            throw new InvalidOperationException($"{operation} retornou NVAPI status {status}.");
        }
    }

    private static void Zero(nint pointer, int length)
    {
        var zero = new byte[length];
        Marshal.Copy(zero, 0, pointer, length);
    }
}

internal sealed record NvGrid(int Rows, int Columns, uint Flags, IReadOnlyList<NvGridDisplay> Displays, int Width, int Height, int RefreshRate);
internal sealed record NvGridDisplay(uint DisplayId, int OverlapX, int OverlapY, int Rotation);
