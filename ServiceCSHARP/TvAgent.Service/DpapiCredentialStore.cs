using System.Runtime.InteropServices;
using System.Text;
using TvAgent.Domain;

namespace TvAgent.Service;

public sealed class DpapiCredentialStore : ICredentialStore
{
    private const int CryptprotectLocalMachine = 0x4;
    private readonly AgentOptions _options;

    public DpapiCredentialStore(AgentOptions options) => _options = options;

    public async Task<string?> ReadAsync(CancellationToken cancellationToken)
    {
        if (!File.Exists(CredentialPath))
        {
            return null;
        }

        EnsureWindows();
        var protectedBytes = await File.ReadAllBytesAsync(CredentialPath, cancellationToken);
        return Encoding.UTF8.GetString(Unprotect(protectedBytes));
    }

    public async Task WriteAsync(string value, CancellationToken cancellationToken)
    {
        EnsureWindows();
        Directory.CreateDirectory(_options.WorkingDirectory);
        var protectedBytes = Protect(Encoding.UTF8.GetBytes(value));
        var temporaryPath = CredentialPath + ".tmp";
        await File.WriteAllBytesAsync(temporaryPath, protectedBytes, cancellationToken);
        File.Move(temporaryPath, CredentialPath, true);
    }

    private string CredentialPath => Path.Combine(_options.WorkingDirectory, "device.credential");

    private static byte[] Protect(byte[] bytes) => Transform(bytes, true);
    private static byte[] Unprotect(byte[] bytes) => Transform(bytes, false);

    private static byte[] Transform(byte[] bytes, bool protect)
    {
        var input = new DataBlob();
        var output = new DataBlob();
        try
        {
            input.Size = bytes.Length;
            input.Data = Marshal.AllocHGlobal(bytes.Length);
            Marshal.Copy(bytes, 0, input.Data, bytes.Length);
            var succeeded = protect
                ? CryptProtectData(ref input, null, nint.Zero, nint.Zero, nint.Zero, CryptprotectLocalMachine, out output)
                : CryptUnprotectData(ref input, nint.Zero, nint.Zero, nint.Zero, nint.Zero, CryptprotectLocalMachine, out output);
            if (!succeeded)
            {
                throw new InvalidOperationException($"DPAPI falhou com código {Marshal.GetLastWin32Error()}.");
            }

            var result = new byte[output.Size];
            Marshal.Copy(output.Data, result, 0, output.Size);
            return result;
        }
        finally
        {
            if (input.Data != nint.Zero) Marshal.FreeHGlobal(input.Data);
            if (output.Data != nint.Zero) LocalFree(output.Data);
        }
    }

    private static void EnsureWindows()
    {
        if (!OperatingSystem.IsWindows()) throw new PlatformNotSupportedException("O armazenamento DPAPI exige Windows.");
    }

    [StructLayout(LayoutKind.Sequential)] private struct DataBlob { public int Size; public nint Data; }
    [DllImport("crypt32.dll", SetLastError = true, CharSet = CharSet.Unicode)] [return: MarshalAs(UnmanagedType.Bool)] private static extern bool CryptProtectData(ref DataBlob input, string? description, nint optionalEntropy, nint reserved, nint prompt, int flags, out DataBlob output);
    [DllImport("crypt32.dll", SetLastError = true, CharSet = CharSet.Unicode)] [return: MarshalAs(UnmanagedType.Bool)] private static extern bool CryptUnprotectData(ref DataBlob input, nint description, nint optionalEntropy, nint reserved, nint prompt, int flags, out DataBlob output);
    [DllImport("kernel32.dll")] private static extern nint LocalFree(nint memory);
}
