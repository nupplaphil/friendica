<?php

declare(strict_types=1);

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation;

use Friendica\App\BaseURL;
use Friendica\BaseModule;
use Friendica\Content\ContactSelector;
use Friendica\Content\Item;
use Friendica\Core\L10n;
use Friendica\Core\PConfig\Capability\IManagePersonalConfigValues;
use Friendica\Core\Protocol;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Event\ArrayFilterEvent;
use Friendica\Model\Contact;
use Friendica\Model\Item as ItemModel;
use Friendica\Model\Post;
use Friendica\Model\Tag;
use Friendica\Util\DateTimeFormat;
use Friendica\Util\Profiler;
use Friendica\Util\Strings;
use ImagickException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Facade class for conversation rendering.
 * This is the main entry point for rendering conversations and threads.
 * It delegates to ConversationDataProvider and PostTemplateBuilder for the actual work.
 */
final readonly class ConversationRenderer
{
	public const MODE_CHANNEL       = 'channel';
	public const MODE_COMMENTS      = 'comments';
	public const MODE_COMMUNITY     = 'community';
	public const MODE_CONTACTS      = 'contacts';
	public const MODE_CONTACT_POSTS = 'contact-posts';
	public const MODE_DISPLAY       = 'display';
	public const MODE_FILED         = 'filed';
	public const MODE_NETWORK       = 'network';
	public const MODE_NOTES         = 'notes';
	public const MODE_SEARCH        = 'search';
	public const MODE_PROFILE       = 'profile';

	public const ORDER_COMMENTED        = 'commented';
	public const ORDER_RECEIVED         = 'received';
	public const ORDER_CREATED          = 'created';
	public const ORDER_PINNED_COMMENTED = 'pinned_commented';
	public const ORDER_PINNED_RECEIVED  = 'pinned_received';
	public const ORDER_PINNED_CREATED   = 'pinned_created';

	public function __construct(
		private L10n $l10n,
		private Item $item,
		private BaseURL $baseURL,
		private IManagePersonalConfigValues $pConfig,
		private EventDispatcherInterface $eventDispatcher,
		private IHandleUserSessions $session,
		private ConversationDataProvider $dataProvider,
		private Profiler $profiler,
		private \Friendica\App\Arguments $args,
		private StatusEditor $statusEditor,
	) {}

	/**
	 * Render a conversation or list of items for HTML display with threaded structure.
	 * This is the main entry point for rendering, delegating to specific render methods.
	 *
	 * @param array $items An array of Posts
	 * @param string $mode One of self::MODE_*
	 * @param bool $update Asynchronous update rendering
	 * @param string $order One of self::ORDER_*
	 * @param int $uid
	 * @param array $request
	 * @return string
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderThreaded(array $items, string $mode, bool $update = false, string $order = self::ORDER_COMMENTED, int $uid = 0, array $request = []): string
	{
		$this->profiler->startRecording('rendering');
		$this->statusEditor->registerAssets();

		$viewerUid = $this->resolveViewerUid($uid);

		$live_update_div = $this->getLiveUpdateHtml($mode, $update, $viewerUid, $request);

		$page_dropping = $viewerUid && $this->pConfig->get($viewerUid, 'system', 'show_page_drop', true);

		if (!$update) {
			$_SESSION['return_path'] = $this->args->getQueryString();
		}

		$cb = ['items' => $items, 'mode' => $mode, 'update' => $update, 'preview' => false];

		$cb = $this->eventDispatcher->dispatch(
			new ArrayFilterEvent(ArrayFilterEvent::CONVERSATION_START, $cb),
		)->getArray();

		$items = $cb['items'];

		$timelineHtml = $this->renderTimelineByItems($items, $viewerUid, $mode, $order);
		$html         = $live_update_div . $timelineHtml;

		if (!$update) {
			$html .= "<div id=\"conversation-end\"></div>\n\n";
			if ($page_dropping) {
				$html .= '<div id="item-delete-selected" class="fakelink" onclick="deleteCheckedItems();">'
					. '<div id="item-delete-selected-icon" class="icon drophide" title="' . $this->l10n->t('Delete Selected Items') . '" onmouseover="imgbright(this);" onmouseout="imgdull(this);"></div>'
					. '<div id="item-delete-selected-desc">' . $this->l10n->t('Delete Selected Items') . '</div>'
					. '</div>'
					. '<img id="item-delete-selected-rotator" class="like-rotator" src="images/rotator.gif" style="display: none;" />'
					. '<div id="item-delete-selected-end"></div>';
			}
		}

		$this->profiler->stopRecording();
		return $html;
	}

	/**
	 * Render all comments for the thread identified by the given uri-id.
	 *
	 * @param int $uriId The URI ID of the thread
	 * @param int $uid The user ID of the viewer, or null for public view
	 * @param array $existing Existing comment URI IDs to exclude
	 * @return string The rendered HTML of all comments
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderCommentsByUriId(int $uriId, int $uid, array $existing = []): string
	{
		$this->profiler->startRecording('rendering');
		$this->statusEditor->registerAssets();

		$viewerUid = $this->resolveViewerUid($uid);

		$selected = array_merge(ItemModel::DISPLAY_FIELDLIST, ['featured', 'contact-uid', 'gravity', 'post-type', 'post-reason']);
		$item     = Post::selectFirst($selected, ['uri-id' => $uriId, 'uid' => [0, $viewerUid]], ['order' => ['uid' => true]]);
		if (empty($item)) {
			$this->profiler->stopRecording();
			return '';
		}

		$root = $this->dataProvider->getRootTemplateDataFromItem($item, $viewerUid, self::MODE_COMMENTS, $existing);
		if (empty($root['children'])) {
			$this->profiler->stopRecording();
			return '';
		}

		$html = '';
		foreach ($root['children'] as $child) {
			// wall_thread.tpl contains a different treating for MODE_DISPLAY that we use here
			$html .= $this->renderItemHtml($child, self::MODE_DISPLAY);
		}

		$this->profiler->stopRecording();
		return $html;
	}

	/**
	 * Render the complete thread for the given item array.
	 * This avoids loading the item from the database when it's already available.
	 *
	 * @param array $item The item array
	 * @param int $uid The user ID of the viewer, or null for public view
	 * @param string $mode The rendering mode (e.g., self::MODE_DISPLAY)
	 * @return string The rendered HTML of the complete thread
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderThreadByItem(array $item, int $uid, string $mode): string
	{
		$this->profiler->startRecording('rendering');
		$this->statusEditor->registerAssets();

		$viewerUid = $this->resolveViewerUid($uid);

		$root = $this->dataProvider->getRootTemplateDataFromItem($item, $viewerUid, $mode);
		if (empty($root)) {
			$this->profiler->stopRecording();
			return '';
		}

		$html = $this->renderThreadedTemplate([$root], $mode, false, false);
		$this->profiler->stopRecording();
		return $html;
	}

	/**
	 * Render a flat list of items (without threading).
	 *
	 * @param array $items An array of Posts
	 * @param string $mode One of self::MODE_*
	 * @param bool $preview Post preview (no actual database record)
	 * @param int $uid The user ID of the viewer
	 * @return string The rendered HTML
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function renderFlat(array $items, string $mode, bool $preview, int $uid = 0): string
	{
		$this->profiler->startRecording('rendering');
		$this->statusEditor->registerAssets();

		$viewerUid = $this->resolveViewerUid($uid);

		$live_update_div = '';

		if ($mode === self::MODE_SEARCH) {
			$live_update_div = '<div id="live-search"></div>' . "\r\n";
		}

		$_SESSION['return_path'] = $this->args->getQueryString();

		$cb = ['items' => $items, 'mode' => $mode, 'update' => false, 'preview' => $preview];

		$cb = $this->eventDispatcher->dispatch(
			new ArrayFilterEvent(ArrayFilterEvent::CONVERSATION_START, $cb),
		)->getArray();

		$items = $cb['items'];

		$html = $this->renderContextLessTimelineByItems(
			$items,
			$mode,
			false,
			$preview,
			false,
			$live_update_div,
			$this->args->getQueryString(),
			$viewerUid,
		);

		$this->profiler->stopRecording();
		return $html;
	}

	/**
	 * Build the live update HTML div based on the mode and request parameters.
	 *
	 * @param string $mode The rendering mode
	 * @param bool $update Whether this is an update
	 * @param int $viewerUid The viewer user ID
	 * @param array $request The request parameters
	 * @return string The live update HTML
	 */
	private function getLiveUpdateHtml(string $mode, bool $update, int $viewerUid, array $request): string
	{
		if ($update) {
			return '';
		}

		$live_update_div = '';

		if ($mode === self::MODE_NETWORK) {
			$live_update_div = '<div id="live-network"></div>' . "\r\n"
				. "<script> var profile_uid = " . $viewerUid
				. "; var netargs = '" . $this->getCommandAfterFirstSlash()
				. '?f='
				. (!empty($request['contactid']) ? '&contactid=' . rawurlencode((string) ($request['contactid'] ?? '')) : '')
				. (!empty($request['search'])    ? '&search=' . rawurlencode((string) ($request['search'] ?? '')) : '')
				. (!empty($request['star'])      ? '&star=' . rawurlencode((string) ($request['star'] ?? '')) : '')
				. (!empty($request['order'])     ? '&order=' . rawurlencode((string) ($request['order'] ?? '')) : '')
				. (!empty($request['bmark'])     ? '&bmark=' . rawurlencode((string) ($request['bmark'] ?? '')) : '')
				. (!empty($request['liked'])     ? '&liked=' . rawurlencode((string) ($request['liked'] ?? '')) : '')
				. (!empty($request['conv'])      ? '&conv=' . rawurlencode((string) ($request['conv'] ?? '')) : '')
				. (!empty($request['nets'])      ? '&nets=' . rawurlencode((string) ($request['nets'] ?? '')) : '')
				. (!empty($request['cmin'])      ? '&cmin=' . rawurlencode((string) ($request['cmin'] ?? '')) : '')
				. (!empty($request['cmax'])      ? '&cmax=' . rawurlencode((string) ($request['cmax'] ?? '')) : '')
				. (!empty($request['file'])      ? '&file=' . rawurlencode((string) ($request['file'] ?? '')) : '')
				. (!empty($request['channel'])   ? '&channel=' . rawurlencode((string) ($request['channel'] ?? '')) : '')
				. (!empty($request['no_sharer']) ? '&no_sharer=' . rawurlencode((string) ($request['no_sharer'] ?? '')) : '')
				. (!empty($request['accounttype']) ? '&accounttype=' . rawurlencode((string) ($request['accounttype'] ?? '')) : '')
				. "'; </script>\r\n";
		} elseif ($mode === self::MODE_PROFILE && (!isset($request['tab']) || $request['tab'] === 'posts')) {
			$live_update_div = '<div id="live-profile"></div>' . "\r\n"
				. "<script> var profile_uid = " . $viewerUid
				. "; var netargs = '?f='; </script>\r\n";
		} elseif ($mode === self::MODE_NOTES) {
			$live_update_div = '<div id="live-notes"></div>' . "\r\n"
				. "<script> var profile_uid = " . $viewerUid
				. "; var netargs = '?f='; </script>\r\n";
		} elseif ($mode === self::MODE_DISPLAY) {
			$live_update_div = '<div id="live-display"></div>' . "\r\n"
				. "<script> var profile_uid = " . ($viewerUid ?: 0) . ";"
				. "</script>";
		} elseif ($mode === self::MODE_CHANNEL) {
			$live_update_div = '<div id="live-channel"></div>' . "\r\n"
				. "<script> var profile_uid = -1; var netargs = '" . $this->getCommandAfterFirstSlash()
				. '?f='
				. (!empty($request['no_sharer']) ? '&no_sharer=' . rawurlencode((string) ($request['no_sharer'] ?? '')) : '')
				. (!empty($request['accounttype']) ? '&accounttype=' . rawurlencode((string) ($request['accounttype'] ?? '')) : '')
				. "'; </script>\r\n";
		} elseif ($mode === self::MODE_COMMUNITY) {
			$live_update_div = '<div id="live-community"></div>' . "\r\n"
				. "<script> var profile_uid = -1; var netargs = '" . $this->getCommandAfterFirstSlash()
				. '?f='
				. (!empty($request['no_sharer']) ? '&no_sharer=' . rawurlencode((string) ($request['no_sharer'] ?? '')) : '')
				. (!empty($request['accounttype']) ? '&accounttype=' . rawurlencode((string) ($request['accounttype'] ?? '')) : '')
				. "'; </script>\r\n";
		} elseif ($mode === self::MODE_CONTACTS) {
			$live_update_div = '<div id="live-contact"></div>' . "\r\n"
				. "<script> var profile_uid = -1; var netargs = '" . $this->getCommandAfterFirstSlash()
				. "?f='; </script>\r\n";
		}

		return $live_update_div;
	}

	/**
	 * Extracts the part of a path after the first slash.
	 *
	 * @return string The part after the first slash, or empty string if no slash exists
	 */
	private function getCommandAfterFirstSlash(): string
	{
		$command = $this->args->getCommand();
		$pos     = strpos($command, '/');
		if ($pos === false) {
			return '';
		}
		return substr($command, $pos + 1);
	}

	/**
	 * Render a timeline from multiple item arrays.
	 * Similar to renderThreadByItem but works with multiple parent items.
	 *
	 * @param array<int, array> $items The parent items to render
	 * @param int $uid The user ID of the viewer
	 * @param string $mode The rendering mode (e.g., self::MODE_DISPLAY)
	 * @param string $order One of self::ORDER_*
	 * @return string The rendered HTML of the timeline
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	private function renderTimelineByItems(array $items, int $uid, string $mode, string $order): string
	{
		$roots = $this->dataProvider->getRootTemplateDataFromItems($items, $uid, $mode, $order);
		if (empty($roots)) {
			return '';
		}

		return $this->renderThreadedTemplate($roots, $mode, false, false);
	}

	/**
	 * Render the conversation template with the given threads.
	 *
	 * @param array<int, array> $threads The thread data to render
	 * @param string $mode The rendering mode (e.g., self::MODE_DISPLAY)
	 * @return string The rendered HTML of the conversation
	 */
	private function renderThreadedTemplate(array $threads, string $mode, bool $update, bool $pagedrop): string
	{
		return Renderer::replaceMacros(Renderer::getMarkupTemplate('threaded_conversation.tpl'), [
			'$live_update' => '',
			'$mode'        => $mode,
			'$update'      => $update,
			'$threads'     => $threads,
			'$dropping'    => ($pagedrop ? $this->l10n->t('Delete Selected Items') : false),
		]);
	}

	/**
	 * Render the context-less list view (search/filed/contact-posts style).
	 *
	 * @param array<int, array> $items The items to render
	 * @param string $mode The rendering mode (e.g., self::MODE_DISPLAY)
	 * @param bool $update Whether this is an AJAX update
	 * @param bool $preview Whether to render in preview mode
	 * @param bool $pagedrop Whether to enable page drop functionality
	 * @param string $liveUpdate The live update URL
	 * @param string $returnPath The return path for navigation
	 * @param int $uid The user ID of the viewer, or null for public view
	 * @return string The rendered HTML of the context-less timeline
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	private function renderContextLessTimelineByItems(array $items, string $mode, bool $update, bool $preview, bool $pagedrop, string $liveUpdate, string $returnPath, int $uid): string
	{
		$formSecurityToken = BaseModule::getFormSecurityToken('contact_action');
		$threads           = $this->buildContextLessThreadList($items, $mode, $preview, $pagedrop, $formSecurityToken, $uid);

		return Renderer::replaceMacros(Renderer::getMarkupTemplate('conversation.tpl'), [
			'$live_update' => $liveUpdate,
			'$update'      => $update,
			'$threads'     => $threads,
			'$dropping'    => ($pagedrop ? $this->l10n->t('Delete Selected Items') : false),
		]);
	}

	/**
	 * Build context-less thread list from items.
	 *
	 * @param array<int, array> $items The items to build from
	 * @param string $mode The rendering mode (e.g., self::MODE_DISPLAY)
	 * @param bool $preview Whether to render in preview mode
	 * @param bool $pagedrop Whether to enable page drop functionality
	 * @param string $formSecurityToken The form security token
	 * @param int $viewerUid The user ID of the viewer
	 * @return array<int, array> The built thread list
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	private function buildContextLessThreadList(array $items, string $mode, bool $preview, bool $pagedrop, string $formSecurityToken, int $viewerUid): array
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
				'dropping' => ($mode === self::MODE_FILED),
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
				'txt_cats'             => $this->l10n->t('Categories:'),
				'txt_folders'          => $this->l10n->t('Filed under:'),
				'has_cats'             => (count($categories) ? 'true' : ''),
				'has_folders'          => (count($folders) ? 'true' : ''),
				'categories'           => $categories,
				'folders'              => $folders,
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
				'star'                 => false,
				'drop'                 => $drop,
				'vote'                 => $likebuttons,
				'like_html'            => '',
				'dislike_html'         => '',
				'comment_html'         => '',
				'conv'                 => $preview ? '' : ['href' => 'display/' . $item['guid'], 'title' => $this->l10n->t('View in context')],
				'previewing'           => $preview ? ' preview ' : '',
				'wait'                 => $this->l10n->t('Please wait'),
				'loading'              => $this->l10n->t('Loading ...'),
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
	 * Render a single item as HTML.
	 *
	 * @param array<string, mixed> $item The item data to render
	 * @param string $mode The rendering mode (e.g., self::MODE_DISPLAY)
	 * @return string The rendered HTML of the item
	 */
	private function renderItemHtml(array $item, string $mode): string
	{
		return Renderer::replaceMacros(Renderer::getMarkupTemplate($item['template']), [
			'$item'   => $item,
			'$mode'   => $mode,
			'$remove' => $this->l10n->t('remove'),
		]);
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
}
