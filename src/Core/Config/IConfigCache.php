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

namespace Friendica\Core\Config;

/**
* Interface for accessing the system config cache
*/
interface IConfigCache
{
	/**
	 * Tries to load the specified configuration array into the config array.
	 * Doesn't overwrite previously set values by default to prevent default config files to supersede DB Config.
	 *
	 * @param array $config
	 * @param bool  $overwrite Force value overwrite if the config key already exists
	 */
	public function load(array $config, bool $overwrite = false);

	/**
	 * Gets a value from the config cache.
	 *
	 * @param string $cat Config category
	 * @param string $key Config key
	 *
	 * @return null|mixed Returns the value of the Config entry or null if not set
	 */
	public function get(string $cat, string $key = null);

	/**
	 * Sets a value in the config cache. Accepts raw output from the config table
	 *
	 * @param string $cat   Config category
	 * @param string $key   Config key
	 * @param mixed  $value Value to set
	 *
	 * @return bool True, if the value is set
	 */
	public function set(string $cat, string $key, $value);

	/**
	 * Deletes a value from the config cache.
	 *
	 * @param string $cat Config category
	 * @param string $key Config key
	 *
	 * @return bool true, if deleted
	 */
	public function delete(string $cat, string $key);

	/**
	 * Returns the whole configuration
	 *
	 * @return array The configuration
	 */
	public function getAll();

	/**
	 * Returns an array with missing categories/Keys of the current config cache
	 *
	 * @param array $config The array to check
	 *
	 * @return array
	 */
	public function keyDiff(array $config);
}
