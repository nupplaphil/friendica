<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Test\Functional\Worker;

use Friendica\Core\Protocol;
use Friendica\Database\DBA;
use Friendica\DI;
use Friendica\Test\FixtureTestCase;
use Friendica\Util\DateTimeFormat;
use Friendica\Util\Strings;
use Friendica\Worker\UpdateContacts;

class UpdateContactsTest extends FixtureTestCase
{
	private const UPDATE_LIMIT = 2;

	protected function setUp(): void
	{
		parent::setUp();

		DI::config()->set('system', 'contact_update_limit', self::UPDATE_LIMIT);

		// Only the contacts of this test are due.
		DBA::update('contact', ['next-update' => DateTimeFormat::utc('now +1 year')], ['self' => false]);
	}

	private function insertContact(string $url, string $baseurl, string $nextUpdate): int
	{
		DBA::insert('contact', [
			'uid'         => 0,
			'url'         => $url,
			'nurl'        => Strings::normaliseLink($url),
			'baseurl'     => $baseurl,
			'network'     => Protocol::ACTIVITYPUB,
			'blocked'     => false,
			'name'        => $url,
			'nick'        => basename($url),
			'next-update' => $nextUpdate,
		]);

		return DBA::lastInsertId();
	}

	private function hasUpdateJob(int $contactId): bool
	{
		return DBA::exists('workerqueue', ['command' => 'UpdateContact', 'parameter' => json_encode([$contactId]), 'done' => false]);
	}

	/**
	 * Inserts more skipped contacts than the limit, followed by one remote contact, and runs the worker twice.
	 *
	 * @param string[] $skippedUrls
	 */
	private function assertSkippedContactsDoNotBlock(array $skippedUrls, string $skippedBaseurl): void
	{
		$skipped = [];
		foreach ($skippedUrls as $url) {
			$skipped[] = $this->insertContact($url, $skippedBaseurl, DBA::NULL_DATETIME);
		}

		$remote = $this->insertContact('https://remote.example/users/remote', '', '2000-01-01 00:00:00');

		UpdateContacts::execute();
		UpdateContacts::execute();

		self::assertTrue($this->hasUpdateJob($remote));

		foreach ($skipped as $id) {
			self::assertFalse($this->hasUpdateJob($id));
			self::assertFalse(DBA::exists('contact', ["`id` = ? AND `next-update` < ?", $id, DateTimeFormat::utcNow()]));
		}
	}

	/**
	 * Local contacts are skipped, but must not stay at the head of the selection and starve the remote contacts behind them.
	 */
	public function testLocalContactsDoNotBlockRemoteContacts(): void
	{
		$baseUrl = (string) DI::baseUrl();

		$urls = [];
		for ($i = 1; $i <= self::UPDATE_LIMIT + 1; $i++) {
			$urls[] = $baseUrl . '/profile/local' . $i;
		}

		$this->assertSkippedContactsDoNotBlock($urls, $baseUrl);
	}

	/**
	 * Contacts on a blocked server are never queued, so they must not stay at the head of the selection either.
	 */
	public function testBlockedContactsDoNotBlockRemoteContacts(): void
	{
		DI::config()->set('system', 'blocklist', [['domain' => 'blocked.example', 'reason' => 'test']]);

		$urls = [];
		for ($i = 1; $i <= self::UPDATE_LIMIT + 1; $i++) {
			$urls[] = 'https://blocked.example/users/blocked' . $i;
		}

		$this->assertSkippedContactsDoNotBlock($urls, '');
	}
}
