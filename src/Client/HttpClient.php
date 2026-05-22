<?php

/**
 * Platine HTTP
 *
 * Platine HTTP Message is the implementation of PSR 7
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2020 Platine HTTP
 * Copyright (c) 2011 - 2017 rehyved.com
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
 *  @file HttpClient.php
 *
 *  The Http Client class
 *
 *  @package    Platine\Http\Client
 *  @author Platine Developers team
 *  @copyright  Copyright (c) 2020
 *  @license    http://opensource.org/licenses/MIT  MIT License
 *  @link   https://www.platine-php.com
 *  @version 1.0.0
 *  @filesource
 */

declare(strict_types=1);

namespace Platine\Http\Client;

use CURLFile;
use CurlHandle;
use InvalidArgumentException;
use Platine\Http\Client\Exception\HttpClientException;
use Platine\Stdlib\Helper\Json;
use RuntimeException;

/**
 * @class HttpClient
 * @package Platine\Http\Client
 */
class HttpClient
{
    /**
     * The base URL
     * @var string
     */
    protected string $baseUrl;

    /**
     * The request headers
     * @var array<string, array<int, mixed>>
     */
    protected array $headers = [];

    /**
     * The request parameters
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * The request cookies
     * @var array<string, mixed>
     */
    protected array $cookies = [];

    /**
     * Multipart files
     * @var array<string, CURLFile|array<CURLFile>>
     */
    protected array $files = [];

    /**
     * File for direct upload (as request body)
     * @var CURLFile|null
     */
    protected ?CURLFile $directFile = null;

    /**
     * Temporary files to be clear later on destruct
     * @var array<resource>
     */
    protected array $tempFiles = [];

    /**
     * Indicating the number of seconds to use as a timeout for HTTP requests
     * @var int
     */
    protected int $timeout = 30;

    /**
     * Indicating if the validity of SSL certificates should be enforced in HTTP requests
     * @var bool
     */
    protected bool $verifySslCertificate = true;

    /**
     * The username to use for basic authentication
     * @var string
     */
    protected string $username = '';

    /**
     * The password to use for basic authentication
     * @var string
     */
    protected string $password = '';

    /**
     * Whether to enable debugging
     * @var bool
     */
    protected bool $debug = false;

    /**
     * The debug stream to use. If null will use STDERR
     * @var resource|null
     */
    protected $debugStream = null;

    /**
     * Create new instance
     * @param string $baseUrl
     */
    public function __construct(string $baseUrl = '')
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        foreach ($this->tempFiles as $tempFile) {
            if (is_resource($tempFile)) {
                fclose($tempFile);
            }
        }
    }

    /**
     * Enable debug
     * @param bool $status
     * @param resource|null $stream
     * @return $this
     */
    public function debug(bool $status, $stream = null): self
    {
        $this->debug = $status;
        $this->debugStream = $stream;

        return $this;
    }

    /**
     * Set the base URL
     * @param string $baseUrl
     * @return $this
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * Return the base URL
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }


    /**
     * Add request header
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function header(string $name, mixed $value): self
    {
        if (array_key_exists($name, $this->headers) === false) {
            $this->headers[$name] = [];
        }
        $this->headers[$name][] = $value;

        // Remove duplicate value
        $this->headers[$name] = array_unique($this->headers[$name]);

        return $this;
    }

    /**
     * Add multiple request headers
     * @param array<string, mixed> $headers
     * @return $this
     */
    public function headers(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }

        return $this;
    }

    /**
     * Add request query parameter
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function parameter(string $name, mixed $value): self
    {
        $this->parameters[$name] = $value;

        return $this;
    }


    /**
     * Add multiple request parameter
     * @param array<string, mixed> $parameters
     * @return $this
     */
    public function parameters(array $parameters): self
    {
        foreach ($parameters as $name => $value) {
            $this->parameter($name, $value);
        }

        return $this;
    }

    /**
     * Add request cookie
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function cookie(string $name, mixed $value): self
    {
        $this->cookies[$name] = $value;

        return $this;
    }

    /**
     * Add multiple request cookie
     * @param array<string, mixed>|null $cookies
     * @return $this
     */
    public function cookies(?array $cookies = null): self
    {
        if ($cookies === null) {
            $cookies = $_COOKIE;
        }

        foreach ($cookies as $name => $value) {
            $this->cookie($name, $value);
        }

        return $this;
    }

    /**
     * Add file for direct upload (as request body)
     * @param string|array{data:string, filename:string, mimetype:string}|CURLFile $file
     * @return $this
     */
    public function addFileAsBody(string|array|CURLFile $file): self
    {
        $this->directFile = $this->createCurlFile($file);
        return $this;
    }

    /**
     * Add multipart file
     * @param string $name
     * @param string|array{data:string, filename:string, mimetype:string}|CURLFile $file
     * @return $this
     */
    public function addFile(
        string $name,
        string|array|CURLFile $file
    ): self {
        $this->files[$name] = $this->createCurlFile($file);
        return $this;
    }

    /**
     * Add multiple multipart file
     * @param string $name
     * @param array<string|array{data:string, filename:string, mimetype:string}|CURLFile> $files
     * @return $this
     */
    public function addFiles(string $name, array $files): self
    {
        $list = [];
        foreach ($files as $index => $file) {
            $list[$index] = $this->createCurlFile($file);
        }

        $this->files[$name] = $list;

        return $this;
    }

    /**
     * Set the basic authentication to use on the request
     * @param string $usename
     * @param string $password
     * @return $this
     */
    public function basicAuthentication(string $usename, string $password = ''): self
    {
        $this->username = $usename;
        $this->password = $password;

        return $this;
    }

    /**
     * Set the request timeout
     * @param int $timeout
     * @return $this
     */
    public function timeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Controls if the validity of SSL certificates should be verified.
     * WARNING: This should never be done in a production setup and should be used for debugging only.
     * @param bool $verifySslCertificate
     * @return self
     */
    public function verifySslCertificate(bool $verifySslCertificate): self
    {
        $this->verifySslCertificate = $verifySslCertificate;

        return $this;
    }

    /**
     * Set request content type
     * @param string $contentType
     * @return $this
     */
    public function contentType(string $contentType): self
    {
        return $this->header('Content-Type', $contentType);
    }

    /**
     * Set the request content type as JSON
     * @return $this
     */
    public function json(): self
    {
        $this->contentType('application/json');

        return $this;
    }

    /**
     * Set the request content type as form
     * @return $this
     */
    public function form(): self
    {
        $this->contentType('application/x-www-form-urlencoded');

        return $this;
    }

    /**
     * Set the request content type as multipart
     * @return $this
     */
    public function multipart(): self
    {
        $this->contentType('multipart/form-data');

        return $this;
    }

    /**
     * Set request accept content type
     * @param string $contentType
     * @return $this
     */
    public function accept(string $contentType): self
    {
        return $this->header('Accept', $contentType);
    }

    /**
     * Set request authorization header
     * @param string $scheme the scheme to use in the value of the Authorization header (e.g. Bearer)
     * @param string $value the value to set for the the Authorization header
     * @return $this
     */
    public function authorization(string $scheme, string $value): self
    {
        return $this->header('Authorization', sprintf('%s %s', $scheme, $value));
    }

    /**
     * Clear all files
     * @return $this
     */
    public function clearFiles(): self
    {
        $this->files = [];
        $this->directFile = null;
        return $this;
    }

    /**
     * Return the headers
     * @return array<string, array<int, mixed>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Return the parameters
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Return the cookies
     * @return array<string, mixed>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Return the files
     * @return array<string, CURLFile|array<CURLFile>>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Return the timeout
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Whether to verify SSL certificate
     * @return bool
     */
    public function isVerifySslCertificate(): bool
    {
        return $this->verifySslCertificate;
    }

    /**
     * Return the username for basic authentication
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Return the password for basic authentication
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Execute the request as a GET request to the specified path
     * @param string $path
     * @return HttpResponse
     */
    public function get(string $path = ''): HttpResponse
    {
        return $this->request($path, HttpMethod::GET);
    }

    /**
     * Execute the request as a POST request to the specified path
     * @param string $path
     * @param mixed $body the request body
     * @return HttpResponse
     */
    public function post(string $path = '', mixed $body = null): HttpResponse
    {
        return $this->request($path, HttpMethod::POST, $body);
    }

    /**
     * Execute the request as a PUT request to the specified path
     * @param string $path
     * @param mixed $body the request body
     * @return HttpResponse
     */
    public function put(string $path = '', mixed $body = null): HttpResponse
    {
        return $this->request($path, HttpMethod::PUT, $body);
    }

    /**
     * Execute the request as a DELETE request to the specified path
     * @param string $path
     * @param mixed $body the request body
     * @return HttpResponse
     */
    public function delete(string $path = '', mixed $body = null): HttpResponse
    {
        return $this->request($path, HttpMethod::DELETE, $body);
    }

    /**
     * Execute the request as a HEAD request to the specified path
     * @param string $path
     * @param mixed $body the request body
     * @return HttpResponse
     */
    public function head(string $path = '', mixed $body = null): HttpResponse
    {
        return $this->request($path, HttpMethod::HEAD, $body);
    }

    /**
     * Execute the request as a TRACE request to the specified path
     * @param string $path
     * @param mixed $body the request body
     * @return HttpResponse
     */
    public function trace(string $path = '', mixed $body = null): HttpResponse
    {
        return $this->request($path, HttpMethod::TRACE, $body);
    }

    /**
     * Execute the request as a OPTIONS request to the specified path
     * @param string $path
     * @param mixed $body the request body
     * @return HttpResponse
     */
    public function options(string $path = '', mixed $body = null): HttpResponse
    {
        return $this->request($path, HttpMethod::OPTIONS, $body);
    }

    /**
     * Execute the request as a CONNECT request to the specified path
     * @param string $path
     * @param mixed $body the request body
     * @return HttpResponse
     */
    public function connect(string $path = '', mixed $body = null): HttpResponse
    {
        return $this->request($path, HttpMethod::CONNECT, $body);
    }

    /**
     * Construct the HTTP request and sends it using the provided method and request body
     * @param string $path
     * @param non-empty-string $method
     * @param mixed $body
     * @return HttpResponse
     */
    public function request(
        string $path,
        string $method = HttpMethod::GET,
        mixed $body = null
    ): HttpResponse {
        $ch = curl_init();

        $this->processUrl($path, $ch);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Do body first as this might add additional headers
        $this->processBody($ch, $body);
        $this->processHeaders($ch);
        $this->processCookies($ch);

        return $this->send($ch);
    }

    /**
     * Send the request
     * @param CurlHandle $ch the cURL handle
     * @return HttpResponse
     */
    protected function send(CurlHandle $ch): HttpResponse
    {
        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
            if (strpos($header, ':') !== false) {
                list($name, $value) = explode(':', $header);
                if (array_key_exists($name, $responseHeaders) === false) {
                    $responseHeaders[$name] = [];
                }
                $responseHeaders[$name][] = trim($value);
            }

            return strlen($header);
        });

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Ensure we are coping with 300 (redirect) responses
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Set request timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        // Set verification of SSL certificates
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySslCertificate);

        if (!empty($this->username)) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, sprintf('%s:%s', $this->username, $this->password));
        }

        if ($this->debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        if ($this->debugStream !== null) {
            curl_setopt($ch, CURLOPT_STDERR, $this->debugStream);
        }

        $response = curl_exec($ch);
        $requestInfo = curl_getinfo($ch);
        $error = curl_error($ch);
        $errorCode = curl_errno($ch);
        if (!empty($error)) {
            throw new HttpClientException($error, $errorCode);
        }

        return new HttpResponse($requestInfo, $responseHeaders, $response, $error);
    }

    /**
     * Process URL
     * @param string $path
     * @param CurlHandle $ch the cURL handle
     * @return void
     */
    protected function processUrl(string $path, CurlHandle $ch): void
    {
        /** @var non-empty-string $url */
        $url = $this->buildUrl($path);
        curl_setopt($ch, CURLOPT_URL, $url);
    }

    /**
     * Build the request full URL
     * @param string $path
     * @return string
     */
    protected function buildUrl(string $path): string
    {
        if (empty($this->baseUrl)) {
            throw new InvalidArgumentException('Base URL can not be empty or null');
        }

        $url = $this->baseUrl;
        if (!empty($path)) {
            $url .= $path;
        }

        if (count($this->parameters) > 0) {
            $url .= '?' . http_build_query($this->parameters);
        }

        // Clean url
        // remove double slashes, except after scheme
        $cleanUrl = (string) preg_replace('/([^:])(\/{2,})/', '$1/', $url);
        // convert arrays with indexes to arrays without
        // (i.e. parameter[0]=1 -> parameter[]=1)
        $finalUrl = (string) preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $cleanUrl);

        return $finalUrl;
    }

    /**
     * Process the request headers
     * @param CurlHandle $ch the cURL handle
     * @return void
     */
    protected function processHeaders(CurlHandle $ch): void
    {
        $headers = [];
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                $headers[] = sprintf('%s: %s', $name, $value);
            }
        }

        if (count($headers) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    }

    /**
     * Process the request cookies
     * @param CurlHandle $ch the cURL handle
     * @return void
     */
    protected function processCookies(CurlHandle $ch): void
    {
        $cookies = [];
        foreach ($this->cookies as $name => $value) {
            $cookies[] = sprintf('%s=%s', $name, $value);
        }
        if (count($cookies) > 0) {
            curl_setopt($ch, CURLOPT_COOKIE, implode(';', $cookies));
        }
    }

    /**
     * Process the request body
     * @param CurlHandle $ch the cURL handle
     * @param array<mixed>|object|string|null $body the request body
     * @return void
     */
    protected function processBody(CurlHandle $ch, array|object|string|null $body = null): void
    {
        $contentType = $this->headers['Content-Type'][0] ?? '';
        // Case 1: direct upload (file as request body)
        if ($this->directFile !== null) {
            $this->handleDirectFileBody($ch, $this->directFile);
            $this->directFile = null;
            return;
        }

        // Case 2: Multipart files + payload
        if (stripos($contentType, 'multipart/form-data') !== false || count($this->files) > 0) {
            if (stripos($contentType, 'multipart/form-data') === false && count($this->files) > 0) {
                $this->multipart();
            }

            $multipartData = $this->buildMultipartData($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipartData);
            return;
        }
        // Case 3: JSON
        if (stripos($contentType, 'application/json') !== false) {
            $body = Json::encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            return;
        }

        // Case 4: Form urlencoded
        if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
            $body = http_build_query($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            return;
        }
        // Case 5: Simple Body
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }

    /**
     * Handle direct file upload (as request body)
     * @param CurlHandle $ch
     * @param CURLFile $curlFile
     * @return void
     */
    protected function handleDirectFileBody(CurlHandle $ch, CURLFile $curlFile): void
    {
        if (!isset($this->headers['Content-Type'][0])) {
            $this->contentType('application/octet-stream');
        }
        $filePath = $curlFile->getFilename();

        $fp = fopen($filePath, 'rb');
        if ($fp === false) {
            throw new RuntimeException(sprintf('Can not open file [%s]', $filePath));
        }

        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_READFUNCTION, function ($curl, $fd, $length) use ($fp) {
            return fread($fp, $length);
        });
        curl_setopt($ch, CURLOPT_INFILESIZE, (int) filesize($filePath));

        $this->tempFiles[] = $fp;
    }

    /**
     * Build multipart data with payload
     * @param array<mixed>|object|string|null $body
     * @return array<mixed>
     */
    protected function buildMultipartData(array|object|string|null $body): array
    {
        $multipartData = [];

        // Body payload
        if (is_array($body)) {
            $this->flattenMultipartData($multipartData, $body);
        }

        // Add multipart files
        foreach ($this->files as $key => $file) {
            if (is_array($file)) {
                // Multiple files: documents[0], documents[1], etc.
                foreach ($file as $index => $curlFile) {
                    $multipartData[$key . '[' . $index . ']'] = $curlFile;
                }
            } else {
                // Unique file
                $multipartData[$key] = $file;
            }
        }

        return $multipartData;
    }

    /**
     * Flatten multipart data
     * @param array<mixed> &$result
     * @param array<mixed> $data
     * @param string $prefix
     * @return void
     */
    protected function flattenMultipartData(
        array &$result,
        array $data,
        string $prefix = ''
    ): void {
        foreach ($data as $key => $value) {
            $fieldName = $prefix ? $prefix . '[' . $key . ']' : $key;

            if (is_array($value)) {
                // Recursive array -> field[subkey]
                $this->flattenMultipartData($result, $value, $fieldName);
            } else {
                // Simple value
                $result[$fieldName] = $value;
            }
        }
    }

    /**
     * Create CURL file
     * @param string|array{data:string, filename:string, mimetype:string}|CURLFile $file
     * @return CURLFile
     * @throws InvalidArgumentException
     */
    protected function createCurlFile(string|array|CURLFile $file): CURLFile
    {
        if ($file instanceof CURLFile) {
            return $file;
        }

        if (is_string($file) && file_exists($file) && is_file($file)) {
            return new CURLFile($file);
        }

        if (is_array($file)) {
            $tempPath = $this->createTempFile($file['data']);

            $curlFile = new CURLFile(
                $tempPath,
                $file['mimetype'],
                $file['filename']
            );

            return $curlFile;
        }

        throw new InvalidArgumentException('Invalid file source');
    }

    /**
     * Create temporary file
     * @param string $data
     * @return string
     */
    protected function createTempFile(string $data): string
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'curl_');

        file_put_contents($tempFile, $data);


        return $tempFile;
    }
}
