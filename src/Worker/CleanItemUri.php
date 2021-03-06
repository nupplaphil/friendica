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

namespace Friendica\Worker;

use Friendica\Core\Logger;
use Friendica\Database\DBA;

class CleanItemUri
{
	/**
	 * Delete unused item-uri entries
	 */
	public static function execute()
	{
		$ret = DBA::e("DELETE FROM `item-uri` WHERE NOT `id` IN (SELECT `uri-id` FROM `item`)
			AND NOT `id` IN (SELECT `parent-uri-id` FROM `item`)
			AND NOT `id` IN (SELECT `thr-parent-id` FROM `item`)");
		Logger::notice('Orphaned URI-ID entries removed', ['result' => $ret, 'rows' => DBA::affectedRows()]);
	}
}
