<?php

declare(strict_types=1);

namespace Platine\Test\Http;

use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use Platine\Dev\PlatineTestCase;
use Platine\Http\Stream;
use RuntimeException;

/**
 * Stream class tests
 *
 * @group core
 * @group http
 * @group message
 */
class StreamTest extends PlatineTestCase
{

    protected $vfsRoot;
    protected $vfsFileStreamPath;

    protected function setUp(): void
    {
        parent::setUp();

        //need setup for each test
        $this->vfsRoot = vfsStream::setup();
        $this->vfsFileStreamPath = vfsStream::newDirectory('files_stream')->at($this->vfsRoot);
    }

    public function testConstructor(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        //Using file
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));

        //Using resource
        $fp = fopen($file->url(), 'r+');
        $s = new Stream($fp);
        $this->assertIsResource($reflection->getValue($s));

        //Using options params
        $s = new Stream(
            $file->url(),
            'r+',
            array(
                    'size' => 256,
                    'seekable' => true,
                    'readable' => true,
                    'writable' => false
                    )
        );
        $this->assertIsResource($reflection->getValue($s));
        $this->assertEquals(256, $s->getSize());
        $this->assertTrue($s->isSeekable());
        $this->assertTrue($s->isReadable());
        $this->assertFalse($s->isWritable());
    }

    public function testConstructorUsingFileFopenFailed(): void
    {
        global $mock_fopen_to_false;
        $mock_fopen_to_false = true;
        $this->expectException(RuntimeException::class);
        $file = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
    }

    public function testConstructorUsingFileInvalidMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $file = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url(), 'foo');
    }

    public function testConstructorUsingStringContentFopenFailed(): void
    {
        global $mock_fopen_to_false;
        $mock_fopen_to_false = true;
        $this->expectException(RuntimeException::class);
        $s = new Stream('Hello World');
    }

    public function testConstructorParamIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = new Stream(36366);
    }

    public function testDetach(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $oldResource = $reflection->getValue($s);
        $resource = $s->detach();
        $this->assertNull($reflection->getValue($s));
        $this->assertNull($s->getSize());
        $this->assertFalse($s->isSeekable());
        $this->assertFalse($s->isReadable());
        $this->assertFalse($s->isWritable());
        $this->assertEquals($oldResource, $resource);
    }

    public function testTellResourceIsAlreadyDetached(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $s->detach();
        $this->assertNull($reflection->getValue($s));
        $this->assertNull($s->getSize());
        $this->assertFalse($s->isSeekable());
        $this->assertFalse($s->isReadable());
        $this->assertFalse($s->isWritable());

        $this->expectException(RuntimeException::class);
        $s->tell();
    }

    public function testTellFunctionFtelleReturnFalse(): void
    {
        global $mock_ftell_to_false;
        $mock_ftell_to_false = true;
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));

        $this->expectException(RuntimeException::class);
        $s->tell();
    }

    public function testTellSuccess(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        //read 2 bytes
        $s->read(2);
        $position = $s->tell();
        $this->assertEquals(2, $position);
    }

    public function testSeekResourceIsAlreadyDetached(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $s->detach();
        $this->assertNull($reflection->getValue($s));
        $this->assertNull($s->getSize());
        $this->assertFalse($s->isSeekable());
        $this->assertFalse($s->isReadable());
        $this->assertFalse($s->isWritable());

        $this->expectException(RuntimeException::class);
        $s->seek(1);
    }

    public function testSeekStreamIsNotSeekable(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url(), 'r+', array('seekable' => false));
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $this->expectException(RuntimeException::class);
        $s->seek(1);
    }

    public function testSeekFunctionFseekReturnMinus1(): void
    {
        global $mock_fseek_to_minus_1;
        $mock_fseek_to_minus_1 = true;
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $this->expectException(RuntimeException::class);
        $s->seek(1);
    }

    public function testSeekSuccess(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        //set pointer to 2 bytes
        $s->seek(2);
        $buffer = $s->read(4);
        $this->assertEquals('st', $buffer);
    }

    public function testWriteResourceIsAlreadyDetached(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $s->detach();
        $this->assertNull($reflection->getValue($s));
        $this->assertNull($s->getSize());
        $this->assertFalse($s->isSeekable());
        $this->assertFalse($s->isReadable());
        $this->assertFalse($s->isWritable());

        $this->expectException(RuntimeException::class);
        $s->write('foo');
    }

    public function testWriteStreamIsNotWritable(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url(), 'r+', array('writable' => false));
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $this->expectException(RuntimeException::class);
        $s->write('foo');
    }

    public function testWriteFunctionFwriteReturnFalse(): void
    {
        global $mock_fwrite_to_false;
        $mock_fwrite_to_false = true;
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $this->expectException(RuntimeException::class);
        $s->write('foo');
    }

    public function testWriteFunctionFStatReturnFalse(): void
    {
        global $mock_fstat_to_false;
        $mock_fstat_to_false = true;
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $s->write('foo');
        $this->assertNull($s->getSize());
    }

    public function testWriteSuccess(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url(), 'w');
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $s->write('foo');
        $this->assertEquals('foo', file_get_contents($file->url()));
    }

    public function testReadResourceIsAlreadyDetached(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $s->detach();
        $this->assertNull($reflection->getValue($s));
        $this->assertNull($s->getSize());
        $this->assertFalse($s->isSeekable());
        $this->assertFalse($s->isReadable());
        $this->assertFalse($s->isWritable());

        $this->expectException(RuntimeException::class);
        $s->read(2);
    }

    public function testReadStreamIsNotReadable(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url(), 'r+', array('readable' => false));
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $this->expectException(RuntimeException::class);
        $s->read(3);
    }

    public function testReadFunctionFwriteReturnFalse(): void
    {
        global $mock_fread_to_false;
        $mock_fread_to_false = true;
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $this->expectException(RuntimeException::class);
        $s->read(3);
    }

    public function testReadSuccess(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $buffer = $s->read(3);
        $this->assertEquals('tes', $buffer);
    }

    public function testGetContentsResourceIsAlreadyDetached(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $s->detach();
        $this->assertNull($reflection->getValue($s));
        $this->assertNull($s->getSize());
        $this->assertFalse($s->isSeekable());
        $this->assertFalse($s->isReadable());
        $this->assertFalse($s->isWritable());

        $this->expectException(RuntimeException::class);
        $s->getContents();
    }

    public function testGetContentsStreamIsNotReadable(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url(), 'r+', array('readable' => false));
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $this->expectException(RuntimeException::class);
        $s->getContents();
    }

    public function testReadFunctionStreamGetContentsReturnFalse(): void
    {
        global $mock_stream_get_contents_to_false;
        $mock_stream_get_contents_to_false = true;

        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $this->expectException(RuntimeException::class);
        $s->getContents();
    }

    public function testGetContentsSuccess(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $buffer = $s->getContents();
        $this->assertEquals('test', $buffer);
    }

    public function testGetMetadata(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());

        //Key is null
        $meta = $s->getMetadata();
        $this->assertIsArray($meta);

        //Key is found
        $meta = $s->getMetadata('seekable');
        $this->assertTrue($meta);

        //Key is not found
        $meta = $s->getMetadata('very_not_found_key');
        $this->assertNull($meta);
    }

    public function testGetMetadataResourceDetached(): void
    {
        $this->expectException(RuntimeException::class);
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());

        $s->close();
        $s->getMetadata();
    }

    public function testToStringSuccess(): void
    {
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $buffer = $s->__toString();
        $this->assertEquals('test', $buffer);
    }

    public function testToStringReturnEmptyWhenException(): void
    {
        global $mock_fseek_throws_exception;
        $mock_fseek_throws_exception = true;
        $file = $this->createVfsFile('stream_source_file', $this->vfsFileStreamPath, 'test');
        $s = new Stream($file->url());
        $reflection = $this->getPrivateProtectedAttribute(Stream::class, 'resource');
        $this->assertIsResource($reflection->getValue($s));
        $buffer = $s->__toString();
        $this->assertEmpty($buffer);
    }
}
