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
 *  @file Message.php
 *
 *  The default or base HTTP Message
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

abstract class Message implements MessageInterface
{

    /**
     * The message protocol version.
     * @var string
     */
    protected string $protocolVersion = '1.1';

    /**
     * The array of message headers
     * @var array
     */
    protected array $headers = [];

    /**
     * The message body.
     * @var StreamInterface
     */
    protected ?StreamInterface $body = null;

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion(string $version): self
    {
        $that = clone $this;
        $that->protocolVersion = $version;
        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $name => $values) {
            $name = implode('-', array_map('ucfirst', explode('-', strtolower($name))));
            $headers[$name] = $values;
        }
        return $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader(string $name): bool
    {
        $name = strtolower($name);
        return isset($this->headers[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader(string $name): array
    {
        $name = strtolower($name);
        return isset($this->headers[$name]) ? $this->headers[$name] : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine(string $name): string
    {
        $name = strtolower($name);
        return isset($this->headers[$name]) ? implode(', ', $this->headers[$name]) : '';
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader(string $name, $value): self
    {
        $that = clone $this;
        $name = strtolower($name);
        $that->headers[$name] = is_array($value) ? $value : [$value];

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader(string $name, $value): self
    {
        $that = clone $this;
        $name = strtolower($name);
        if (is_array($value)) {
            $value = array_values($value);
            $that->headers[$name] = isset($that->headers[$name]) ? array_merge($that->headers[$name], $value) : $value;
        } else {
            $that->headers[$name][] = $value;
        }
        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader(string $name): self
    {
        $that = clone $this;
        $name = strtolower($name);
        unset($that->headers[$name]);
        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(): StreamInterface
    {
        if ($this->body === null) {
            $this->body = new Stream();
        }
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body): self
    {
        $that = clone $this;
        $that->body = $body;
        $size = $body->getSize();

        if ($size === null) {
            $that = $that->withHeader('Transfer-Encoding', 'chunked');
            $that = $that->withoutHeader('Content-Length');
        } else {
            $that = $that->withHeader('Content-Length', (string) $size);
            $that = $that->withoutHeader('Transfer-Encoding');
        }

        return $that;
    }
}
