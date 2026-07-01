<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content;

use Friendica\Content\Text\HTML;
use Friendica\Core\Protocol;
use Friendica\Core\Renderer;
use Friendica\Database\DBA;
use Friendica\DI;
use Friendica\Model\Contact;

/**
 * This class handles methods related to the page functionality
 */
class PagesManager
{
	/**
	 * Function to list all pages a user is connected with
	 *
	 * @param int     $uid         of the profile owner
	 * @param boolean $lastitem    Sort by lastitem
	 * @param boolean $showhidden  Show pages which are not hidden
	 * @param boolean $showprivate Show private pages
	 *
	 * @return array
	 *    'url'   => page url
	 *    'name'  => page name
	 *    'id'    => number of the key from the array
	 *    'micro' => contact photo in format micro
	 *    'thumb' => contact photo in format thumb
	 * @throws \Exception
	 */
	public static function getList($uid, $lastitem, $showhidden = true, $showprivate = false)
	{
		if ($lastitem) {
			$params = ['order' => ['last-item' => true]];
		} else {
			$params = ['order' => ['name']];
		}

		$condition = [
			'contact-type' => [Contact::TYPE_ORGANISATION, Contact::TYPE_NEWS],
			'network'      => [Protocol::DFRN, Protocol::ACTIVITYPUB],
			'uid'          => $uid,
			'blocked'      => false,
			'pending'      => false,
			'archive'      => false,
		];

		$condition = DBA::mergeConditions($condition, ["`platform` NOT IN (?, ?)", 'peertube', 'wordpress']);

		if (!$showprivate) {
			$condition = DBA::mergeConditions($condition, ['manually-approve' => false]);
		}

		if (!$showhidden) {
			$condition = DBA::mergeConditions($condition, ['hidden' => false]);
		}

		$pagesList = [];

		$fields   = ['id', 'url', 'alias', 'name', 'micro', 'thumb', 'avatar', 'network', 'uid'];
		$contacts = DBA::select('account-user-view', $fields, $condition, $params);
		if (!$contacts) {
			return $groupList;
		}

		while ($contact = DBA::fetch($contacts)) {
			$pagesList[] = [
				'url'     => $contact['url'],
				'alias'   => $contact['alias'],
				'name'    => $contact['name'],
				'id'      => $contact['id'],
				'micro'   => $contact['micro'],
				'thumb'   => $contact['thumb'],
				'network' => $contact['network'],
			];
		}
		DBA::close($contacts);

		return($pagesList);
	}


	/**
	 * Pages list widget
	 *
	 * Sidebar widget to show subscribed Friendica pages. If activated
	 * in the settings, it appears in the network page sidebar
	 *
	 * @param int $uid The ID of the User
	 * @return string
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 * @throws \ImagickException
	 */
	public static function widget(int $uid): string
	{
		//sort by last updated item
		$contacts      = self::getList($uid, true, true, true);
		$total         = count($contacts);
		$visibleGroups = 10;

		$id = 0;

		$entries = [];

		foreach ($contacts as $contact) {
			$entry = [
				'url'          => 'contact/' . $contact['id'] . '/conversations',
				'external_url' => Contact::magicLinkByContact($contact),
				'name'         => $contact['name'],
				'cid'          => $contact['id'],
				'micro'        => DI::baseUrl()->remove(Contact::getMicro($contact)),
				'id'           => ++$id,
			];
			$entries[] = $entry;
		}

		$tpl = Renderer::getMarkupTemplate('widget/pages_list.tpl');


		$addonHelper = DI::addonHelper();

		return Renderer::replaceMacros(
			$tpl,
			[
				'$title'                        => DI::l10n()->t('Pages'),
				'$pages'                        => $entries,
				'$link_desc'                    => DI::l10n()->t('External link to page'),
				'$new_page'                     => 'register/?type=page',
				'$total'                        => $total,
				'$visible_pages'                => $visibleGroups,
				'$showless'                     => DI::l10n()->t('show less'),
				'$showmore'                     => DI::l10n()->t('show more'),
				'$create_new_page'              => DI::l10n()->t('Create new page'),
				'$addon_page_directory_enabled' => $addonHelper->isAddonEnabled("pagedirectory"),
				'$visit_pagedirectory'          => DI::l10n()->t('Find pages to follow'),
			],
		);
	}

	/**
	 * Format page list as contact block
	 *
	 * This function is used to show the page list in
	 * the advanced profile.
	 *
	 * @param int $uid The ID of the User
	 * @return string
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 * @throws \ImagickException
	 */
	public static function profileAdvanced($uid)
	{
		if (!Feature::isEnabled($uid, Feature::PAGES)) {
			return '';
		}

		$o = '';

		// placeholder in case somebody wants configurability
		$show_total = 9999;

		//don't sort by last updated item
		$lastitem = false;

		$contacts = self::getList($uid, $lastitem, false, false);

		$total_shown = 0;
		foreach ($contacts as $contact) {
			$o .= HTML::micropro($contact, true, 'pagelist-profile-advanced');
			$total_shown++;
			if ($total_shown == $show_total) {
				break;
			}
		}

		return $o;
	}

	/**
	 * count unread page items
	 *
	 * Count unread items of connected groups and private pages
	 *
	 * @return array
	 *    'id' => contact id
	 *    'name' => contact/page name
	 *    'count' => counted unseen page items
	 * @throws \Exception
	 */
	public static function countUnseenItems()
	{
		$stmtContacts = DBA::p(
			"SELECT `contact`.`id`, `contact`.`name`, COUNT(*) AS `count` FROM `post-user-view`
				INNER JOIN `contact` ON `post-user-view`.`contact-id` = `contact`.`id`
				WHERE `post-user-view`.`uid` = ? AND `post-user-view`.`visible` AND NOT `post-user-view`.`deleted` AND `post-user-view`.`unseen`
				AND `contact`.`network` IN (?, ?) AND `contact`.`contact-type` = ?
				AND NOT `contact`.`blocked` AND NOT `contact`.`hidden`
				AND NOT `contact`.`pending` AND NOT `contact`.`archive`
				AND `contact`.`uid` = ?
				GROUP BY `contact`.`id`",
			DI::userSession()->getLocalUserId(),
			Protocol::DFRN,
			Protocol::ACTIVITYPUB,
			Contact::TYPE_ORGANISATION,
			Contact::TYPE_NEWS,
			DI::userSession()->getLocalUserId(),
		);

		return DBA::toArray($stmtContacts);
	}
}
