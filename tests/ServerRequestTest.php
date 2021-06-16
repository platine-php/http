<?php

declare(strict_types=1);

namespace Platine\Test\Http;

use Platine\Http\Uri;
use Platine\Http\ServerRequest;
use Platine\Http\UploadedFile;
use Platine\Http\Stream;
use Platine\Dev\PlatineTestCase;

/**
 * ServerRequest class tests
 *
 * @group core
 * @group http
 * @group message
 */
class ServerRequestTest extends PlatineTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER = [];
        $_POST = [];
    }

    public function testConstructor(): void
    {
        //Default
        $sr = new ServerRequest();
        $this->assertInstanceOf(Uri::class, $sr->getUri());
        $this->assertEquals('GET', $sr->getMethod());
        $this->assertEmpty($sr->getServerParams());

        //Using uri
        $sr = new ServerRequest('POST', 'http://hostname:9090/path?arg=value#anchor');
        $this->assertInstanceOf(Uri::class, $sr->getUri());
        $this->assertEquals('POST', $sr->getMethod());
        $this->assertEquals('hostname', $sr->getUri()->getHost());

        //Using server params
        $sr = new ServerRequest('POST', '', array('foo' => 'bar'));
        $this->assertNotEmpty($sr->getServerParams());
        $this->assertArrayHasKey('foo', $sr->getServerParams());
    }

    public function testCreateFromGlobals(): void
    {
        $_SERVER['HTTP_HOST'] = 'foo.bar.com';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
        //Default
        $sr = ServerRequest::createFromGlobals();
        $this->assertInstanceOf(Uri::class, $sr->getUri());
        $this->assertEquals('GET', $sr->getMethod());
        $this->assertEquals('1.0', $sr->getProtocolVersion());
        $this->assertNotEmpty($sr->getServerParams());

        $this->assertArrayHasKey('HTTP_HOST', $sr->getServerParams());

        //Using method override
        $_POST['_method'] = 'PATCH';
        $sr = ServerRequest::createFromGlobals();
        $this->assertEquals('PATCH', $sr->getMethod());

        //Using request method server param
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $sr = ServerRequest::createFromGlobals();
        $this->assertEquals('PATCH', $sr->getMethod());
    }

    public function testCreateFromGlobalsUsingServerRequestMethodParam(): void
    {
        //use protocol version 1.0
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $sr = ServerRequest::createFromGlobals();
        $this->assertEquals('PATCH', $sr->getMethod());
    }

    public function testCreateFromGlobalsProtocolVersionIs11AndHostHeaderNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $sr = ServerRequest::createFromGlobals();
    }

    public function testwithAndGetCookieParams(): void
    {
        $sr = new ServerRequest();
        $sr = $sr->withCookieParams(array('foo' => 'bar'));

        $this->assertNotEmpty($sr->getCookieParams());
        $this->assertArrayHasKey('foo', $sr->getCookieParams());
    }

    public function testWithAndGetQueryParams(): void
    {
        $sr = new ServerRequest();
        $sr = $sr->withQueryParams(array('foo' => 'bar'));

        $this->assertNotEmpty($sr->getQueryParams());
        $this->assertArrayHasKey('foo', $sr->getQueryParams());
    }

    public function testWithAndGetParsedBody(): void
    {
        $sr = new ServerRequest();
        $sr = $sr->withParsedBody(array('foo' => 'bar'));

        $this->assertNotEmpty($sr->getParsedBody());
        $this->assertArrayHasKey('foo', $sr->getParsedBody());

        //parsed body is not null,object,array
        $this->expectException(\InvalidArgumentException::class);
        $sr = $sr->withParsedBody(1);
    }

    public function testWithAndGetAttributes(): void
    {
        $sr = new ServerRequest();
        $sr = $sr->withAttribute('foo', 'bar');

        $this->assertNotEmpty($sr->getAttributes());
        $this->assertEquals('bar', $sr->getAttribute('foo'));
        $this->assertEquals('baz', $sr->getAttribute('foo_not_exists', 'baz'));
        $this->assertNull($sr->getAttribute('foo_not_exists'));
    }

    public function testWithoutAttributes(): void
    {
        $sr = new ServerRequest();
        $sr = $sr->withAttribute('foo', 'bar');

        $this->assertNotEmpty($sr->getAttributes());
        $this->assertEquals('bar', $sr->getAttribute('foo'));
        $this->assertEquals('baz', $sr->getAttribute('foo_not_exists', 'baz'));
        $this->assertNull($sr->getAttribute('foo_not_exists'));

        $sr = $sr->withoutAttribute('foo');
        $this->assertEmpty($sr->getAttributes());
        $this->assertNull($sr->getAttribute('foo'));
        $this->assertEquals('baz', $sr->getAttribute('foo_not_exists', 'baz'));
        $this->assertNull($sr->getAttribute('foo_not_exists'));
    }

    public function testWithAndGetUploadedFilesInvalidFileStructure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $sr = new ServerRequest();
        $sr = $sr->withUploadedFiles(array('foo' => 'bar'));
    }

    public function testWithAndGetUploadedFiles(): void
    {
        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
                ->disableOriginalConstructor()
                ->getMock();
        $files = array($uploadedFile);

        $sr = new ServerRequest();
        $sr = $sr->withUploadedFiles($files);

        $this->assertNotEmpty($sr->getUploadedFiles());
        $this->assertCount(1, $sr->getUploadedFiles());

        //Using array of array
        $files = array(array($uploadedFile));
        $sr = $sr->withUploadedFiles($files);

        $this->assertNotEmpty($sr->getUploadedFiles());
        $this->assertCount(1, $sr->getUploadedFiles());
    }
}
