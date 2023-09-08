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
 *  @file UploadedFile.php
 *
 *  The UploadedFile class that represent the data for file upload
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

class UploadedFile implements UploadedFileInterface
{
    /**
     * The uploaded file name
     * @var string
     */
    protected ?string $filename = null;

    /**
     * Whether the uploaded file is moved
     * @var bool
     */
    protected bool $moved = false;

    /**
     * The uploaded file stream
     * @var StreamInterface
     */
    protected ?StreamInterface $stream = null;

    /**
     *  The uploaded file size
     * @var int|null
     */
    protected ?int $size;

    /**
     *  The uploaded file error
     * @var int
     */
    protected int $error = \UPLOAD_ERR_OK;

    /**
     * The uploaded file client name
     * @var string|null
     */
    protected ?string $clientFilename;

    /**
     * The uploaded file client media type
     * @var string|null
     */
    protected ?string $clientMediaType;

    /**
     * Create new uploaded file instance
     *
     * @param string|StreamInterface $filenameOrStream the filename or stream
     * @param int|null $size the upload file size
     * @param int $error the upload error code
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     */
    public function __construct(
        $filenameOrStream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        if ($filenameOrStream instanceof StreamInterface) {
            if (!$filenameOrStream->isReadable()) {
                throw new \InvalidArgumentException('Stream is not readable');
            }
            $this->stream = $filenameOrStream;
            $this->size = $size ? $size : $filenameOrStream->getSize();
        } else {
            $this->filename = $filenameOrStream;
            $this->size = $size;
        }

        $this->error = $this->filterError($error);
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
    }

    /**
    * Create uploaded file from global variable $_FILES
    * @return array<mixed>
    */
    public static function createFromGlobals(): array
    {
        return static::normalize($_FILES);
    }

    /**
     * {@inheritdoc}
     */
    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new \RuntimeException('Stream is not avaliable! Uploaded file is moved');
        }

        if ($this->stream === null) {
            $this->stream = new Stream($this->filename);
        }

        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new \RuntimeException('Uploaded file is already moved');
        }

        $targetPath = $this->filterTargetPath($targetPath);
        if ($this->filename !== null) {
            if (php_sapi_name() === 'cli') {
                if (rename($this->filename, $targetPath) === false) {
                    throw new \RuntimeException('Unable to rename the uploaded file');
                }
            } else {
                if (
                        is_uploaded_file($this->filename) === false || move_uploaded_file(
                            $this->filename,
                            $targetPath
                        ) === false
                ) {
                    throw new \RuntimeException('Unable to move the uploaded file');
                }
            }
        } else {
            $stream = $this->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $dest = new Stream($targetPath);
            $bufferSize = 8192;
            while (!$stream->eof()) {
                if (!$dest->write($stream->read($bufferSize))) {
                    break;
                }
            }
            $stream->close();
        }
        $this->moved = true;
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
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    /**
     * Normalize files according to standard
     * @param  array  $files
     * @return array
     */
    public static function normalize(array $files): array
    {
        $normalized = [];
        foreach ($files as $name => $info) {
            if ($info instanceof UploadedFileInterface) {
                $normalized[$name] = $info;
                continue;
            }

            if (!isset($info['error'])) {
                if (is_array($info)) {
                    $normalized[$name] = static::normalize($info);
                }
                continue;
            }

            $normalized[$name] = [];
            if (!is_array($info['error'])) {
                $normalized[$name] = new static(
                    isset($info['tmp_name']) ? $info['tmp_name'] : '',
                    !empty($info['size']) ? $info['size'] : null,
                    $info['error'],
                    !empty($info['name']) ? $info['name'] : null,
                    !empty($info['type']) ? $info['type'] : null,
                );
            } else {
                $nestedInfo = [];
                foreach (array_keys($info['error']) as $key) {
                    $nestedInfo[$key]['tmp_name'] = isset($info['tmp_name'][$key]) ? $info['tmp_name'][$key] : '';
                    $nestedInfo[$key]['name'] = isset($info['name'][$key]) ? $info['name'][$key] : '';
                    $nestedInfo[$key]['size'] = isset($info['size'][$key]) ? $info['size'][$key] : null;
                    $nestedInfo[$key]['error'] = isset($info['error'][$key]) ? $info['error'][$key] : 0;
                    $nestedInfo[$key]['type'] = isset($info['type'][$key]) ? $info['type'][$key] : '';

                    $normalized[$name] = static::normalize($nestedInfo);
                }
            }
        }

        return $normalized;
    }

    /**
     * Filter the uploded file error
     * @param  int    $error
     * @return int
     */
    protected function filterError(int $error): int
    {
        $validErrors = [
            \UPLOAD_ERR_OK,
            \UPLOAD_ERR_INI_SIZE,
            \UPLOAD_ERR_FORM_SIZE,
            \UPLOAD_ERR_PARTIAL,
            \UPLOAD_ERR_NO_FILE,
            \UPLOAD_ERR_NO_TMP_DIR,
            \UPLOAD_ERR_CANT_WRITE,
            \UPLOAD_ERR_EXTENSION
        ];

        if (!in_array($error, $validErrors)) {
            throw new \InvalidArgumentException('Upload error code must be a PHP file upload error.');
        }

        return $error;
    }

    /**
     * Filter the uploded file target path
     * @param  string    $targetPath
     * @return string
     */
    protected function filterTargetPath(string $targetPath): string
    {
        if ($targetPath === '') {
            throw new \InvalidArgumentException('Target path can not be empty.');
        }
        return $targetPath;
    }
}
