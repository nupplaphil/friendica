<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Protocol\ActivityPub;

use Friendica\Content\Text\HTML;
use Friendica\Content\Item as ContentItem;
use Friendica\Content\Conversation\Repository\UserDefinedChannel;
use Friendica\Core\Protocol;
use Friendica\Model\Contact;
use Friendica\Model\Post\Engagement;
use Friendica\Network\HTTPClient\Client\HttpClient;
use Friendica\Network\HTTPClient\Client\HttpClientAccept;
use Friendica\Network\HTTPClient\Client\HttpClientOptions;
use Friendica\Network\HTTPClient\Client\HttpClientRequest;
use Friendica\Protocol\Relay;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for processing the FediBuzz firehose stream
 */
class Firehose
{
	private const FIREHOSE_URL    = 'https://fedi.buzz/api/v1/streaming/public';
	private const MAX_RETRY_DELAY = 60;
	private const CAUSER_URL      = 'https://relay.fedi.buzz/instance/relay.fedi.buzz';

	/**
	 * @param LoggerInterface      $logger
	 * @param HttpClient           $httpClient
	 * @param ContentItem          $contentItem
	 * @param UserDefinedChannel   $userDefinedChannel
	 */
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly HttpClient $httpClient,
		private readonly ContentItem $contentItem,
		private readonly UserDefinedChannel $userDefinedChannel,
	) {}

	/**
	 * Connects to the firehose and reconnects with exponential back-off on failure.
	 */
	public function streamLoop(): void
	{
		$url           = self::FIREHOSE_URL;
		$retryDelay    = 1;
		$maxRetryDelay = self::MAX_RETRY_DELAY;

		while (true) {
			try {
				$body = $this->httpClient->get(
					$url,
					HttpClientAccept::STREAMING,
					[
						HttpClientOptions::REQUEST => HttpClientRequest::STREAMING,
						HttpClientOptions::STREAM  => true,
					],
				)->getBodyStream();

				// Reset retry delay on successful connection
				$retryDelay = 1;

				$this->processStream($body);

			} catch (\Throwable $e) {
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
	public function processStream(StreamInterface $body): void
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
	public function processUpdate(array $data): void
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

		$content    = trim(($data['spoiler_text'] ?? '') . "\n" . ($data['plain_content'] ?? HTML::toBBCode($data['content'] ?? '')));
		$author_url = $data['account']['uri'] ?? $data['account']['url'] ?? $data['account']['acct'] ?? '';
		$author     = Contact::getByURL($author_url, null, ['id', 'unsearchable']);
		$causer     = self::CAUSER_URL;
		$url        = $data['uri'] ?? $data['url'] ?? '';
		if (isset($data['language']) && is_string($data['language'])) {
			$languages = [$data['language']];
		} else {
			$languages = [];
		}

		if (!isset($author['id']) || $author['unsearchable']) {
			$this->logger->info('Skipping unsearchable or unknown author', ['url' => $url, 'author-url' => $author_url, 'author' => $author]);
			return;
		}

		if (Relay::isSolicitedPost($tags, $content, $author['id'], $url, Protocol::ACTIVITYPUB, 0, $languages)) {
			$this->logger->info('Matched post', ['url' => $url]);
			Receiver::handlePost($url, $causer, $data);
			return;
		}

		$searchtext = Engagement::getSearchTextForActivity($content, $author['id'], $tags, [Receiver::PUBLIC_COLLECTION]);
		$languages  = $this->contentItem->getLanguageArray($content, 1, 0, $author['id']);
		$language   = !empty($languages) ? array_key_first($languages) : '';
		if ($this->userDefinedChannel->match($searchtext, $language)) {
			$this->logger->info('Matched channel', ['url' => $url]);
			Receiver::handlePost($url, $causer, $data);
			return;
		}
	}
}
