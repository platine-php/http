<?php

/**
 * Platine HTTP
 *
 * Platine HTTP Message is the implementation of PSR 7
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2020 Platine HTTP
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
 *  @file Request.php
 *
 *  The Request class is the representation of an outgoing, client-side request.
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

class Request extends Message implements RequestInterface
{

    /**
     * The request target
     * @var string
     */
    protected string $requestTarget = '';

    /**
     * The request method
     * @var string
     */
    protected string $method = 'GET';

    /**
     * The request Uri
     * @var UriInterface
     */
    protected ?UriInterface $uri;

    /**
     * Create new request instance
     * @param string $method the HTTP request method
     * @param UriInterface|string|null $uri    the request Uri
     */
    public function __construct(string $method = 'GET', $uri = null)
    {
        $this->method = $this->filterMethod($method);
        if ($uri === null) {
            $uri = new Uri();
        } elseif (is_string($uri)) {
            $uri = new Uri($uri);
        }
        $this->uri = $uri;

        if ($this->protocolVersion === '1.1') {
            $this->headers['host'] = [$this->getHostHeader()];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null && $this->requestTarget !== '') {
            return $this->requestTarget;
        }

        if ($this->uri !== null) {
            $requestTarget = $this->uri->getPath();
            $query = $this->uri->getQuery();
            if ($query !== '') {
                $requestTarget .= '?' . $query;
            }
            if ($requestTarget !== '') {
                return $requestTarget;
            }
        }

        return '/';
    }

    /**
     * {@inheritdoc}
     */
    public function withRequestTarget($requestTarget): self
    {
        $that = clone $this;
        $that->requestTarget = $requestTarget;

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod(string $method): self
    {
        $that = clone $this;
        $that->method = $method;

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): UriInterface
    {
        if ($this->uri === null) {
            $this->uri = new Uri();
        }
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): self
    {
        $that = clone $this;
        $that->uri = $uri;

        if ($preserveHost && $that->hasHeader('Host')) {
            return $that;
        }
        return $that->withHeader('Host', $that->getHostHeader());
    }

    /**
     * Return the string representation of the request
     * @return string
     */
    public function __toString(): string
    {
        $request = sprintf(
            "%s %s HTTP/%s\r\n",
            $this->getMethod(),
            $this->getRequestTarget(),
            $this->getProtocolVersion()
        );
        foreach (array_keys($this->headers) as $header) {
            if (strtolower($header) === 'cookie') {
                $cookie = implode('; ', $this->getHeader('Cookie'));
                $request .= sprintf(
                    "%s: %s\r\n",
                    $header,
                    $cookie
                );
            } else {
                $request .= sprintf(
                    "%s: %s\r\n",
                    $header,
                    $this->getHeaderLine($header)
                );
            }
        }

        return sprintf(
            "%s\r\n%s",
            $request,
            $this->getBody()
        );
    }

    /**
     * Return the "Host" header value
     *
     * @return string
     */
    protected function getHostHeader(): string
    {
        $host = $this->uri->getHost();
        if ($host !== '') {
            $port = $this->uri->getPort();
            if ($port !== null) {
                $host .= ':' . $port;
            }
        }
        return $host;
    }

    /**
     * Filter HTTP request method
     *
     * @param  string $method the method to filter
     * @return string
     */
    protected function filterMethod(string $method): string
    {
        if (!preg_match('/^[!#$%&\'*+\-.^_`|~0-9a-zA-Z]+$/', $method)) {
            throw new \InvalidArgumentException(sprintf(
                'HTTP Method %s must be compliant with '
                                    . 'the "RFC 7230" standart',
                $method
            ));
        }
        return $method;
    }
}
