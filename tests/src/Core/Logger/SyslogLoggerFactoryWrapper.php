<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\src\Core\Logger;

use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\Logger\Exception\LogLevelException;
use Friendica\Core\Logger\Factory\SyslogLogger;
use Friendica\Core\Logger\Type\SyslogLogger as SyslogLoggerClass;

/** @phpstan-ignore class.extendsDeprecatedClass (test wrapper for deprecated SyslogLogger) */
class SyslogLoggerFactoryWrapper extends SyslogLogger
{
	public function create(IManageConfigValues $config): SyslogLoggerWrapper
	{
		$logOpts     = (int) ($config->get('system', 'syslog_flags') ?? SyslogLoggerClass::DEFAULT_FLAGS);
		$logFacility = (int) ($config->get('system', 'syslog_facility') ?? SyslogLoggerClass::DEFAULT_FACILITY);
		$loglevel    = SyslogLogger::mapLegacyConfigDebugLevel($config->get('system', 'loglevel')); // @phpstan-ignore staticMethod.deprecatedClass (testing deprecated mapLegacyConfigDebugLevel)

		if (!array_key_exists($loglevel, SyslogLoggerClass::logLevels)) {
			throw new LogLevelException(sprintf('The level "%s" is not valid.', $loglevel));
		}

		$loglevel = SyslogLoggerClass::logLevels[$loglevel];

		return new SyslogLoggerWrapper(
			$this->channel,   // @phpstan-ignore property.deprecatedClass (testing deprecated property from AbstractLoggerTypeFactory)
			$this->introspection, // @phpstan-ignore property.deprecatedClass (testing deprecated property from AbstractLoggerTypeFactory)
			$loglevel,
			$logOpts,
			$logFacility
		);
	}
}
