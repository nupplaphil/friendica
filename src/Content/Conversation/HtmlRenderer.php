<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation;

use Friendica\App\BaseURL;
use Friendica\App\Page;
use Friendica\AppHelper;
use Friendica\BaseModule;
use Friendica\Content\Conversation as ConversationContent;
use Friendica\Content\Conversation\Entity\Channel as ChannelEntity;
use Friendica\Content\Conversation\Factory\Channel as ChannelFactory;
use Friendica\Content\Conversation\Repository\UserDefinedChannel;
use Friendica\Content\Item;
use Friendica\Content\ContactSelector;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Core\PConfig\Capability\IManagePersonalConfigValues;
use Friendica\Core\Protocol;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Core\Theme;
use Friendica\Database\DBA;
use Friendica\Event\ArrayFilterEvent;
use Friendica\Model\Contact;
use Friendica\Model\Item as ItemModel;
use Friendica\Model\Post;
use Friendica\Model\Post\Category;
use Friendica\Model\Tag;
use Friendica\Model\Verb;
use Friendica\Protocol\Activity;
use Friendica\Security\Security;
use Friendica\User\Settings\Entity\UserGServer as UserGServerEntity;
use Friendica\User\Settings\Repository\UserGServer as UserGServerRepository;
use Friendica\Util\DateTimeFormat;
use Friendica\Util\Profiler;
use Friendica\Util\Strings;
use ImagickException;
use Psr\EventDispatcher\EventDispatcherInterface;

final class HtmlRenderer
{
	private bool $assetsRegistered = false;

	public function __construct(
		private readonly UserGServerRepository $userGServer,
		private readonly ChannelFactory $channel,
		private readonly UserDefinedChannel $userDefinedChannel,
		private readonly Profiler $profiler,
		private readonly Activity $activity,
		private readonly L10n $l10n,
		private readonly Item $item,
		private readonly BaseURL $baseURL,
		private readonly AppHelper $appHelper,
		private readonly IManageConfigValues $config,
		private readonly IManagePersonalConfigValues $pConfig,
		private readonly EventDispatcherInterface $eventDispatcher,
		private readonly IHandleUserSessions $session,
		private readonly Page $page,
		private readonly ThreadedPostTemplateRenderer $postTemplateRenderer,
	) {}

	/**
	 * Render the top-level post for the thread identified by the given uri-id.
	 *
	 * @param int $uriId The URI ID of the thread
	 * @param int|null $uid The user ID of the viewer, or null for public view
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @return string The rendered HTML of the post
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderPostByUriId(int $uriId, ?int $uid, string $mode): string
	{
		$viewerUid = $this->resolveViewerUid($uid);
		$root      = $this->getRootTemplateData($uriId, $viewerUid, 0);
		if (empty($root)) {
			return '';
		}

		$post             = $root;
		$post['children'] = [];

		return $this->renderItemHtml($post, $mode);
	}

	/**
	 * Render all comments for the thread identified by the given uri-id.
	 *
	 * @param int $uriId The URI ID of the thread
	 * @param int|null $uid The user ID of the viewer, or null for public view
	 * @param int $maxComments Maximum number of comments to render
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @return string The rendered HTML of all comments
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderCommentsByUriId(int $uriId, ?int $uid, int $maxComments, string $mode): string
	{
		$viewerUid = $this->resolveViewerUid($uid);
		$root      = $this->getRootTemplateData($uriId, $viewerUid, $maxComments);
		if (empty($root['children'])) {
			return '';
		}

		$html = '';
		foreach ($root['children'] as $child) {
			$html .= $this->renderItemHtml($child, $mode);
		}

		return $html;
	}

	/**
	 * Render the complete thread for the thread identified by the given uri-id.
	 *
	 * @param int $uriId The URI ID of the thread
	 * @param int|null $uid The user ID of the viewer, or null for public view
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @return string The rendered HTML of the complete thread
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderThreadByUriId(int $uriId, ?int $uid, string $mode): string
	{
		$maxComments = $mode === ConversationContent::MODE_DISPLAY ? $this->config->get('system', 'max_display_comments', 1000) : $this->config->get('system', 'max_comments', 100);
		$viewerUid   = $this->resolveViewerUid($uid);
		$root        = $this->getRootTemplateData($uriId, $viewerUid, $maxComments, $mode);
		if (empty($root)) {
			return '';
		}

		return $this->renderConversation([$root], $mode);
	}

	/**
	 * Render the conversation template with the given threads.
	 *
	 * @param array<int, array> $threads The thread data to render
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @return string The rendered HTML of the conversation
	 */
	private function renderConversation(array $threads, string $mode): string
	{
		return Renderer::replaceMacros(Renderer::getMarkupTemplate('threaded_conversation.tpl'), [
			'$live_update' => '',
			'$mode'        => $mode,
			'$update'      => false,
			'$threads'     => $threads,
			'$dropping'    => false,
			'$remove'      => $this->l10n->t('remove'),
		]);
	}

	/**
	 * Render the context-less list view (search/filed/contact-posts style).
	 *
	 * @param array<int, array> $items The items to render
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @param bool $update Whether this is an AJAX update
	 * @param bool $preview Whether to render in preview mode
	 * @param bool $pagedrop Whether to enable page drop functionality
	 * @param string $liveUpdate The live update URL
	 * @param string $returnPath The return path for navigation
	 * @param int|null $uid The user ID of the viewer, or null for public view
	 * @return string The rendered HTML of the context-less timeline
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderContextLessTimelineByItems(array $items, string $mode, bool $update, bool $preview, bool $pagedrop, string $liveUpdate, string $returnPath, ?int $uid = null): string
	{
		$viewerUid         = $this->resolveViewerUid($uid);
		$formSecurityToken = BaseModule::getFormSecurityToken('contact_action');
		$threads           = $this->buildContextLessThreadList($items, $mode, $preview, $pagedrop, $formSecurityToken, $viewerUid);

		return Renderer::replaceMacros(Renderer::getMarkupTemplate('conversation.tpl'), [
			'$return_path' => $returnPath,
			'$live_update' => $liveUpdate,
			'$remove'      => $this->l10n->t('remove'),
			'$mode'        => $mode,
			'$update'      => $update,
			'$threads'     => $threads,
			'$dropping'    => ($pagedrop ? $this->l10n->t('Delete Selected Items') : false),
		]);
	}

	/**
	 * Build context-less thread rows for modules that still consume `threads` arrays directly.
	 *
	 * @param array<int, array> $items The items to build threads from
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @param bool $preview Whether to render in preview mode
	 * @param bool $pagedrop Whether to enable page drop functionality
	 * @param int|null $uid The user ID of the viewer, or null for public view
	 * @return array<int, array> The built thread data
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function buildContextLessThreadsByItems(array $items, string $mode, bool $preview, bool $pagedrop, ?int $uid = null): array
	{
		$viewerUid         = $this->resolveViewerUid($uid);
		$formSecurityToken = BaseModule::getFormSecurityToken('contact_action');

		return $this->buildContextLessThreadList($items, $mode, $preview, $pagedrop, $formSecurityToken, $viewerUid);
	}

	/**
	 * Render the threaded list view from prefetched items (preview and non-preview compatible).
	 *
	 * @param array<int, array> $items The items to render
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @param bool $update Whether this is an AJAX update
	 * @param bool $preview Whether to render in preview mode
	 * @param bool $pagedrop Whether to enable page drop functionality
	 * @param string $liveUpdate The live update URL
	 * @param string $returnPath The return path for navigation
	 * @param int|null $uid The user ID of the viewer, or null for public view
	 * @return string The rendered HTML of the threaded timeline
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderThreadedTimelineByItems(array $items, string $mode, bool $update, bool $preview, bool $pagedrop, string $liveUpdate, string $returnPath, ?int $uid = null): string
	{
		$viewerUid         = $this->resolveViewerUid($uid);
		$formSecurityToken = BaseModule::getFormSecurityToken('contact_action');
		$threads           = $this->buildThreadTemplateData($items, $viewerUid, $mode, $preview, $pagedrop, $formSecurityToken);

		if (!$threads) {
			return '';
		}

		return $this->renderConversation($threads, $mode);
	}

	/**
	 * Render a timeline from a list of root uri-ids.
	 *
	 * @param array<int, int|string> $uriIds The URI IDs to render
	 * @param int $uid The user ID of the viewer
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @return string The rendered HTML of the timeline
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderTimelineByUriIds(array $uriIds, int $uid, string $mode): string
	{
		$maxComments = $this->config->get('system', 'max_comments', 100);
		$viewerUid   = $this->resolveViewerUid($uid);
		$threads     = [];
		foreach (array_values(array_unique(array_map(intval(...), $uriIds))) as $uriId) {
			if ($uriId <= 0) {
				continue;
			}

			$threads[] = $this->getRootTemplateData($uriId, $viewerUid, $maxComments, $mode);
		}

		return $this->renderConversation($threads, $mode);
	}

	/**
	 * Get the root template data for a thread.
	 *
	 * @param int $uriId The URI ID of the thread
	 * @param int $viewerUid The user ID of the viewer
	 * @param int $maxComments Maximum number of comments to include
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @return array<string, mixed>|null The root template data, or null if not found
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	protected function getRootTemplateData(int $uriId, int $viewerUid, int $maxComments, string $mode = ConversationContent::MODE_DISPLAY): ?array
	{
		$this->registerAssets();

		$thread = $this->loadThread($uriId, $viewerUid, $maxComments, $mode);
		if (empty($thread['items'])) {
			return null;
		}

		$root = $this->buildRootTemplateData($thread['items'], $thread['profile_owner'], $viewerUid, $mode);
		if (empty($root)) {
			return null;
		}

		return $root;
	}

	/**
	 * Build the root template data from items.
	 *
	 * @param array<int, array> $items The items to build from
	 * @param int $profileOwner The ID of the profile owner
	 * @param int $viewerUid The user ID of the viewer
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @return array<string, mixed>|null The root template data, or null if not found
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	protected function buildRootTemplateData(array $items, int $profileOwner, int $viewerUid, string $mode): ?array
	{
		$this->appHelper->setProfileOwner($profileOwner);

		$items = $this->dispatchConversationStart($items);
		if (empty($items)) {
			return null;
		}

		$threads = $this->buildThreadTemplateData(
			$items,
			$viewerUid,
			$mode,
			false,
			false,
			BaseModule::getFormSecurityToken('contact_action'),
		);
		if (empty($threads[0])) {
			return null;
		}

		return $threads[0];
	}

	/**
	 * Dispatch the conversation start event.
	 *
	 * @param array<int, array> $items The items to dispatch
	 * @return array<int, array> The filtered items after event dispatch
	 */
	protected function dispatchConversationStart(array $items): array
	{
		$cb = [
			'items'   => $items,
			'mode'    => ConversationContent::MODE_DISPLAY,
			'update'  => false,
			'preview' => false,
		];

		return $this->eventDispatcher->dispatch(
			new ArrayFilterEvent(ArrayFilterEvent::CONVERSATION_START, $cb),
		)->getArray()['items'];
	}

	/**
	 * Build thread template data from items.
	 *
	 * @param array<int, array> $items The items to build from
	 * @param int $viewerUid The user ID of the viewer
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @param bool $preview Whether to render in preview mode
	 * @param bool $pagedrop Whether to enable page drop functionality
	 * @param string $formSecurityToken The form security token
	 * @return array<int, array> The built thread template data
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	protected function buildThreadTemplateData(array $items, int $viewerUid, string $mode, bool $preview, bool $pagedrop, string $formSecurityToken): array
	{
		if (!$items) {
			return [];
		}

		$convResponses = $this->buildConversationResponses($viewerUid);

		if (in_array($mode, [ConversationContent::MODE_CHANNEL, ConversationContent::MODE_COMMUNITY, ConversationContent::MODE_CONTACTS, ConversationContent::MODE_PROFILE])) {
			$writable = true;
		} else {
			$writable = $items[0]['writable'] || (($items[0]['uid'] == 0) && in_array($items[0]['network'], Protocol::FEDERATED));
		}

		if (!$viewerUid) {
			$writable = false;
		}

		$parentItems = [];
		foreach ($items as $item) {
			$this->builtinActivityPuller($item, $convResponses);

			if ($item['network'] === Protocol::MAIL && $viewerUid !== $item['uid']) {
				continue;
			}

			if (!$this->item->isVisibleActivity($item)) {
				continue;
			}

			$item['pagedrop'] = $pagedrop;
			if ($item['gravity'] == ItemModel::GRAVITY_PARENT) {
				$parentItems[] = $item;
			}
		}

		$profileOwner = 0;
		switch ($mode) {
			case ConversationContent::MODE_NETWORK:
			case ConversationContent::MODE_NOTES:
				$profileOwner = (int) $this->session->getLocalUserId();
				$writable     = true;
				break;
			case ConversationContent::MODE_PROFILE:
			case ConversationContent::MODE_DISPLAY:
				$profileOwner = (int) $this->appHelper->getProfileOwner();
				$writable     = Security::canWriteToUserWall($profileOwner) || $writable;
				break;
			case ConversationContent::MODE_CHANNEL:
			case ConversationContent::MODE_COMMUNITY:
			case ConversationContent::MODE_CONTACTS:
				$profileOwner = 0;
				break;
		}

		$renderContext = new RenderThreadContext($mode, $preview, $writable, $profileOwner);
		$threads       = [];
		foreach ($parentItems as $item) {
			$templateData = $this->postTemplateRenderer->renderThreadRoot($item, $renderContext, $convResponses, $formSecurityToken);
			if ($templateData !== null) {
				$threads[] = $templateData;
			}
		}

		return $threads;
	}

	/**
	 * Build conversation responses array.
	 *
	 * @param int $viewerUid The user ID of the viewer
	 * @return array<string, array> The conversation responses array
	 */
	protected function buildConversationResponses(int $viewerUid): array
	{
		$convResponses = [
			'like'        => [],
			'dislike'     => [],
			'attendyes'   => [],
			'attendno'    => [],
			'attendmaybe' => [],
			'announce'    => [],
		];

		if ($this->pConfig->get($viewerUid, 'system', 'hide_dislike')) {
			unset($convResponses['dislike']);
		}

		return $convResponses;
	}

	/**
	 * Load a thread with children.
	 *
	 * @param int $uriId The URI ID of the thread
	 * @param int $viewerUid The user ID of the viewer
	 * @param int $maxComments Maximum number of comments to load
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @return array{items: array, profile_owner: int} The thread data with items and profile owner
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	protected function loadThread(int $uriId, int $viewerUid, int $maxComments, string $mode): array
	{
		$selected  = array_merge(ItemModel::DISPLAY_FIELDLIST, ['featured', 'contact-uid', 'gravity', 'post-type', 'post-reason']);
		$params    = ['order' => ['uid' => true]];
		$condition = ['uri-id' => $uriId, 'uid' => [0, $viewerUid]];

		$item = Post::selectFirstForUser($viewerUid, $selected, $condition, $params);
		if (empty($item) && !$viewerUid) {
			$item = Post::selectFirst($selected, ['uri-id' => $uriId, 'uid' => 0], $params);
		}

		if (empty($item)) {
			return ['items' => [], 'profile_owner' => 0];
		}

		if ($item['gravity'] != ItemModel::GRAVITY_PARENT) {
			$parentUriId = (int) $item['parent-uri-id'];
			$item        = Post::selectFirstForUser($viewerUid, $selected, ['uri-id' => $parentUriId, 'uid' => [0, $viewerUid]], $params);
			if (empty($item) && !$viewerUid) {
				$item = Post::selectFirst($selected, ['uri-id' => $parentUriId, 'uid' => 0], $params);
			}
		}

		if (empty($item)) {
			return ['items' => [], 'profile_owner' => 0];
		}

		$userGservers = $this->userGServer->listIgnoredByUser($viewerUid);
		$ignoredGsids = array_map(static function (UserGServerEntity $userGServer) {
			return $userGServer->gsid;
		}, $userGservers->getArrayCopy());

		$renderUserId = $viewerUid ?: (int) $item['uid'];
		$items        = $this->addChildren([$item], false, 'commented', $renderUserId, $mode, $ignoredGsids, $maxComments);

		return ['items' => $items, 'profile_owner' => (int) $item['uid']];
	}

	/**
	 * Resolve the viewer UID from the given UID or session.
	 *
	 * @param int|null $uid The optional user ID, or null to use session
	 * @return int The resolved viewer UID
	 */
	private function resolveViewerUid(?int $uid): int
	{
		return $uid ?? (int) $this->session->getLocalUserId();
	}

	/**
	 * Render a single item as HTML.
	 *
	 * @param array<string, mixed> $item The item data to render
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @return string The rendered HTML of the item
	 */
	protected function renderItemHtml(array $item, string $mode): string
	{
		return Renderer::replaceMacros(Renderer::getMarkupTemplate($item['template']), [
			'$item'   => $item,
			'$mode'   => $mode,
			'$remove' => $this->l10n->t('remove'),
		]);
	}

	/**
	 * Register required assets for the conversation rendering.
	 * Registers typeahead.js and tagsinput CSS/JS files.
	 */
	private function registerAssets(): void
	{
		if ($this->assetsRegistered) {
			return;
		}

		$this->page->registerFooterScript(Theme::getPathForFile('asset/typeahead.js/dist/typeahead.bundle.js'));
		$this->page->registerFooterScript(Theme::getPathForFile('js/friendica-tagsinput/friendica-tagsinput.js'));
		$this->page->registerStylesheet(Theme::getPathForFile('js/friendica-tagsinput/friendica-tagsinput.css'));
		$this->page->registerStylesheet(Theme::getPathForFile('js/friendica-tagsinput/friendica-tagsinput-typeahead.css'));
		$this->assetsRegistered = true;
	}

	/**
	 * Build context-less thread list from items.
	 *
	 * @param array<int, array> $items The items to build from
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @param bool $preview Whether to render in preview mode
	 * @param bool $pagedrop Whether to enable page drop functionality
	 * @param string $formSecurityToken The form security token
	 * @param int $viewerUid The user ID of the viewer
	 * @return array<int, array> The built thread list
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	protected function buildContextLessThreadList(array $items, string $mode, bool $preview, bool $pagedrop, string $formSecurityToken, int $viewerUid): array
	{
		$threads = [];
		$uriids  = [];

		foreach ($items as $item) {
			if (in_array($item['uri-id'], $uriids)) {
				continue;
			}

			$uriids[] = $item['uri-id'];

			if (!$this->item->isVisibleActivity($item)) {
				continue;
			}

			if ($item['network'] === Protocol::MAIL && $viewerUid !== $item['uid']) {
				continue;
			}

			$profileName = $item['author-name'];
			if (!empty($item['author-link']) && empty($item['author-name'])) {
				$profileName = $item['author-link'];
			}

			$tags = Tag::populateFromItem($item);

			$author = [
				'uid'     => 0,
				'id'      => $item['author-id'],
				'network' => $item['author-network'],
				'url'     => $item['author-link'],
				'alias'   => $item['author-alias'],
			];
			$profileLink = Contact::magicLinkByContact($author);

			$sparkle = '';
			if (str_starts_with($profileLink, 'contact/redir/')) {
				$sparkle = ' sparkle';
			}

			$locate = ['location' => $item['location'], 'coord' => $item['coord'], 'html' => ''];
			$locate = $this->eventDispatcher->dispatch(
				new ArrayFilterEvent(ArrayFilterEvent::RENDER_LOCATION, $locate),
			)->getArray();
			$locationHtml = $locate['html'] ?: Strings::escapeHtml($locate['location'] ?: $locate['coord'] ?: '');

			$this->item->localize($item);
			$drop = [
				'dropping' => ($mode === ConversationContent::MODE_FILED),
				'pagedrop' => $pagedrop,
				'select'   => $this->l10n->t('Select'),
				'delete'   => $this->l10n->t('Delete'),
			];

			$likebuttons = [
				'like'     => null,
				'dislike'  => null,
				'share'    => null,
				'announce' => null,
			];

			if ($this->pConfig->get($viewerUid, 'system', 'hide_dislike')) {
				unset($likebuttons['dislike']);
			}

			$bodyHtml               = ItemModel::prepareBody($item, true, $preview);
			[$categories, $folders] = $this->item->determineCategoriesTerms($item, $viewerUid);

			$pinned = !empty($item['featured']) ? $this->l10n->t('Pinned item') : '';
			if ($this->item->redundantSummary($item['body'], $item['content-warning'])) {
				$item['content-warning'] = '';
			}

			$tmpItem = [
				'template'             => 'search_item.tpl',
				'id'                   => ($preview ? 'P0' : $item['id']),
				'guid'                 => ($preview ? 'Q0' : $item['guid']),
				'commented'            => $item['commented'],
				'received'             => $item['received'],
				'created_date'         => $item['created'],
				'uriid'                => $item['uri-id'],
				'author_gsid'          => $item['author-gsid'],
				'network'              => $item['network'],
				'network_name'         => ContactSelector::networkToName($item['author-network'], $item['network'], $item['author-gsid']),
				'network_svg'          => ContactSelector::networkToSVG($item['network'], $item['author-gsid'], '', $viewerUid),
				'linktitle'            => $this->l10n->t('View %s\'s profile @ %s', $profileName, $item['author-link']),
				'profile_url'          => $profileLink,
				'item_photo_menu_html' => $this->item->photoMenu($item, $formSecurityToken),
				'name'                 => $profileName,
				'sparkle'              => $sparkle,
				'lock'                 => false,
				'thumb'                => $this->baseURL->remove($this->item->getAuthorAvatar($item)),
				'title'                => $item['title'],
				'summary'              => $item['content-warning'],
				'body_html'            => $bodyHtml,
				'tags'                 => $tags['tags'],
				'hashtags'             => $tags['hashtags'],
				'mentions'             => $tags['mentions'],
				'implicit_mentions'    => $tags['implicit_mentions'],
				'txt_cats'             => $this->l10n->t('Categories:'),
				'txt_folders'          => $this->l10n->t('Filed under:'),
				'has_cats'             => (count($categories) ? 'true' : ''),
				'has_folders'          => (count($folders) ? 'true' : ''),
				'categories'           => $categories,
				'folders'              => $folders,
				'text'                 => strip_tags($bodyHtml),
				'localtime'            => $this->l10n->fullDateTime($item['created']),
				'utc'                  => DateTimeFormat::utc($item['created'], 'c'),
				'ago'                  => (($item['app']) ? $this->l10n->t('%s from %s', $this->l10n->relativeDateTime($item['created']), $item['app']) : $this->l10n->relativeDateTime($item['created'])),
				'location_html'        => $locationHtml,
				'indent'               => '',
				'owner_name'           => '',
				'owner_url'            => '',
				'owner_photo'          => $this->baseURL->remove($this->item->getOwnerAvatar($item)),
				'plink'                => ItemModel::getPlink($item),
				'edpost'               => false,
				'pinned'               => $pinned,
				'isstarred'            => 'unstarred',
				'star'                 => false,
				'drop'                 => $drop,
				'vote'                 => $likebuttons,
				'like_html'            => '',
				'dislike_html '        => '',
				'comment_html'         => '',
				'conv'                 => $preview ? '' : ['href' => 'display/' . $item['guid'], 'title' => $this->l10n->t('View in context')],
				'previewing'           => $preview ? ' preview ' : '',
				'wait'                 => $this->l10n->t('Please wait'),
				'thread_level'         => 1,
			];

			$arr = ['item' => $item, 'output' => $tmpItem];
			$arr = $this->eventDispatcher->dispatch(
				new ArrayFilterEvent(ArrayFilterEvent::DISPLAY_ITEM, $arr),
			)->getArray();

			$threads[] = [
				'id'      => $item['id'],
				'network' => $item['network'],
				'items'   => [$arr['output']],
			];
		}

		return $threads;
	}

	/**
	 * Process built-in activity types and update conversation responses.
	 *
	 * @param array<string, mixed> $activity The activity data to process
	 * @param array<string, array> $convResponses The conversation responses array to update (by reference)
	 * @return void
	 */
	private function builtinActivityPuller(array $activity, array &$convResponses): void
	{
		$threadParent = $activity['thr-parent-row'] ?? [];

		foreach ($convResponses as $mode => $value) {
			switch ($mode) {
				case 'like':
					$verb = Activity::LIKE;
					break;
				case 'dislike':
					$verb = Activity::DISLIKE;
					break;
				case 'attendyes':
					$verb = Activity::ATTEND;
					break;
				case 'attendno':
					$verb = Activity::ATTENDNO;
					break;
				case 'attendmaybe':
					$verb = Activity::ATTENDMAYBE;
					break;
				case 'announce':
					$verb = Activity::ANNOUNCE;
					break;
				default:
					return;
			}

			if (!empty($activity['verb']) && $this->activity->match($activity['verb'], $verb) && ($activity['gravity'] != ItemModel::GRAVITY_PARENT)) {
				$author = [
					'uid'     => 0,
					'id'      => $activity['author-id'],
					'network' => $activity['author-network'],
					'url'     => $activity['author-link'],
					'alias'   => $activity['author-alias'],
				];
				$url     = Contact::magicLinkByContact($author);
				$sparkle = str_starts_with($url, 'contact/redir/') ? ' class="sparkle" ' : '';
				$link    = '<a href="' . $url . '"' . $sparkle . '>' . htmlentities((string) $activity['author-name']) . '</a>';

				if (empty($activity['thr-parent-id'])) {
					$activity['thr-parent-id'] = $activity['parent-uri-id'];
				}

				if (($verb == Activity::ANNOUNCE) && !empty($threadParent['causer-id']) && ($threadParent['causer-id'] == $activity['author-id'])) {
					continue;
				}

				if (!isset($convResponses[$mode][$activity['thr-parent-id']])) {
					$convResponses[$mode][$activity['thr-parent-id']] = [
						'links' => [],
						'self'  => 0,
					];
				} elseif (in_array($link, $convResponses[$mode][$activity['thr-parent-id']]['links'])) {
					continue;
				}

				if ($this->session->getPublicContactId() == $activity['author-id']) {
					$convResponses[$mode][$activity['thr-parent-id']]['self'] = 1;
				}

				$convResponses[$mode][$activity['thr-parent-id']]['links'][] = $link;
				return;
			}
		}
	}

	/**
	 * Add children to the parent items.
	 *
	 * @param array<int, array> $parents The parent items
	 * @param bool $blockAuthors Whether to block hidden authors
	 * @param string $order The sorting order
	 * @param int $uid The user ID
	 * @param string $mode The rendering mode (e.g., ConversationContent::MODE_DISPLAY)
	 * @param array<int, int> $ignoredGsids The ignored global server IDs
	 * @param int $maxComments Maximum number of comments to include
	 * @return array<int, array> The items with children added
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	private function addChildren(array $parents, bool $blockAuthors, string $order, int $uid, string $mode, array $ignoredGsids, int $maxComments): array
	{
		$this->profiler->startRecording('rendering');

		$self = $uid !== 0 ? Contact::getPublicIdByUserId($uid) : 0;

		$activities      = [];
		$uriIds          = [];
		$commentCounter  = [];
		$activityCounter = [];
		$postChannels    = [];

		foreach ($parents as $parent) {
			if (!empty($parent['thr-parent-id']) && !empty($parent['gravity']) && ($parent['gravity'] == ItemModel::GRAVITY_ACTIVITY)) {
				$uriId = $parent['thr-parent-id'];
				if (!empty($parent['author-id'])) {
					$activities[$uriId] = ['causer-id' => $parent['author-id']];
					foreach (['commented', 'received', 'created'] as $field) {
						if (!empty($parent[$field])) {
							$activities[$uriId][$field] = $parent[$field];
						}
					}
				}
			} else {
				$uriId = $parent['uri-id'];
			}

			$uriIds[]                = $uriId;
			$commentCounter[$uriId]  = 0;
			$activityCounter[$uriId] = 0;
			$postChannels[$uriId]    = $parent['channel'] ?? '';
		}

		$condition = ['parent-uri-id' => $uriIds];
		if ($blockAuthors) {
			$condition['author-hidden'] = false;
		}

		$emojis      = $this->getEmojis($uriIds, $uid);
		$quoteshares = $this->getQuoteShares($uriIds);
		$counts      = $this->getCounts($uriIds);

		if (!$this->config->get('system', 'legacy_activities')) {
			$condition = DBA::mergeConditions($condition, ["(`gravity` != ? OR `origin`)", ItemModel::GRAVITY_ACTIVITY]);
		}

		$condition = DBA::mergeConditions(
			$condition,
			["`uid` IN (0, ?) AND (NOT `verb` IN (?, ?, ?) OR `verb` IS NULL)", $uid, Activity::FOLLOW, Activity::VIEW, Activity::READ],
		);
		$condition = DBA::mergeConditions($condition, ["(`uid` != ? OR `private` != ?)", 0, ItemModel::PRIVATE]);
		$condition = DBA::mergeConditions(
			$condition,
			[
				"`visible` AND NOT `deleted` AND NOT `author-blocked` AND NOT `owner-blocked`
			AND ((NOT `contact-pending` AND (`contact-rel` IN (?, ?))) OR `self` OR `contact-uid` = ?)",
				Contact::SHARING,
				Contact::FRIEND,
				0,
			],
		);

		$threadParents = Post::select(['uri-id', 'causer-id'], $condition, ['order' => ['uri-id' => false, 'uid']]);
		$thrParent     = [];
		while ($row = Post::fetch($threadParents)) {
			$thrParent[$row['uri-id']] = $row;
		}
		DBA::close($threadParents);

		$params      = ['order' => ['uri-id' => true, 'uid' => true]];
		$threadItems = Post::select(array_merge(ItemModel::DISPLAY_FIELDLIST, ['featured', 'contact-uid', 'gravity', 'post-type', 'post-reason']), $condition, $params);

		$channels = [];
		foreach ($this->userDefinedChannel->selectByUid($uid) as $userChannel) {
			$channels[$userChannel->code] = $userChannel;
		}
		foreach ($this->channel->getTimelines($uid) as $systemChannel) {
			$channels[$systemChannel->code] = $systemChannel;
		}

		$items       = [];
		$quoteUriIds = [];
		$authors     = [];

		while ($row = Post::fetch($threadItems)) {
			if (!empty($items[$row['uri-id']]) && ($row['uid'] == 0)) {
				continue;
			}

			if (in_array($row['author-gsid'], $ignoredGsids)
				|| in_array($row['owner-gsid'], $ignoredGsids)
				|| in_array($row['causer-gsid'], $ignoredGsids)
			) {
				continue;
			}

			if (($mode != ConversationContent::MODE_CONTACTS) && !$row['origin']) {
				$row['featured'] = false;
			}

			if ($maxComments > 0) {
				if (($row['gravity'] == ItemModel::GRAVITY_COMMENT) && (++$commentCounter[$row['parent-uri-id']] > $maxComments)) {
					continue;
				}
				if (($row['gravity'] == ItemModel::GRAVITY_ACTIVITY) && (++$activityCounter[$row['parent-uri-id']] > $maxComments)) {
					continue;
				}
			}

			$authors[] = $row['author-id'];
			$authors[] = $row['owner-id'];

			if (in_array($row['gravity'], [ItemModel::GRAVITY_PARENT, ItemModel::GRAVITY_COMMENT])) {
				$quoteUriIds[$row['uri-id']] = [
					'uri-id'        => $row['uri-id'],
					'uri'           => $row['uri'],
					'parent-uri-id' => $row['parent-uri-id'],
					'parent-uri'    => $row['parent-uri'],
				];
			}

			$items[$row['uri-id']] = $this->addRowInformation($row, $activities[$row['uri-id']] ?? [], $thrParent[$row['thr-parent-id']] ?? [], $postChannels[$row['thr-parent-id']] ?? '', $uid, $channels);
		}
		DBA::close($threadItems);

		$quotes = Post::select(array_merge(ItemModel::DISPLAY_FIELDLIST, ['featured', 'contact-uid', 'gravity', 'post-type', 'post-reason']), ['quote-uri-id' => array_column($quoteUriIds, 'uri-id'), 'body' => '', 'uid' => 0]);
		while ($quote = Post::fetch($quotes)) {
			$row                  = $quote;
			$row['uid']           = $uid;
			$row['verb']          = $row['body'] = $row['raw-body'] = Activity::ANNOUNCE;
			$row['gravity']       = ItemModel::GRAVITY_ACTIVITY;
			$row['object-type']   = Activity\ObjectType::NOTE;
			$row['parent-uri']    = $quoteUriIds[$quote['quote-uri-id']]['parent-uri'];
			$row['parent-uri-id'] = $quoteUriIds[$quote['quote-uri-id']]['parent-uri-id'];
			$row['thr-parent']    = $quoteUriIds[$quote['quote-uri-id']]['uri'];
			$row['thr-parent-id'] = $quoteUriIds[$quote['quote-uri-id']]['uri-id'];

			$authors[]             = $row['author-id'];
			$authors[]             = $row['owner-id'];
			$items[$row['uri-id']] = $this->addRowInformation($row, [], [], $postChannels[$row['thr-parent-id']] ?? '', $uid, $channels);
		}
		DBA::close($quotes);

		$authors   = array_unique($authors);
		$blocks    = [];
		$ignores   = [];
		$collapses = [];
		if (!empty($authors)) {
			$userContacts = DBA::select('user-contact', ['cid', 'blocked', 'ignored', 'collapsed'], ['uid' => $uid, 'cid' => $authors]);
			while ($userContact = DBA::fetch($userContacts)) {
				if ($userContact['blocked']) {
					$blocks[] = $userContact['cid'];
				}
				if ($userContact['ignored']) {
					$ignores[] = $userContact['cid'];
				}
				if ($userContact['collapsed']) {
					$collapses[] = $userContact['cid'];
				}
			}
			DBA::close($userContacts);
		}

		foreach ($items as $key => $row) {
			$items[$key]['emojis']      = $emojis[$key]      ?? [];
			$items[$key]['counts']      = $counts[$key]      ?? 0;
			$items[$key]['quoteshares'] = $quoteshares[$key] ?? [];

			$alwaysDisplay                        = in_array($mode, [ConversationContent::MODE_CONTACTS, ConversationContent::MODE_CONTACT_POSTS]);
			$items[$key]['user-blocked-author']   = !$alwaysDisplay && in_array($row['author-id'], $blocks);
			$items[$key]['user-ignored-author']   = !$alwaysDisplay && in_array($row['author-id'], $ignores);
			$items[$key]['user-blocked-owner']    = !$alwaysDisplay && in_array($row['owner-id'], $blocks);
			$items[$key]['user-ignored-owner']    = !$alwaysDisplay && in_array($row['owner-id'], $ignores);
			$items[$key]['user-collapsed-author'] = !$alwaysDisplay && in_array($row['author-id'], $collapses);
			$items[$key]['user-collapsed-owner']  = !$alwaysDisplay && in_array($row['owner-id'], $collapses);

			if (in_array($mode, [ConversationContent::MODE_CHANNEL, ConversationContent::MODE_COMMUNITY, ConversationContent::MODE_NETWORK])
				&& (in_array($row['author-id'], $blocks) || in_array($row['owner-id'], $blocks) || in_array($row['author-id'], $ignores) || in_array($row['owner-id'], $ignores))
			) {
				unset($items[$key]);
			}
		}

		$items = $this->convSort($items, $order);
		$this->profiler->stopRecording();

		return $items;
	}

	/**
	 * Add additional information to a row.
	 *
	 * @param array<string, mixed> $row The row data to enhance
	 * @param array<string, mixed> $activity The activity data
	 * @param array<string, mixed> $thrParent The thread parent data
	 * @param string $channel The channel code
	 * @param int $uid The user ID
	 * @param array<string, ChannelEntity> $channels The available channels
	 * @return array<string, mixed> The enhanced row data
	 */
	private function addRowInformation(array $row, array $activity, array $thrParent, string $channel, int $uid, array $channels): array
	{
		$this->profiler->startRecording('rendering');

		if (!$row['writable']) {
			$row['writable'] = in_array($row['network'], Protocol::FEDERATED);
		}

		if (!empty($activity)) {
			if ($row['gravity'] == ItemModel::GRAVITY_PARENT) {
				$row['post-reason']   = ItemModel::PR_ANNOUNCEMENT;
				$row                  = array_merge($row, $activity);
				$contact              = Contact::getById($activity['causer-id'], ['url', 'name', 'thumb']);
				$row['causer-link']   = $contact['url'];
				$row['causer-avatar'] = $contact['thumb'];
				$row['causer-name']   = $contact['name'];
			} elseif (($row['gravity'] == ItemModel::GRAVITY_ACTIVITY) && ($row['verb'] == Activity::ANNOUNCE) && ($row['author-id'] == $activity['causer-id'])) {
				return $row;
			}
		}

		if ($channel) {
			$row['channel']     = $channel;
			$row['post-reason'] = ItemModel::PR_CHANNEL;
		}

		switch ($row['post-reason']) {
			case ItemModel::PR_TO:
				$row['direction'] = ['direction' => 7, 'title' => $this->l10n->t('You had been addressed (%s).', 'to')];
				break;
			case ItemModel::PR_CC:
				$row['direction'] = ['direction' => 7, 'title' => $this->l10n->t('You had been addressed (%s).', 'cc')];
				break;
			case ItemModel::PR_BTO:
				$row['direction'] = ['direction' => 7, 'title' => $this->l10n->t('You had been addressed (%s).', 'bto')];
				break;
			case ItemModel::PR_BCC:
				$row['direction'] = ['direction' => 7, 'title' => $this->l10n->t('You had been addressed (%s).', 'bcc')];
				break;
			case ItemModel::PR_AUDIENCE:
				$row['direction'] = ['direction' => 7, 'title' => $this->l10n->t('You had been addressed (%s).', 'audience')];
				break;
			case ItemModel::PR_FOLLOWER:
				$row['direction'] = ['direction' => 6, 'title' => $this->l10n->t('You are following %s.', $row['causer-name'] ?: $row['author-name'])];
				break;
			case ItemModel::PR_TAG:
				$tags             = Category::getArrayByURIId($row['uri-id'], $row['uid'], Category::SUBCRIPTION);
				$row['direction'] = ['direction' => 4, 'title' => empty($tags) ? $this->l10n->t('You subscribed to one or more tags in this post.') : $this->l10n->t('You subscribed to %s.', implode(', ', $tags))];
				break;
			case ItemModel::PR_ANNOUNCEMENT:
				if (!empty($row['causer-id']) && $this->pConfig->get($this->session->getLocalUserId(), 'system', 'display_resharer')) {
					$row['owner-id']     = $row['causer-id'];
					$row['owner-link']   = $row['causer-link'];
					$row['owner-avatar'] = $row['causer-avatar'];
					$row['owner-name']   = $row['causer-name'];
				}

				if (in_array($row['gravity'], [ItemModel::GRAVITY_PARENT, ItemModel::GRAVITY_COMMENT]) && !empty($row['causer-id'])) {
					$causer = [
						'uid'     => 0,
						'id'      => $row['causer-id'],
						'network' => $row['causer-network'],
						'url'     => $row['causer-link'],
						'alias'   => $row['causer-alias'],
					];
					$row['reshared'] = $this->l10n->t('%s reshared this.', '<a href="' . htmlentities(Contact::magicLinkByContact($causer)) . '">' . htmlentities((string) $row['causer-name']) . '</a>');
				}
				$row['direction'] = ['direction' => 3, 'title' => empty($row['causer-id']) ? $this->l10n->t('Reshared') : $this->l10n->t('Reshared by %s <%s>', $row['causer-name'], $row['causer-link'])];
				break;
			case ItemModel::PR_COMMENT:
				$row['direction'] = ['direction' => 5, 'title' => $this->l10n->t('%s is participating in this thread.', $row['author-name'])];
				break;
			case ItemModel::PR_STORED:
				$row['direction'] = ['direction' => 8, 'title' => $this->l10n->t('Stored for general reasons')];
				break;
			case ItemModel::PR_GLOBAL:
				$row['direction'] = ['direction' => 9, 'title' => $this->l10n->t('Global post')];
				break;
			case ItemModel::PR_RELAY:
				$row['direction'] = ['direction' => 10, 'title' => empty($row['causer-id']) ? $this->l10n->t('Sent via an relay server') : $this->l10n->t('Sent via the relay server %s <%s>', $row['causer-name'], $row['causer-link'])];
				break;
			case ItemModel::PR_FETCHED:
				$row['direction'] = ['direction' => 2, 'title' => empty($row['causer-id']) ? $this->l10n->t('Fetched') : $this->l10n->t('Fetched because of %s <%s>', $row['causer-name'], $row['causer-link'])];
				break;
			case ItemModel::PR_COMPLETION:
				$row['direction'] = ['direction' => 2, 'title' => $this->l10n->t('Stored because of a child post to complete this thread.')];
				break;
			case ItemModel::PR_DIRECT:
				$row['direction'] = ['direction' => 6, 'title' => $this->l10n->t('Local delivery')];
				break;
			case ItemModel::PR_ACTIVITY:
				$row['direction'] = ['direction' => 2, 'title' => $this->l10n->t('Stored because of your activity (like, comment, bookmark, ...)')];
				break;
			case ItemModel::PR_DISTRIBUTE:
				$row['direction'] = ['direction' => 6, 'title' => $this->l10n->t('Distributed')];
				break;
			case ItemModel::PR_PUSHED:
				$row['direction'] = ['direction' => 1, 'title' => $this->l10n->t('Pushed to us')];
				break;
			case ItemModel::PR_CHANNEL:
				$title            = $channels[$channel]->label       ?? $channel;
				$description      = $channels[$channel]->description ?? '';
				$row['direction'] = ['direction' => 11, 'title' => $description ? $this->l10n->t('Channel "%s": %s', $title, $description) : $this->l10n->t('Channel "%s"', $title)];
				break;
		}

		$row['thr-parent-row'] = $thrParent;
		$this->profiler->stopRecording();

		return $row;
	}

	/**
	 * Get emoji reactions for the given URI IDs.
	 *
	 * @param array<int, int> $uriIds The URI IDs to get emojis for
	 * @param int $uid The user ID of the viewer
	 * @return array<int, array> The emoji reactions data
	 */
	private function getEmojis(array $uriIds, int $uid): array
	{
		$emojis = [];
		foreach (Post\Counts::get(['parent-uri-id' => $uriIds]) as $count) {
			$emojis[$count['uri-id']][$count['reaction']]['emoji'] = $count['reaction'];
			$emojis[$count['uri-id']][$count['reaction']]['verb']  = Verb::getByID($count['vid']);
			$emojis[$count['uri-id']][$count['reaction']]['total'] = $count['count'];
			$emojis[$count['uri-id']][$count['reaction']]['count'] = 0;
			$emojis[$count['uri-id']][$count['reaction']]['title'] = [];
		}

		$activityVerbs = [
			Activity::LIKE,
			Activity::DISLIKE,
			Activity::ATTEND,
			Activity::ATTENDMAYBE,
			Activity::ATTENDNO,
			Activity::ANNOUNCE,
			Activity::VIEW,
			Activity::READ,
		];
		$verbs     = array_merge($activityVerbs, [Activity::EMOJIREACT, Activity::POST]);
		$condition = DBA::mergeConditions(['parent-uri-id' => $uriIds, 'gravity' => [ItemModel::GRAVITY_ACTIVITY, ItemModel::GRAVITY_COMMENT], 'verb' => $verbs], ["NOT `deleted`"]);
		$condition = DBA::mergeConditions($condition, ["((`uid` = ? AND `global`) OR (`uid` = ? AND NOT `global`))", 0, $uid]);
		$separator = chr(255) . chr(255) . chr(255);
		$sql       = "SELECT `parent-uri-id`, `thr-parent-id`, `body`, `verb`, `gravity`, `private`, GROUP_CONCAT(REPLACE(`author-name`, '" . $separator . "', ' ') SEPARATOR '" . $separator . "' LIMIT 50) AS `title` FROM `post-user-view` WHERE " . array_shift($condition) . " GROUP BY `parent-uri-id`, `thr-parent-id`, `verb`, `body`, `gravity`, `private`";

		$rows = DBA::p($sql, $condition);
		while ($row = DBA::fetch($rows)) {
			$emoji = $row['gravity'] == ItemModel::GRAVITY_ACTIVITY ? ($row['body'] ?: $row['verb']) : '';
			if (!isset($emojis[$row['thr-parent-id']][$emoji]['title'])) {
				continue;
			}

			if (($emoji === Activity::VIEW) && ($row['private'] === ItemModel::PRIVATE)) {
				continue;
			}

			$names                                          = explode($separator, (string) $row['title']);
			$emojis[$row['thr-parent-id']][$emoji]['title'] = array_unique(array_merge($emojis[$row['thr-parent-id']][$emoji]['title'] ?? [], $names));
			if ($row['private'] === ItemModel::PRIVATE) {
				$emojis[$row['thr-parent-id']][$emoji]['total'] += count($names);
			}
			$emojis[$row['thr-parent-id']][$emoji]['count'] += count($names);
		}
		DBA::close($rows);

		foreach ($emojis as $uriId => $row) {
			foreach ($row as $emoji => $value) {
				if (($value['count'] < $value['total']) && ($value['count'] < 50)) {
					$emojis[$uriId][$emoji]['total'] = $value['count'];
				}
				if ($emojis[$uriId][$emoji]['total'] === 0) {
					unset($emojis[$uriId][$emoji]);
				}
			}
		}

		return $emojis;
	}

	/**
	 * Get comment counts for the given URI IDs.
	 *
	 * @param array<int, int> $uriIds The URI IDs to get counts for
	 * @return array<int, int> The comment counts per URI ID
	 */
	private function getCounts(array $uriIds): array
	{
		$counts = [];
		foreach (Post\Counts::get(['parent-uri-id' => $uriIds, 'vid' => Verb::getID(Activity::POST)]) as $count) {
			$counts[$count['parent-uri-id']] = ($counts[$count['parent-uri-id']] ?? 0) + $count['count'];
		}

		return $counts;
	}

	/**
	 * Get quote shares for the given URI IDs.
	 *
	 * @param array<int, int> $uriIds The URI IDs to get quote shares for
	 * @return array<int, array> The quote shares data
	 */
	private function getQuoteShares(array $uriIds): array
	{
		$condition = DBA::mergeConditions(['quote-uri-id' => $uriIds], ["NOT `quote-uri-id` IS NULL"]);
		$separator = chr(255) . chr(255) . chr(255);
		$sql       = "SELECT `quote-uri-id`, COUNT(*) AS `total`, GROUP_CONCAT(REPLACE(`name`, '" . $separator . "', ' ') SEPARATOR '" . $separator . "' LIMIT 50) AS `title` FROM `post-quote` INNER JOIN `post` ON `post`.`uri-id` = `post-quote`.`uri-id` INNER JOIN `contact` ON `post`.`author-id` = `contact`.`id` WHERE " . array_shift($condition) . " GROUP BY `quote-uri-id`";
		$quotes    = [];

		$rows = DBA::p($sql, $condition);
		while ($row = DBA::fetch($rows)) {
			$quotes[$row['quote-uri-id']]['total'] = $row['total'];
			$quotes[$row['quote-uri-id']]['title'] = array_unique(explode($separator, (string) $row['title']));
		}
		DBA::close($rows);

		return $quotes;
	}

	/**
	 * Get children items for a parent item.
	 *
	 * @param array<int, array> $itemList The list of all items (passed by reference, modified)
	 * @param array<string, mixed> $parent The parent item
	 * @param bool $recursive Whether to recursively get children
	 * @return array<int, array> The children items
	 */
	private function getItemChildren(array &$itemList, array $parent, bool $recursive = true): array
	{
		$this->profiler->startRecording('rendering');
		$children = [];
		foreach ($itemList as $index => $item) {
			if ($item['gravity'] != ItemModel::GRAVITY_PARENT) {
				if ($recursive) {
					$thrParent = $item['thr-parent-id'];
					if ($thrParent == '') {
						$thrParent = $item['parent-uri-id'];
					}

					if ($thrParent == $parent['uri-id']) {
						$item['children'] = $this->getItemChildren($itemList, $item);
						$children[]       = $item;
						unset($itemList[$index]);
					}
				} elseif ($item['parent-uri-id'] == $parent['uri-id']) {
					$children[] = $item;
					unset($itemList[$index]);
				}
			}
		}
		$this->profiler->stopRecording();

		return $children;
	}

	/**
	 * Sort item children.
	 *
	 * @param array<int, array> $items The items to sort
	 * @return array<int, array> The sorted items
	 */
	private function sortItemChildren(array $items): array
	{
		$this->profiler->startRecording('rendering');
		$result = $items;
		usort($result, $this->sortThrReceivedRev(...));
		foreach ($result as $key => $item) {
			if (isset($result[$key]['children'])) {
				$result[$key]['children'] = $this->sortItemChildren($result[$key]['children']);
			}
		}
		$this->profiler->stopRecording();

		return $result;
	}

	/**
	 * Add children to the item list.
	 *
	 * @param array<int, array> $children The children to add
	 * @param array<int, array> $itemList The item list to add to (passed by reference)
	 * @return void
	 */
	private function addChildrenToList(array $children, array &$itemList): void
	{
		foreach ($children as $child) {
			$itemList[] = $child;
			if (isset($child['children'])) {
				$this->addChildrenToList($child['children'], $itemList);
			}
		}
	}

	/**
	 * Smart flatten conversation structure.
	 * Flattens nested conversation structures for better readability.
	 *
	 * @param array<string, mixed> $parent The parent conversation item
	 * @return array<string, mixed> The flattened conversation structure
	 */
	private function smartFlattenConversation(array $parent): array
	{
		$this->profiler->startRecording('rendering');
		if (!isset($parent['children']) || count($parent['children']) == 0) {
			$this->profiler->stopRecording();
			return $parent;
		}

		for ($index = 0; $index < count($parent['children']); $index++) {
			$child = $parent['children'][$index];
			if (isset($child['children']) && count($child['children'])) {
				$countPostClosure = function ($var) {
					return $var['verb'] === Activity::POST;
				};

				$childPostCount     = count(array_filter($child['children'], $countPostClosure));
				$remainingPostCount = count(array_filter(array_slice($parent['children'], $index), $countPostClosure));
				if ($childPostCount == 1 && $remainingPostCount == 1) {
					$childIndex = 0;
					while (($childIndex < count($child['children'])) && ($child['children'][$childIndex]['verb'] !== Activity::POST)) {
						$childIndex++;
					}
					if (isset($child['children'][$childIndex])) {
						$movedItem = $child['children'][$childIndex];
						unset($parent['children'][$index]['children'][$childIndex]);
						$parent['children'][] = $movedItem;
					}
				} else {
					$parent['children'][$index] = $this->smartFlattenConversation($child);
				}
			}
		}

		$this->profiler->stopRecording();
		return $parent;
	}

	/**
	 * Sort conversation items.
	 *
	 * @param array<int, array> $itemList The items to sort
	 * @param string $order The sorting order (e.g., 'received', 'commented', 'created')
	 * @return array<int, array> The sorted conversation items
	 */
	private function convSort(array $itemList, string $order): array
	{
		$this->profiler->startRecording('rendering');
		$parents = [];
		if (!(is_array($itemList) && count($itemList))) {
			$this->profiler->stopRecording();
			return $parents;
		}

		$itemArray = [];
		foreach ($itemList as $item) {
			$itemArray[$item['uri-id']] = $item;
		}

		foreach ($itemArray as $item) {
			if ($item['gravity'] == ItemModel::GRAVITY_PARENT) {
				$parents[] = $item;
			}
		}

		if (stristr($order, 'pinned_received')) {
			usort($parents, $this->sortThrFeaturedReceived(...));
		} elseif (stristr($order, 'pinned_commented')) {
			usort($parents, $this->sortThrFeaturedCommented(...));
		} elseif (stristr($order, 'pinned_created')) {
			usort($parents, $this->sortThrFeaturedCreated(...));
		} elseif (stristr($order, 'received')) {
			usort($parents, $this->sortThrReceived(...));
		} elseif (stristr($order, 'commented')) {
			usort($parents, $this->sortThrCommented(...));
		} elseif (stristr($order, 'created')) {
			usort($parents, $this->sortThrCreated(...));
		}

		foreach ($parents as $index => $parent) {
			$parents[$index]['children'] = array_merge(
				$this->getItemChildren($itemArray, $parent, true),
				$this->getItemChildren($itemArray, $parent, false),
			);
		}
		foreach ($parents as $index => $parent) {
			$parents[$index]['children'] = $this->sortItemChildren($parents[$index]['children']);
		}

		if (!$this->pConfig->get($this->session->getLocalUserId(), 'system', 'no_smart_threading', 0)) {
			foreach ($parents as $index => $parent) {
				$parents[$index] = $this->smartFlattenConversation($parent);
			}
		}

		foreach ($parents as $parent) {
			if (count($parent['children'])) {
				$this->addChildrenToList($parent['children'], $parents);
			}
		}

		$this->profiler->stopRecording();
		return $parents;
	}

	/**
	 * Sort threads by featured and received date.
	 * Featured threads come first, then sorted by received date descending.
	 *
	 * @param array<string, mixed> $a First thread to compare
	 * @param array<string, mixed> $b Second thread to compare
	 * @return int Comparison result (-1, 0, 1)
	 */
	private function sortThrFeaturedReceived(array $a, array $b): int
	{
		if ($b['featured'] && !$a['featured']) {
			return 1;
		} elseif (!$b['featured'] && $a['featured']) {
			return -1;
		}

		return strcmp((string) $b['received'], (string) $a['received']);
	}

	/**
	 * Sort threads by featured and commented date.
	 * Featured threads come first, then sorted by commented date descending.
	 *
	 * @param array<string, mixed> $a First thread to compare
	 * @param array<string, mixed> $b Second thread to compare
	 * @return int Comparison result (-1, 0, 1)
	 */
	private function sortThrFeaturedCommented(array $a, array $b): int
	{
		if ($b['featured'] && !$a['featured']) {
			return 1;
		} elseif (!$b['featured'] && $a['featured']) {
			return -1;
		}

		return strcmp((string) $b['commented'], (string) $a['commented']);
	}

	/**
	 * Sort threads by featured and created date.
	 * Featured threads come first, then sorted by created date descending.
	 *
	 * @param array<string, mixed> $a First thread to compare
	 * @param array<string, mixed> $b Second thread to compare
	 * @return int Comparison result (-1, 0, 1)
	 */
	private function sortThrFeaturedCreated(array $a, array $b): int
	{
		if ($b['featured'] && !$a['featured']) {
			return 1;
		} elseif (!$b['featured'] && $a['featured']) {
			return -1;
		}

		return strcmp((string) $b['created'], (string) $a['created']);
	}

	/**
	 * Sort threads by received date descending.
	 *
	 * @param array<string, mixed> $a First thread to compare
	 * @param array<string, mixed> $b Second thread to compare
	 * @return int Comparison result (-1, 0, 1)
	 */
	private function sortThrReceived(array $a, array $b): int
	{
		return strcmp((string) $b['received'], (string) $a['received']);
	}

	/**
	 * Sort threads by received date ascending (reversed).
	 *
	 * @param array<string, mixed> $a First thread to compare
	 * @param array<string, mixed> $b Second thread to compare
	 * @return int Comparison result (-1, 0, 1)
	 */
	private function sortThrReceivedRev(array $a, array $b): int
	{
		return strcmp((string) $a['received'], (string) $b['received']);
	}

	/**
	 * Sort threads by commented date descending.
	 *
	 * @param array<string, mixed> $a First thread to compare
	 * @param array<string, mixed> $b Second thread to compare
	 * @return int Comparison result (-1, 0, 1)
	 */
	private function sortThrCommented(array $a, array $b): int
	{
		return strcmp((string) $b['commented'], (string) $a['commented']);
	}

	/**
	 * Sort threads by created date descending.
	 *
	 * @param array<string, mixed> $a First thread to compare
	 * @param array<string, mixed> $b Second thread to compare
	 * @return int Comparison result (-1, 0, 1)
	 */
	private function sortThrCreated(array $a, array $b): int
	{
		return strcmp((string) $b['created'], (string) $a['created']);
	}

}
