<?php
/**
 * @copyright Copyright (C) 2020, Friendica
 *
 * @license GNU APGL version 3 or any later version
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

use Friendica\Core\Logger;
use Friendica\Core\System;
use Friendica\DI;
use Friendica\Network\Fetch\IFetch;
use Friendica\Util\Network;

class Fetch implements IFetch
{
	/**
	 * {@inheritDoc}
	 *
	 * @param int $redirects The recursion counter for internal use - default 0
	 *
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function url(string $url, bool $binary = false, int $timeout = 0, string $accept_content = '', string $cookiejar = '', int &$redirects = 0)
	{
		$ret = $this->urlFull($url, $binary, $timeout, $accept_content, $cookiejar, $redirects);

		return $ret->getBody();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $redirects The recursion counter for internal use - default 0
	 *
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function urlFull(string $url, bool $binary = false, int $timeout = 0, string $accept_content = '', string $cookiejar = '', int &$redirects = 0)
	{
		return $this->curl(
			$url,
			$binary,
			[
				'timeout'        => $timeout,
				'accept_content' => $accept_content,
				'cookiejar'      => $cookiejar
			],
			$redirects
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $redirects The recursion counter for internal use - default 0
	 *
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function curl(string $url, bool $binary = false, array $opts = [], int &$redirects = 0)
	{
		$stamp1 = microtime(true);

		$a = DI::app();

		if (strlen($url) > 1000) {
			Logger::log('URL is longer than 1000 characters. Callstack: ' . System::callstack(20), Logger::DEBUG);
			return CurlResult::createErrorCurl(substr($url, 0, 200));
		}

		$parts2     = [];
		$parts      = parse_url($url);
		$path_parts = explode('/', $parts['path'] ?? '');
		foreach ($path_parts as $part) {
			if (strlen($part) <> mb_strlen($part)) {
				$parts2[] = rawurlencode($part);
			} else {
				$parts2[] = $part;
			}
		}
		$parts['path'] = implode('/', $parts2);
		$url           = Network::unparseURL($parts);

		if (Network::isUrlBlocked($url)) {
			Logger::log('domain of ' . $url . ' is blocked', Logger::DATA);
			return CurlResult::createErrorCurl($url);
		}

		$ch = @curl_init($url);

		if (($redirects > 8) || (!$ch)) {
			return CurlResult::createErrorCurl($url);
		}

		@curl_setopt($ch, CURLOPT_HEADER, true);

		if (!empty($opts['cookiejar'])) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $opts["cookiejar"]);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $opts["cookiejar"]);
		}

		// These settings aren't needed. We're following the location already.
		//	@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		//	@curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

		if (!empty($opts['accept_content'])) {
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				['Accept: ' . $opts['accept_content']]
			);
		}

		if (!empty($opts['header'])) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['header']);
		}

		@curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		@curl_setopt($ch, CURLOPT_USERAGENT, $a->getUserAgent());

		$range = intval(DI::config()->get('system', 'curl_range_bytes', 0));

		if ($range > 0) {
			@curl_setopt($ch, CURLOPT_RANGE, '0-' . $range);
		}

		// Without this setting it seems as if some webservers send compressed content
		// This seems to confuse curl so that it shows this uncompressed.
		/// @todo  We could possibly set this value to "gzip" or something similar
		curl_setopt($ch, CURLOPT_ENCODING, '');

		if (!empty($opts['headers'])) {
			@curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['headers']);
		}

		if (!empty($opts['nobody'])) {
			@curl_setopt($ch, CURLOPT_NOBODY, $opts['nobody']);
		}

		if (!empty($opts['timeout'])) {
			@curl_setopt($ch, CURLOPT_TIMEOUT, $opts['timeout']);
		} else {
			$curl_time = DI::config()->get('system', 'curl_timeout', 60);
			@curl_setopt($ch, CURLOPT_TIMEOUT, intval($curl_time));
		}

		// by default we will allow self-signed certs
		// but you can override this

		$check_cert = DI::config()->get('system', 'verifyssl');
		@curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (($check_cert) ? true : false));

		if ($check_cert) {
			@curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		}

		$proxy = DI::config()->get('system', 'proxy');

		if (strlen($proxy)) {
			@curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
			@curl_setopt($ch, CURLOPT_PROXY, $proxy);
			$proxyuser = @DI::config()->get('system', 'proxyuser');

			if (strlen($proxyuser)) {
				@curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyuser);
			}
		}

		if (DI::config()->get('system', 'ipv4_resolve', false)) {
			curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		}

		if ($binary) {
			@curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		}

		// don't let curl abort the entire application
		// if it throws any errors.

		$s         = @curl_exec($ch);
		$curl_info = @curl_getinfo($ch);

		// Special treatment for HTTP Code 416
		// See https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/416
		if (($curl_info['http_code'] == 416) && ($range > 0)) {
			@curl_setopt($ch, CURLOPT_RANGE, '');
			$s         = @curl_exec($ch);
			$curl_info = @curl_getinfo($ch);
		}

		$curlResponse = new CurlResult($url, $s, $curl_info, curl_errno($ch), curl_error($ch));

		if ($curlResponse->isRedirectUrl()) {
			$redirects++;
			Logger::log('curl: redirect ' . $url . ' to ' . $curlResponse->getRedirectUrl());
			@curl_close($ch);
			return self::curl($curlResponse->getRedirectUrl(), $binary, $opts, $redirects);
		}

		@curl_close($ch);

		DI::profiler()->saveTimestamp($stamp1, 'network', System::callstack());

		return $curlResponse;
	}
}
