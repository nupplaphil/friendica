<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Federation\Collection;

use Friendica\Federation\Entity;

final class DeliveryQueueItems extends \Friendica\BaseCollection
{
	/**
	 * @param Entity\DeliveryQueueItem[] $entities
	 * @param int|null                   $totalCount
	 */
	public function __construct(array $entities = [], int $totalCount = null)
	{
		parent::__construct($entities, $totalCount);
	}

	/**
	 * @return Entity\DeliveryQueueItem
	 */
	public function current(): Entity\DeliveryQueueItem
	{
		return parent::current();
	}
}
