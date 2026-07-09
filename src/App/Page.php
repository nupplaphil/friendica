<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\App;

use ArrayAccess;
use DOMDocument;
use DOMXPath;
use Friendica\App;
use Friendica\AppHelper;
use Friendica\Content\Nav;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Core\PConfig\Capability\IManagePersonalConfigValues;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Model\UserSession;
use Friendica\Core\System;
use Friendica\Core\Theme;
use Friendica\DI;
use Friendica\Event\HtmlFilterEvent;
use Friendica\Network\HTTPException;
use Friendica\Util\Images;
use Friendica\Util\Network;
use Friendica\Util\Profiler;
use Friendica\Util\Strings;
use GuzzleHttp\Psr7\Utils;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Contains the page specific environment variables for the current Page
 * - Contains all stylesheets
 * - Contains all footer-scripts
 * - Contains all page specific content (header, footer, content, ...)
 *
 * The run() method is the single point where the page will get printed to the screen
 */
class Page implements ArrayAccess
{
	/**
	 * @var array Contains all stylesheets, which should get loaded during page
	 */
	private $stylesheets = [];
	/**
	 * @var array Contains all scripts, which are added to the footer at last
	 */
	private $footerScripts = [];
	/**
	 * @var array The page content, which are showed directly
	 */
	private $page = [
		'aside'       => '',
		'bottom'      => '',
		'content'     => '',
		'footer'      => '',
		'htmlhead'    => '',
		'nav'         => '',
		'page_title'  => '',
		'right_aside' => '',
		'template'    => '',
		'title'       => '',
		'section'     => '',
		'module'      => '',
	];

	private $timestamp = 0;
	private $method    = '';
	private $module    = '';
	private $command   = '';

	/**
	 * @param string $basePath The Page basepath
	 */
	public function __construct(
		private readonly string $basePath,
		private readonly EventDispatcherInterface $eventDispatcher,
	) {
		$this->timestamp = microtime(true);
	}

	public function setLogging(string $method, string $module, string $command)
	{
		$this->method  = $method;
		$this->module  = $module;
		$this->command = $command;
	}

	public function logRuntime(IManageConfigValues $config, string $origin = '')
	{
		$ignore = $config->get('system', 'runtime_ignore');
		if (in_array($this->module, $ignore) || in_array($this->command, $ignore)) {
			return;
		}

		$signature = !empty($_SERVER['HTTP_SIGNATURE']);
		$load      = number_format(System::currentLoad(), 2);
		$runtime   = number_format(microtime(true) - $this->timestamp, 3);
		if ($runtime > $config->get('system', 'runtime_loglimit')) {
			DI::logger()->debug('Runtime', ['method' => $this->method, 'module' => $this->module, 'runtime' => $runtime, 'load' => $load, 'origin' => $origin, 'signature' => $signature, 'request' => $_SERVER['REQUEST_URI'] ?? '']);
		}
	}

	// ArrayAccess interface

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists($offset): bool
	{
		return isset($this->page[$offset]);
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		return $this->page[$offset] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value): void
	{
		$this->page[$offset] = $value;
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset($offset): void
	{
		if (isset($this->page[$offset])) {
			unset($this->page[$offset]);
		}
	}

	/**
	 * Register a stylesheet file path to be included in the <head> tag of every page.
	 * Inclusion is done in App->initHead().
	 * The path can be absolute or relative to the Friendica installation base folder.
	 *
	 * @param string $path
	 * @param string $media
	 * @see Page::initHead()
	 */
	public function registerStylesheet(string $path, string $media = 'screen')
	{
		$path = Network::appendQueryParam($path, ['v' => App::VERSION]);

		if (mb_strpos($path, $this->basePath . DIRECTORY_SEPARATOR) === 0) {
			$path = mb_substr($path, mb_strlen($this->basePath . DIRECTORY_SEPARATOR));
		}

		$this->stylesheets[trim($path, '/')] = $media;
	}

	/**
	 * Initializes Page->page['htmlhead'].
	 *
	 * Includes:
	 * - Page title
	 * - Favicons
	 * - Registered stylesheets (through App->registerStylesheet())
	 * - Infinite scroll data
	 * - head.tpl template
	 *
	 * @param Arguments                   $args      The Friendica App Arguments
	 * @param L10n                        $l10n      The l10n language instance
	 * @param IManageConfigValues         $config    The Friendica configuration
	 * @param IManagePersonalConfigValues $pConfig   The Friendica personal configuration (for user)
	 * @param int                         $localUID  The local user id
	 *
	 * @throws HTTPException\InternalServerErrorException
	 */
	private function initHead(
		AppHelper $appHelper,
		Arguments $args,
		L10n $l10n,
		IManageConfigValues $config,
		IManagePersonalConfigValues $pConfig,
		int $localUID,
	) {
		// Default title: current module called
		if (empty($this->page['title']) && $args->getModuleName()) {
			$this->page['title'] = $l10n->t(ucfirst($args->getModuleName()));
		}

		// Append the sitename to the page title
		$this->page['title'] = (!empty($this->page['title']) ? $this->page['title'] . ' | ' : '') . $config->get('config', 'sitename', '');

		if (!empty(Renderer::$theme['stylesheet'])) {
			$stylesheet = Renderer::$theme['stylesheet'];
		} else {
			$stylesheet = $appHelper->getCurrentThemeStylesheetPath();
		}

		$this->registerStylesheet($stylesheet);

		$shortcut_icon = $config->get('system', 'shortcut_icon');
		if ($shortcut_icon == '') {
			$shortcut_icon = 'images/friendica.svg';
		}

		$touch_icon = $config->get('system', 'touch_icon');
		if ($touch_icon == '') {
			$touch_icon = 'images/friendica-192.png';
		}

		$this->page['htmlhead'] = $this->eventDispatcher->dispatch(
			new HtmlFilterEvent(HtmlFilterEvent::HEAD, $this->page['htmlhead']),
		)->getHtml();

		$tpl = Renderer::getMarkupTemplate('head.tpl');
		/* put the head template at the beginning of page['htmlhead']
		 * since the code added by the modules frequently depends on it
		 * being first
		 */
		$this->page['htmlhead'] = Renderer::replaceMacros($tpl, [
			'$l10n' => [
				'delitem'          => $l10n->t('Delete this item?'),
				'blockAuthor'      => $l10n->t("Block this author? They won't be able to follow you nor see your public posts, and you won't be able to see their posts and their notifications."),
				'ignoreAuthor'     => $l10n->t("Ignore this author? You won't be able to see their posts and their notifications."),
				'collapseAuthor'   => $l10n->t("Collapse this author's posts?"),
				'ignoreServer'     => $l10n->t("Ignore this author's server?"),
				'ignoreServerDesc' => $l10n->t("You won't see any content from this server including reshares in your Network page, the community pages and individual conversations."),

				'likeError'     => $l10n->t('Like not successful'),
				'dislikeError'  => $l10n->t('Dislike not successful'),
				'announceError' => $l10n->t('Sharing not successful'),
				'attendError'   => $l10n->t('Attendance unsuccessful'),
				'srvError'      => $l10n->t('Backend error'),
				'netError'      => $l10n->t('Network error'),

				// Dropzone
				'dictDefaultMessage'           => $l10n->t('Drop files here to upload'),
				'dictFallbackMessage'          => $l10n->t("Your browser does not support drag and drop file uploads."),
				'dictFallbackText'             => $l10n->t('Please use the fallback form below to upload your files like in the olden days.'),
				'dictFileTooBig'               => $l10n->t('File is too big ({{filesize}}MiB). Max filesize: {{maxFilesize}}MiB.'),
				'dictInvalidFileType'          => $l10n->t("You can't upload files of this type."),
				'dictResponseError'            => $l10n->t('Server responded with {{statusCode}} code.'),
				'dictCancelUpload'             => $l10n->t('Cancel upload'),
				'dictUploadCanceled'           => $l10n->t('Upload canceled.'),
				'dictCancelUploadConfirmation' => $l10n->t('Are you sure you want to cancel this upload?'),
				'dictRemoveFile'               => $l10n->t('Remove file'),
				'dictMaxFilesExceeded'         => $l10n->t("You can't upload any more files."),
			],

			'$local_user'     => $localUID,
			'$generator'      => 'Friendica' . ' ' . App::VERSION,
			'$update_content' => (int) $pConfig->get($localUID, 'system', 'update_content'),
			'$shortcut_icon'  => $shortcut_icon,
			'$touch_icon'     => $touch_icon,
			'$block_public'   => intval($config->get('system', 'block_public')),
			'$stylesheets'    => $this->stylesheets,
			'$loading'        => [
				'fetching'            => $l10n->t('Fetching...'),
				'receiving'           => $l10n->t('Receiving data...'),
				'processing'          => $l10n->t('Processing...'),
				'posting'             => $l10n->t('Posting...'),
				'delay_messages_json' => json_encode([
					// Generic
					$l10n->t('The queue manager is lining up your request...'),
					$l10n->t('The server elves are working hard...'),
					$l10n->t('Gears and sprockets are aligning; hold tight...'),
					$l10n->t('The turbocharger is starting in 3... 2... 1...'),
					$l10n->t('Our background gnomes are optimizing the flow...'),
					$l10n->t('An accountant gnome is crunching the numbers; this will be quick...'),

					// Coffee references
					$l10n->t('Espresso shot warming up — extraction in progress...'),
					$l10n->t('Fresh beans grinding; aroma calibration underway...'),
					$l10n->t('Barista AI is steaming milk for optimal latency...'),
					$l10n->t('Your cup is being poured; crema is forming...'),

					// Star Trek
					$l10n->t('Scotty is fine-tuning the warp nacelles in Engineering on the Enterprise...'),
					$l10n->t('Helm of the USS Enterprise is plotting a safer course through the nebula...'),
					$l10n->t('Spock is analyzing the sensor array on the bridge of the Enterprise...'),
					$l10n->t('Commander Sisko orders a power reroute to the Defiant\'s engines in Deep Space Nine Ops...'),
					$l10n->t('Phaser banks on the starboard side are charging for diagnostics...'),
					$l10n->t('The holodeck safety protocols are initializing in Ten Forward...'),
					$l10n->t('The warp core is stabilizing...'),
					$l10n->t('Chief O\'Brien is performing a systems check in DS9 Engineering...'),

					// Back to the Future
					$l10n->t('Doc Brown is tuning the flux capacitor at the Twin Pines Garage...'),
					$l10n->t('The DeLorean is revving toward 88 mph on Hill Valley Square...'),
					$l10n->t('Mr. Fusion is charging up for a timeline jump in the mall parking lot...'),
					$l10n->t('The Hill Valley clock tower is syncing with the time circuits; please stand by...'),

					// The Hitchhiker's Guide to the Galaxy
					$l10n->t('The infinite improbability drive is weighing the odds...'),
					$l10n->t('Bistromathics calculations are conjuring improbable tea...'),
					$l10n->t('Ford Prefect is consulting The Guide\'s entry on unlikely shortcuts...'),
					$l10n->t('Trillian is paging through the appendix for a sensible route to Magrathea...'),
					$l10n->t('Probability waves in the Improbability Drive are folding into a plausible outcome...'),
					$l10n->t('Deep Thought is queued; the answer arrives after lunch...'),
					$l10n->t('The Vogon translator is buffering — expect slow poetry...'),
					$l10n->t('Pan Galactic networks are retrying with extra vinegar...'),

					// Star Wars
					$l10n->t('The Millennium Falcon is engaging the hyperdrive for the Kessel Run...'),
					$l10n->t('Leia is entering hyperspace coordinates into the Rebel nav console...'),
					$l10n->t('R2-D2 is feeding the navicomputer precise jump vectors...'),
					$l10n->t('The Force is guiding the data transmission...'),
					$l10n->t('R2-D2 is recalculating the jump coordinates...'),

					// Warhammer 40k
					$l10n->t('The Machine Spirit is awakening...'),
					$l10n->t('The Warp is stabilizing for safe transit...'),
					$l10n->t('The Adeptus Mechanicus is blessing the circuits...'),

					// Doctor Who
					$l10n->t('The TARDIS is materializing your request...'),
					$l10n->t('The Doctor is recalculating the time vortex...'),
					$l10n->t('The TARDIS console hums — Allons-y, loading in progress...'),

					// Futurama
					$l10n->t('Good news, everyone! The server is responding...'),
					$l10n->t('Bender is optimizing the database query...'),
					$l10n->t('The Planet Express delivery is in progress...'),

					// Rick and Morty
					$l10n->t('Burp... the quantum computer is processing, Morty...'),
					$l10n->t('The portal to the right dimension is opening...'),
					$l10n->t('Wubba lubba dub dub! Just a moment...'),

					// Discworld
					$l10n->t('The Librarian is finding the right book... Ook.'),
					$l10n->t('Death is consulting his hourglass...'),
					$l10n->t('The magic is flowing through the Octavo...'),

					// The Elder Scrolls
					$l10n->t('Fus Ro Dah! The page is loading...'),
					$l10n->t('The Elder Scrolls are being consulted...'),
					$l10n->t('The Dragonborn is resting at the inn...'),

					// Portal
					$l10n->t('The Aperture Science test is initializing...'),
					$l10n->t('GLaDOS is recalculating test parameters...'),
					$l10n->t('The companion cube is being positioned...'),

					// Half-Life
					$l10n->t('The HEV suit systems are coming online...'),
					$l10n->t('Dr. Kleiner is adjusting the teleporter...'),
					$l10n->t('The Combine forces are being held at bay...'),

					// Dungeons & Dragons
					$l10n->t('The DM is rolling for your loading time...'),
					$l10n->t('The party is resting at the campfire...'),
					$l10n->t('Rolling for initiative on page load...'),

					// The IT Crowd
					$l10n->t('Jen asks if you have tried turning it off and on again?'),
					$l10n->t('Jen is fixing the server issue...'),
					$l10n->t('The firewall is being reconfigured by Roy...'),

					// Silicon Valley
					$l10n->t('Pied Piper is compressing your request...'),
					$l10n->t('Pied Piper\'s algorithm is finding the optimal path...'),
					$l10n->t('Richard is explaining it to Big Head...'),

					// Matrix
					$l10n->t('The Oracle is seeing the future of your request...'),
					$l10n->t('Neo is bending the loading time...'),
					$l10n->t('Following the white rabbit through the code...'),

					// The Lord of the Rings
					$l10n->t('The server is carrying this request to Mordor one step at a time...'),
					$l10n->t('Gandalf is checking the bridge logs before you shall pass...'),
					$l10n->t('An orc courier misread the address; rerouting via Rivendell...'),

					// Game of Thrones
					$l10n->t('The request is forging alliances before it reaches your page...'),
					$l10n->t('The data is crossing the Wall, please keep your cloak on...'),
					$l10n->t('The maesters are debating the checksum in the Citadel...'),

					// Dune
					$l10n->t('The spice flow is stabilizing your connection...'),
					$l10n->t('The Mentats are computing the safest path through this query...'),
					$l10n->t('Fremen scouts report stable latency across the spice fields...'),

					// Stargate
					$l10n->t('The gate is dialing, please stand clear of the event horizon...'),
					$l10n->t('The iris is opening for authorized packets...'),
					$l10n->t('Ancient protocols are negotiating packet clearance...'),

					// Battlestar Galactica
					$l10n->t('DRADIS has the response on scope...'),
					$l10n->t('The fleet is making one more jump to deliver your data...'),
					$l10n->t('Command confirms a green corridor for your data...'),

					// The Expanse
					$l10n->t('The Rocinante is burning hard toward your timeline...'),
					$l10n->t('Belt traffic is dense, your request is still on course, beratna...'),
					$l10n->t('Holden marked this request priority; making course adjustments...'),

					// Ghostbusters
					$l10n->t('We trapped a rogue exception in the containment unit...'),
					$l10n->t('Don’t cross the streams, the backend is almost ready...'),
					$l10n->t('Containment field needs a minor recalibration...'),

					// Indiana Jones
					$l10n->t('The response is in the archive, behind one puzzle and three traps...'),
					$l10n->t('We swapped the idol with a sandbag, now the query can escape...'),
					$l10n->t('Watch out for rolling boulders during archive retrieval...'),

					// Blade Runner
					$l10n->t('These packets won’t be lost in time tonight...'),
					$l10n->t('A replicant is running diagnostics on your request...'),
					$l10n->t('Neon rain is slowing transit, but diagnostics are underway...'),

					// Pokémon
					$l10n->t('Your request used Quick Attack, processing speed rose sharply...'),
					$l10n->t('The server found a rare response in tall grass...'),
					$l10n->t('A wild response appeared; capture in progress...'),

					// Minecraft
					$l10n->t('The creeper took a wrong turn; regenerating the chunk...'),
					$l10n->t('Redstone is syncing the circuits; please wait a tick...'),
					$l10n->t('A wandering trader is delivering your response; please hold...'),

					// Kerbal Space Program
					$l10n->t('Staging confirmed — booster separation in 3... 2... 1...'),
					$l10n->t('Telemetry shows a slight wobble; SAS is stabilizing the packet...'),
					$l10n->t('Mission Control: executing a corrective burn to avoid debris...'),

					// One Piece
					$l10n->t('The log pose is recalibrating to the next island... hold course...'),
					$l10n->t('The crew is plotting a new route around the Calm Belt...'),
					$l10n->t('A navigator consults the map; the Grand Line path is forming...'),

					// Studio Ghibli
					$l10n->t('The soot sprites are scattering; please wait for them to regroup...'),
					$l10n->t('A catbus is delivering your response; hold on tight...'),
					$l10n->t('The bathhouse spirits are processing your request; patience is appreciated...'),

					// Marvel Universe
					$l10n->t('S.H.I.E.L.D. is routing your request through a secure relay...'),
					$l10n->t('Stark tech is compiling an optimized response — deploying nano-patch...'),
					$l10n->t('Asgardian thunder is clearing the cache; processing resumes...'),

					// DC Universe
					$l10n->t('The Batcomputer is analyzing the packet; tactical response pending...'),
					$l10n->t('The Flash zipped your request through; expect a temporal wobble...'),
					$l10n->t('Kryptonian diagnostics verify integrity under yellow-sun checks...'),
				], JSON_UNESCAPED_UNICODE),
			],

			'$spaErrors' => [
				'timeout'         => $l10n->t('Timeout'),
				'timeout_message' => $l10n->t('The request took too long. Please try again.'),
				'close'           => $l10n->t('Close'),
				'delay_title'     => $l10n->t('Please wait'),
			],

			// Dropzone
			'$max_imagesize' => round(Images::getMaxUploadBytes() / 1000000, 0),

		]) . $this->page['htmlhead'];

		if ($pConfig->get($localUID, 'accessibility', 'hide_empty_descriptions')) {
			$this->page['htmlhead'] .= "<style>.empty-description {display: none;}</style>\n";
		}
		if ($pConfig->get($localUID, 'accessibility', 'hide_custom_emojis')) {
			$this->page['htmlhead'] .= "<style>span.emoji.mastodon img {display: none;}</style>\n";
		}
	}

	/**
	 * Returns the complete URL of the current page, e.g.: http(s)://something.com/network
	 *
	 * Taken from http://webcheatsheet.com/php/get_current_page_url.php
	 */
	private function curPageURL(): string
	{
		$pageURL = 'http';
		if (!empty($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on")) {
			$pageURL .= "s";
		}

		$pageURL .= "://";

		if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		}
		return $pageURL;
	}

	/**
	 * Initializes Page->page['footer'].
	 *
	 * Includes:
	 * - JavaScript homebase
	 * - Mobile toggle link
	 * - Registered footer scripts (through App->registerFooterScript())
	 * - footer.tpl template
	 *
	 * @param Mode $mode The Friendica runtime mode
	 * @param L10n $l10n The l10n instance
	 *
	 * @throws HTTPException\InternalServerErrorException
	 */
	private function initFooter(UserSession $session, Mode $mode, L10n $l10n)
	{
		// If you're just visiting, let javascript take you home
		if (!empty($_SESSION['visitor_home'])) {
			$homebase = $_SESSION['visitor_home'];
		} elseif (!empty($session->getLocalUserNickname())) {
			$homebase = 'profile/' . $session->getLocalUserNickname();
		}

		if (isset($homebase)) {
			$this->page['footer'] .= '<script>var homebase="' . $homebase . '";</script>' . "\n";
		}

		/*
		 * Add a "toggle mobile" link if we're using a mobile device
		 */
		if ($mode->isMobile() || $mode->isTablet()) {
			if (isset($_SESSION['show-mobile']) && !$_SESSION['show-mobile']) {
				$link = 'toggle_mobile?address=' . urlencode($this->curPageURL());
			} else {
				$link = 'toggle_mobile?off=1&address=' . urlencode($this->curPageURL());
			}
			$this->page['footer'] .= Renderer::replaceMacros(Renderer::getMarkupTemplate("toggle_mobile_footer.tpl"), [
				'$toggle_link' => $link,
				'$toggle_text' => $l10n->t('toggle mobile'),
			]);
		}

		$this->page['footer'] = $this->eventDispatcher->dispatch(
			new HtmlFilterEvent(HtmlFilterEvent::FOOTER, $this->page['footer']),
		)->getHtml();

		$tpl                  = Renderer::getMarkupTemplate('footer.tpl');
		$this->page['footer'] = Renderer::replaceMacros($tpl, [
			'$footerScripts' => array_unique($this->footerScripts),
			'$close'         => $l10n->t('Close'),
		]) . $this->page['footer'];
	}

	/**
	 * Initializes Page->page['content'].
	 *
	 * Includes:
	 * - module content
	 * - hooks for content
	 *
	 * @param ResponseInterface  $response The Module response class
	 * @param Mode               $mode     The Friendica execution mode
	 *
	 * @throws HTTPException\InternalServerErrorException
	 */
	private function initContent(ResponseInterface $response, Mode $mode)
	{
		// initialise content region
		if ($mode->isNormal()) {
			$this->page['content'] = $this->eventDispatcher->dispatch(
				new HtmlFilterEvent(HtmlFilterEvent::PAGE_CONTENT_TOP, $this->page['content']),
			)->getHtml();
		}

		$this->page['content'] .= (string) $response->getBody();
	}

	/**
	 * Register a javascript file path to be included in the <footer> tag of every page.
	 * Inclusion is done in App->initFooter().
	 * The path can be absolute or relative to the Friendica installation base folder.
	 *
	 * @param string $path
	 *
	 * @see Page::initFooter()
	 *
	 */
	public function registerFooterScript($path)
	{
		$path = Network::appendQueryParam($path, ['v' => App::VERSION]);

		$url = str_replace($this->basePath . DIRECTORY_SEPARATOR, '', $path);

		$this->footerScripts[] = trim($url, '/');
	}

	/**
	 * Executes the creation of the current page and prints it to the screen
	 *
	 * @param BaseURL                     $baseURL   The Friendica Base URL
	 * @param Arguments                   $args      The Friendica App arguments
	 * @param Mode                        $mode      The current node mode
	 * @param ResponseInterface           $response  The Response of the module class, including type, content & headers
	 * @param L10n                        $l10n      The l10n language class
	 * @param Profiler                    $profiler
	 * @param IManageConfigValues         $config    The Configuration of this node
	 * @param IManagePersonalConfigValues $pconfig   The personal/user configuration
	 * @param Nav                         $nav
	 * @param int                         $localUID
	 * @throws HTTPException\MethodNotAllowedException
	 * @throws HTTPException\InternalServerErrorException
	 * @throws HTTPException\ServiceUnavailableException
	 */
	public function run(
		AppHelper $appHelper,
		UserSession $session,
		BaseURL $baseURL,
		Arguments $args,
		Mode $mode,
		ResponseInterface $response,
		L10n $l10n,
		Profiler $profiler,
		IManageConfigValues $config,
		IManagePersonalConfigValues $pconfig,
		Nav $nav,
		int $localUID,
	) {
		$moduleName = $args->getModuleName();

		$this->command = $moduleName;
		$this->method  = $args->getMethod();

		/* Create the page content.
		 * Calls all hooks which are including content operations
		 *
		 * Sets the $Page->page['content'] variable
		 */
		$timestamp = microtime(true);
		$this->initContent($response, $mode);

		// Load current theme info after module has been initialized as theme could have been set in module
		$currentTheme    = $appHelper->getCurrentTheme();
		$theme_info_file = 'view/theme/' . $currentTheme . '/theme.php';
		if (file_exists($theme_info_file)) {
			require_once $theme_info_file;
		}

		if (function_exists(str_replace('-', '_', $currentTheme) . '_init')) {
			$func = str_replace('-', '_', $currentTheme) . '_init';
			$func($appHelper, $pconfig, $session, $this, $mode);
		}

		/* Create the page head after setting the language
		 * and getting any auth credentials.
		 *
		 * Moved initHead() and initFooter() to after
		 * all the module functions have executed so that all
		 * theme choices made by the modules can take effect.
		 */
		$this->initHead($appHelper, $args, $l10n, $config, $pconfig, $localUID);

		/* Build the page ending -- this is stuff that goes right before
		 * the closing </body> tag
		 */
		$this->initFooter($session, $mode, $l10n);

		$profiler->set(microtime(true) - $timestamp, 'aftermath');

		if (!$mode->isAjax()) {
			$this->page['content'] = $this->eventDispatcher->dispatch(
				new HtmlFilterEvent(HtmlFilterEvent::PAGE_END, $this->page['content']),
			)->getHtml();
		}

		// Add the navigation (menu) template
		if ($moduleName != 'install' && $moduleName != 'maintenance') {
			$this->page['htmlhead'] .= Renderer::replaceMacros(Renderer::getMarkupTemplate('nav_head.tpl'), []);
			$this->page['nav'] = $nav->getHtml();
		}

		// Build the page - now that we have all the components
		if (isset($_GET["mode"]) && (($_GET["mode"] == "raw") || ($_GET["mode"] == "minimal"))) {
			$doc = new DOMDocument();

			$target = new DOMDocument();
			$target->loadXML("<root></root>");

			$content = mb_convert_encoding($this->page["content"], 'HTML-ENTITIES', "UTF-8");

			/// @TODO one day, kill those error-suppressing @ stuff, or PHP should ban it
			@$doc->loadHTML($content);

			$xpath = new DOMXPath($doc);

			$list = $xpath->query("//*[contains(@id,'tread-wrapper-')]");  /* */

			foreach ($list as $item) {
				$item = $target->importNode($item, true);

				// And then append it to the target
				$target->documentElement->appendChild($item);
			}

			if ($_GET["mode"] == "raw") {
				$response->withBody(Utils::streamFor($target->saveHTML()));
				System::echoResponse($response);
				System::exit();
			}
		}

		$page = $this->page;

		// add and escape some common but crucial content for direct "echo" in HTML (security)
		$page['title']   = htmlspecialchars($page['title'] ?? '');
		$page['section'] = htmlspecialchars($args->get(0) ?? 'generic');
		$page['module']  = htmlspecialchars($args->getModuleName());

		header("X-Friendica-Version: " . App::VERSION);
		header("Content-type: text/html; charset=utf-8");

		if ($config->get('system', 'hsts') && ($baseURL->getScheme() === 'https')) {
			header("Strict-Transport-Security: max-age=31536000");
		}

		// Some security stuff
		header('X-Content-Type-Options: nosniff');
		header('X-XSS-Protection: 1; mode=block');
		header('X-Permitted-Cross-Domain-Policies: none');
		header('X-Frame-Options: sameorigin');

		// Things like embedded OSM maps don't work, when this is enabled
		// header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; connect-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' https: data:; media-src 'self' https:; child-src 'self' https:; object-src 'none'");

		/* We use $_GET["mode"] for special page templates. So we will check if we have
		 * to load another page template than the default one.
		 * The page templates are located in /view/php/ or in the theme directory.
		 */
		if (isset($_GET['mode'])) {
			$template = Theme::getPathForFile('php/' . Strings::sanitizeFilePathItem($_GET['mode']) . '.php');
		}

		// If there is no page template use the default page template
		if (empty($template)) {
			$template = Theme::getPathForFile('php/default.php');
		}

		// Theme templates expect $a as an App instance
		$a = $appHelper;

		// Used as is in view/php/default.php
		$lang = $l10n->getCurrentLang();

		ob_start();
		require_once $template;
		$body = ob_get_clean();

		return $response->withBody(Utils::streamFor($body));
	}
}
