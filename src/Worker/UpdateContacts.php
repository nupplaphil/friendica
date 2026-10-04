<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Worker;

use Friendica\Core\Worker;
use Friendica\Database\DBA;
use Friendica\DI;
use Friendica\Model\Contact;
use Friendica\Model\GServer;
use Friendica\Util\DateTimeFormat;

/**
 * Update federated contacts
 */
class UpdateContacts
{
	/**
	 * Local and blocked contacts are never updated here, this only moves them out of the selection.
	 */
	private const SKIPPED_NEXT_UPDATE = 'now +1 month';

	public static function execute()
	{
		$update_limit = DI::config()->get('system', 'contact_update_limit');
		if (empty($update_limit)) {
			return;
		}

		$updating = Worker::countWorkersByCommand('UpdateContact');
		$limit    = $update_limit - $updating;
		if ($limit <= 0) {
			DI::logger()->info('The number of currently running jobs exceed the limit');
			return;
		}

		DI::logger()->info('Updating contact', ['count' => $limit]);

		$condition = ['self' => false];

		if (DI::config()->get('system', 'update_active_contacts')) {
			$condition = array_merge(['local-data' => true], $condition);
		}

		$condition = DBA::mergeConditions(["`next-update` < ?", DateTimeFormat::utcNow()], $condition);
		$contacts  = DBA::select('contact', ['id', 'url', 'gsid', 'baseurl'], $condition, ['order' => ['next-update'], 'limit' => $limit]);
		$count     = 0;
		$skipped   = [];
		while ($contact = DBA::fetch($contacts)) {
			if (Contact::isLocal($contact['url']) || Contact::isBlocked($contact['id'])) {
				$skipped[] = $contact['id'];
				continue;
			}

			try {
				if ((!empty($contact['gsid']) || !empty($contact['baseurl'])) && GServer::reachable($contact)) {
					$stamp   = (float) microtime(true);
					$success = Contact::updateFromProbe($contact['id']);
					DI::logger()->debug('Direct update', ['id' => $contact['id'], 'count' => $count, 'duration' => round((float) microtime(true) - $stamp, 3), 'success' => $success]);
					++$count;
				} elseif (UpdateContact::add(['priority' => Worker::PRIORITY_LOW, 'dont_fork' => true], $contact['id'])) {
					DI::logger()->debug('Update by worker', ['id' => $contact['id'], 'count' => $count]);
					++$count;
				}
			} catch (\InvalidArgumentException $e) {
				DI::logger()->notice($e->getMessage(), ['contact' => $contact]);
			}

			Worker::coolDown();
		}
		DBA::close($contacts);

		if (!empty($skipped)) {
			Contact::update(['next-update' => DateTimeFormat::utc(self::SKIPPED_NEXT_UPDATE)], ['id' => $skipped]);
		}

		DI::logger()->info('Initiated update for federated contacts', ['count' => $count]);
	}
}
