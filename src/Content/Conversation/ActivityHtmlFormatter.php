<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation;

use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\Network\HTTPException\InternalServerErrorException;
use Friendica\Util\Profiler;

/**
 * Formats activity HTML for conversation rendering.
 * This class extracts the activity formatting logic to avoid circular dependencies.
 */
final readonly class ActivityHtmlFormatter
{
	public function __construct(
		private L10n $l10n,
		private IManageConfigValues $config,
		private Profiler $profiler,
	) {}

	/**
	 * Returns the liker phrase based on a list of likers
	 *
	 * @param string $verb   the activity verb
	 * @param array  $likers a list of likers
	 *
	 * @return string the liker phrase
	 *
	 * @throws InternalServerErrorException in case either the verb is invalid or the list of likers is empty
	 */
	public function getLikerPhrase(string $verb, array $likers): string
	{
		$total = count($likers);

		if ($total === 0) {
			throw new InternalServerErrorException(sprintf('There has to be at least one Liker for verb "%s"', $verb));
		} elseif ($total === 1) {
			$likerString = $likers[0];
		} else {
			if ($total < $this->config->get('system', 'max_likers')) {
				$likerString = implode(', ', array_slice($likers, 0, -1));
				$likerString .= ' ' . $this->l10n->t('and') . ' ' . $likers[count($likers) - 1];
			} else {
				$likerString = implode(', ', array_slice($likers, 0, $this->config->get('system', 'max_likers') - 1));
				$likerString .= ' ' . $this->l10n->t('and %d other people', $total - $this->config->get('system', 'max_likers'));
			}
		}

		return match ($verb) {
			'like'        => $this->l10n->tt('%2$s likes this.', '%2$s like this.', $total, $likerString),
			'dislike'     => $this->l10n->tt('%2$s doesn\'t like this.', '%2$s don\'t like this.', $total, $likerString),
			'attendyes'   => $this->l10n->tt('%2$s attends.', '%2$s attend.', $total, $likerString),
			'attendno'    => $this->l10n->tt('%2$s doesn\'t attend.', '%2$s don\'t attend.', $total, $likerString),
			'attendmaybe' => $this->l10n->tt('%2$s attends maybe.', '%2$s attend maybe.', $total, $likerString),
			'announce'    => $this->l10n->tt('%2$s reshared this.', '%2$s reshared this.', $total, $likerString),
			default       => throw new InternalServerErrorException(sprintf('Unknown verb "%s"', $verb)),
		};
	}

	/**
	 * Format the activity text for an item/photo/video
	 *
	 * @param array  $links    array of pre-linked names of actors
	 * @param string $verb     one of 'like', 'dislike', 'attendyes', 'attendno', 'attendmaybe'
	 * @param int    $id       item id
	 * @param string $activity Activity URI
	 * @param array  $emojis   Array with emoji reactions
	 * @return string formatted text
	 * @throws InternalServerErrorException
	 */
	public function format(array $links, string $verb, int $id, string $activity, array $emojis): string
	{
		$this->profiler->startRecording('rendering');
		$expanded = '';

		$phrase = $this->getLikerPhrase($verb, $links);
		$total  = max(count($links), $emojis[$activity]['total'] ?? 0);

		if ($total > 1) {
			$spanatts  = "class=\"btn btn-link fakelink\" onclick=\"openClose('{$verb}list-$id');\"";
			$explikers = $phrase;

			switch ($verb) {
				case 'like':
					$phrase = $this->l10n->tt('<button type="button" %2$s>%1$d person</button> likes this', '<button type="button" %2$s>%1$d people</button> like this', $total, $spanatts);
					break;
				case 'dislike':
					$dislike_translation_plural = '<button type="button" %2$s>%1$d people</button> don\'t like this';
					$phrase                     = $this->l10n->tt('<button type="button" %2$s>%1$d person</button> doesn\'t like this', $dislike_translation_plural, $total, $spanatts);
					break;
				case 'attendyes':
					$phrase = $this->l10n->tt('<button type="button" %2$s>%1$d person</button> attends', '<button type="button" %2$s>%1$d people</button> attend', $total, $spanatts);
					break;
				case 'attendno':
					$phrase = $this->l10n->tt('<button type="button" %2$s>%1$d person</button> doesn\'t attend', '<button type="button" %2$s>%1$d people</button> don\'t attend', $total, $spanatts);
					break;
				case 'attendmaybe':
					$phrase = $this->l10n->tt('<button type="button" %2$s>%1$d person</button> attends maybe', '<button type="button" %2$s>%1$d people</button> attend maybe', $total, $spanatts);
					break;
				case 'announce':
					$phrase = $this->l10n->tt('<button type="button" %2$s>%1$d person</button> reshared this', '<button type="button" %2$s>%1$d people</button> reshared this', $total, $spanatts);
					break;
			}

			$expanded .= "\t" . '<p class="wall-item-' . $verb . '-expanded" id="' . $verb . 'list-' . $id . '" style="display: none;" >' . $explikers . '</p>';
		}

		$output = Renderer::replaceMacros(Renderer::getMarkupTemplate('voting_fakelink.tpl'), [
			'$phrase' => $phrase,
			'$type'   => $verb,
			'$id'     => $id,
		]);
		$output .= $expanded;

		$this->profiler->stopRecording();
		return $output;
	}
}
