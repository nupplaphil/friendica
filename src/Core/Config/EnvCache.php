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

use Friendica\Network\HTTPException\InternalServerErrorException;

/**
 * A Decorator around a given Config Cache class
 *
 * If used, environment variables with a specific prefix can override system configs
 */
class EnvCache implements IConfigCache
{
	/** @var string The default prefix if none is set */
	const DEFAULT_PREFIX = 'FRN_';

	/** @var string The default splitter if none is set */
	const ENV_SPLIT = '__';

	/** @var IConfigCache The config cache  to decorate */
	private $configCache;

	/** @var string[] A list of matching environment variables */
	private $envConfig;

	/**
	 * @param IConfigCache $configCache The config cache to decorate
	 * @param array   $server The $_SERVER array
	 *
	 * @throws InternalServerErrorException In case the environment settings are disabled for the config
	 */
	public function __construct(IConfigCache $configCache, array $server)
	{
		$this->configCache = $configCache;

		$this->loadEnvironmentConfig($server);
	}

	/**
	 * Loads the matching environment variables into the environment array
	 *
	 * @param array $server The $_SERVER array
	 *
	 * @throws InternalServerErrorException In case the environment settings are disabled for the config
	 */
	private function loadEnvironmentConfig(array $server)
	{
		if (!(bool)$this->configCache->get('system', 'config_env_variables')) {
			throw new InternalServerErrorException('Cannot use Environment variables because usage is disabled');
		}

		$prefix = $this->configCache->get('system', 'config_env_variables_prefix') ?? self::DEFAULT_PREFIX;
		$splitter = $this->configCache->get('system', 'config_env_variables_splitter') ?? self::ENV_SPLIT;

		$envTempConfig = array_filter($server, function ($key) use ($prefix) {
			return strpos($key, $prefix) === 0;
		}, ARRAY_FILTER_USE_KEY);

		foreach ($envTempConfig as $envKey => $value) {
			list($category, $key) = explode($splitter, $envKey);
			$this->envConfig[$category][$key] = $value;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(array $config, bool $overwrite = false)
	{
		$this->configCache->load($config, $overwrite);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get(string $cat, string $key = null)
	{
		if (isset($this->envConfig[$cat][$key])) {
			return $this->envConfig[$cat][$key];
		} elseif (!isset($key) && isset($this->envConfig[$cat])) {
			return $this->envConfig[$cat];
		} else {
			return $this->configCache->get($cat, $key);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function set(string $cat, string $key, $value)
	{
		return $this->configCache->set($cat, $key, $value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete(string $cat, string $key)
	{
		return $this->configCache->delete($cat, $key);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAll()
	{
		return array_merge($this->configCache, $this->envConfig);
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
					if (!isset($this->envConfig[$category][$key]) &&
						!$this->configCache->get($category, $key)) {
						$return[$category][$key] = $config[$category][$key];
					}
				}
			}
		}

		return $return;
	}
}
