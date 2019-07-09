<?php

namespace Friendica\App;

use Friendica\Core\Config\Cache\ConfigCache;
use Friendica\Database\Database;

/**
 * Mode of the current Friendica Node
 *
 * @package Friendica\App
 */
class Mode
{
	const LOCALCONFIGPRESENT = 1;
	const DBAVAILABLE = 2;
	const DBCONFIGAVAILABLE = 4;
	const MAINTENANCEDISABLED = 8;

	/***
	 * @var int the mode of this Application
	 *
	 */
	private $mode;

	/**
	 * @var ConfigCache The configuration (config file)
	 */
	private $configCache;

	/**
	 * @var Database The database connection
	 */
	private $dba;

	public function __construct(Database $dba, ConfigCache $configCache)
	{
		$this->dba = $dba;
		$this->configCache = $configCache;
		$this->mode = 0;
	}

	/**
	 * Sets the App mode
	 *
	 * - App::MODE_INSTALL    : Either the database connection can't be established or the config table doesn't exist
	 * - App::MODE_MAINTENANCE: The maintenance mode has been set
	 * - App::MODE_NORMAL     : Normal run with all features enabled
	 *
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function determine()
	{
		$this->mode = 0;

		if (!$this->configCache->get('database', 'hostname') ||
		    !$this->configCache->get('database', 'username') ||
		    !$this->configCache->get('database', 'database')) {
			return;
		}

		$this->mode |= Mode::LOCALCONFIGPRESENT;

		if (!$this->dba->connected()) {
			return;
		}

		$this->mode |= Mode::DBAVAILABLE;

		if ($this->dba->fetchFirst("SHOW TABLES LIKE 'config'") === false) {
			return;
		}

		$this->mode |= Mode::DBCONFIGAVAILABLE;

		if ($this->configCache->get('system', 'maintenance') ||
			!empty($this->dba->selectFirst('config', ['v'], ['cat' => 'system', 'k' => 'maintenance'])['v'])) {
			return;
		}

		$this->mode |= Mode::MAINTENANCEDISABLED;
	}

	/**
	 * Checks, if the Friendica Node has the given mode
	 *
	 * @param int $mode A mode to test
	 *
	 * @return bool returns true, if the mode is set
	 */
	public function has($mode)
	{
		return ($this->mode & $mode) > 0;
	}


	/**
	 * Install mode is when the local config file is missing or the DB schema hasn't been installed yet.
	 *
	 * @return bool
	 */
	public function isInstall()
	{
		return !$this->has(Mode::LOCALCONFIGPRESENT) ||
			!$this->has(MODE::DBCONFIGAVAILABLE);
	}

	/**
	 * Normal mode is when the local config file is set, the DB schema is installed and the maintenance mode is off.
	 *
	 * @return bool
	 */
	public function isNormal()
	{
		return $this->has(Mode::LOCALCONFIGPRESENT) &&
			$this->has(Mode::DBAVAILABLE) &&
			$this->has(Mode::DBCONFIGAVAILABLE) &&
			$this->has(Mode::MAINTENANCEDISABLED);
	}
}
