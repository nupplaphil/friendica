<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Util;

use Exception;
use Friendica\Core\Cache\Capability\ICanCache;
use Friendica\Core\Cache\Enum\Duration;
use Friendica\Network\HTTPClient\Capability\ICanSendHttpRequests;
use Friendica\Network\HTTPClient\Client\HttpClientAccept;
use Psr\Log\LoggerInterface;

final readonly class HibpPasswordExposedChecker implements PasswordExposedChecker
{
	public function __construct(
		private ICanSendHttpRequests $httpClient,
		private ICanCache $cache,
		private LoggerInterface $logger,
	) {
	}

	public function isExposed(string $password): bool
	{
		$hash     = strtoupper(sha1($password));
		$prefix   = substr($hash, 0, 5);
		$suffix   = substr($hash, 5);
		$cacheKey = 'PasswordExposed:' . $prefix;

		$response = $this->cache->get($cacheKey);

		if ($response === null) {
			try {
				$response = $this->httpClient->fetch(
					'https://api.pwnedpasswords.com/range/' . $prefix,
					HttpClientAccept::TEXT,
					10
				);
				$this->cache->set($cacheKey, $response, Duration::MONTH);
			} catch (Exception $e) {
				$this->logger->error('PasswordExposed check failed: ' . $e->getMessage(), [
					'code'  => $e->getCode(),
					'file'  => $e->getFile(),
					'line'  => $e->getLine(),
					'trace' => $e->getTraceAsString(),
				]);
				return false;
			}
		}

		foreach (explode("\n", (string) $response) as $line) {
			$line = trim($line);
			if (str_starts_with($line, $suffix . ':')) {
				return true;
			}
		}

		return false;
	}
}
