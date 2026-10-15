using TvAgent.SessionHost;

namespace TvAgent.Tests;

public sealed class ImageDimensionsTests
{
    [Fact]
    public void ReadsPngDimensionsWithoutDecodingImage()
    {
        var path = Path.GetTempFileName();
        try
        {
            File.WriteAllBytes(path, [137, 80, 78, 71, 13, 10, 26, 10, 0, 0, 0, 13, 73, 72, 68, 82, 0, 0, 30, 0, 0, 0, 4, 56]);

            var dimensions = ImageDimensions.ReadPng(path);

            Assert.Equal(7680, dimensions.Width);
            Assert.Equal(1080, dimensions.Height);
        }
        finally
        {
            File.Delete(path);
        }
    }
}
