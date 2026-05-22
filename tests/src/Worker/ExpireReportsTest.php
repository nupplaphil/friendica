<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\src\Worker;

use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Database\Database;
use Friendica\Moderation\Entity\Report as ReportEntity;
use Friendica\Test\MockedTestCase;
use Friendica\Worker\ExpireReports;
use Psr\Log\LoggerInterface;

class ExpireReportsTest extends MockedTestCase
{
	public function testCleanupExpiredReportsDeletesClosedAndOpenReports(): void
	{
		$database = \Mockery::mock(Database::class);
		$config   = \Mockery::mock(IManageConfigValues::class);
		$logger   = \Mockery::mock(LoggerInterface::class);

		$closedResult = (object) ['status' => 'closed'];
		$openResult   = (object) ['status' => 'open'];

		$config->shouldReceive('get')
			->with('system', 'dbclean-expire-limit')
			->once()
			->andReturn(2);

		$database->shouldReceive('select')
			->times(2)
			->andReturnUsing(function (string $table, array $fields, array $condition) use ($closedResult, $openResult) {
				self::assertSame('report', $table);
				self::assertSame(['id'], $fields);
				self::assertSame('`status` = ? AND COALESCE(`edited`, `created`) < ?', $condition[0]);
				self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $condition[2]);

				if ($condition[1] === ReportEntity::STATUS_CLOSED) {
					return $closedResult;
				}

				return $openResult;
			});

		$database->shouldReceive('fetch')
			->with($closedResult)
			->twice()
			->andReturn(['id' => 11], false);
		$database->shouldReceive('fetch')
			->with($openResult)
			->twice()
			->andReturn(['id' => 22], false);

		$database->shouldReceive('close')->with($closedResult)->once();
		$database->shouldReceive('close')->with($openResult)->once();

		$database->shouldReceive('delete')->with('report-rule', ['rid' => 11])->once();
		$database->shouldReceive('delete')->with('report-post', ['rid' => 11])->once();
		$database->shouldReceive('delete')->with('report', ['id' => 11])->once();
		$database->shouldReceive('delete')->with('report-rule', ['rid' => 22])->once();
		$database->shouldReceive('delete')->with('report-post', ['rid' => 22])->once();
		$database->shouldReceive('delete')->with('report', ['id' => 22])->once();
		$logger->shouldReceive('notice')
			->twice()
			->with('Deleted expired reports', \Mockery::on(static function (array $context): bool {
				return isset($context['label'], $context['rows']) && !isset($context['pass']) && $context['rows'] === 1;
			}));

		ExpireReports::cleanupExpiredReports($database, $config, $logger);
	}

	public function testCleanupExpiredReportsSkipsWhenLimitDisabled(): void
	{
		$database = \Mockery::mock(Database::class);
		$config   = \Mockery::mock(IManageConfigValues::class);
		$logger   = \Mockery::mock(LoggerInterface::class);

		$config->shouldReceive('get')
			->with('system', 'dbclean-expire-limit')
			->once()
			->andReturn(0);

		$database->shouldNotReceive('select');
		$database->shouldNotReceive('fetch');
		$database->shouldNotReceive('close');
		$database->shouldNotReceive('delete');
		$logger->shouldNotReceive('notice');

		ExpireReports::cleanupExpiredReports($database, $config, $logger);

		self::assertTrue(true);
	}
}
