<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\src\Content;

class ItemTestDice
{
	private $profiler;
	private $eventDispatcher;
	private $config;
	private $l10n;
	private $baseUrl;
	private $mockFactory;

	public function __construct($profiler, $eventDispatcher, $config, $l10n, $baseUrl, \Closure $mockFactory)
	{
		$this->profiler        = $profiler;
		$this->eventDispatcher = $eventDispatcher;
		$this->config          = $config;
		$this->l10n            = $l10n;
		$this->baseUrl         = $baseUrl;
		$this->mockFactory     = $mockFactory;
	}

	public function create($class)
	{
		if ($class === \Friendica\Util\Profiler::class) {
			return $this->profiler;
		}

		if ($class === \Psr\EventDispatcher\EventDispatcherInterface::class) {
			return $this->eventDispatcher;
		}

		if ($class === \Friendica\Core\Config\Capability\IManageConfigValues::class) {
			return $this->config;
		}

		if ($class === \Friendica\Core\L10n::class) {
			return $this->l10n;
		}

		if ($class === \Friendica\App\BaseURL::class) {
			return $this->baseUrl;
		}

		return ($this->mockFactory)($class);
	}
}
