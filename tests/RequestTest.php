<?php

declare(strict_types=1);

namespace Platine\Test\Http;

use Platine\Http\Uri;
use Platine\Http\Request;
use Platine\Http\Stream;
use Platine\Dev\PlatineTestCase;

/**
 * Request class tests
 *
 * @group core
 * @group http
 * @group message
 */
class RequestTest extends PlatineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testConstructor(): void
    {
        //Default
        $r = new Request();
        $this->assertInstanceOf(Uri::class, $r->getUri());
        $this->assertEquals('GET', $r->getMethod());

        //Using uri
        $r = new Request('POST', 'http://hostname:9090/path?arg=value#anchor');
        $this->assertInstanceOf(Uri::class, $r->getUri());
        $this->assertEquals('POST', $r->getMethod());
        $this->assertEquals('hostname', $r->getUri()->getHost());

        //Invalid HTTP Method
        $this->expectException(\InvalidArgumentException::class);
        $r = new Request('PO\\ST');
    }

    public function testGetRequestTarget(): void
    {
        //Using uri
        $r = new Request('POST', 'http://hostname:9090/path?arg=value#anchor');
        $this->assertInstanceOf(Uri::class, $r->getUri());
        $this->assertEquals('POST', $r->getMethod());
        $this->assertEquals('/path?arg=value', $r->getRequestTarget());

        //when uri is empty
        $r = new Request();
        $this->assertInstanceOf(Uri::class, $r->getUri());
        $this->assertEquals('/', $r->getRequestTarget());

        //using request target value
        $r = $r->withRequestTarget('/path?arg=value');
        $this->assertEquals('/path?arg=value', $r->getRequestTarget());
    }

    public function testWithRequestTarget(): void
    {
        $r = new Request();
        $this->assertEquals('/', $r->getRequestTarget());

        $r = $r->withRequestTarget('/path?arg=value');
        $this->assertEquals('/path?arg=value', $r->getRequestTarget());
    }

    public function testWithMethod(): void
    {
        $r = new Request();
        $this->assertEquals('GET', $r->getMethod());

        $r = $r->withMethod('PATCH');
        $this->assertEquals('PATCH', $r->getMethod());
    }

    public function testGetUri(): void
    {
        //uri is not set
        $r = new Request();
        $reflection = $this->getPrivateProtectedAttribute(Request::class, 'uri');
        $reflection->setValue($r, null);
        $this->assertInstanceOf(Uri::class, $r->getUri());

        //Uri is set
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $r = new Request('HEAD', $uri);
        $this->assertInstanceOf(Uri::class, $r->getUri());
        $this->assertEquals($uri, $r->getUri());
    }

    public function testWithUri(): void
    {
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $r = new Request('HEAD', $uri);
        $this->assertInstanceOf(Uri::class, $r->getUri());

        $r = $r->withUri(new Uri());
        $this->assertNotEquals($uri, $r->getUri());
        $this->assertArrayHasKey('Host', $r->getHeaders());

        //Preserve host
        $r = new Request('HEAD', $uri);
        $r = $r->withHeader('Host', 'foo.bar');
        $r = $r->withUri(new Uri(), true);
        $this->assertNotEquals($uri, $r->getUri());
        $this->assertArrayHasKey('Host', $r->getHeaders());
        $this->assertEquals('foo.bar', $r->getHeaderLine('Host'));
    }

    public function testToString(): void
    {
        //uri is not set
        $r = new Request();
        $r = $r->withHeader('Host', 'foo.bar');
        $r = $r->withHeader('cookie', 'foo=1;bar=2');
        $r = $r->withBody(new Stream('body'));

        $expected = <<<EOF
GET / HTTP/1.1\r
host: foo.bar\r
cookie: foo=1;bar=2\r
content-length: 4\r
\r
body
EOF;
        $this->assertEquals($expected, $r->__toString());
    }
}
