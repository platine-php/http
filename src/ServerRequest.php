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
 *  @file ServerRequest.php
 *
 *  The ServerRequest class is the representation of an incoming, server-side HTTP request.
 *
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

use InvalidArgumentException;

class ServerRequest extends Request implements ServerRequestInterface
{

    /**
     * The array of servers params
     * @var array<string, mixed>
     */
    protected array $serverParams = [];

    /**
     * The array of cookie params
     * @var array<string, mixed>
     */
    protected array $cookieParams = [];

    /**
     * The array of query params
     * @var array<string, mixed>
     */
    protected array $queryParams = [];

    /**
     * The array of uploaded files
     * @var array<string, mixed>
     */
    protected array $uploadedFiles = [];

    /**
     * The parse body content
     * @var object|array<string, mixed>|null
     */
    protected $parsedBody;

    /**
     * The array of request attributes
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Create new ServerRequest object
     * @param string $method       the HTTP request method
     * @param UriInterface|string|null $uri
     * @param array<string, mixed>  $serverParams the array of server params
     */
    public function __construct(
        string $method = 'GET',
        $uri = null,
        array $serverParams = []
    ) {
        $this->serverParams = $serverParams;
        parent::__construct($method, $uri);
    }

    /**
     * Create instance using global variables
     * @return ServerRequest
     * @throws InvalidArgumentException
     */
    public static function createFromGlobals(): ServerRequest
    {
        $method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $protocolVersion = '1.1';
        if (!empty($_SERVER['SERVER_PROTOCOL'])) {
            $parts = explode('/', $_SERVER['SERVER_PROTOCOL'], 2);
            if (!empty($parts[1]) && preg_match('/^\d\.\d$/', $parts[1])) {
                $protocolVersion = $parts[1];
            }
        }

        $uri = Uri::createFromGlobals();
        $uploadedFiles = UploadedFile::createFromGlobals();

        $request = (new self($method, $uri, $_SERVER))
                ->withoutHeader('Host')
                ->withProtocolVersion($protocolVersion)
                ->withQueryParams($_GET)
                ->withParsedBody($_POST)
                ->withCookieParams($_COOKIE)
                ->withUploadedFiles($uploadedFiles);

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headerNameParts = array_map('ucfirst', explode('-', $headerName));

                $headerName = implode('-', $headerNameParts);
                $headerValues = array_map('trim', explode(',', $value));

                $request = $request->withAddedHeader($headerName, $headerValues);
            }
        }

        if ($protocolVersion === '1.1' && !$request->hasHeader('Host')) {
            throw new InvalidArgumentException(
                'Invalid request! "HTTP/1.1" request must contain a "Host" header'
            );
        }

        return $request->withBody(new Stream('php://input'));
    }

    /**
     * {@inheritdoc}
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookieParams(array $cookies): self
    {
        $that = clone $this;
        $that->cookieParams = $cookies;

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryParams(array $query): self
    {
        $that = clone $this;
        $that->queryParams = $query;

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function withUploadedFiles(array $uploadedFiles): self
    {
        $that = clone $this;
        $that->uploadedFiles = $this->filterUploadedFiles($uploadedFiles);

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * {@inheritdoc}
     */
    public function withParsedBody($data): self
    {
        $that = clone $this;
        $that->parsedBody = $this->filterParsedBody($data);

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute(string $name, $default = null)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function withAttribute(string $name, $value): self
    {
        $that = clone $this;
        $that->attributes[$name] = $value;

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutAttribute(string $name): self
    {
        $that = clone $this;
        if (isset($that->attributes[$name])) {
            unset($that->attributes[$name]);
        }

        return $that;
    }

    /**
     * Filter the uploaded files
     * @param  array  $uploadedFiles the list of uploaded file
     * @return array
     */
    protected function filterUploadedFiles(array $uploadedFiles): array
    {
        foreach ($uploadedFiles as $uploadedFile) {
            if (is_array($uploadedFile)) {
                $this->filterUploadedFiles($uploadedFile);
            } elseif (!$uploadedFile instanceof UploadedFileInterface) {
                throw new InvalidArgumentException('Invalid structure of uploaded files tree, 
                                                    each uploaded file must be an instance of UploadedFileInterface');
            }
        }

        return $uploadedFiles;
    }

    /**
     * Filter the parsed body content
     * @param  object|array|null $data the parsed body
     * @return mixed
     */
    protected function filterParsedBody($data)
    {
        if ($data !== null && !is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException('Invalid parsed body! Parsed body must be an array or an object');
        }

        return $data;
    }
}
