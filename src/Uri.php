<?php

/**
 * Platine HTTP
 *
 * Platine HTTP Message is the implementation of PSR 7
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2020 Platine HTTP
 * Copyright (c) 2019 Dion Chaika
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 *  @file Uri.php
 *
 *  The Uri class used to manage HTTP Uri
 *
 *  @package    Platine\Http
 *  @author Platine Developers Team
 *  @copyright  Copyright (c) 2020
 *  @license    http://opensource.org/licenses/MIT  MIT License
 *  @link   http://www.iacademy.cf
 *  @version 1.0.0
 *  @filesource
 */

declare(strict_types=1);

namespace Platine\Http;

class Uri implements UriInterface
{

    /**
     * The Uri scheme
     * @var string
     */
    protected string $scheme = '';

    /**
     * The Uri user information
     * @var string
     */
    protected string $userInfo = '';

    /**
     * The Uri host
     * @var string
     */
    protected string $host = '';

    /**
     * The Uri port
     * @var int|null
     */
    protected ?int $port = null;

    /**
     * The Uri path
     * @var string
     */
    protected string $path = '';

    /**
     * The Uri query
     * @var string
     */
    protected ?string $query = '';

    /**
     * The Uri fragment
     * @var string
     */
    protected string $fragment = '';

    /**
     * Create new Uri instance
     * @param string $uri
     */
    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = parse_url($uri);

            if ($parts === false) {
                throw new \InvalidArgumentException('URL is malformed.');
            }
            $scheme = !empty($parts['scheme']) ? $parts['scheme'] : '';
            $user = !empty($parts['user']) ? $parts['user'] : '';
            $password = !empty($parts['pass']) ? $parts['pass'] : null;
            $host = !empty($parts['host']) ? $parts['host'] : '';
            $port = !empty($parts['port']) ? $parts['port'] : null;
            $path = !empty($parts['path']) ? $parts['path'] : '';
            $query = !empty($parts['query']) ? $parts['query'] : '';
            $fragment = !empty($parts['fragment']) ? $parts['fragment'] : '';

            $userInfo = $user;
            if ($userInfo !== null && $password !== null && $password !== '') {
                $userInfo .= ':' . $password;
            }

            $this->scheme = $this->filterScheme($scheme);
            $this->userInfo = $userInfo;
            $this->host = $this->filterHost($host);
            $this->port = $this->filterPort($port);
            $this->path = $this->filterPath($path);
            $this->query = $this->filterQuery($query);
            $this->fragment = $this->filterFragment($fragment);
        }
    }

    /**
     * Create new Uri using Super Global
     * @return Uri the new Uri instance
     */
    public static function createFromGlobals(): self
    {
        $isSecure = !empty($_SERVER['HTTPS'])
                        && strnatcasecmp($_SERVER['HTTPS'], 'off') !== 0;
        $scheme = $isSecure ? 'https' : 'http';
        $host = !empty($_SERVER['SERVER_NAME'])
                ? $_SERVER['SERVER_NAME']
                : (!empty($_SERVER['SERVER_ADDR'])
                    ? $_SERVER['SERVER_ADDR']
                : '127.0.0.1');
        $port = !empty($_SERVER['SERVER_PORT'])
                ? (int) $_SERVER['SERVER_PORT']
                : ($isSecure ? 443 : 80);
        $path = '/';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $parts = explode('?', $_SERVER['REQUEST_URI'], 2);
            $path = $parts[0];
        }
        $query = !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

        return (new static())
                        ->withScheme($scheme)
                        ->withHost($host)
                        ->withPort($port)
                        ->withPath($path)
                        ->withQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthority(): string
    {
        $authority = $this->host;
        if ($authority !== '') {
            if ($this->userInfo !== '') {
                $authority = $this->userInfo . '@' . $authority;
            }

            if ($this->port !== null) {
                $authority .= ':' . $this->port;
            }
        }
        return $authority;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme(string $scheme): self
    {
        $that = clone $this;
        $that->scheme = $this->filterScheme($scheme);
        $that->port = !$this->isStandardPort($that->scheme, $that->port) ? $that->port : null;

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function withUserInfo(string $user, string $password = null): self
    {
        $userInfo = $user;
        if ($userInfo !== null && $password !== null && $password !== '') {
            $userInfo .= ':' . $password;
        }
        $that = clone $this;
        $that->userInfo = $userInfo;

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function withHost(string $host): self
    {
        $that = clone $this;
        $that->host = $this->filterHost($host);

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function withPort(?int $port): self
    {
        $that = clone $this;
        $that->port = $this->filterPort($port);

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function withPath(string $path): self
    {
        $that = clone $this;
        $that->path = $this->filterPath($path);

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function withQuery(string $query): self
    {
        $that = clone $this;
        $that->query = $this->filterQuery($query);

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function withFragment(string $fragment): self
    {
        $that = clone $this;
        $that->fragment = $this->filterFragment($fragment);

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        $uri = '';
        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        if ($authority !== '' && strncmp($this->path, '/', 1) !== 0) {
            $uri .= '/' . $this->path;
        } elseif ($authority === '' && strncmp($this->path, '//', 2) === 0) {
            $uri .= '/' . ltrim($this->path, '/');
        } else {
            $uri .= $this->path;
        }

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    /**
     * Filter Uri scheme
     * @param  string $scheme
     * @return string
     */
    protected function filterScheme(string $scheme): string
    {
        if ($scheme !== '') {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*$/', $scheme)) {
                throw new \InvalidArgumentException(
                    'Scheme must be compliant with the "RFC 3986" standart'
                );
            }
            return strtolower($scheme);
        }
        return $scheme;
    }

    /**
     * Filter Uri host
     * @param  string $host
     * @return string
     */
    protected function filterHost(string $host): string
    {
        if ($host !== '') {
            //Matching an IPvFuture or an IPv6address.
            if (preg_match('/^\[.+\]$/', $host)) {
                $host = trim($host, '[]');

                // Matching an IPvFuture.
                if (preg_match('/^(v|V)/', $host)) {
                    if (
                            !preg_match(
                                '/^(v|V)[a-fA-F0-9]\.([a-zA-Z0-9\-._~]|[!$&\'()*+,;=]|\:)$/',
                                $host
                            )
                    ) {
                        throw new \InvalidArgumentException(
                            'IP address must be compliant with the '
                                        . '"IPvFuture" of the "RFC 3986" standart.'
                        );
                    }
                    // Matching an IPv6address.
                    // TODO
                } elseif (
                        filter_var(
                            $host,
                            \FILTER_VALIDATE_IP,
                            \FILTER_FLAG_IPV6
                        ) === false
                ) {
                    throw new \InvalidArgumentException(
                        'IP address must be compliant with the "IPv6address"'
                                    . ' of the "RFC 3986" standart.'
                    );
                }
                $host = '[' . $host . ']';

                // Matching an IPv4address.
            } elseif (
                    preg_match(
                        '/^([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\./',
                        $host
                    )
            ) {
                if (
                        filter_var(
                            $host,
                            \FILTER_VALIDATE_IP,
                            \FILTER_FLAG_IPV4
                        ) === false
                ) {
                    throw new \InvalidArgumentException(
                        'IP address must be compliant with the '
                                    . '"IPv4address" of the "RFC 3986" standart.'
                    );
                }

                // Matching a domain name.
            } else {
                if (
                        !preg_match(
                            '/^([a-zA-Z0-9\-._~]|%[a-fA-F0-9]{2}|[!$&\'()*+,;=])*$/',
                            $host
                        )
                ) {
                    throw new \InvalidArgumentException(
                        'Host be compliant with the "RFC 3986" standart.'
                    );
                }
            }

            return strtolower($host);
        }
        return $host;
    }

    /**
     * Filter Uri port
     *
     * @param int|null $port
     *
     * @return int|null
     */
    protected function filterPort(?int $port): ?int
    {
        if ($port !== null) {
            if ($port < 1 || $port > 65535) {
                throw new \InvalidArgumentException(
                    'TCP or UDP port must be between 1 and 65535'
                );
            }
            return !$this->isStandardPort($this->scheme, $port) ? $port : null;
        }
        return $port;
    }

    /**
     * Filter Uri path
     * @param  string $path
     * @return string
     */
    protected function filterPath(string $path): string
    {
        if ($this->scheme === '' && strncmp($path, ':', 1) === 0) {
            throw new \InvalidArgumentException(
                'Path of a URI without a scheme cannot begin with a colon'
            );
        }

        $authority = $this->getAuthority();
        if ($authority === '' && strncmp($path, '//', 2) === 0) {
            throw new \InvalidArgumentException(
                'Path of a URI without an authority cannot begin with two slashes'
            );
        }

        if ($authority !== '' && $path !== '' && strncmp($path, '/', 1) !== 0) {
            throw new \InvalidArgumentException(
                'Path of a URI with an authority must be empty or begin with a slash'
            );
        }

        if ($path !== '' && $path !== '/') {
            if (
                    !preg_match(
                        '/^([a-zA-Z0-9\-._~]|%[a-fA-F0-9]{2}|[!$&\'()*+,;=]|\:|\@|\/|\%)*$/',
                        $path
                    )
            ) {
                throw new \InvalidArgumentException(
                    'Path must be compliant with the "RFC 3986" standart'
                );
            }
            return preg_replace_callback(
                '/(?:[^a-zA-Z0-9\-._~!$&\'()*+,;=:@\/%]++|%(?![a-fA-F0-9]{2}))/',
                function ($matches) {
                    return rawurlencode($matches[0]);
                },
                $path
            );
        }

        return $path;
    }

    /**
     * Filter Uri query
     * @param  string $query
     * @return string
     */
    protected function filterQuery(string $query): string
    {
        if ($query !== '') {
            if (
                    !preg_match(
                        '/^([a-zA-Z0-9\-._~]|%[a-fA-F0-9]{2}|[!$&\'()*+,;=]|\:|\@|\/|\?|\%)*$/',
                        $query
                    )
            ) {
                throw new \InvalidArgumentException(
                    'Query must be compliant with the "RFC 3986" standart'
                );
            }
            return preg_replace_callback(
                '/(?:[^a-zA-Z0-9\-._~!$&\'()*+,;=:@\/?%]++|%(?![a-fA-F0-9]{2}))/',
                function ($matches) {
                    return rawurlencode($matches[0]);
                },
                $query
            );
        }

        return $query;
    }

    /**
     * Filter Uri fragment
     * @param  string $fragment
     * @return string
     */
    protected function filterFragment(string $fragment): string
    {
        if ($fragment !== '') {
            return preg_replace_callback(
                '/(?:[^a-zA-Z0-9\-._~!$&\'()*+,;=:@\/?%]++|%(?![a-fA-F0-9]{2}))/',
                function ($matches) {
                    return rawurlencode($matches[0]);
                },
                $fragment
            );
        }

        return $fragment;
    }

    /**
     * Check whether the port is standard for the given scheme
     * @param  string  $scheme the scheme
     * @param  int|null     $port   the port
     * @return boolean
     */
    protected function isStandardPort(string $scheme, ?int $port): bool
    {
        return $port === ($scheme === 'https' ? 443 : 80);
    }
}
