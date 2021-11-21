<?php
/**
 * @copyright Copyright (C) 2010-2021, the Friendica project
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

namespace Friendica\Module;

use Friendica\BaseModule;
use Friendica\Core\Addon;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Network\HTTPException\NotFoundException;
use Psr\Log\LoggerInterface;

class Statistics extends BaseModule
{
	/** @var IManageConfigValues */
	protected $config;
	/** @var LoggerInterface */
	protected $logger;

	public function __construct(IManageConfigValues $config, LoggerInterface $logger, L10n $l10n, array $parameters = [])
	{
		parent::__construct($l10n, $parameters);

		$this->logger = $logger;
		$this->config = $config;

		if (!$this->config->get("system", "nodeinfo")) {
			throw new NotFoundException();
		}
	}

	public function rawContent()
	{
		$registration_open =
			intval($this->config->get('config', 'register_policy')) !== Register::CLOSED
			&& !$this->config->get('config', 'invitation_only');

		/// @todo mark the "service" addons and load them dynamically here
		$services = [
			'appnet'      => Addon::isEnabled('appnet'),
			'buffer'      => Addon::isEnabled('buffer'),
			'dreamwidth'  => Addon::isEnabled('dreamwidth'),
			'gnusocial'   => Addon::isEnabled('gnusocial'),
			'libertree'   => Addon::isEnabled('libertree'),
			'livejournal' => Addon::isEnabled('livejournal'),
			'pumpio'      => Addon::isEnabled('pumpio'),
			'twitter'     => Addon::isEnabled('twitter'),
			'tumblr'      => Addon::isEnabled('tumblr'),
			'wordpress'   => Addon::isEnabled('wordpress'),
		];

		$statistics = array_merge([
			'name'                  => $this->config->get('config', 'sitename'),
			'network'               => FRIENDICA_PLATFORM,
			'version'               => FRIENDICA_VERSION . '-' . DB_UPDATE_VERSION,
			'registrations_open'    => $registration_open,
			'total_users'           => $this->config->get('nodeinfo', 'total_users'),
			'active_users_halfyear' => $this->config->get('nodeinfo', 'active_users_halfyear'),
			'active_users_monthly'  => $this->config->get('nodeinfo', 'active_users_monthly'),
			'local_posts'           => $this->config->get('nodeinfo', 'local_posts'),
			'services'              => $services,
		], $services);

		header("Content-Type: application/json");
		echo json_encode($statistics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		$this->logger->debug("statistics.", ['statistics' => $statistics]);
		exit();
	}
}
