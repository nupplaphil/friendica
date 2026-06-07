<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content;

use Friendica\App\Arguments;
use Friendica\App\Mode;
use Friendica\App\Page;
use Friendica\Content\Conversation\HtmlRenderer;
use Friendica\Core\ACL;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Core\PConfig\Capability\IManagePersonalConfigValues;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Core\Theme;
use Friendica\Database\DBA;
use Friendica\Event\ArrayFilterEvent;
use Friendica\Event\HtmlFilterEvent;
use Friendica\Model\Item as ItemModel;
use Friendica\Model\User;
use Friendica\User\Settings\Entity\UserGServer as UserGServerEntity;
use Friendica\User\Settings\Repository\UserGServer as UserGServerRepository;
use Friendica\Util\Crypto;
use Friendica\Util\Profiler;
use Friendica\Util\Temporal;
use ImagickException;
use Psr\EventDispatcher\EventDispatcherInterface;

class Conversation
{
	public const MODE_CHANNEL       = 'channel';
	public const MODE_COMMUNITY     = 'community';
	public const MODE_CONTACTS      = 'contacts';
	public const MODE_CONTACT_POSTS = 'contact-posts';
	public const MODE_DISPLAY       = 'display';
	public const MODE_FILED         = 'filed';
	public const MODE_NETWORK       = 'network';
	public const MODE_NOTES         = 'notes';
	public const MODE_SEARCH        = 'search';
	public const MODE_PROFILE       = 'profile';

	public function __construct(private readonly UserGServerRepository $userGServer, private readonly Profiler $profiler, private readonly L10n $l10n, private readonly Arguments $args, private readonly IManageConfigValues $config, private readonly IManagePersonalConfigValues $pConfig, private Page $page, private readonly Mode $mode, private readonly EventDispatcherInterface $eventDispatcher, private readonly IHandleUserSessions $session, private readonly HtmlRenderer $htmlRenderer) {}

	public function statusEditor(array $x = [], int $notes_cid = 0, bool $popup = false): string
	{

		// The user viewing, not the user being viewed
		$user = User::getById($this->session->getLocalUserId(), ['uid', 'nickname', 'allow_location', 'default-location']);
		if (empty($user['uid'])) {
			return '';
		}

		$this->profiler->startRecording('rendering');
		$o = '';

		$x['allow_location'] ??= $user['allow_location'];
		$x['default_location'] ??= $user['default-location'];
		$x['nickname'] ??= $user['nickname'];
		$x['lockstate'] = $x['lockstate'] ?? ACL::getLockstateForUserId($user['uid']) ? 'lock' : 'unlock';
		$x['acl'] ??= ACL::getFullSelectorHTML($this->page, $user['uid'], true);
		$x['bang'] ??= '';
		$x['visitor'] ??= 'block';
		$x['is_owner'] ??= true;
		$x['profile_uid'] ??= $this->session->getLocalUserId();

		$geotag = !empty($x['allow_location']) ? Renderer::replaceMacros(Renderer::getMarkupTemplate('jot_geotag.tpl'), []) : '';

		$tpl = Renderer::getMarkupTemplate('jot-header.tpl');
		$this->page['htmlhead'] .= Renderer::replaceMacros($tpl, [
			'$newpost'       => 'true',
			'$geotag'        => $geotag,
			'$nickname'      => $x['nickname'],
			'$ispublic'      => $this->l10n->t('Visible to <strong>everybody</strong>'),
			'$linkurl'       => $this->l10n->t('Please enter a image/video/audio/webpage URL:'),
			'$term'          => $this->l10n->t('Tag term:'),
			'$fileas'        => $this->l10n->t('Save to Folder'),
			'$whereareu'     => $this->l10n->t('Where are you right now?'),
			'$delitems'      => $this->l10n->t("Delete item\x28s\x29?"),
			'$postPublished' => $this->l10n->t('Post published.'),
			'$goToPost'      => $this->l10n->t('Go to post'),
			'$is_mobile'     => $this->mode->isMobile(),
		]);

		$jotplugins = $this->eventDispatcher->dispatch(
			new HtmlFilterEvent(HtmlFilterEvent::JOT_TOOL, ''),
		)->getHtml();

		if ($this->config->get('system', 'set_creation_date')) {
			$created_at = Temporal::getDateTimeField(
				new \DateTime(DBA::NULL_DATETIME),
				new \DateTime('now'),
				null,
				$this->l10n->t('Created at'),
				'created_at',
			);
		} else {
			$created_at = '';
		}

		$tpl = Renderer::getMarkupTemplate('jot.tpl');

		if (isset($x['contact_account_type']) && $x['contact_account_type'] === User::ACCOUNT_TYPE_COMMUNITY) {
			$new_post = $this->l10n->t('Post to group');
		} else {
			$new_post = $this->l10n->t('New Post');
		}

		$o .= Renderer::replaceMacros($tpl, [
			'$new_post'            => $new_post,
			'$return_path'         => $this->args->getQueryString(),
			'$action'              => 'item',
			'$share'               => ($x['button'] ?? '') ?: $this->l10n->t('Post'),
			'$loading'             => $this->l10n->t('Loading...'),
			'$upload'              => $this->l10n->t('Upload photo'),
			'$shortupload'         => $this->l10n->t('upload photo'),
			'$attach'              => $this->l10n->t('Attach file'),
			'$shortattach'         => $this->l10n->t('attach file'),
			'$edbold'              => $this->l10n->t('Bold'),
			'$editalic'            => $this->l10n->t('Italic'),
			'$eduline'             => $this->l10n->t('Underline'),
			'$edquote'             => $this->l10n->t('Quote'),
			'$edemojis'            => $this->l10n->t('Add emojis'),
			'$contentwarn'         => $this->l10n->t('Content Warning'),
			'$edcode'              => $this->l10n->t('Code'),
			'$edimg'               => $this->l10n->t('Image'),
			'$edurl'               => $this->l10n->t('Link'),
			'$edattach'            => $this->l10n->t('Link or Media'),
			'$setloc'              => $this->l10n->t('Set your location'),
			'$shortsetloc'         => $this->l10n->t('set location'),
			'$noloc'               => $this->l10n->t('Clear browser location'),
			'$shortnoloc'          => $this->l10n->t('clear location'),
			'$title'               => $x['title'] ?? '',
			'$placeholdertitle'    => $this->l10n->t('Set title'),
			'$summary'             => $x['summary'] ?? '',
			'$placeholdersummary'  => Feature::isEnabled($this->session->getLocalUserId(), Feature::SUMMARY) ? $this->l10n->t('Set summary, abstract or spoiler text') : '',
			'$category'            => $x['category'] ?? '',
			'$placeholdercategory' => Feature::isEnabled($this->session->getLocalUserId(), Feature::CATEGORIES) ? $this->l10n->t("Categories \x28comma-separated list\x29") : '',
			'$sensitive'           => ['sensitive', $this->l10n->t('Sensitive post'), $x['sensitive'] ?? false],
			'$scheduled_at'        => Temporal::getDateTimeField(
				new \DateTime(),
				new \DateTime('now + 6 months'),
				null,
				$this->l10n->t('Scheduled at'),
				'scheduled_at',
			),
			'$created_at'   => $created_at,
			'$wait'         => $this->l10n->t('Please wait'),
			'$permset'      => $this->l10n->t('Permission settings'),
			'$shortpermset' => $this->l10n->t('Permissions'),
			'$wall'         => $notes_cid ? 0 : 1,
			'$posttype'     => $notes_cid ? ItemModel::PT_PERSONAL_NOTE : ItemModel::PT_ARTICLE,
			'$content'      => $x['content'] ?? '',
			'$post_id'      => $x['post_id'] ?? '',
			'$defloc'       => $x['default_location'],
			'$visitor'      => $x['visitor'],
			'$pvisit'       => $notes_cid ? 'none' : $x['visitor'],
			'$public'       => $this->l10n->t('Public post'),
			'$lockstate'    => $x['lockstate'],
			'$bang'         => $x['bang'],
			'$profile_uid'  => $x['profile_uid'],
			'$preview'      => $this->l10n->t('Preview'),
			'$jotplugins'   => $jotplugins,
			'$notes_cid'    => $notes_cid,
			'$cancel'       => $this->l10n->t('Cancel'),
			'$rand_num'     => Crypto::randomDigits(12),

			// ACL permissions box
			'$acl' => $x['acl'],

			//jot nav tab (used in some themes)
			'$message' => $this->l10n->t('Message'),
			'$browser' => $this->l10n->t('Add file'),

			'$compose_link_title'  => $this->l10n->t('Open Compose page'),
			'$always_open_compose' => $this->pConfig->get($this->session->getLocalUserId(), 'frio', 'always_open_compose', false),
		]);


		if ($popup == true) {
			$o = '<div id="jot-popup" style="display: none;">' . $o . '</div>';
		}

		$this->profiler->stopRecording();
		return $o;
	}

	/**
	 * "Render" a conversation or list of items for HTML display.
	 * There are two major forms of display:
	 *      - Sequential or unthreaded ("New Item View" or search results)
	 *      - conversation view
	 * The $mode parameter decides between the various renderings and also
	 * figures out how to determine page owner and other contextual items
	 * that are based on unique features of the calling module.
	 * @param array  $items   An array of Posts
	 * @param string $mode    One of self::MODE_*
	 * @param bool   $update  Asynchronous update rendering
	 * @param bool   $preview Post preview (no actual database record)
	 * @param string $order   Either "received" or "commented"
	 * @param int    $uid
	 * @return string
	 * @throws ImagickException
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function render(array $items, string $mode, bool $update = false, bool $preview = false, string $order = 'commented', int $uid = 0): string
	{
		$this->profiler->startRecording('rendering');

		$this->page->registerFooterScript(Theme::getPathForFile('asset/typeahead.js/dist/typeahead.bundle.js'));
		$this->page->registerFooterScript(Theme::getPathForFile('js/friendica-tagsinput/friendica-tagsinput.js'));
		$this->page->registerStylesheet(Theme::getPathForFile('js/friendica-tagsinput/friendica-tagsinput.css'));
		$this->page->registerStylesheet(Theme::getPathForFile('js/friendica-tagsinput/friendica-tagsinput-typeahead.css'));

		$live_update_div = '';

		$userGservers = $this->userGServer->listIgnoredByUser($this->session->getLocalUserId());

		$ignoredGsids = array_map(function (UserGServerEntity $userGServer) {
			return $userGServer->gsid;
		}, $userGservers->getArrayCopy());

		if ($mode === self::MODE_NETWORK) {
			if (!$update) {
				/*
				* The special div is needed for liveUpdate to kick in for this page.
				* We only launch liveUpdate if you aren't filtering in some incompatible
				* way and also you aren't writing a comment (discovered in javascript).
				*/
				$live_update_div = '<div id="live-network"></div>' . "\r\n"
					. "<script> var profile_uid = " . $_SESSION['uid']
					. "; var netargs = '" . substr($this->args->getCommand(), 8)
					. '?f='
					. (!empty($_GET['contactid']) ? '&contactid=' . rawurlencode((string) $_GET['contactid']) : '')
					. (!empty($_GET['search'])    ? '&search=' . rawurlencode((string) $_GET['search'])    : '')
					. (!empty($_GET['star'])      ? '&star=' . rawurlencode((string) $_GET['star'])      : '')
					. (!empty($_GET['order'])     ? '&order=' . rawurlencode((string) $_GET['order'])     : '')
					. (!empty($_GET['bmark'])     ? '&bmark=' . rawurlencode((string) $_GET['bmark'])     : '')
					. (!empty($_GET['liked'])     ? '&liked=' . rawurlencode((string) $_GET['liked'])     : '')
					. (!empty($_GET['conv'])      ? '&conv=' . rawurlencode((string) $_GET['conv'])      : '')
					. (!empty($_GET['nets'])      ? '&nets=' . rawurlencode((string) $_GET['nets'])      : '')
					. (!empty($_GET['cmin'])      ? '&cmin=' . rawurlencode((string) $_GET['cmin'])      : '')
					. (!empty($_GET['cmax'])      ? '&cmax=' . rawurlencode((string) $_GET['cmax'])      : '')
					. (!empty($_GET['file'])      ? '&file=' . rawurlencode((string) $_GET['file'])      : '')
					. (!empty($_GET['channel'])   ? '&channel=' . rawurlencode((string) $_GET['channel'])   : '')
					. (!empty($_GET['no_sharer']) ? '&no_sharer=' . rawurlencode((string) $_GET['no_sharer']) : '')
					. (!empty($_GET['accounttype']) ? '&accounttype=' . rawurlencode((string) $_GET['accounttype']) : '')
					. "'; </script>\r\n";
			}
		} elseif ($mode === self::MODE_PROFILE) {
			if (!$update) {
				$tab = !empty($_GET['tab']) ? trim((string) $_GET['tab']) : 'posts';

				if ($tab === 'posts') {
					/*
					* This is ugly, but we can't pass the profile_uid through the session to the ajax updater,
					* because browser prefetching might change it on us. We have to deliver it with the page.
					*/

					$live_update_div = '<div id="live-profile"></div>' . "\r\n"
						. "<script> var profile_uid = " . $uid
						. "; var netargs = '?f='; </script>\r\n";
				}
			}
		} elseif ($mode === self::MODE_NOTES) {
			if (!$update) {
				$live_update_div = '<div id="live-notes"></div>' . "\r\n"
					. "<script> var profile_uid = " . $this->session->getLocalUserId()
					. "; var netargs = '?f='; </script>\r\n";
			}
		} elseif ($mode === self::MODE_DISPLAY) {
			if (!$update) {
				$live_update_div = '<div id="live-display"></div>' . "\r\n"
					. "<script> var profile_uid = " . ($this->session->getLocalUserId() ?: 0) . ";"
					. "</script>";
			}
		} elseif ($mode === self::MODE_CHANNEL) {
			if (!$update) {
				$live_update_div = '<div id="live-channel"></div>' . "\r\n"
					. "<script> var profile_uid = -1; var netargs = '" . substr($this->args->getCommand(), 8)
					. '?f='
					. (!empty($_GET['no_sharer']) ? '&no_sharer=' . rawurlencode((string) $_GET['no_sharer']) : '')
					. (!empty($_GET['accounttype']) ? '&accounttype=' . rawurlencode((string) $_GET['accounttype']) : '')
					. "'; </script>\r\n";
			}
		} elseif ($mode === self::MODE_COMMUNITY) {
			if (!$update) {
				$live_update_div = '<div id="live-community"></div>' . "\r\n"
					. "<script> var profile_uid = -1; var netargs = '" . substr($this->args->getCommand(), 10)
					. '?f='
					. (!empty($_GET['no_sharer']) ? '&no_sharer=' . rawurlencode((string) $_GET['no_sharer']) : '')
					. (!empty($_GET['accounttype']) ? '&accounttype=' . rawurlencode((string) $_GET['accounttype']) : '')
					. "'; </script>\r\n";
			}
		} elseif ($mode === self::MODE_CONTACTS) {
			if (!$update) {
				$live_update_div = '<div id="live-contact"></div>' . "\r\n"
					. "<script> var profile_uid = -1; var netargs = '" . substr($this->args->getCommand(), 8)
					. "?f='; </script>\r\n";
			}
		} elseif ($mode === self::MODE_SEARCH) {
			$live_update_div = '<div id="live-search"></div>' . "\r\n";
		}

		$page_dropping = $this->session->getLocalUserId() && $this->pConfig->get($this->session->getLocalUserId(), 'system', 'show_page_drop', true) && ($this->session->getLocalUserId() == $uid && $mode != self::MODE_SEARCH);

		if (!$update) {
			$_SESSION['return_path'] = $this->args->getQueryString();
		}

		$cb = ['items' => $items, 'mode' => $mode, 'update' => $update, 'preview' => $preview];

		$cb = $this->eventDispatcher->dispatch(
			new ArrayFilterEvent(ArrayFilterEvent::CONVERSATION_START, $cb),
		)->getArray();

		$items = $cb['items'];

		$contextLessMode = in_array($mode, [self::MODE_FILED, self::MODE_SEARCH, self::MODE_CONTACT_POSTS]);
		if ($contextLessMode) {
			$o = $this->htmlRenderer->renderContextLessTimelineByItems(
				$items,
				$mode,
				$update,
				$preview,
				$page_dropping,
				$live_update_div,
				$this->args->getQueryString(),
				$uid ?: null,
			);

			$this->profiler->stopRecording();
			return $o;
		}

		if ($preview) {
			$o = $this->htmlRenderer->renderThreadedTimelineByItems(
				$items,
				$mode,
				$update,
				true,
				$page_dropping,
				$live_update_div,
				$this->args->getQueryString(),
				$uid ?: null,
			);

			$this->profiler->stopRecording();
			return $o;
		}

		$rootUriIds = [];
		foreach ($items as $item) {
			if (($item['gravity'] ?? null) == ItemModel::GRAVITY_PARENT) {
				$rootUriIds[] = (int) $item['uri-id'];
			}
		}

		$timelineHtml = $this->htmlRenderer->renderTimelineByUriIds($rootUriIds, $uid, $mode);
		$o            = $live_update_div . $timelineHtml;

		if (!$update) {
			$o .= "<div id=\"conversation-end\"></div>\n\n";
			if ($page_dropping) {
				$o .= '<div id="item-delete-selected" class="fakelink" onclick="deleteCheckedItems();">'
					. '<div id="item-delete-selected-icon" class="icon drophide" title="' . $this->l10n->t('Delete Selected Items') . '" onmouseover="imgbright(this);" onmouseout="imgdull(this);"></div>'
					. '<div id="item-delete-selected-desc">' . $this->l10n->t('Delete Selected Items') . '</div>'
					. '</div>'
					. '<img id="item-delete-selected-rotator" class="like-rotator" src="images/rotator.gif" style="display: none;" />'
					. '<div id="item-delete-selected-end"></div>';
			}
		}

		$this->profiler->stopRecording();
		return $o;
	}

}
