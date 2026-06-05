<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Console;

use Asika\SimpleConsole\Console;
use Friendica\App\Mode;
use Friendica\Content\Item as ContentItem;
use Friendica\Content\Conversation\Repository\UserDefinedChannel;
use Friendica\Content\Text\HTML;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\KeyValueStorage\Capability\IManageKeyValuePairs;
use Friendica\Core\Protocol;
use Friendica\Model\Contact;
use Friendica\Model\Post\Engagement;
use Friendica\Network\HTTPClient\Client\HttpClient;
use Friendica\Network\HTTPClient\Client\HttpClientAccept;
use Friendica\Network\HTTPClient\Client\HttpClientOptions;
use Friendica\Network\HTTPClient\Client\HttpClientRequest;
use Friendica\Protocol\ActivityPub\Receiver;
use Friendica\Protocol\Relay;
use Friendica\System\Daemon as SysDaemon;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Console command for streaming federation updates from FediBuzz relay
 */
final class FediBuzzRelay extends Console
{
	/**
	 * @param Mode                 $mode
	 * @param IManageConfigValues  $config
	 * @param IManageKeyValuePairs $keyValue
	 * @param SysDaemon            $daemon
	 * @param HttpClient           $httpClient
	 * @param LoggerInterface      $logger
	 * @param ContentItem          $contentItem
	 * @param UserDefinedChannel   $userDefinedChannel
	 * @param array|null           $argv
	 */
	public function __construct(
		private readonly Mode $mode,
		private readonly IManageConfigValues $config,
		private readonly IManageKeyValuePairs $keyValue,
		private readonly SysDaemon $daemon,
		private readonly HttpClient $httpClient,
		private readonly LoggerInterface $logger,
		private readonly ContentItem $contentItem,
		private readonly UserDefinedChannel $userDefinedChannel,
		?array $argv = null
	) {
		parent::__construct($argv);
	}

	protected function getHelp(): string
	{
		return <<<HELP
fedibuzzrelay - Interact with the FediBuzz relay daemon
Synopsis
	bin/console fedibuzzrelay start [-h|--help|-?] [-v] [-f]
	bin/console fedibuzzrelay stop [-h|--help|-?] [-v]
	bin/console fedibuzzrelay status [-h|--help|-?] [-v]

Description
	Interact with the FediBuzz relay daemon for streaming federation updates

Options
	-h|--help|-?    Show help information
	-v              Show more debug information.
	-f|--foreground Runs the daemon in the foreground

Examples
	bin/console fedibuzzrelay start -f
		Starts the daemon in the foreground

	bin/console fedibuzzrelay status
		Gets the status of the daemon

	bin/console fedibuzzrelay stop
		Stops the daemon
HELP;
	}

	protected function doExecute()
	{
		if ($this->mode->isInstall()) {
			throw new RuntimeException("Friendica isn't properly installed yet");
		}

		$this->config->reload();

		if (empty($this->config->get('fedibuzzrelay', 'pidfile'))) {
			throw new RuntimeException(
				<<< TXT
					Please set fedibuzzrelay.pidfile in config/local.config.php. For example:

					'fedibuzzrelay' => [
						'pidfile' => '/path/to/fedibuzzrelay.pid',
					],
				TXT,
			);
		}

		$pidfile = $this->config->get('fedibuzzrelay', 'pidfile');

		$daemonMode = $this->getArgument(0);
		$foreground = $this->getOption(['f', 'foreground']) ?? false;

		if (empty($daemonMode)) {
			throw new RuntimeException("Please use either 'start', 'stop' or 'status'");
		}

		$this->daemon->init($pidfile);

		if ($daemonMode == 'status') {
			if ($this->daemon->isRunning()) {
				$this->out(sprintf("Daemon process %s is running (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
			} else {
				$this->out(sprintf("Daemon process %s isn't running (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
			}
			return 0;
		}

		if ($daemonMode == 'stop') {
			if (!$this->daemon->isRunning()) {
				$this->out(sprintf("Daemon process %s isn't running (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
				return 0;
			}

			if ($this->daemon->stop()) {
				$this->keyValue->set('fedibuzzrelay_daemon_mode', false);
				$this->out(sprintf("Daemon process %s was killed (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
				return 0;
			}

			return 1;
		}

		if ($this->daemon->isRunning()) {
			$this->out(sprintf("Daemon process %s is already running (%s)", $this->daemon->getPid(), $this->daemon->getPidfile()));
			return 1;
		}

		if ($daemonMode == "start") {
			$this->out("Starting FediBuzz relay daemon");

			$this->daemon->start(function () {
				$this->listen();
			}, $foreground);

			return 0;
		}

		$this->err('Invalid command');
		$this->out($this->getHelp());
		return 1;
	}

	/**
	 * Main daemon listening logic
	 */
	private function listen(): void
	{
		$url           = 'https://fedi.buzz/api/v1/streaming/public';
		$retryDelay    = 1;
		$maxRetryDelay = 60;

		while (true) {
			try {
				$body = $this->httpClient->get(
					$url,
					HttpClientAccept::STREAMING,
					[
						HttpClientOptions::REQUEST => HttpClientRequest::STREAMING,
						HttpClientOptions::STREAM => true
					]
				)->getBodyStream();

				// Reset retry delay on successful connection
				$retryDelay = 1;

				$this->processStream($body);

			} catch (\Exception $e) {
				$this->logger->info('Connection lost', ['code' => $e->getCode(), 'message' => $e->getMessage(), 'delay' => $retryDelay]);
				sleep($retryDelay);
				$retryDelay = min($retryDelay * 2, $maxRetryDelay);
			}
		}
	}

	/**
	 * Process the incoming stream from FediBuzz
	 *
	 * @param StreamInterface $body
	 * @return void
	 */
	private function processStream(StreamInterface $body): void
	{
		$buffer       = '';
		$currentEvent = null;
		$currentData  = '';

		while (!$body->eof()) {
			$buffer .= $body->read(8192);

			$lines  = explode("\n", $buffer);
			$buffer = array_pop($lines);

			foreach ($lines as $line) {
				$line = trim($line);
				if (str_starts_with($line, 'event: ')) {
					$currentEvent = substr($line, 7);
					$currentData  = '';
				} elseif (str_starts_with($line, 'data: ')) {
					$currentData .= substr($line, 6);
				} elseif ($line === '') {
					$data = json_decode($currentData, true);
					// @todo Handle other event types.
					// @see https://docs.joinmastodon.org/methods/streaming/#events-3
					if ($data !== null && $currentEvent === 'update') {
						$this->processUpdate($data);
					}
					$currentEvent = null;
					$currentData  = '';
				}
			}
			flush();
		}
	}

	/**
	 * Process an update event from the FediBuzz stream
	 *
	 * @param array $data
	 * @return void
	 */
	private function processUpdate(array $data): void
	{
		if (isset($data['reblog']) && is_array($data['reblog'])) {
			$data = $data['reblog'];
		}

		$tags = [];
		if (isset($data['tags']) && is_array($data['tags'])) {
			foreach ($data['tags'] as $tag) {
				if (isset($tag['name']) && is_string($tag['name'])) {
					$tags[] = $tag['name'];
				}
			}
		}

		$content  = trim(($data['spoiler_text'] ?? '') . "\n" . ($data['plain_content'] ?? HTML::toBBCode($data['content'] ?? '')));
		$authorid = Contact::getIdForURL($data['account']['uri'] ?? $data['account']['url'] ?? $data['account']['acct'] ?? '');
		$causer   = 'https://relay.fedi.buzz/instance/relay.fedi.buzz'; // We need the causer here to have an indicator that the post came from fedi.buzz.
		$url      = $data['uri'] ?? $data['url'] ?? '';
		if (isset($data['language']) && is_string($data['language'])) {
			$languages = [$data['language']];
		} else {
			$languages = [];
		}

		if (Relay::isSolicitedPost($tags, $content, $authorid, $url, Protocol::ACTIVITYPUB, 0, $languages)) {
			$this->logger->info('Matched post', ['url' => $url]);
			Receiver::handlePost($url, $causer, $data);
			return;
		}

		$searchtext = Engagement::getSearchTextForActivity($content, $authorid, $tags, [Receiver::PUBLIC_COLLECTION]);
		$languages  = $this->contentItem->getLanguageArray($content, 1, 0, $authorid);
		$language   = !empty($languages) ? array_key_first($languages) : '';
		if ($this->userDefinedChannel->match($searchtext, $language)) {
			$this->logger->info('Matched channel', ['url' => $url]);
			Receiver::handlePost($url, $causer, $data);
			return;
		}
	}
}
