<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Widget;

use Friendica\Core\Renderer;
use Friendica\Database\DBA;
use Friendica\Model\Contact;
use Friendica\Network\HTTPException;
use Friendica\Util\Strings;
use Friendica\Model\Tag;
use Friendica\DI;
use Friendica\Model\User;

class Hovercard
{
	/**
	 * @param array $contact
	 * @param int   $localUid Used to show user actions
	 * @return string
	 * @throws HTTPException\InternalServerErrorException
	 * @throws HTTPException\ServiceUnavailableException
	 * @throws \ImagickException
	 */
	public static function getHTML(array $contact, int $localUid = 0): string
	{
		if ($localUid) {
			$actions = Contact::photoMenu($contact, $localUid);
		} else {
			$actions = [];
		}

		$tags = [];
		if ($contact['keywords']) {
			// Separator is defined in Module\Settings\Profile\Index::cleanKeywords
			foreach (explode(', ', (string) $contact['keywords']) as $tag_label) {
				$tags[] = [
					'url'   => '/search?tag=' . urlencode($tag_label),
					'label' => Tag::TAG_CHARACTER[Tag::HASHTAG] . $tag_label,
				];
			}
		}

		$contact_url = Contact::getProfileLink($contact);

		$administrator = false;
		$moderator     = false;
		if (Contact::isLocalById($contact['id'])) {
			$local_id = User::getIdForUrl($contact['url']);
			// check if contact is a Moderator
			if (User::isModerator($local_id)) {
				$moderator = true;
			}
			// check if contact is an Admin
			if (User::isSiteAdmin($local_id)) {
				$administrator = true;
				$moderator = false;
				// do not show as Admin if this is a sub-account of an Admin
				$check = User::getById($local_id,['parent-uid']);
				if ($check['parent-uid']) {
					$administrator = false;
				}
			}
		}

		// Move the contact data to the profile array so we can deliver it to
		$tpl = Renderer::getMarkupTemplate('hovercard.tpl');
		return Renderer::replaceMacros($tpl, [
			'$profile' => [
				'is_admin'          => $administrator,
				'admin_title'       => DI::l10n()->t('Administrator'),
				'is_mod'            => $moderator,
				'moderator_title'   => DI::l10n()->t('Moderator'),
				'name'              => $contact['name'],
				'nick'              => $contact['nick'],
				'addr'              => $contact['addr'] ?: $contact_url,
				'thumb'             => Contact::getThumb($contact),
				'url'               => Contact::magicLinkByContact($contact),
				'nurl'              => $contact['nurl'],
				'location'          => $contact['location'],
				'about'             => $contact['about'],
				'network_link'      => Strings::formatNetworkName($contact['network'], $contact_url, $contact['gsid']),
				'tags'              => $tags,
				'bd'                => $contact['bd'] <= DBA::NULL_DATE ? '' : $contact['bd'],
				'account_type_name' => Contact::getAccountType($contact['contact-type']),
				'account_type'      => $contact['contact-type'],
				'manually_approve'  => $contact['manually-approve'],
				'private'           => $contact['prv'],
				'contact_type'      => $contact['contact-type'],
				'actions'           => $actions,
				'self'              => $contact['self'],
			],
		]);
	}
}
