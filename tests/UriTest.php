<?php

declare(strict_types=1);

namespace Platine\Test\Http;

use Platine\Http\Uri;
use Platine\Dev\PlatineTestCase;

/**
 * Uri class tests
 *
 * @group core
 * @group http
 * @group message
 */
class UriTest extends PlatineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER = [];
    }

    public function testConstructorParseUrlReturnFalse(): void
    {
        global $mock_parse_url;
        $mock_parse_url = true;
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri('/path/not/found/');
    }

    public function testConstructorParamUriWithoutUserInfo(): void
    {
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('hostname', $uri->getHost());
        $this->assertEquals(9090, $uri->getPort());
        $this->assertEquals('/path', $uri->getPath());
        $this->assertEquals('arg=value', $uri->getQuery());
        $this->assertEquals('anchor', $uri->getFragment());
        $this->assertEquals('hostname:9090', $uri->getAuthority());
    }

    public function testCreateFromGlobalDefault(): void
    {
        $uri = Uri::createFromGlobals();
        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('127.0.0.1', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertEquals('/', $uri->getPath());
        $this->assertEmpty($uri->getQuery());
        $this->assertEmpty($uri->getFragment());
        $this->assertEquals('127.0.0.1', $uri->getAuthority());
    }

    public function testCreateFromGlobalUsingServerName(): void
    {
        $_SERVER['SERVER_NAME'] = 'foo.server.com';

        $uri = Uri::createFromGlobals();
        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('foo.server.com', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertEquals('/', $uri->getPath());
        $this->assertEmpty($uri->getQuery());
        $this->assertEmpty($uri->getFragment());
        $this->assertEquals('foo.server.com', $uri->getAuthority());
    }

    public function testCreateFromGlobalUsingServerAddr(): void
    {
        $_SERVER['SERVER_ADDR'] = '1.2.3.4';

        $uri = Uri::createFromGlobals();
        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('1.2.3.4', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertEquals('/', $uri->getPath());
        $this->assertEmpty($uri->getQuery());
        $this->assertEmpty($uri->getFragment());
        $this->assertEquals('1.2.3.4', $uri->getAuthority());
    }

    public function testCreateFromGlobalUsingServerRequestUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/foo/bar';

        $uri = Uri::createFromGlobals();
        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('127.0.0.1', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertEquals('/foo/bar', $uri->getPath());
        $this->assertEmpty($uri->getQuery());
        $this->assertEmpty($uri->getFragment());
        $this->assertEquals('127.0.0.1', $uri->getAuthority());
    }

    public function testCreateFromGlobalUsingServerPort(): void
    {
        $_SERVER['SERVER_PORT'] = 124;

        $uri = Uri::createFromGlobals();
        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('127.0.0.1', $uri->getHost());
        $this->assertEquals(124, $uri->getPort());
        $this->assertEquals('/', $uri->getPath());
        $this->assertEmpty($uri->getQuery());
        $this->assertEmpty($uri->getFragment());
        $this->assertEquals('127.0.0.1:124', $uri->getAuthority());
    }

    public function testConstructorUriWithUserInfo(): void
    {
        $uri = new Uri('http://username:password@hostname:9090/path?arg=value#anchor');
        $this->assertEquals('username:password', $uri->getUserInfo());
    }

    public function testWithScheme(): void
    {
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $uri = $uri->withScheme('https');
        $this->assertEquals('https', $uri->getScheme());
    }

    public function testWithUserInfo(): void
    {
        $uri = new Uri('http://username:password@hostname:9090/path?arg=value#anchor');
        $this->assertEquals('username:password', $uri->getUserInfo());

        $uri = $uri->withUserInfo('username1');
        $this->assertEquals('username1', $uri->getUserInfo());

        $uri = $uri->withUserInfo('username2', 'password1');
        $this->assertEquals('username2:password1', $uri->getUserInfo());
    }

    public function testWithHost(): void
    {
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $uri = $uri->withHost('foo.com');
        $this->assertEquals('foo.com', $uri->getHost());
    }

    public function testWithPort(): void
    {
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $uri = $uri->withPort(1500);
        $this->assertEquals(1500, $uri->getPort());

        $uri = $uri->withPort(80);
        $this->assertNull($uri->getPort());
    }

    public function testWithPath(): void
    {
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $uri = $uri->withPath('/foo/bar');
        $this->assertEquals('/foo/bar', $uri->getPath());
    }

    public function testWithQuery(): void
    {
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $uri = $uri->withQuery('a=b&c=d');
        $this->assertEquals('a=b&c=d', $uri->getQuery());
    }

    public function testWithFragment(): void
    {
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $uri = $uri->withFragment('foobar');
        $this->assertEquals('foobar', $uri->getFragment());
    }

    public function testToString(): void
    {
        $uri = new Uri('http://hostname:9090/path?arg=value#anchor');
        $uri = $uri->withFragment('foobar');
        $uri = $uri->withPort(80);
        $this->assertEquals('http://hostname/path?arg=value#foobar', $uri->__toString());
    }

    public function testToStringAutorityIsEmpty(): void
    {
        global $mock_strncmp_to_zero;
        $uri = new Uri('foo');

        $mock_strncmp_to_zero = true;

        $uri = $uri->withFragment('foobar');
        $uri = $uri->withPort(80);
        $this->assertEmpty($uri->getAuthority());
        $this->assertEquals('/foo#foobar', $uri->__toString());
    }

    public function testToStringPathIsEmpty(): void
    {
        $uri = new Uri('http://hostname:9090?arg=value#anchor');
        $uri = $uri->withFragment('foobar');
        $uri = $uri->withPort(80);
        $this->assertEquals('http://hostname/?arg=value#foobar', $uri->__toString());
    }

    public function testFilterSchemeInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri();
        $this->runPrivateProtectedMethod($uri, 'filterScheme', array('htt@p'));
    }

    public function testFilterSchemeParamIsEmpty(): void
    {
        $uri = new Uri();
        $result = $this->runPrivateProtectedMethod($uri, 'filterScheme', array(''));
        $this->assertEmpty($result);
    }

    public function testFilterHostParamIsEmpty(): void
    {
        $uri = new Uri();
        $result = $this->runPrivateProtectedMethod($uri, 'filterHost', array(''));
        $this->assertEmpty($result);
    }

    public function testFilterHostInvalidIpvFuture(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri();
        $this->runPrivateProtectedMethod($uri, 'filterHost', array('[v1.fe80::za+en1]'));
    }

    public function testFilterHostInvalidIpv6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri();
        $this->runPrivateProtectedMethod($uri, 'filterHost', array('[fe80::za]'));
    }

    public function testFilterHostIpvFutureAndIpv6IsValid(): void
    {
        $uri = new Uri();

        //ipv6
        $ip = '[ff00::110]';
        $result = $this->runPrivateProtectedMethod($uri, 'filterHost', array($ip));
        $this->assertEquals($result, $ip);

        //ipvfuture
        $ip = '[v6.1]';
        $result = $this->runPrivateProtectedMethod($uri, 'filterHost', array($ip));
        $this->assertEquals($result, $ip);
    }

    public function testFilterHostInvalidIpv4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri();
        $this->runPrivateProtectedMethod($uri, 'filterHost', array('12.34.45.345'));
    }

    public function testFilterHostInvalidDomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri();
        $this->runPrivateProtectedMethod($uri, 'filterHost', array('foo@bar'));
    }

    public function testFilterPortIsValid(): void
    {
        $uri = new Uri('http://hostname:9090?arg=value#anchor');
        $port = 9090;
        $result = $this->runPrivateProtectedMethod($uri, 'filterPort', array($port));
        $this->assertEquals($result, $port);
    }

    public function testFilterPortParamIsNull(): void
    {
        $uri = new Uri();
        $port = null;
        $result = $this->runPrivateProtectedMethod($uri, 'filterPort', array($port));
        $this->assertNull($result);
    }

    public function testFilterPortInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri();
        $this->runPrivateProtectedMethod($uri, 'filterPort', array(0));
    }

    public function testFilterQueryInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri();
        $this->runPrivateProtectedMethod($uri, 'filterQuery', array('foo[]=array&foo[hash]=hash'));
    }

    public function testFilterQueryParamIsEmpty(): void
    {
        $uri = new Uri();
        $param = '';
        $result = $this->runPrivateProtectedMethod($uri, 'filterQuery', array($param));
        $this->assertEmpty($result);
    }

    public function testFilterQueryParamIsValid(): void
    {
        global $mock_preg_match_to_true;

        $uri = new Uri();

        $mock_preg_match_to_true = true;
        $param = 'a=b&c=d&e=45&%';
        $result = $this->runPrivateProtectedMethod($uri, 'filterQuery', array($param));
        $this->assertEquals($result, 'a=b&c=d&e=45&%25');
    }

    public function testFilterPathInvalidPathStartColon(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri('//foohost');
        $this->runPrivateProtectedMethod($uri, 'filterPath', array(':foo'));
    }

    public function testFilterPathInvalidPathContainsDoubleSlash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri('foo');
        $this->runPrivateProtectedMethod($uri, 'filterPath', array('//foo'));
    }

    public function testFilterPathInvalidPathNotStartToSlash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri('http://foo');
        $this->runPrivateProtectedMethod($uri, 'filterPath', array('pathfoo'));
    }

    public function testFilterPathInvalidPathCharsNotAllowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $uri = new Uri('foo');
        $this->runPrivateProtectedMethod($uri, 'filterPath', array('path^foo'));
    }

    public function testFilterPathIsValid(): void
    {
        global $mock_preg_match_to_true;
        $mock_preg_match_to_true = true;
        $uri = new Uri('foo');
        $param = 'path?';
        $result = $this->runPrivateProtectedMethod($uri, 'filterPath', array($param));
        $this->assertEquals($result, 'path%3F');
    }

    public function testFilterFragmentParamIsEmpty(): void
    {
        $uri = new Uri();
        $param = '';
        $result = $this->runPrivateProtectedMethod($uri, 'filterFragment', array($param));
        $this->assertEmpty($result);
    }

    public function testFilterFragment(): void
    {
        $uri = new Uri();
        $param = '#fragment';
        $result = $this->runPrivateProtectedMethod($uri, 'filterFragment', array($param));
        $this->assertEquals($result, '%23fragment');
    }
}
