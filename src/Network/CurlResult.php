<?php
/**
 * @copyright Copyright (C) 2020, Friendica
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Friendica\Network;

use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * A content class for Curl call results
 */
class CurlResult implements IResponse
{
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @var int HTTP return code or 0 if timeout or failure
	 */
	private $statusCode;

	/**
	 * @var string the content type of the Curl call
	 */
	private $contentType = '';

	/**
	 * @var string the HTTP headers of the Curl call
	 */
	private $header;

	/**
	 * @var array the HTTP headers of the Curl call
	 */
	private $header_fields;

	/**
	 * @var boolean true (if HTTP 2xx result) or false
	 */
	private $isSuccess;

	/**
	 * @var string the URL which was called
	 */
	private $url;

	/**
	 * @var string fetched content
	 */
	private $body;

	/**
	 * @var array some informations about the fetched data
	 */
	private $info;

	/**
	 * @var boolean true if the curl request timed out
	 */
	private $isTimeout;

	/**
	 * @var int the error number or 0 (zero) if no error
	 */
	private $errorNumber;

	/**
	 * @var string the error message or '' (the empty string) if no
	 */
	private $error;

	/**
	 * Creates an errored CURL response
	 *
	 * @param LoggerInterface $logger The Friendica logger
	 * @param string $url optional URL
	 *
	 * @return CurlResult a CURL with error response
	 */
	public static function createErrorCurl(LoggerInterface $logger, $url = '')
	{
		return new CurlResult($logger, $url, '', ['http_code' => 0]);
	}

	/**
	 * @param LoggerInterface $logger The Friendica logger
	 * @param string $url the URL which was called
	 * @param string $result the result of the curl execution
	 * @param array $info an additional info array
	 * @param int $errorNumber the error number or 0 (zero) if no error
	 * @param string $error the error message or '' (the empty string) if no
	 */
	public function __construct(LoggerInterface $logger, $url, $result, $info, $errorNumber = 0, $error = '')
	{
		$this->logger = $logger;

		$this->statusCode  = $info['http_code'] ?? 0;
		$this->url         = $url;
		$this->info        = $info;
		$this->errorNumber = $errorNumber;
		$this->error       = $error;

		$this->logger->notice('CurlResult', ['url' => $url, 'statusCode' => $this->statusCode, 'result' => $result]);

		$this->parseBodyHeader($result);
		$this->checkSuccess();

		if (!empty($this->info['content_type'])) {
			$this->contentType = $this->info['content_type'];
		}
	}

	private function parseBodyHeader($result)
	{
		// Pull out multiple headers, e.g. proxy and continuation headers
		// allow for HTTP/2.x without fixing code

		$header = '';
		$base = $result;
		while (preg_match('/^HTTP\/.+? \d+/', $base)) {
			$chunk = substr($base, 0, strpos($base, "\r\n\r\n") + 4);
			$header .= $chunk;
			$base = substr($base, strlen($chunk));
		}

		$this->body = substr($result, strlen($header));
		$this->header = $header;
		$this->header_fields = []; // Is filled on demand
	}

	private function checkSuccess()
	{
		$this->isSuccess = ($this->statusCode >= 200 && $this->statusCode <= 299) || $this->errorNumber == 0;

		// Everything higher or equal 400 is not a success
		if ($this->statusCode >= 400) {
			$this->isSuccess = false;
		}

		if (!$this->isSuccess) {
			$this->logger->notice('CurlResult error.', ['url' => $this->url, 'statusCode' => $this->statusCode, 'error' => $this->error]);
			$this->logger->debug('CurlResult headers.', ['info' => $this->info]);
		}

		if (!$this->isSuccess && $this->errorNumber == CURLE_OPERATION_TIMEDOUT) {
			$this->isTimeout = true;
		} else {
			$this->isTimeout = false;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getStatusCode()
	{
		return $this->statusCode;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getContentType()
	{
		return $this->contentType;
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasHeader($name)
	{
		$name = strtolower(trim($name));

		$headers = $this->getHeaders();

		return array_key_exists($name, $headers);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getHeaders()
	{
		if (!empty($this->header_fields)) {
			return $this->header_fields;
		}

		$this->header_fields = [];

		$lines = explode("\n", trim($this->header));
		foreach ($lines as $line) {
			$parts                             = explode(':', $line);
			$headerfield                       = strtolower(trim(array_shift($parts)));
			$this->header_fields[$headerfield] = $parts;
		}

		return $this->header_fields;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSuccess()
	{
		return $this->isSuccess;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getUrl()
	{
		return $this->url;
	}

	/**
	 * @return string
	 */
	public function getBody()
	{
		return $this->body;
	}

	/**
	 * @return array
	 */
	public function getInfo()
	{
		return $this->info;
	}

	/**
	 * @return int
	 */
	public function getErrorNumber()
	{
		return $this->errorNumber;
	}

	/**
	 * @return string
	 */
	public function getError()
	{
		return $this->error;
	}

	/**
	 * @return bool
	 */
	public function isTimeout()
	{
		return $this->isTimeout;
	}

	/**
	 * @inheritDoc
	 */
	public function getProtocolVersion()
	{
		// TODO: Implement getProtocolVersion() method.
	}

	/**
	 * @inheritDoc
	 */
	public function withProtocolVersion($version)
	{
		// TODO: Implement withProtocolVersion() method.
	}

	/**
	 * @inheritDoc
	 */
	public function getHeader($name)
	{
		$name = strtolower(trim($name));

		$headers = $this->getHeaders();

		if (isset($headers[$name])) {
			return $headers[$name];
		}

		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderLine($name)
	{
		$header = $this->getHeader($name);

		return trim(implode(',', $header));
	}

	/**
	 * @inheritDoc
	 */
	public function withHeader($name, $value, bool $reset = true)
	{
		$clone = clone $this;
		$header = $clone->getHeader($name);

		if ($reset) {
			$header[$name] = [];
		}

		if (is_array($value)) {
			$header[$name] = $value;
		} else {
			$header[$name][] = $value;
		}
		$clone->header_fields = $header;

		return $clone;
	}

	/**
	 * @inheritDoc
	 */
	public function withAddedHeader($name, $value)
	{
		return $this->withHeader($name, $value, false);
	}

	/**
	 * @inheritDoc
	 */
	public function withoutHeader($name)
	{
		$clone = clone $this;
		$clone->getHeader($name);
		if (isset($clone->header_fields[$name])) {
			unset($clone->header_fields[$name]);
		}

		return $clone;
	}

	/**
	 * @inheritDoc
	 */
	public function withBody(StreamInterface $body)
	{
		$clone = clone $this;
		$clone->body = $body;

		return $clone;
	}

	/**
	 * @inheritDoc
	 */
	public function withStatus($code, $reasonPhrase = '')
	{
		$clone = clone $this;
		$clone->statusCode = $code;

		return $clone;
	}

	/**
	 * @inheritDoc
	 */
	public function getReasonPhrase()
	{
		// TODO: Implement getReasonPhrase() method.
	}
}
