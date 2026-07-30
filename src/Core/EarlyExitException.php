<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Core;

use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 *
 * Carries a ResponseInterface out of the dispatch flow to be returned by handleRequest().
 * Replaces System::exit() for early-terminating modules.
 */
class EarlyExitException extends \RuntimeException
{
	public function __construct(
		private readonly ResponseInterface $response,
	) {
		parent::__construct('Module requested early exit');
	}

	public function getResponse(): ResponseInterface
	{
		return $this->response;
	}
}
