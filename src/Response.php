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
 *  @file Response.php
 *
 *  The Response class is a representation of an outgoing, server-side response.
 *
 *  @package    Platine\Http
 *  @author Platine Developers Team
 *  @copyright  Copyright (c) 2020
 *  @license    http://opensource.org/licenses/MIT  MIT License
 *  @link   https://www.platine-php.com
 *  @version 1.0.0
 *  @filesource
 */

declare(strict_types=1);

namespace Platine\Http;

use InvalidArgumentException;

class Response extends Message implements ResponseInterface
{
    /**
     * The reason phrases.
     */
    protected const REASON_PHRASES = [
        //
        // Informational
        //
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        //
        // Successful
        //
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        //
        // Redirection
        //
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        //
        // Client Error
        //
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        //
        // Server Error
        //
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required'
    ];

    /**
     * The Response HTTP status code
     * @var int
     */
    protected int $statusCode = 200;

    /**
     * The Reason phrase
     * @var string
     */
    protected string $reasonPhrase = 'OK';

    /**
     * Create new Response instance
     * @param int $statusCode
     * @param string      $reasonPhrase
     */
    public function __construct(int $statusCode = 200, string $reasonPhrase = '')
    {
        $this->statusCode = $this->filterStatusCode($statusCode);
        if ($reasonPhrase === '') {
            $reasonPhrase = isset(static::REASON_PHRASES[$this->statusCode])
                    ? static::REASON_PHRASES[$this->statusCode]
                    : '';
        }
        $this->reasonPhrase = $reasonPhrase;

        // Set common headers
        $this->setCommonHeaders();
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        $that = clone $this;
        $that->statusCode = $this->filterStatusCode($code);

        if ($reasonPhrase === '') {
            $reasonPhrase = isset(static::REASON_PHRASES[$that->statusCode])
                    ? static::REASON_PHRASES[$that->statusCode]
                    : '';
        }
        $that->reasonPhrase = $reasonPhrase;

        return $that;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    /**
     * The string representation of this response
     * @return string
     */
    public function __toString(): string
    {
        $response = sprintf(
            "HTTP/%s %d %s\r\n",
            $this->getProtocolVersion(),
            $this->getStatusCode(),
            $this->getReasonPhrase()
        );

        foreach (array_keys($this->headers) as $header) {
            if (strtolower($header) === 'set-cookie') {
                foreach ($this->getHeader('Set-Cookie') as $setCookie) {
                    $response .= sprintf(
                        "%s: %s\r\n",
                        $header,
                        $setCookie
                    );
                }
            } else {
                $response .= sprintf(
                    "%s: %s\r\n",
                    $header,
                    $this->getHeaderLine($header)
                );
            }
        }
        return sprintf(
            "%s\r\n%s",
            $response,
            $this->getBody()
        );
    }

    /**
     * Filter status code
     * @param  int    $code
     * @return int
     */
    protected function filterStatusCode(int $code): int
    {
        if ($code === 306) {
            throw new InvalidArgumentException(
                'Invalid status code! Status code 306 is unused.'
            );
        }

        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException(
                'Invalid status code! Status code must be between 100 and 599.'
            );
        }

        return $code;
    }

    /**
     * Set the common headers to be used
     * @return $this
     */
    protected function setCommonHeaders(): self
    {
        $this->withAddedHeader(
            'Content-Security-Policy',
            'default-src \'self\'; frame-ancestors \'self\'; form-action \'self\';'
        );

        $this->withAddedHeader('X-Content-Type-Options', 'nosniff');

        return $this;
    }
}
