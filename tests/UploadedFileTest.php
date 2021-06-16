<?php

declare(strict_types=1);

namespace Platine\Test\Http;

use Platine\Http\UploadedFile;
use Platine\Http\Stream;
use Platine\Http\StreamInterface;
use org\bovigo\vfs\vfsStream;
use Platine\Dev\PlatineTestCase;

/**
 * UploadedFile class tests
 *
 * @group core
 * @group http
 * @group message
 */
class UploadedFileTest extends PlatineTestCase
{

    public const STREAM_TEST_FILENAME = 'file_name_test_stream';
    public const STREAM_TEST_TARGET_FILENAME = 'file_name_test_target_stream';

    protected $vfsRoot;
    protected $vfsFileStreamPath;

    protected function setUp(): void
    {
        parent::setUp();

        $_FILES = [];

        //need setup for each test
        $this->vfsRoot = vfsStream::setup();
        $this->vfsFileStreamPath = vfsStream::newDirectory('files_stream')->at($this->vfsRoot);
    }

    public function testConstructorUsingFileAsStream(): void
    {
        $file = $this->getDefaultFileStreamTestPath();
        $u = new UploadedFile($file);
        $reflection = $this->getPrivateProtectedAttribute(UploadedFile::class, 'filename');
        $this->assertEquals($reflection->getValue($u), $file);
    }

    public function testConstructorUsingFileAsStreamNotReadable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $stream = $this->getMockBuilder(Stream::class)->getMock();

        $stream->expects($this->any())
                ->method('isReadable')
                ->will($this->returnValue(false));


        $u = new UploadedFile($stream);
    }

    public function testConstructorUsingFileAsStreamIsReadable(): void
    {
        $stream = $this->getMockBuilder(Stream::class)->getMock();

        $stream->expects($this->any())
                ->method('isReadable')
                ->will($this->returnValue(true));

        $stream->expects($this->any())
                ->method('getSize')
                ->will($this->returnValue(123));

        $reflection = $this->getPrivateProtectedAttribute(UploadedFile::class, 'size');
        $u = new UploadedFile($stream);
        $this->assertEquals($reflection->getValue($u), 123);
    }

    public function testGetStreamWhenAlreadyMoved(): void
    {
        $this->expectException(\RuntimeException::class);


        $u = new UploadedFile($this->getDefaultFileStreamTestPath());
        $reflection = $this->getPrivateProtectedAttribute(UploadedFile::class, 'moved');
        $reflection->setValue($u, true);
        $u->getStream();
    }

    public function testGetStreamUsingFileWhenIsNull(): void
    {
        $file = $this->getDefaultFileStreamTestPath();

        $u = new UploadedFile($file);
        $this->assertInstanceOf(StreamInterface::class, $u->getStream());
    }

    public function testMoveToWhenAlreadyMoved(): void
    {
        $this->expectException(\RuntimeException::class);


        $u = new UploadedFile($this->getDefaultFileStreamTestPath());
        $reflection = $this->getPrivateProtectedAttribute(UploadedFile::class, 'moved');
        $reflection->setValue($u, true);
        $u->moveTo('foo');
    }

    public function testMoveToWhenRunningUnderCliUsingFileSuccess(): void
    {
        global $mock_php_sapi_name_to_cli;
        $mock_php_sapi_name_to_cli = true;

        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $target = $this->getDefaultFileStreamTestTargetPath();

        $u = new UploadedFile($source->url());
        $u->moveTo($target);

        $reflection = $this->getPrivateProtectedAttribute(UploadedFile::class, 'moved');
        $uploadContent = file_get_contents($target);
        $this->assertTrue($reflection->getValue($u));
        $this->assertEquals($uploadContent, 'test');
    }

    public function testMoveToWhenRunningUnderCliUsingFileFail(): void
    {
        global $mock_php_sapi_name_to_cli, $mock_rename_to_false;
        $this->expectException(\RuntimeException::class);

        $mock_php_sapi_name_to_cli = true;
        $mock_rename_to_false = true;

        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $target = $this->getDefaultFileStreamTestTargetPath();

        $u = new UploadedFile($source->url());

        $u->moveTo($target);
    }

    public function testMoveToWhenNotRunningUnderCliUsingFileIsUploadedFileFail(): void
    {
        global $mock_php_sapi_name_to_apache,
        $mock_is_uploaded_file_to_false;
        $this->expectException(\RuntimeException::class);

        $mock_php_sapi_name_to_apache = true;
        $mock_is_uploaded_file_to_false = true;

        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $target = $this->getDefaultFileStreamTestTargetPath();

        $u = new UploadedFile($source->url());

        $u->moveTo($target);
    }

    public function testMoveToWhenNotRunningUnderCliUsingFileMoveUploadedFileFail(): void
    {
        global $mock_php_sapi_name_to_apache,
        $mock_is_uploaded_file_to_true,
        $mock_move_uploaded_file_to_false;
        $this->expectException(\RuntimeException::class);

        $mock_php_sapi_name_to_apache = true;
        $mock_is_uploaded_file_to_true = true;
        $mock_move_uploaded_file_to_false = true;

        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $target = $this->getDefaultFileStreamTestTargetPath();

        $u = new UploadedFile($source->url());

        $u->moveTo($target);
    }

    public function testMoveToWhenNotRunningUnderCliSuccess(): void
    {
        global $mock_php_sapi_name_to_apache,
        $mock_is_uploaded_file_to_true,
        $mock_move_uploaded_file_to_true;

        $mock_php_sapi_name_to_apache = true;
        $mock_is_uploaded_file_to_true = true;
        $mock_move_uploaded_file_to_true = true;

        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $target = $this->getDefaultFileStreamTestTargetPath();

        $u = new UploadedFile($source->url());
        $u->moveTo($target);
        $reflection = $this->getPrivateProtectedAttribute(UploadedFile::class, 'moved');
        $this->assertTrue($reflection->getValue($u));
    }

    public function testMoveToWhenNotRunningUnderCliUsingStreamSuccess(): void
    {
        global $mock_php_sapi_name_to_apache;

        $mock_php_sapi_name_to_apache = true;

        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $target = $this->createVfsFile('target_file', $this->vfsFileStreamPath, '');
        $stream = new Stream($source->url());

        $u = new UploadedFile($stream);
        $u->moveTo($target->url());
        $reflection = $this->getPrivateProtectedAttribute(UploadedFile::class, 'moved');
        $this->assertTrue($reflection->getValue($u));

        $uploadContent = file_get_contents($target->url());
        $this->assertEquals($uploadContent, 'test');
    }

    public function testMoveToWhenNotRunningUnderCliUsingStreamZeroByteSuccess(): void
    {
        global $mock_php_sapi_name_to_apache, $mock_fwrite_to_zero;

        $mock_php_sapi_name_to_apache = true;
        $mock_fwrite_to_zero = true;

        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $target = $this->createVfsFile('target_file', $this->vfsFileStreamPath, 'target');

        $stream = new Stream($source->url());
        $u = new UploadedFile($stream);
        $u->moveTo($target->url());
        $reflection = $this->getPrivateProtectedAttribute(UploadedFile::class, 'moved');
        $this->assertTrue($reflection->getValue($u));

        $uploadContent = file_get_contents($target->url());
        $this->assertEquals($uploadContent, 'target');
    }

    public function testMoveToTargetPathIsEmptyOrInvalid(): void
    {
        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $this->expectException(\InvalidArgumentException::class);
        $stream = new Stream($source->url());
        $u = new UploadedFile($stream);
        $u->moveTo('');
    }

    public function testGetSize(): void
    {
        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');

        //Using Constructor Param
        $u = new UploadedFile($source->url(), 120);
        $this->assertEquals(120, $u->getSize());

        // Using stream size
        $stream = new Stream($source->url());
        $u = new UploadedFile($stream);
        $this->assertEquals(4, $u->getSize());
    }

    public function testGetError(): void
    {
        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');

        //Using Constructor Param
        $u = new UploadedFile($source->url(), null, \UPLOAD_ERR_PARTIAL);
        $this->assertEquals(\UPLOAD_ERR_PARTIAL, $u->getError());
    }

    public function testGetClientFilename(): void
    {
        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');

        //Using Constructor Param
        $u = new UploadedFile($source->url(), null, \UPLOAD_ERR_OK, 'clientFilename');
        $this->assertEquals('clientFilename', $u->getClientFilename());
    }

    public function testGetClientMediaType(): void
    {
        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');

        //Using Constructor Param
        $u = new UploadedFile($source->url(), null, \UPLOAD_ERR_OK, null, 'getClientMediaType');
        $this->assertEquals('getClientMediaType', $u->getClientMediaType());
    }

    public function testFilterErrorInvalid(): void
    {
        $source = $this->createVfsFile('source_file', $this->vfsFileStreamPath, 'test');
        $this->expectException(\InvalidArgumentException::class);
        //Using Constructor Param
        $u = new UploadedFile($source->url(), null, 99999);
    }

    public function testNormalize(): void
    {
        $files = [];

        //Array is empty
        $normalized = UploadedFile::normalize($files);
        $this->assertCount(0, $normalized);

        //Array contains instance of UploadedFile
        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
                ->disableOriginalConstructor()
                ->getMock();
        $files = array($uploadedFile);
        $normalized = UploadedFile::normalize($files);
        $this->assertCount(1, $normalized);

        //Array does not contain instance of UploadedFile
        $files = array(
            array('name' => 'foofile')
        );
        $normalized = UploadedFile::normalize($files);
        $this->assertCount(1, $normalized);
        $this->assertCount(0, $normalized[0]);

        //Array does not contain instance of UploadedFile
        //And create new instance of UploadedFile
        $files = array(
            array('name' => 'foofile', 'error' => 0)
        );
        $normalized = UploadedFile::normalize($files);
        $this->assertCount(1, $normalized);
        $this->assertInstanceOf(UploadedFile::class, $normalized[0]);

        //Array does not contain instance of UploadedFile
        //And create new instance of UploadedFile
        $files = array(
            'form_name' => array(
                'name' => array(
                    'details' => array(
                        'photo' => 'myphoto.png'
                    )
                ),
                'error' => array(
                    'details' => array(
                        'photo' => 6
                    )
                )
            )
        );
        $normalized = UploadedFile::normalize($files);
        $this->assertCount(1, $normalized);
        $this->assertInstanceOf(UploadedFile::class, $normalized['form_name']['details']['photo']);
        $this->assertArrayHasKey('form_name', $normalized);
        $this->assertArrayHasKey('details', $normalized['form_name']);
        $this->assertArrayHasKey('photo', $normalized['form_name']['details']);
        $this->assertInstanceOf(UploadedFile::class, $normalized['form_name']['details']['photo']);
        $this->assertEquals('myphoto.png', $normalized['form_name']['details']['photo']->getClientFilename());
    }

    public function testCreateFromGlobal(): void
    {
        $_FILES = array(
            'avatar' => array(
                'tmp_name' => 'phpUxcOty',
                'name' => 'my-avatar.png',
                'size' => 90996,
                'type' => 'image/png',
                'error' => 0,
        ));

        $files = UploadedFile::createFromGlobals();
        $this->assertArrayHasKey('avatar', $files);
        $this->assertInstanceOf(UploadedFile::class, $files['avatar']);
        $this->assertEquals('my-avatar.png', $files['avatar']->getClientFilename());
        $this->assertEquals(90996, $files['avatar']->getSize());
        $this->assertEquals('image/png', $files['avatar']->getClientMediaType());
        $this->assertEquals(0, $files['avatar']->getError());
    }

    private function getDefaultFileStreamTestPath()
    {
        return $this->vfsFileStreamPath->url() . DIRECTORY_SEPARATOR . static::STREAM_TEST_FILENAME;
    }

    private function getDefaultFileStreamTestTargetPath()
    {
        return $this->vfsFileStreamPath->url() . DIRECTORY_SEPARATOR . static::STREAM_TEST_TARGET_FILENAME;
    }
}
