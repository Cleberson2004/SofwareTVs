using System.Buffers.Binary;

namespace TvAgent.SessionHost;

public static class ImageDimensions
{
    private static readonly byte[] PngSignature = [137, 80, 78, 71, 13, 10, 26, 10];

    public static (int Width, int Height) ReadPng(string path)
    {
        Span<byte> header = stackalloc byte[24];
        using var stream = File.OpenRead(path);
        if (stream.Read(header) != header.Length || !header[..8].SequenceEqual(PngSignature))
        {
            throw new InvalidDataException("A campanha final não é um PNG válido.");
        }

        var width = BinaryPrimitives.ReadInt32BigEndian(header[16..20]);
        var height = BinaryPrimitives.ReadInt32BigEndian(header[20..24]);
        if (width < 1 || height < 1)
        {
            throw new InvalidDataException("O PNG possui dimensões inválidas.");
        }

        return (width, height);
    }
}
