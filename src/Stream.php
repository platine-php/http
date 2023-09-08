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
 *  @file Stream.php
 *
 *  The Default or base Stream class
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

use Exception;
use InvalidArgumentException;
use RuntimeException;

class Stream implements StreamInterface
{
    /**
     * The writable stream modes.
     */
    protected const MODES_WRITE = ['r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'];

    /**
     * The readable stream modes.
     */
    protected const MODES_READ = ['r', 'r+', 'w+', 'a+', 'x+', 'c+'];

    /**
     * The stream resource
     * @var resource|null
     */
    protected $resource;

    /**
     * The stream size
     * @var int|null
     */
    protected ?int $size;

    /**
     * Whether the stream is seekable
     * @var boolean
     */
    protected bool $seekable = false;

    /**
     * Whether the stream is writable
     * @var boolean
     */
    protected bool $writable = false;

    /**
     * Whether the stream is readable
     * @var boolean
     */
    protected bool $readable = false;

    /**
     * Create new Stream
     * @param string|resource $content the filename or resource instance
     * @param string $mode    the stream mode
     * @param array<string, mixed>  $options the stream options
     */
    public function __construct($content = '', string $mode = 'r+', array $options = [])
    {
        if (is_string($content)) {
            if (is_file($content) || strpos($content, 'php://') === 0) {
                $mode = $this->filterMode($mode);
                $resource = fopen($content, $mode);
                if ($resource === false) {
                    throw new RuntimeException(sprintf('Unable to create a stream from file [%s] !', $content));
                }
                $this->resource = $resource;
            } else {
                $resource = fopen('php://temp', 'r+');
                if ($resource === false || fwrite($resource, $content) === false) {
                    throw new RuntimeException('Unable to create a stream from string');
                }
                $this->resource = $resource;
            }
        } elseif (is_resource($content)) {
            $this->resource = $content;
        } else {
            throw new InvalidArgumentException('Stream resource must be valid PHP resource');
        }

        if (isset($options['size']) && is_int($options['size']) && $options['size'] >= 0) {
            $this->size = $options['size'];
        } else {
            $fstat = fstat($this->resource);
            if ($fstat === false) {
                $this->size = null;
            } else {
                $this->size = !empty($fstat['size']) ? $fstat['size'] : null;
            }
        }

        $meta = stream_get_meta_data($this->resource);
        $this->seekable = isset($options['seekable'])
                            && is_bool($options['seekable'])
                                ? $options['seekable']
                                : (!empty($meta['seekable'])
                                    ? $meta['seekable']
                                    : false
                                   );

        if (isset($options['writable']) && is_bool($options['writable'])) {
            $this->writable = $options['writable'];
        } else {
            foreach (static::MODES_WRITE as $mode) {
                if (strncmp($meta['mode'], $mode, strlen($mode)) === 0) {
                    $this->writable = true;
                    break;
                }
            }
        }

        if (isset($options['readable']) && is_bool($options['readable'])) {
            $this->readable = $options['readable'];
        } else {
            foreach (static::MODES_READ as $mode) {
                if (strncmp($meta['mode'], $mode, strlen($mode)) === 0) {
                    $this->readable = true;
                    break;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        try {
            if ($this->seekable) {
                $this->rewind();
            }
            return $this->getContents();
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->resource !== null && fclose($this->resource)) {
            $this->detach();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        $resource = $this->resource;
        if ($resource !== null) {
            $this->resource = null;
            $this->size = null;
            $this->seekable = false;
            $this->writable = false;
            $this->readable = false;
        }
        return $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream resource is detached');
        }
        $position = ftell($this->resource);
        if ($position === false) {
            throw new RuntimeException('Unable to tell the current position of the stream read/write pointer');
        }

        return $position;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, $whence = SEEK_SET): void
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream resource is detached');
        }

        if (!$this->seekable) {
            throw new RuntimeException('Stream is not seekable');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Can not seek to a position in the stream');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $string): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream resource is detached');
        }

        if (!$this->writable) {
            throw new RuntimeException('Stream is not writable');
        }
        $bytes = fwrite($this->resource, $string);

        if ($bytes === false) {
            throw new RuntimeException('Unable to write data to the stream');
        }

        $fstat = fstat($this->resource);
        if ($fstat === false) {
            $this->size = null;
        } else {
            $this->size = !empty($fstat['size']) ? $fstat['size'] : null;
        }
        return $bytes;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $length): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream resource is detached');
        }

        if (!$this->readable) {
            throw new RuntimeException('Stream is not readable');
        }

        $data = fread($this->resource, $length);
        if ($data === false) {
            throw new RuntimeException('Unable to read data from the stream');
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents(): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream resource is detached');
        }

        if (!$this->readable) {
            throw new RuntimeException('Stream is not readable');
        }

        $contents = stream_get_contents($this->resource);

        if ($contents === false) {
            throw new RuntimeException('Unable to get contents of the stream');
        }

        return $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(string $key = null)
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream resource is detached');
        }

        $meta = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $meta;
        }
        return !empty($meta[$key]) ? $meta[$key] : null;
    }

    /**
     * Check if the given mode is valid
     * @param  string $mode the mode
     * @return string
     * @throws InvalidArgumentException
     */
    protected function filterMode(string $mode): string
    {
        if (
                !in_array($mode, static::MODES_WRITE) && !in_array($mode, static::MODES_READ)
        ) {
            throw new InvalidArgumentException(sprintf('Invalid mode %s', $mode));
        }
        return $mode;
    }
}
