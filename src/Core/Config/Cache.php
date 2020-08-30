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

use ParagonIE\HiddenString\HiddenString;

/**
 * The Friendica config cache for the application
 * Initial, all *.config.php files are loaded into this cache with the
 * ConfigFileLoader ( @see ConfigFileLoader )
 */
class Cache implements IConfigCache
{
	/**
	 * @var array
	 */
	private $config;

	/**
	 * @var bool
	 */
	private $hidePasswordOutput;

	/**
	 * @param array $config             A initial config array
	 * @param bool  $hidePasswordOutput True, if cache variables should take extra care of password values
	 */
	public function __construct(array $config = [], bool $hidePasswordOutput = true)
	{
		$this->hidePasswordOutput = $hidePasswordOutput;
		$this->load($config);
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(array $config, bool $overwrite = false)
	{
		$categories = array_keys($config);

		foreach ($categories as $category) {
			if (is_array($config[$category])) {
				$keys = array_keys($config[$category]);

				foreach ($keys as $key) {
					$value = $config[$category][$key];
					if (isset($value)) {
						if ($overwrite) {
							$this->set($category, $key, $value);
						} else {
							$this->setDefault($category, $key, $value);
						}
					}
				}
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get(string $cat, string $key = null)
	{
		if (isset($this->config[$cat][$key])) {
			return $this->config[$cat][$key];
		} elseif (!isset($key) && isset($this->config[$cat])) {
			return $this->config[$cat];
		} else {
			return null;
		}
	}

	/**
	 * Sets a default value in the config cache. Ignores already existing keys.
	 *
	 * @param string $cat   Config category
	 * @param string $key   Config key
	 * @param mixed  $value Default value to set
	 */
	private function setDefault(string $cat, string $key, $value)
	{
		if (!isset($this->config[$cat][$key])) {
			$this->set($cat, $key, $value);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function set(string $cat, string $key, $value)
	{
		if (!isset($this->config[$cat])) {
			$this->config[$cat] = [];
		}

		if ($this->hidePasswordOutput &&
		    $key == 'password' &&
		    is_string($value)) {
			$this->config[$cat][$key] = new HiddenString((string)$value);
		} else {
			$this->config[$cat][$key] = $value;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete(string $cat, string $key)
	{
		if (isset($this->config[$cat][$key])) {
			unset($this->config[$cat][$key]);
			if (count($this->config[$cat]) == 0) {
				unset($this->config[$cat]);
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAll()
	{
		return $this->config;
	}

	/**
	 * {@inheritDoc}
	 */
	public function keyDiff(array $config)
	{
		$return = [];

		$categories = array_keys($config);

		foreach ($categories as $category) {
			if (is_array($config[$category])) {
				$keys = array_keys($config[$category]);

				foreach ($keys as $key) {
					if (!isset($this->config[$category][$key])) {
						$return[$category][$key] = $config[$category][$key];
					}
				}
			}
		}

		return $return;
	}
}
