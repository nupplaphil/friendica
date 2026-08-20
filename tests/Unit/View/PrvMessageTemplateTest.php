<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\Unit\View;

use DOMDocument;
use DOMXPath;
use Friendica\Render\FriendicaSmarty;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the private message form.
 *
 * The reply form used to concatenate the counterpart's contact name and the
 * conversation's parent-uri into raw HTML which was then rendered with
 * `nofilter`. Both values are controlled by the remote side, so a federated
 * peer could store JavaScript in the victim's conversation view.
 */
class PrvMessageTemplateTest extends TestCase
{
	private const XSS_NAME    = '<img src=x onerror=alert(1)>';
	private const XSS_REPLYTO = 'https://evil.example/m/1"><img src=x onerror=alert(2)>';

	private string $workDir;

	protected function setUp(): void
	{
		parent::setUp();

		// The template dirs of FriendicaSmarty are relative to the project root
		chdir(dirname(__DIR__, 3));

		$this->workDir = sys_get_temp_dir() . '/friendica-tpl-test-' . getmypid();
	}

	protected function tearDown(): void
	{
		if (is_dir($this->workDir)) {
			exec('rm -rf ' . escapeshellarg($this->workDir));
		}

		parent::tearDown();
	}

	/**
	 * frio ships its own prv_message.tpl, vier falls back to the base template
	 */
	public static function themeProvider(): array
	{
		return [
			'frio' => ['frio'],
			'vier' => ['vier'],
		];
	}

	#[DataProvider('themeProvider')]
	public function testReplyFormEscapesContactNameAndParentUri(string $theme): void
	{
		$html = $this->render($theme, [
			'recipient' => ['name' => self::XSS_NAME, 'id' => 52],
			'replyto'   => self::XSS_REPLYTO,
			'select'    => '',
		]);

		$xpath = $this->xpath($html);

		// The payload must not have become markup. The base template legitimately
		// contains a rotator <img>, so pin the payload itself instead of all images.
		self::assertSame(0, $xpath->query('//img[@src="x"]')->length, 'contact name was rendered as markup');
		self::assertSame(0, $xpath->query('//*[@onerror]')->length, 'an event handler attribute was injected');

		// ... but it must still be visible as text to the user
		self::assertStringContainsString(htmlspecialchars(self::XSS_NAME, ENT_QUOTES), $html);

		// The recipient id is still submitted
		$recipient = $xpath->query('//input[@name="recipient"]');
		self::assertSame(1, $recipient->length);
		self::assertSame('52', $recipient->item(0)->getAttribute('value'));

		// The parent uri survives the escaping round trip unchanged
		$replyto = $xpath->query('//input[@name="replyto"]');
		self::assertSame(1, $replyto->length);
		self::assertSame(self::XSS_REPLYTO, $replyto->item(0)->getAttribute('value'));
	}

	/**
	 * The "new message" form passes a pre-rendered recipient widget, which must
	 * stay raw HTML - otherwise the contact selector breaks.
	 */
	#[DataProvider('themeProvider')]
	public function testNewMessageFormKeepsRenderedRecipientWidget(string $theme): void
	{
		$html = $this->render($theme, [
			'recipient' => null,
			'replyto'   => '',
			'select'    => '<select name="recipient"><option value="52">Alice</option></select>',
		]);

		$xpath = $this->xpath($html);

		self::assertSame(1, $xpath->query('//select[@name="recipient"]')->length);
		// No stray reply-to field on a brand new conversation
		self::assertSame(0, $xpath->query('//input[@name="replyto"]')->length);
	}

	private function render(string $theme, array $vars): string
	{
		$smarty = new FriendicaSmarty($theme, [], $this->workDir, false);

		foreach ($vars as $key => $value) {
			$smarty->assign($key, $value);
		}

		return $smarty->fetch('file:prv_message.tpl');
	}

	private function xpath(string $html): DOMXPath
	{
		$doc = new DOMDocument();
		self::assertTrue(@$doc->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>'));

		return new DOMXPath($doc);
	}
}
