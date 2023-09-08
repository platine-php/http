<?php

declare(strict_types=1);

namespace Platine\Test\Http;

use InvalidArgumentException;
use Platine\Dev\PlatineTestCase;
use Platine\Http\Response;
use Platine\Http\Stream;
use Platine\Http\StreamInterface;

/**
 * Response class tests
 *
 * @group core
 * @group http
 * @group message
 */
class ResponseTest extends PlatineTestCase
{
    public function testConstructor(): void
    {
        //Default
        $resp = new Response();
        $this->assertEquals(200, $resp->getStatusCode());
        $this->assertEquals('OK', $resp->getReasonPhrase());
    }

    public function testWithAndGetStatusCode(): void
    {
        $resp = new Response();
        //Using standart reason phrase
        $resp = $resp->withStatus(404, '');
        $this->assertEquals(404, $resp->getStatusCode());
        $this->assertEquals('Not Found', $resp->getReasonPhrase());

        //Using custom reason phrase
        $resp = $resp->withStatus(401, 'Not authorized');
        $this->assertEquals(401, $resp->getStatusCode());
        $this->assertEquals('Not authorized', $resp->getReasonPhrase());

        //invalid status code
        $this->expectException(InvalidArgumentException::class);
        $resp = $resp->withStatus(999, '');
    }

    public function testfilterStatusCodeUnsedCode(): void
    {
        $resp = new Response();
        $this->expectException(InvalidArgumentException::class);
        $resp = $resp->withStatus(306, '');
    }

    public function testWithAndGetBody(): void
    {
        //body is not set
        $r = new Response();
        $reflection = $this->getPrivateProtectedAttribute(Response::class, 'body');
        $reflection->setValue($r, null);
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());

        //body is set
        $body = new Stream();
        $r = (new Response())->withBody($body);
        $this->assertInstanceOf(StreamInterface::class, $r->getBody());
        $this->assertEquals($body, $r->getBody());
    }

    public function testToString(): void
    {
        $r = new Response();
        $r = $r->withAddedHeader('Host', 'foo.com');
        $r = $r->withAddedHeader('Host', array('foo.bar', 'foo.baz'));
        $r = $r->withHeader('set-cookie', 'foo=1;bar=2');
        $r = $r->withBody(new Stream('body'));

        $expected = <<<EOF
HTTP/1.1 200 OK\r
host: foo.com, foo.bar, foo.baz\r
set-cookie: foo=1;bar=2\r
content-length: 4\r
\r
body
EOF;
        $this->assertEquals($expected, $r->__toString());
    }
}
