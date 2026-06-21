// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later
/**
 * Advanced Composer - Writing & Accessibility Assistant
 */
(function () {
	'use strict';

	// URL-Guard: Exit immediately if we are not on the standalone compose page.
	// Friendica 2026.05 uses clean routing (/compose); the search-string check is
	// retained only as a cheap, harmless tolerance for proxy/rewrite edge cases and
	// never broadens the match beyond compose pages.
	if (!window.location.pathname.includes('/compose') && !window.location.search.includes('compose')) {
		return;
	}

	// Single namespaced state object on window to avoid polluting the global scope
	// with multiple ad-hoc flags. This minimises the risk of name collisions when
	// Advanced Composer runs alongside many other addons on the same page.
	const acState = window.__advancedcomposer || (window.__advancedcomposer = {
		emojiCaptureBound: false,
		keydownBound: false,
		wasNativePreviewVisible: null,
		previewDebounceTimer: null
	});

	// Localizations passed from PHP (must be defined by server-side PHP)
	const l10n = window.AdvancedComposerL10n || {};

	// Helper: build a button icon+label using Remixicon classes
	function buildButtonContent(btn, iconClass, labelText) {
		const icon = document.createElement('i');
		icon.className = iconClass;
		const span = document.createElement('span');
		span.className = 'ec-btn-text';
		span.textContent = labelText;
		btn.textContent = '';
		btn.appendChild(icon);
		btn.appendChild(document.createTextNode(' '));
		btn.appendChild(span);
	}

	// Icon class constants for Remixicon
	const ICON_PEN = 'ri ri-quill-pen-line';
	const ICON_MAXIMIZE = 'ri ri-fullscreen-line';
	const ICON_MINIMIZE = 'ri ri-fullscreen-exit-line';
	const ICON_REFRESH = 'ri ri-refresh-line';
	const ICON_EYE = 'ri ri-eye-line';
	const ICON_IMAGE = 'ri ri-image-line';
	const ICON_WARNING = 'ri ri-error-warning-line';
	const ICON_HELP = 'ri ri-question-line';
	const ICON_SHIELD = 'ri ri-shield-check-line';

	let initInterval;

	// Safe HTML escaping helper to prevent any potential XSS/injection vectors via translation strings
	function escapeHTML(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	// Helper to dynamically adjust split-screen preview layout
	function syncPreviewLayout() {
		try {
			const textarea = document.getElementById('profile-jot-text')
				|| document.querySelector('textarea.profile-jot-text')
				|| document.querySelector('textarea.comment-edit-text')
				|| document.querySelector('[id^="comment-edit-text-"]');
			if (!textarea) return;

			const parentForm = textarea.closest('form') || textarea.parentNode;
			if (!parentForm) return;

			const jotPreview = document.getElementById('jot-preview-content');
			const idMatch = textarea.id.match(/comment-edit-text-([a-zA-Z0-9_-]+)/);
			const commentPreview = idMatch ? document.getElementById(`comment-edit-preview-${idMatch[1]}`) : null;
			const previewContainer = commentPreview || jotPreview;

			const isVisible = previewContainer && window.getComputedStyle(previewContainer).display !== 'none';

			const refreshClone = parentForm.querySelector('.ec-refresh-btn');
			if (refreshClone) {
				if (isVisible) {
					refreshClone.style.setProperty('display', 'inline-flex', 'important');
				} else {
					refreshClone.style.setProperty('display', 'none', 'important');
				}
			}

			const dropzone = parentForm.querySelector('.dropzone')
				|| parentForm.querySelector('[id^="dropzone-"]')
				|| document.getElementById('jot-text-wrap');

			if (isVisible && dropzone && previewContainer) {
				parentForm.classList.add('ec-preview-active-form');
				const layoutParent = dropzone.parentNode;
				if (layoutParent) {
					layoutParent.classList.add('ec-split-layout');
				}

				// If text is excessively large, overlay a warning inside the preview panel.
				// We do NOT clear the preview container (innerHTML = '') to avoid destroying
				// Friendica-managed preview content. Instead we add an absolutely-positioned
				// overlay so the warning is purely cosmetic and fully reversible.
				if (textarea.value.length >= 100000) {
					if (!previewContainer.querySelector('.ec-preview-too-long-warning')) {
						// Ensure the container is a positioning context for the overlay
						if (window.getComputedStyle(previewContainer).position === 'static') {
							previewContainer.style.position = 'relative';
						}

						const warnNode = document.createElement('div');
						warnNode.className = 'ec-preview-too-long-warning';
						warnNode.style.cssText = [
							'position:absolute', 'inset:0', 'z-index:10',
							'display:flex', 'flex-direction:column',
							'align-items:center', 'justify-content:center',
							'text-align:center',
							'background:var(--background-color,#fff)',
							'color:var(--text-color,#333)',
							"font-family:'Outfit','Inter',sans-serif",
							'padding:24px', 'min-height:250px'
						].join(';');

						// Build warning icon using Remixicon
						const warnIcon = document.createElement('i');
						warnIcon.className = ICON_WARNING;
						warnIcon.style.fontSize = '48px';
						warnIcon.style.color = '#e05d5d';
						warnIcon.style.marginBottom = '16px';
						const warnTitle = document.createElement('h3');
						warnTitle.style.cssText = 'margin:0 0 8px;font-weight:600;font-size:18px';
						warnTitle.textContent = l10n.previewTooLongTitle;

						const warnDesc = document.createElement('p');
						warnDesc.style.cssText = 'margin:0;font-size:14px;opacity:.8;line-height:1.6;max-width:320px';
						warnDesc.textContent = l10n.previewTooLongDesc;

						warnNode.appendChild(warnIcon);
						warnNode.appendChild(warnTitle);
						warnNode.appendChild(warnDesc);
						previewContainer.appendChild(warnNode);
					}
				} else {
					// Remove warning card if text length drops back below the threshold
					const warnNode = previewContainer.querySelector('.ec-preview-too-long-warning');
					if (warnNode) {
						warnNode.remove();
					}
				}
			} else {
				parentForm.classList.remove('ec-preview-active-form');
				if (dropzone && dropzone.parentNode) {
					dropzone.parentNode.classList.remove('ec-split-layout');
				}
			}
		} catch (err) {
			// Silent fallback
		}
	}

	// Helper: capture current scroll offsets of the window and all scrollable parent containers of an element
	function getScrollOffsets(el) {
		const offsets = [];
		let parent = el;
		while (parent && parent !== document.body && parent !== document.documentElement) {
			if (parent.scrollTop || parent.scrollLeft) {
				offsets.push({
					element: parent,
					top: parent.scrollTop,
					left: parent.scrollLeft
				});
			}
			parent = parent.parentNode;
		}
		offsets.push({
			element: window,
			top: window.scrollY || window.pageYOffset || document.documentElement.scrollTop,
			left: window.scrollX || window.pageXOffset || document.documentElement.scrollLeft
		});
		return offsets;
	}

	// Helper: restore captured scroll offsets
	function restoreScrollOffsets(offsets) {
		if (!offsets) return;
		offsets.forEach(function (offset) {
			try {
				if (offset.element === window) {
					window.scrollTo(offset.left, offset.top);
				} else {
					offset.element.scrollTop = offset.top;
					offset.element.scrollLeft = offset.left;
				}
			} catch (e) { }
		});
	}

	// Robust polling to safely attach to the composer whenever it enters the DOM
	function initAdvancedComposer() {
		// Completely abort on mobile/tablet viewports to protect mobile composer UX and save resources
		if (window.innerWidth < 992) {
			if (initInterval) {
				clearInterval(initInterval);
				initInterval = null;
			}
			return;
		}

		let debounceTimer;

		// 1. Find the editor textarea (supports standard composer and standalone /compose)
		const textarea = document.getElementById('profile-jot-text')
			|| document.querySelector('textarea.profile-jot-text')
			|| document.querySelector('textarea.comment-edit-text')
			|| document.querySelector('[id^="comment-edit-text-"]');
		if (!textarea) return;

		const parentForm = textarea.closest('form') || textarea.parentNode;
		if (!parentForm) return;

		// Prevent double-binding
		if (textarea.dataset.advancedComposerAttached === 'true') {
			if (initInterval) {
				clearInterval(initInterval);
				initInterval = null;
			}
			return;
		}
		textarea.dataset.advancedComposerAttached = 'true';

		// Stop dynamic polling since editor is found and bound
		if (initInterval) {
			clearInterval(initInterval);
			initInterval = null;
		}

		// 2. Find the wrapper container for inserting the panel (directly below the editor box)
		const jotWrap = document.getElementById('jot-text-wrap')
			|| textarea.closest('.dropzone')
			|| textarea.parentNode;
		if (!jotWrap) return;

		// 3. Find the visible toolbar/button group
		const toolbarGroup = document.querySelector('.btn-toolbar')
			|| document.querySelector('#profile-jot-submit-wrapper')
			|| document.querySelector('.profile-jot-submit-wrapper');
		if (toolbarGroup) {
			toolbarGroup.classList.add('ec-toolbar-active');
		}

		// 4. Get references to the static buttons from the template
		const toggleBtn = document.getElementById('easy-compose-toggle');
		const distractionBtn = document.getElementById('easy-compose-distraction-toggle');
		const focusPreviewBtn = document.getElementById('easy-compose-focus-preview-toggle');
		const epZenToggleBtn = document.getElementById('easy-compose-ep-zen-toggle');

		// If buttons don't exist, Advanced Composer is disabled - abort
		if (!toggleBtn || !distractionBtn || !focusPreviewBtn || !epZenToggleBtn) {
			return;
		}

		// Set onclick handler for epZenToggleBtn (was previously set during button creation)
		if (epZenToggleBtn && !epZenToggleBtn.onclick) {
			epZenToggleBtn.onclick = function () {
				const active = document.body.classList.toggle('ec-show-ep-list-in-zen');
				epZenToggleBtn.classList.toggle('active', active);
			};
		}

		// Create panel HTML
		const panel = document.createElement('div');
		panel.id = 'easy-compose-panel';
		panel.className = 'easy-compose-panel collapsed';

		// Restore panel open state from session (guarded: sessionStorage may be
		// unavailable in very old browsers or under strict CSP sandbox restrictions).
		try {
			if (sessionStorage.getItem('ec_panel_open') === 'true') {
				panel.classList.remove('collapsed');
				if (toggleBtn) toggleBtn.classList.add('active');
			}
		} catch (e) { /* sessionStorage unavailable — start collapsed */ }

		// Detect dark mode from textarea background brightness
		try {
			const computedBg = window.getComputedStyle(textarea).backgroundColor;
			const rgb = computedBg.match(/\d+/g);
			if (rgb && rgb.length >= 3) {
				const r = parseInt(rgb[0], 10);
				const g = parseInt(rgb[1], 10);
				const b = parseInt(rgb[2], 10);
				const brightness = (r * 299 + g * 587 + b * 114) / 1000;
				if (brightness < 120) {
					panel.classList.add('dark-theme');
					parentForm.classList.add('ec-dark-theme');
					document.body.classList.add('ec-dark-theme');
				} else {
					parentForm.classList.remove('ec-dark-theme');
					document.body.classList.remove('ec-dark-theme');
				}
			}
		} catch (e) {
			// Fallback
		}

		panel.innerHTML = '';

		// 1. Header
		const header = document.createElement('div');
		header.className = 'ec-header';

		const headerTitle = document.createElement('div');
		headerTitle.className = 'ec-header-title';

		const titleSpan = document.createElement('span');
		titleSpan.className = 'ec-title-text';
		titleSpan.textContent = l10n.title;

		const subtitleSpan = document.createElement('span');
		subtitleSpan.className = 'ec-subtitle-text';
		subtitleSpan.textContent = l10n.subtitle;

		headerTitle.appendChild(titleSpan);
		headerTitle.appendChild(subtitleSpan);

		const charCountSlot = document.createElement('span');
		charCountSlot.id = 'ec-char-count-slot';
		charCountSlot.className = 'ec-char-count';

		const headerCloseBtn = document.createElement('button');
		headerCloseBtn.type = 'button';
		headerCloseBtn.id = 'easy-compose-close';
		headerCloseBtn.className = 'ec-close-btn';
		headerCloseBtn.title = l10n.btnClose;
		headerCloseBtn.textContent = l10n.btnCloseSymbol;

		header.appendChild(headerTitle);
		header.appendChild(charCountSlot);
		header.appendChild(headerCloseBtn);

		// 2. Help & Privacy Disclosure
		const helpDisclosure = document.createElement('details');
		helpDisclosure.className = 'ec-help-disclosure';

		const helpSummary = document.createElement('summary');
		helpSummary.className = 'ec-help-summary';

		const helpIconSpan = document.createElement('span');
		helpIconSpan.className = 'ec-help-icon';
		helpIconSpan.setAttribute('aria-hidden', 'true');

				// Create help icon using Remixicon
				const helpIcon = document.createElement('i');
				helpIcon.className = ICON_HELP;
				helpIcon.setAttribute('aria-hidden', 'true');
				helpIconSpan.appendChild(helpIcon);

		const helpTextSpan = document.createElement('span');
		helpTextSpan.textContent = l10n.helpToggleLabel;

		helpSummary.appendChild(helpIconSpan);
		helpSummary.appendChild(helpTextSpan);

		const helpBody = document.createElement('div');
		helpBody.className = 'ec-help-body';

		// Privacy badge
		const privacyBadge = document.createElement('div');
		privacyBadge.className = 'ec-help-privacy-badge';

				// Create privacy icon using Remixicon
				const privacyIcon = document.createElement('i');
				privacyIcon.className = ICON_SHIELD;
				privacyIcon.setAttribute('aria-hidden', 'true');
				privacyBadge.appendChild(privacyIcon);

				// Create privacy badge text
				const privacyBadgeStrong = document.createElement('strong');
				privacyBadgeStrong.textContent = l10n.helpPrivacyBadge;
				privacyBadge.appendChild(privacyBadgeStrong);

		const privacyText = document.createElement('p');
		privacyText.className = 'ec-help-privacy-text';
		privacyText.textContent = l10n.helpPrivacyDetail;

		const divider = document.createElement('hr');
		divider.className = 'ec-help-divider';

		// Criteria list
		const criteriaDl = document.createElement('dl');
		criteriaDl.className = 'ec-help-criteria';

		const criteria = [
			{ title: l10n.helpParaTitle, body: l10n.helpParaBody },
			{ title: l10n.helpBalanceTitle, body: l10n.helpBalanceBody },
			{ title: l10n.helpLinkTitle, body: l10n.helpLinkBody },
			{ title: l10n.helpHashtagTitle, body: l10n.helpHashtagBody },
			{ title: l10n.helpAltTitle, body: l10n.helpAltBody },
			{ title: l10n.helpEmojiTitle, body: l10n.helpEmojiBody },
			{ title: l10n.helpParagraphA11yTitle, body: l10n.helpParagraphA11yBody }
		];

		criteria.forEach(item => {
			const dt = document.createElement('dt');
			dt.textContent = item.title;
			const dd = document.createElement('dd');
			dd.textContent = item.body;
			criteriaDl.appendChild(dt);
			criteriaDl.appendChild(dd);
		});

		helpBody.appendChild(privacyBadge);
		helpBody.appendChild(privacyText);
		helpBody.appendChild(divider);
		helpBody.appendChild(criteriaDl);

		helpDisclosure.appendChild(helpSummary);
		helpDisclosure.appendChild(helpBody);

		// 3. Body
		const body = document.createElement('div');
		body.className = 'ec-body';

		// 3.1 Indicators Section (Structure)
		const structureSection = document.createElement('div');
		structureSection.className = 'ec-section ec-structure';

		const structureTitle = document.createElement('h4');
		structureTitle.className = 'ec-section-title';
		structureTitle.textContent = l10n.structureTitle;

		const indicatorGroup = document.createElement('div');
		indicatorGroup.className = 'ec-indicator-group';

		const indicators = [
			{ id: 'paragraphs', label: l10n.lblParagraphs },
			{ id: 'sentence-length', label: l10n.lblSentenceLength },
			{ id: 'links', label: l10n.lblLinks },
			{ id: 'hashtags', label: l10n.lblHashtags }
		];

		indicators.forEach(ind => {
			const indicator = document.createElement('div');
			indicator.className = 'ec-indicator';

			const labelDiv = document.createElement('div');
			labelDiv.className = 'ec-indicator-label';

			const labelSpan = document.createElement('span');
			labelSpan.textContent = ind.label;

			const valSpan = document.createElement('span');
			valSpan.id = `ec-val-${ind.id}`;
			valSpan.className = 'ec-indicator-value';
			valSpan.textContent = '-';

			labelDiv.appendChild(labelSpan);
			labelDiv.appendChild(valSpan);

			const progressBar = document.createElement('div');
			progressBar.className = 'ec-progress-bar';

			const progressFill = document.createElement('div');
			progressFill.id = `ec-bar-${ind.id}`;
			progressFill.className = 'ec-progress-fill';

			progressBar.appendChild(progressFill);

			indicator.appendChild(labelDiv);
			indicator.appendChild(progressBar);
			indicatorGroup.appendChild(indicator);
		});

		structureSection.appendChild(structureTitle);
		structureSection.appendChild(indicatorGroup);

		// 3.2 Accessibility Checklist Section
		const a11ySection = document.createElement('div');
		a11ySection.className = 'ec-section ec-a11y';

		const a11yTitle = document.createElement('h4');
		a11yTitle.className = 'ec-section-title';
		a11yTitle.textContent = l10n.a11yTitle;

		const checklistUl = document.createElement('ul');
		checklistUl.className = 'ec-checklist';

		const checklistItems = [
			{ id: 'ec-chk-alt', text: l10n.a11yAltOk },
			{ id: 'ec-chk-emoji', text: l10n.a11yEmojiOk },
			{ id: 'ec-chk-paragraphs', text: l10n.a11yParagraphOk }
		];

		checklistItems.forEach(item => {
			const li = document.createElement('li');
			li.id = item.id;
			li.className = 'ec-checklist-item';

			const iconSpan = document.createElement('span');
			iconSpan.className = 'ec-icon';

			const textSpan = document.createElement('span');
			textSpan.className = 'ec-chk-text';
			textSpan.textContent = item.text;

			li.appendChild(iconSpan);
			li.appendChild(textSpan);
			checklistUl.appendChild(li);
		});

		a11ySection.appendChild(a11yTitle);
		a11ySection.appendChild(checklistUl);

		// 3.3 Tips & Readability Section
		const tipsSection = document.createElement('div');
		tipsSection.className = 'ec-section ec-tips';

		const tipsTitle = document.createElement('h4');
		tipsTitle.className = 'ec-section-title';
		tipsTitle.textContent = l10n.readabilityTitle;

		const tipsContainer = document.createElement('div');
		tipsContainer.id = 'ec-tips-container';
		tipsContainer.className = 'ec-tips-box';

		const tipSuccess = document.createElement('div');
		tipSuccess.className = 'ec-tip ec-tip-success';
		tipSuccess.textContent = l10n.tipExcellent;

		tipsContainer.appendChild(tipSuccess);
		tipsSection.appendChild(tipsTitle);
		tipsSection.appendChild(tipsContainer);

		body.appendChild(structureSection);
		body.appendChild(a11ySection);
		body.appendChild(tipsSection);

		// 4. Addon Brand Info
		const brandDiv = document.createElement('div');
		brandDiv.className = 'ec-addon-brand';
		
		const fullText = l10n.brandText;
		const match = fullText.match(/(settings|Einstellungen)/i);
		if (match) {
			const keyword = match[0];
			const parts = fullText.split(keyword);
			
			const textBefore = document.createTextNode(parts[0]);
			const link = document.createElement('a');
			link.href = 'settings/addons';
			link.className = 'ec-brand-settings-link';
			link.textContent = keyword;
			const textAfter = document.createTextNode(parts[1]);
			
			brandDiv.appendChild(textBefore);
			brandDiv.appendChild(link);
			brandDiv.appendChild(textAfter);
		} else {
			brandDiv.textContent = fullText;
		}

		// Append all assembled DOM sections to the main panel
		panel.appendChild(header);
		panel.appendChild(helpDisclosure);
		panel.appendChild(body);
		panel.appendChild(brandDiv);

		// Inject the panel. To keep the Editor and Preview adjacent siblings in the DOM
		// (which allows a clean side-by-side CSS Grid layout without wrappers),
		// we insert the panel after the preview container if it is found, or after the editor wrapper.
		const jotPreview = document.getElementById('jot-preview-content')
			|| document.querySelector('[id^="comment-edit-preview-"]');
		if (jotPreview && jotPreview.parentNode === jotWrap.parentNode) {
			jotPreview.parentNode.insertBefore(panel, jotPreview.nextSibling);
		} else {
			jotWrap.parentNode.insertBefore(panel, jotWrap.nextSibling);
		}

		// Trigger initial sync of character counter
		updateCharacterCountSlot();

		// Event: Toggle Button Click
		if (toggleBtn) {
			toggleBtn.onclick = function (e) {
				e.preventDefault();
				panel.classList.toggle('collapsed');
				const isOpen = !panel.classList.contains('collapsed');
				toggleBtn.classList.toggle('active', isOpen);
				try { sessionStorage.setItem('ec_panel_open', isOpen); } catch (e) { /* unavailable */ }
				if (isOpen) {
					runAnalysis(textarea.value);
					startValuePolling();
				} else {
					stopValuePolling();
				}
			};
		}

		// Event: Close Button Click
		const closeBtn = document.getElementById('easy-compose-close');
		if (closeBtn) {
			closeBtn.onclick = function () {
				panel.classList.add('collapsed');
				if (toggleBtn) toggleBtn.classList.remove('active');
				try { sessionStorage.setItem('ec_panel_open', 'false'); } catch (e) { /* unavailable */ }
				stopValuePolling();
			};
		}

		// Event: Debounced keystroke analysis
		let lastValue = textarea.value;

		// Caret position tracking to resolve cursor jumps on programmatic updates (e.g. emoji picker)
		let savedSelectionStart = 0;
		let savedSelectionEnd = 0;
		try {
			savedSelectionStart = textarea.selectionStart || 0;
			savedSelectionEnd = textarea.selectionEnd || 0;
		} catch (e) {}
		let watcherDebounce;
		let previewToken = 0;

		// Capture phase listener on document to intercept Friendica's emoji picker clicks.
		// The picker (.fg-emoji-list) is appended directly under <body> as a floating overlay,
		// so it is not a descendant of jotWrap — document level is required.
		// No global state object needed: textarea and caret variables come from this closure directly.
		// Guard: register only once per page load even if initAdvancedComposer runs multiple times.
		if (!acState.emojiCaptureBound) {
			acState.emojiCaptureBound = true;
			document.addEventListener('click', function (e) {
				const emojiLink = e.target.closest('.fg-emoji-list a');
				if (!emojiLink) return;

				// Only act when our textarea is still in the DOM and was the last focused editor
				if (!document.body.contains(textarea)) return;

				const emojiText = emojiLink.textContent;
				if (!emojiText) return;

				const savedStart = savedSelectionStart;
				const savedEnd = savedSelectionEnd;
				const newPos = savedStart + emojiText.length;

				// Predict updated lastValue synchronously (textarea.value still holds pre-insert state here)
				lastValue = textarea.value.substring(0, savedStart) + emojiText + textarea.value.substring(savedEnd);

				// Trigger analysis update immediately (debounced)
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(function () {
					runAnalysis(lastValue);
					syncPreviewLayout();
				}, 300);

				// Capture scroll position synchronously before any deferred callbacks fire
				const scrollOffsets = getScrollOffsets(textarea);

				// Restore caret in staggered intervals to override focus-jumping by picker scripts
				[10, 50, 100, 200].forEach(function (delay) {
					setTimeout(function () {
						try {
							textarea.focus({ preventScroll: true });
							textarea.setSelectionRange(newPos, newPos);
							savedSelectionStart = newPos;
							savedSelectionEnd = newPos;
							restoreScrollOffsets(scrollOffsets);
						} catch (err) { }
					}, delay);
				});
			}, true); // Capture phase — runs before picker's own focus handlers
		}

		function saveSelection() {
			try {
				savedSelectionStart = textarea.selectionStart;
				savedSelectionEnd = textarea.selectionEnd;
			} catch (e) { }
		}

		// Restores selection/caret position asynchronously to override WebKit/Blink default focus behavior
		function restoreCaretAfterProgrammaticChange() {
			const diff = textarea.value.length - lastValue.length;
			lastValue = textarea.value;

			// If the user is actively focusing another input or textarea (like EasyPhoto's alt text input
			// or the post title field), do NOT steal focus or restore the caret in the main editor.
			const active = document.activeElement;
			if (active && active !== textarea && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA')) {
				return;
			}

			let newStart = savedSelectionStart;
			let newEnd = savedSelectionEnd;
			if (diff > 0) {
				newStart = savedSelectionStart + diff;
				newEnd = savedSelectionStart + diff;
			}

			// Capture scroll offsets synchronously BEFORE the setTimeout
			const scrollOffsets = getScrollOffsets(textarea);

			setTimeout(function () {
				try {
					textarea.focus({ preventScroll: true });
					textarea.setSelectionRange(newStart, newEnd);
					savedSelectionStart = newStart;
					savedSelectionEnd = newEnd;
					restoreScrollOffsets(scrollOffsets);
				} catch (e) { }
			}, 10);
		}

		function adjustTextareaHeight(forceReset) {
			if (!document.body.classList.contains('ec-distraction-free')) {
				if (forceReset) {
					textarea.style.height = ''; // Reset height only when exiting Zen mode
				}
				return;
			}
			// Capture the current window scroll position before modifying heights.
			// This prevents the browser from jumping to the top of the page when
			// the textarea height momentarily collapses during height calculation.
			const scrollPos = window.scrollY || window.pageYOffset;
			textarea.style.height = 'auto';
			const sh = textarea.scrollHeight;
			textarea.style.height = (sh > 0 ? sh : textarea.offsetHeight) + 'px';
			window.scrollTo(window.scrollX, scrollPos);
		}

		// Keep caret position and lastValue synchronized on all user interactions
		// Note: programmatic textarea.value assignments (e.g. from other addons or Friendica internals)
		// are detected by the MutationObserver and the 800 ms fallback polling below.
		// We intentionally do NOT use Object.defineProperty to intercept the value setter,
		// as that technique can collide with other editor addons that may wrap the same property.
		textarea.addEventListener('keyup', function () {
			lastValue = textarea.value;
			saveSelection();
			adjustTextareaHeight();
		});
		textarea.addEventListener('click', saveSelection);
		textarea.addEventListener('focus', saveSelection);
		textarea.addEventListener('drop', function () {
			setTimeout(function () {
				lastValue = textarea.value;
				saveSelection();
			}, 1);
		});

		textarea.addEventListener('input', function () {
			lastValue = textarea.value;
			saveSelection();
			adjustTextareaHeight();
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () {
				runAnalysis(textarea.value);
				syncPreviewLayout();
			}, 300);
		});

		// --- AUTO-REFRESH FOR NORMAL PREVIEW ---
		// Find the standard Friendica preview button and refresh button
		let normalPreviewBtn = null;
		let normalPreviewFormId = null;
		let normalPreviewRefreshBtn = null;
		let normalPreviewDebounceTimer = null;
		
		// Try to find preview button and form ID for this textarea
		const textareaIdMatch = textarea.id.match(/comment-edit-text-([a-zA-Z0-9_-]+)/);
		if (textareaIdMatch) {
			normalPreviewFormId = textareaIdMatch[1];
			normalPreviewBtn = document.getElementById(`comment-edit-preview-link-${normalPreviewFormId}`)
				|| parentForm.querySelector('[id^="comment-edit-preview-link-"]')
				|| document.getElementById('jot-preview-link');
			// Look for the refresh button in the toolbar
			normalPreviewRefreshBtn = document.getElementById(`fc-toolbar-refresh-${normalPreviewFormId}`)
				|| parentForm.querySelector('.ec-refresh-btn');
		} else {
			// For profile jot text (main compose page)
			normalPreviewBtn = document.getElementById('jot-preview-link');
			normalPreviewRefreshBtn = parentForm.querySelector('.ec-refresh-btn');
		}
		
		// Also find the preview container to check if it's visible
		let normalPreviewContainer = null;
		if (normalPreviewBtn || normalPreviewRefreshBtn) {
			if (normalPreviewFormId) {
				normalPreviewContainer = parentForm.querySelector(`#comment-edit-preview-${normalPreviewFormId}`)
					|| parentForm.querySelector('.comment-edit-preview')
					|| document.getElementById('jot-preview-content');
			} else {
				normalPreviewContainer = parentForm.querySelector('.comment-edit-preview')
					|| parentForm.querySelector('[id^="comment-edit-preview-"]')
					|| document.getElementById('jot-preview-content');
			}
		}
		
		if (normalPreviewBtn || normalPreviewRefreshBtn) {
			// Track previous scroll height to detect content changes
			let lastScrollHeight = 0;
			
			const normalPreviewInputHandler = function() {
				clearTimeout(normalPreviewDebounceTimer);
				normalPreviewDebounceTimer = setTimeout(function() {
					// Check if preview container is visible
					let isPreviewVisible = false;
					if (normalPreviewContainer && document.body.contains(normalPreviewContainer)) {
						isPreviewVisible = window.getComputedStyle(normalPreviewContainer).display !== 'none';
						// Store current scroll height for comparison
						lastScrollHeight = normalPreviewContainer.scrollHeight;
					}
					
					// Only refresh if preview is visible (to avoid toggling it closed)
					if (isPreviewVisible) {
						// Enhanced scroll function that checks if content actually changed
						const scrollToBottom = function() {
							if (normalPreviewContainer && document.body.contains(normalPreviewContainer)) {
								try {
									const currentScrollHeight = normalPreviewContainer.scrollHeight;
									
									// Only scroll if content height changed or we're at the bottom
									if (currentScrollHeight > lastScrollHeight) {
										normalPreviewContainer.scrollTop = currentScrollHeight;
										lastScrollHeight = currentScrollHeight;
									} else {
										// Force scroll to bottom anyway
										normalPreviewContainer.scrollTop = currentScrollHeight;
									}
								} catch (e) {
									// Silent error handling
								}
							}
						};
						
						if (normalPreviewRefreshBtn && document.body.contains(normalPreviewRefreshBtn)) {
							try {
								normalPreviewRefreshBtn.click();
								// Scroll after a delay to allow AJAX to complete
								// Try multiple times to ensure we catch slow responses
								setTimeout(scrollToBottom, 800);
								setTimeout(scrollToBottom, 1500);
							} catch (e) {
								// Silent error handling
							}
						} else if (typeof preview_comment === 'function' && normalPreviewFormId) {
							try {
								preview_comment(normalPreviewFormId);
								// Scroll after a delay to allow AJAX to complete
								setTimeout(scrollToBottom, 800);
								setTimeout(scrollToBottom, 1500);
							} catch (e) {
								// Silent error handling
							}
						} else if (normalPreviewBtn && document.body.contains(normalPreviewBtn)) {
							try {
								normalPreviewBtn.click();
								// Scroll after a delay to allow AJAX to complete
								setTimeout(scrollToBottom, 800);
								setTimeout(scrollToBottom, 1500);
							} catch (e) {
								// Silent error handling
							}
						}
					}
				}, 2000);
			};
			textarea.addEventListener('input', normalPreviewInputHandler);
		}

		// Event: Intercept clicks on the parent form (Emoji Picker, formatting buttons, uploads, smileybutton)
		if (parentForm) {
			parentForm.addEventListener('click', function (e) {
				if (e.target.closest('button') || e.target.closest('.fakelink') || e.target.closest('a') || e.target.closest('#profile-smiley-wrapper span')) {
					setTimeout(function () {
						lastValue = textarea.value;
						runAnalysis(textarea.value);
						syncPreviewLayout();
						adjustTextareaHeight();
					}, 200);
				}
			});
		}

		// Watch for programmatic changes (e.g. image uploads completing, emoji selection, custom toolbar buttons)
		// Uses MutationObserver on the textarea's parent instead of setInterval polling,
		// which avoids continuous CPU wake-ups and cleans up automatically when the node leaves the DOM.
		const watcherObserver = new MutationObserver(function () {
			if (!document.body.contains(textarea)) {
				watcherObserver.disconnect();
				return;
			}
			if (textarea.value !== lastValue) {
				restoreCaretAfterProgrammaticChange();
				adjustTextareaHeight();
				clearTimeout(watcherDebounce);
				watcherDebounce = setTimeout(function () {
					runAnalysis(textarea.value);
					syncPreviewLayout();
				}, 300);
			}
		});
		// Observe subtree of the parent container for attribute/child changes that
		// accompany programmatic value updates (upload completions, emoji insertions, etc.).
		// Note on Performance: A broad subtree observer is used to reliably detect programmatic
		// insertions from other addons/plugins. Any potential performance overhead from frequent DOM
		// reflows/mutations is significantly mitigated by our highly efficient 300ms debounce on runAnalysis().
		watcherObserver.observe(textarea.parentNode || document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['value', 'data-value']
		});
		// Safety: disconnect if textarea is ever removed from DOM (e.g. comment collapsed)
		const watcherRemovalObserver = new MutationObserver(function () {
			if (!document.body.contains(textarea)) {
				watcherObserver.disconnect();
				watcherRemovalObserver.disconnect();
				stopValuePolling();
			}
		});
		watcherRemovalObserver.observe(document.body, { childList: true, subtree: true });

		// Fallback watcher for programmatic textarea.value changes
		// that trigger neither an input event nor a DOM mutation
		// (e.g. direct JS assignment by emoji picker, upload handler, toolbar plugins).
		// Runs only when the panel is open to minimize CPU load.
		// Note on Performance: The 800ms polling checks only raw variables (cheap string inequality)
		// and operates strictly while the analysis panel is expanded, preventing any noticeable CPU
		// usage even on low-end mobile devices.
		let valuePollingInterval = null;

		function startValuePolling() {
			if (valuePollingInterval) return;
			valuePollingInterval = setInterval(function () {
				if (!document.body.contains(textarea)) {
					stopValuePolling();
					return;
				}
				if (textarea.value !== lastValue) {
					restoreCaretAfterProgrammaticChange();
					runAnalysis(textarea.value);
					syncPreviewLayout();
					adjustTextareaHeight();
				}
			}, 800);
		}

		function stopValuePolling() {
			if (valuePollingInterval) {
				clearInterval(valuePollingInterval);
				valuePollingInterval = null;
			}
		}

		// Event: Distraction-free toggle
		const distractionToggle = document.getElementById('easy-compose-distraction-toggle');

		function toggleDistractionFree() {
			// NOTE for developers: Toggling this global body class hides all surrounding layout columns
			// and focuses exclusively on the editor workspace. The class is prefixed with 'ec-'
			// to avoid collisions with other addons or core system components.
			const active = document.body.classList.toggle('ec-distraction-free');
			if (distractionToggle) {
				distractionToggle.classList.toggle('active', active);
				if (active) {
					buildButtonContent(distractionToggle, ICON_MINIMIZE, l10n.btnZen);

					// Close preview when entering Zen mode
					try {
						const parentForm = textarea.closest('form') || textarea.parentNode;
						if (parentForm) {
							const previewContainer = parentForm.querySelector('.comment-edit-preview')
								|| parentForm.querySelector('[id^="comment-edit-preview-"]');
							const previewBtn = parentForm.querySelector('[id^="comment-edit-preview-link-"]');

							if (previewContainer && previewBtn && window.getComputedStyle(previewContainer).display !== 'none') {
								previewBtn.click();
							}
						}
					} catch (err) {
						// Safe fallback
					}
				} else {
					buildButtonContent(distractionToggle, ICON_MAXIMIZE, l10n.btnZen);
					
					// Clean up Zen-mode EasyPhoto visibility
					document.body.classList.remove('ec-show-ep-list-in-zen');
					const epZenToggleBtn = document.getElementById('easy-compose-ep-zen-toggle');
					if (epZenToggleBtn) {
						epZenToggleBtn.classList.remove('active');
					}
				}
			}
			adjustTextareaHeight(true);
		}

		if (distractionToggle) {
			distractionToggle.onclick = function () {
				toggleDistractionFree();
			};
		}

		if (focusPreviewBtn) {
			focusPreviewBtn.onclick = function () {
				openFocusPreview();
			};
		}

		// ─── Server-side AJAX Preview ─────────────────────────────────────────
		// Fetches the rendered preview HTML from the server via AJAX and displays
		// it in our custom full-screen overlay. Security relies on Friendica's own
		// server-side sanitization of the preview endpoint; cloneNode(true) is used
		// to copy already-rendered DOM nodes without re-executing any script elements.
		// Uses an integer token (previewToken) to prevent race conditions from
		// overlapping slow AJAX requests.
		function openFocusPreview() {
			previewToken++;
			const myToken = previewToken;

			const parentForm = textarea.closest('form') || textarea.parentNode;
			if (!parentForm) return;

			let previewContainer = parentForm.querySelector('.comment-edit-preview')
				|| parentForm.querySelector('[id^="comment-edit-preview-"]')
				|| document.getElementById('jot-preview-content');

			const idMatch = textarea.id.match(/comment-edit-text-([a-zA-Z0-9_-]+)/);
			const formId = idMatch ? idMatch[1] : null;

			const previewBtn = parentForm.querySelector('[id^="comment-edit-preview-link-"]')
				|| document.getElementById('jot-preview-link')
				|| document.getElementById(`comment-edit-preview-link-${formId}`);

			if (previewBtn) {
				const isVisible = previewContainer && window.getComputedStyle(previewContainer).display !== 'none';
				acState.wasNativePreviewVisible = isVisible;

				// Mark the native container as stale using a plain JS property.
				// This leaves all DOM nodes, child elements, and any event listeners
				// attached by other addons completely untouched — we never call
				// innerHTML = '' on Friendica's own preview container.
				// tryTransferContent() checks this flag and ignores stale content.
				if (previewContainer) {
					previewContainer._ecStale = true;
					previewContainer._ecOldHtml = previewContainer.innerHTML;
				}

				// Trigger a fresh AJAX render via Friendica's own preview_comment() API
				// if available (stable since Friendica 2022.x), falling back to a single
				// previewBtn.click() for older instances.
				// NOTE: We deliberately do NOT do the synchronous double-click trick here.
				// When the preview is already visible, preview_comment() re-renders it
				// directly (idempotent). A synchronous double previewBtn.click() on a
				// visible container toggles it off then on, creating a race window between
				// the two events and potential interference with other addons' click handlers.
				// The direct API call avoids both problems and is theme-independent.
				if (typeof preview_comment === 'function' && formId) {
					preview_comment(formId);
				} else if (!isVisible) {
					// Container is hidden: one click opens it and fires the AJAX request.
					previewBtn.click();
				} else {
					// Container already visible but preview_comment() unavailable:
					// toggle off, then back on with a short gap so Friendica's own handler
					// has time to complete before the second click.
					previewBtn.click();
					setTimeout(function () { previewBtn.click(); }, 80);
				}
			}

			// Build overlay element
			let overlay = document.getElementById('easy-compose-preview-overlay');
			if (!overlay) {
				overlay = document.createElement('div');
				overlay.id = 'easy-compose-preview-overlay';
				overlay.className = 'ec-preview-overlay';
				if (document.body.classList.contains('ec-dark-theme')) {
					overlay.classList.add('dark-theme');
				}
				document.body.appendChild(overlay);
			}

			overlay.innerHTML = '';

			// ── Topbar ────────────────────────────────────────────────────────────
			const topbar = document.createElement('div');
			topbar.className = 'ec-preview-topbar';

			const deviceSelector = document.createElement('div');
			deviceSelector.className = 'ec-preview-device-selector';

			const desktopBtn = document.createElement('button');
			desktopBtn.type = 'button';
			desktopBtn.className = 'ec-device-btn active';
			desktopBtn.setAttribute('data-device', 'desktop');
			desktopBtn.textContent = l10n.btnDesktop;

			const mobileBtn = document.createElement('button');
			mobileBtn.type = 'button';
			mobileBtn.className = 'ec-device-btn';
			mobileBtn.setAttribute('data-device', 'mobile');
			mobileBtn.textContent = l10n.btnMobile;

			deviceSelector.appendChild(desktopBtn);
			deviceSelector.appendChild(mobileBtn);

			// ── Refresh button ───────────────────────────────────────────────
			const refreshBtn = document.createElement('button');
			refreshBtn.type = 'button';
			refreshBtn.className = 'ec-preview-refresh-btn';
			refreshBtn.title = l10n.btnRefreshPreview;
			buildButtonContent(refreshBtn, ICON_REFRESH, l10n.btnRefreshPreview);

			const topbarLeft = document.createElement('div');
			topbarLeft.className = 'ec-preview-topbar-left';
			topbarLeft.appendChild(deviceSelector);
			topbarLeft.appendChild(refreshBtn);

			const closeBtn = document.createElement('button');
			closeBtn.type = 'button';
			closeBtn.className = 'ec-preview-close-btn';
			closeBtn.textContent = l10n.btnBackToEditor;

			topbar.appendChild(topbarLeft);
			topbar.appendChild(closeBtn);

			// ── Card Shell ────────────────────────────────────────────────────────
			const contentArea = document.createElement('div');
			contentArea.className = 'ec-preview-content-area';

			const cardWrapper = document.createElement('div');
			cardWrapper.className = 'ec-preview-card-wrapper desktop';

			const cardHeader = document.createElement('div');
			cardHeader.className = 'ec-preview-card-header';

			const avatarPlaceholder = document.createElement('div');
			avatarPlaceholder.className = 'ec-preview-avatar-placeholder';

			const authorInfo = document.createElement('div');
			authorInfo.className = 'ec-preview-author-info';

			const authorName = document.createElement('span');
			authorName.className = 'ec-preview-author-name';

			const timestamp = document.createElement('span');
			timestamp.className = 'ec-preview-timestamp';

			authorInfo.appendChild(authorName);
			authorInfo.appendChild(timestamp);
			cardHeader.appendChild(avatarPlaceholder);
			cardHeader.appendChild(authorInfo);

			const cardBody = document.createElement('div');
			cardBody.className = 'ec-preview-card-body';

			const loadingDiv = document.createElement('div');
			loadingDiv.className = 'ec-preview-loading';
			loadingDiv.textContent = l10n.btnLoadingPreview;
			cardBody.appendChild(loadingDiv);

			cardWrapper.appendChild(cardHeader);
			cardWrapper.appendChild(cardBody);
			contentArea.appendChild(cardWrapper);
			overlay.appendChild(topbar);
			overlay.appendChild(contentArea);

			document.body.classList.add('ec-preview-overlay-active');

			// ── Event Handlers ────────────────────────────────────────────────────
			closeBtn.onclick = closeFocusPreview;

			refreshBtn.onclick = function () {
				cardBody.innerHTML = '';
				const loadDiv = document.createElement('div');
				loadDiv.className = 'ec-preview-loading';
				loadDiv.textContent = l10n.btnLoadingPreview;
				cardBody.appendChild(loadDiv);

				refreshBtn.classList.add('ec-refreshing');
				setTimeout(function () { refreshBtn.classList.remove('ec-refreshing'); }, 600);

				if (previewBtn) {
					// Mark as stale without touching Friendica's DOM — same approach as openFocusPreview().
					if (previewContainer) {
						previewContainer._ecStale = true;
						previewContainer._ecOldHtml = previewContainer.innerHTML;
					}
					const isVisible = previewContainer && window.getComputedStyle(previewContainer).display !== 'none';
					if (typeof preview_comment === 'function' && formId) {
						preview_comment(formId);
					} else if (!isVisible) {
						previewBtn.click();
					} else {
						previewBtn.click();
						setTimeout(function () { previewBtn.click(); }, 80);
					}
				}

				previewToken++;
				startPollingPreview(previewToken);
			};

			const deviceBtns = overlay.querySelectorAll('.ec-device-btn');
			deviceBtns.forEach(function(btn) {
				btn.onclick = function () {
					deviceBtns.forEach(function(b) { b.classList.remove('active'); });
					btn.classList.add('active');
					cardWrapper.className = 'ec-preview-card-wrapper ' +
						(btn.getAttribute('data-device') === 'mobile' ? 'mobile' : 'desktop');
				};
			});

			const escapeHandler = function (e) {
				if (e.key === 'Escape') {
					closeFocusPreview();
					document.removeEventListener('keydown', escapeHandler);
				}
			};
			document.addEventListener('keydown', escapeHandler);

			// Transfers rendered preview content from Friendica's native preview container
			// into our overlay card. Uses MutationObserver as the primary mechanism so we
			// react immediately when Friendica's AJAX fills the container, with no wasted
			// polling cycles. A setTimeout-based fallback covers browsers and edge cases
			// where the observer fires before the DOM subtree is fully populated, or where
			// the container was already filled before the observer was attached.
			//
			// Token guard: every call receives the token value at the moment it was started.
			// If openFocusPreview() or the Refresh button increments previewToken before this
			// resolves, the stale observer/timeout silently exits without touching the UI.
			function startPollingPreview(myTokenId) {
				// Event listener for Friendica's native live update dispatch.
				// This gives us an instant, reliable hook when the AJAX completes,
				// independent of DOM mutations.
				const liveUpdateHandler = function () {
					const container = getContainer();
					if (container) {
						container._ecStale = false;
					}
					onContentReady();
				};
				document.addEventListener('postprocess_liveupdate', liveUpdateHandler);

				// --- shared transfer logic used by both the observer and the fallback ---
				function tryTransferContent(container) {
					if (previewToken !== myTokenId) {
						return false;
					}
					if (!document.body.classList.contains('ec-preview-overlay-active')) {
						return false;
					}

					// Stale guard: if we marked this container as stale (fresh AJAX triggered),
					// do not copy its current content unless the innerHTML has actually changed.
					if (container && container._ecStale === true) {
						if (container._ecOldHtml !== undefined && container.innerHTML !== container._ecOldHtml) {
							container._ecStale = false;
						} else {
							return false;
						}
					}
					if (!container || !container.innerHTML.trim()) {
						return false;
					}
					if (container.querySelector('.ec-preview-too-long-warning')) {
						return false;
					}

					var titleEl = container.querySelector('.wall-item-title')
						|| container.querySelector('.comment-edit-preview-title');
					var bodyEl = container.querySelector('.wall-item-body')
						|| container.querySelector('.comment-edit-preview')
						|| container;

					cardBody.innerHTML = '';
					if (titleEl && bodyEl !== container) {
						cardBody.appendChild(titleEl.cloneNode(true));
					}
					// cloneNode(true) copies already-server-sanitized DOM nodes;
					// script elements in cloned subtrees are not executed by the browser.
					cardBody.appendChild(bodyEl.cloneNode(true));
					// Clear the stale flag now that fresh content has been successfully transferred.
					container._ecStale = false;
					_ecFillCardHeader(overlay, container);

					// Scroll to bottom after content update so newest text is visible
					setTimeout(function() {
						cardBody.scrollTop = cardBody.scrollHeight;
					}, 0);

					return true;
				}

				// Resolve the live container reference (same selectors as before)
				function getContainer() {
					return parentForm.querySelector('.comment-edit-preview')
						|| parentForm.querySelector('[id^="comment-edit-preview-"]')
						|| document.getElementById('jot-preview-content');
				}

				let transferred = false;
				let observer = null;
				let fallbackHandle = null;

				function onContentReady() {
					if (transferred) {
						return;
					}
					var container = getContainer();
					if (tryTransferContent(container)) {
						transferred = true;
						if (observer) { observer.disconnect(); observer = null; }
						if (fallbackHandle !== null) { clearTimeout(fallbackHandle); fallbackHandle = null; }
						document.removeEventListener('postprocess_liveupdate', liveUpdateHandler);
					}
				}

				// --- Primary path: MutationObserver ---
				// Fires immediately when Friendica's AJAX handler inserts rendered content.
				// Supported in all relevant browsers (Chrome, Firefox, Safari 7+, Edge).
				// We observe the container if it already exists, otherwise parentForm,
				// so we also catch the case where the container itself is created by Friendica.
				const observeTarget = getContainer() || parentForm;
				try {
					observer = new MutationObserver(function () {
						const container = getContainer();
						if (container) {
							container._ecStale = false;
						}
						onContentReady();
					});
					observer.observe(observeTarget, { childList: true, subtree: true });
				} catch (e) {
					// Extremely old engine without MutationObserver — fall through to polling only
					observer = null;
				}

				// --- Fallback path: lightweight setTimeout polling ---
				// Runs in parallel with the observer for two reasons:
				// 1. Catches the case where Friendica had already filled the container before
				//    the observer was attached (synchronous AJAX response on fast servers).
				// 2. Safety net for WebKit versions that deliver MutationObserver callbacks
				//    at a coarser granularity than the DOM mutation itself.
				// Max wait: 15 × 300 ms = 4.5 s — after that we give up silently and the
				// loading indicator remains, which is the same behaviour as before.
				let attempts = 0;
				(function pollFallback() {
					if (transferred) return;
					if (previewToken !== myTokenId) return;
					if (!document.body.classList.contains('ec-preview-overlay-active')) return;
					onContentReady();
					if (!transferred) {
						if (attempts < 15) {
							attempts++;
							fallbackHandle = setTimeout(pollFallback, 300);
						} else {
							// Timed out — disconnect observer, leave loading indicator visible
							if (observer) { observer.disconnect(); observer = null; }
							document.removeEventListener('postprocess_liveupdate', liveUpdateHandler);
						}
					}
				}());
			}

			// Auto-refresh preview after 2 seconds of inactivity
			const previewInputHandler = function() {
				clearTimeout(acState.previewDebounceTimer);
				acState.previewDebounceTimer = setTimeout(function() {
					if (document.body.classList.contains('ec-preview-overlay-active')) {
						try {
							refreshBtn.click();
						} catch (e) {}
					}
				}, 2000);
			};
			textarea.addEventListener('input', previewInputHandler);
			overlay._ecPreviewInputHandler = previewInputHandler;

			// Start observer + fallback
			startPollingPreview(myToken);
		}

		// Helper to fill card header elements safely using textContent
		function _ecFillCardHeader(overlay, previewContainer) {
			const avatarPlaceholder = overlay.querySelector('.ec-preview-avatar-placeholder');
			const authorNameEl = overlay.querySelector('.ec-preview-author-name');
			const timestampEl = overlay.querySelector('.ec-preview-timestamp');

			if (avatarPlaceholder) {
				avatarPlaceholder.textContent = '';
				const realAvatar = document.querySelector('#main-menu img#avatar')
					|| document.querySelector('img#avatar')
					|| document.querySelector('#main-menu img')
					|| (previewContainer && previewContainer.querySelector('.author-avatar img'))
					|| (previewContainer && previewContainer.querySelector('.avatar img'))
					|| document.querySelector('.widget.profile-sidebar img')
					|| document.querySelector('.navbar-right img')
					|| document.querySelector('#profile-photo');

				if (realAvatar && realAvatar.getAttribute('src')) {
					const imgEl = document.createElement('img');
					imgEl.src = realAvatar.getAttribute('src');
					imgEl.className = 'ec-preview-avatar';
					imgEl.alt = '';
					avatarPlaceholder.appendChild(imgEl);
				} else {
					const fallbackEl = document.createElement('div');
					fallbackEl.className = 'ec-preview-avatar-fallback';
					fallbackEl.textContent = l10n.avatarFallback;
					avatarPlaceholder.appendChild(fallbackEl);
				}
			}

			if (authorNameEl) {
				const realName = document.querySelector('#main-menu .user-title strong')
					|| document.querySelector('#nav-user-linkmenu strong')
					|| (previewContainer && previewContainer.querySelector('.author-name'))
					|| (previewContainer && previewContainer.querySelector('.author-wrapper a'))
					|| document.querySelector('.profile-sidebar .name')
					|| document.querySelector('.navbar-right .username');
				authorNameEl.textContent = realName
					? realName.textContent.trim()
					: (l10n.lblYou);
			}

			if (timestampEl) {
				timestampEl.textContent = l10n.previewTimestamp;
			}
		}

		function closeFocusPreview() {
			previewToken++;
			document.body.classList.remove('ec-preview-overlay-active');
			const overlay = document.getElementById('easy-compose-preview-overlay');
			if (overlay && overlay.parentNode) {
				// Remove the auto-refresh input handler
				if (overlay._ecPreviewInputHandler) {
					textarea.removeEventListener('input', overlay._ecPreviewInputHandler);
					overlay._ecPreviewInputHandler = null;
				}
				overlay.parentNode.removeChild(overlay);
			}

			// Clear the preview auto-refresh timer
			clearTimeout(acState.previewDebounceTimer);
			acState.previewDebounceTimer = null;

			// COMPATIBILITY: Restore native preview to its original state (closed if it was closed)
			const parentForm = textarea.closest('form') || textarea.parentNode;
			if (parentForm && acState.wasNativePreviewVisible === false) {
				const previewContainer = parentForm.querySelector('.comment-edit-preview')
					|| parentForm.querySelector('[id^="comment-edit-preview-"]')
					|| document.getElementById('jot-preview-content');

				if (previewContainer) {
					previewContainer.style.display = 'none';
				}
			}
			syncPreviewLayout();
		}

		// Event: Escape key exits distraction free mode
		// Guard: register only once per page load, even if initAdvancedComposer runs multiple times
		if (!acState.keydownBound) {
			acState.keydownBound = true;
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && document.body.classList.contains('ec-distraction-free')) {
					toggleDistractionFree();
				}
			});
		}

		// Trigger initial analysis & preview check
		runAnalysis(textarea.value);
		syncPreviewLayout();

		// Re-check shortly after startup so integrations loaded later,
		// especially EasyPhoto's .ep-list, are detected reliably.
		[250, 1000].forEach(function (delay) {
			setTimeout(function () {
				if (document.body.contains(textarea)) {
					runAnalysis(textarea.value);
				}
			}, delay);
		});


		// If panel was restored as open, start the fallback value watcher immediately
		if (!panel.classList.contains('collapsed')) {
			startValuePolling();
		}
	}

	/**
	 * Text analysis and UI update
	 */
	function runAnalysis(text) {
		updateCharacterCountSlot();
		const cleanText = text.trim();
		const textLength = cleanText.length;

		// Safety cap: If the text is excessively long (e.g. > 100,000 characters),
		// abort the intensive analysis to prevent regular expression backtracking freezes and OOM crashes.
		if (textLength > 100000) {
			const tipsContainer = document.getElementById('ec-tips-container');
			if (tipsContainer) {
				tipsContainer.textContent = '';
				const warnDiv = document.createElement('div');
				warnDiv.className = 'ec-tip ec-tip-warn';
				warnDiv.textContent = l10n.tipTooLong;
				tipsContainer.appendChild(warnDiv);
			}
			return;
		}

		// 1. Evaluate Paragraph Structure
		const paragraphs = cleanText.split(/\n\s*\n/).filter(p => p.trim().length > 0);
		const paragraphCount = paragraphs.length;

		let paragraphScore = 100;
		let paragraphLabel = l10n.lblParaBalanced;
		if (textLength > 600 && paragraphCount === 1) {
			paragraphScore = 30;
			paragraphLabel = l10n.lblParaOneBlock;
		} else if (textLength > 1200 && paragraphCount < 3) {
			paragraphScore = 60;
			paragraphLabel = l10n.lblParaCompact;
		} else if (paragraphCount > 1) {
			paragraphScore = 100;
			paragraphLabel = l10n.lblParaStructured;
		} else {
			paragraphScore = 100;
			paragraphLabel = l10n.lblParaShort;
		}

		// 2. Evaluate Text Balance (Sentence length)
		const sentences = cleanText.split(/[.!?]+(?:\s|$)/).filter(s => s.trim().length > 0);
		let totalWords = 0;
		let maxSentenceWords = 0;
		let longSentencesCount = 0;

		sentences.forEach(sentence => {
			const words = sentence.trim().split(/\s+/).filter(w => w.length > 0);
			const count = words.length;
			totalWords += count;
			if (count > maxSentenceWords) {
				maxSentenceWords = count;
			}
			if (count > 25) {
				longSentencesCount++;
			}
		});

		const avgSentenceWords = sentences.length > 0 ? (totalWords / sentences.length) : 0;
		let balanceScore = 100;
		let balanceLabel = l10n.lblBalanceEasy;

		if (avgSentenceWords > 24 || longSentencesCount > 1) {
			balanceScore = 40;
			balanceLabel = l10n.lblBalanceNested;
		} else if (avgSentenceWords > 16 || longSentencesCount > 0) {
			balanceScore = 75;
			balanceLabel = l10n.lblBalanceMedium;
		}

		// 3. Evaluate Link Density
		const linkMatches = cleanText.match(/https?:\/\/[^\s\[\]]+/g) || [];
		const linkCount = linkMatches.length;
		let linkScore = 100;
		let linkLabel = l10n.lblLinkSubtle;

		if (linkCount > 5) {
			linkScore = 30;
			linkLabel = l10n.lblLinkDense;
		} else if (linkCount > 3) {
			linkScore = 70;
			linkLabel = l10n.lblLinkMany;
		}

		// 4. Evaluate Hashtag Density
		const hashtagMatches = cleanText.match(/#\w+/g) || [];
		const hashtagCount = hashtagMatches.length;
		let hashtagScore = 100;
		let hashtagLabel = l10n.lblHashtagSubtle;

		if (hashtagCount > 6) {
			hashtagScore = 30;
			hashtagLabel = l10n.lblHashtagDense;
		} else if (hashtagCount > 3) {
			hashtagScore = 75;
			hashtagLabel = l10n.lblHashtagMany;
		}

		// 5. Evaluate Accessibility Checklist (BBCode & Markdown Images)
		// COMPATIBILITY NOTE FOR DEVELOPERS:
		// We explicitly support three image syntax formats:
		// A. Standard BBCode: [img=url]description[/img] or [img alt="description"]url[/img]
		// B. QuickPhoto/EasyPhoto simplified BBCode format: [img]url|description[/img]
		// C. Markdown images: ![description](url)

		const imgMatches = cleanText.match(/\[img(.*?)\](.*?)\[\/img\]/gi) || [];
		let imagesMissingAlt = 0;

		imgMatches.forEach(match => {
			const parts = match.match(/\[img(.*?)\](.*?)\[\/img\]/i);
			if (parts) {
				const attributes = parts[1].trim();
				const content = parts[2].trim();

				if (attributes.startsWith('=')) {
					if (content.length === 0) {
						imagesMissingAlt++;
					}
				} else {
					// COMPATIBILITY: QuickPhoto / EasyPhoto simplified format [img]url|description[/img]
					if (attributes.length === 0 && content.includes('|')) {
						const pipeIndex = content.indexOf('|');
						const desc = content.substring(pipeIndex + 1).trim();

					// Placeholder text from QuickPhoto/EasyPhoto
					if (desc.length === 0 || desc === l10n.placeholder ||
						(window.qp_i18n && desc === window.qp_i18n.imageDesc) ||
						(window.easyphoto_l10n && desc === window.easyphoto_l10n.placeholder)) {
						imagesMissingAlt++;
					}
					} else {
						// Standard [img alt=...] or standard [img] tag
						const hasAltAttribute = /alt\s*=\s*['"]/i.test(attributes);
						if (!hasAltAttribute) {
							imagesMissingAlt++;
						}
					}
				}
			}
		});

		// Detect Markdown images: ![alt](url)
		const mdImgMatches = cleanText.match(/!\[(.*?)\]\((.*?)\)/gi) || [];
		let mdImagesMissingAlt = 0;
		mdImgMatches.forEach(match => {
			const parts = match.match(/!\[(.*?)\]\((.*?)\)/i);
			if (parts) {
				const altText = parts[1].trim();
				if (altText.length === 0) {
					mdImagesMissingAlt++;
				}
			}
		});

		const hasImages = imgMatches.length > 0 || mdImgMatches.length > 0;
		const altOk = !hasImages || (imagesMissingAlt === 0 && mdImagesMissingAlt === 0);

		// Dynamically show/hide the EasyPhoto Zen-mode toggle button
		const epZenToggleBtn = document.getElementById('easy-compose-ep-zen-toggle');
		if (epZenToggleBtn) {
			const hasEasyPhoto = document.querySelector('.ep-list') !== null;
			const parentLi = epZenToggleBtn.parentElement && epZenToggleBtn.parentElement.tagName === 'LI' ? epZenToggleBtn.parentElement : null;

			if (hasImages && hasEasyPhoto) {
				epZenToggleBtn.classList.remove('ec-hidden');
				if (parentLi) {
					parentLi.classList.remove('ec-hidden');
				}
			} else {
				epZenToggleBtn.classList.add('ec-hidden');
				if (parentLi) {
					parentLi.classList.add('ec-hidden');
				}
				// Clean up any active state if images were deleted or EasyPhoto was disabled
				document.body.classList.remove('ec-show-ep-list-in-zen');
				epZenToggleBtn.classList.remove('active');
			}
		}

		const emojiFloodRegex = /(?:\p{Emoji_Presentation}|\p{Extended_Pictographic}){5,}/u;
		const hasEmojiFlood = emojiFloodRegex.test(cleanText);

		const needsParagraphs = textLength >= 300 && paragraphCount === 1;

		let hasShouting = false;
		sentences.forEach(s => {
			const words = s.trim().split(/\s+/).filter(w => w.length > 0);
			if (words.length > 4 && s === s.toUpperCase() && /[A-Z]/.test(s)) {
				hasShouting = true;
			}
		});

		// --- Update UI Progress Bars & Labels ---
		updateIndicator('paragraphs', paragraphScore, paragraphLabel);
		updateIndicator('sentence-length', balanceScore, balanceLabel);
		updateIndicator('links', linkScore, linkLabel);
		updateIndicator('hashtags', hashtagScore, hashtagLabel);

		// --- Update Accessibility Checklist ---
		const chkAlt = document.getElementById('ec-chk-alt');
		if (chkAlt) {
			if (!hasImages) {
				chkAlt.className = 'ec-checklist-item neutral';
				chkAlt.querySelector('.ec-chk-text').textContent = l10n.a11yAltOk;
			} else if (altOk) {
				chkAlt.className = 'ec-checklist-item ok';
				chkAlt.querySelector('.ec-chk-text').textContent = l10n.a11yAltOk;
			} else {
				chkAlt.className = 'ec-checklist-item warn';
				chkAlt.querySelector('.ec-chk-text').textContent = l10n.a11yAltWarn;
			}
		}

		const chkEmoji = document.getElementById('ec-chk-emoji');
		if (chkEmoji) {
			if (!hasEmojiFlood) {
				chkEmoji.className = 'ec-checklist-item ok';
				chkEmoji.querySelector('.ec-chk-text').textContent = l10n.a11yEmojiOk;
			} else {
				chkEmoji.className = 'ec-checklist-item warn';
				chkEmoji.querySelector('.ec-chk-text').textContent = l10n.a11yEmojiWarn;
			}
		}

		const chkPara = document.getElementById('ec-chk-paragraphs');
		if (chkPara) {
			if (textLength < 300) {
				chkPara.className = 'ec-checklist-item neutral';
				chkPara.querySelector('.ec-chk-text').textContent = l10n.a11yParagraphNeutral;
			} else if (paragraphCount > 1) {
				chkPara.className = 'ec-checklist-item ok';
				chkPara.querySelector('.ec-chk-text').textContent = l10n.a11yParagraphOk;
			} else {
				chkPara.className = 'ec-checklist-item warn';
				chkPara.querySelector('.ec-chk-text').textContent = l10n.a11yParagraphWarn;
			}
		}

		// --- Generate Dynamic Tips & Style Advice ---
		const tipsContainer = document.getElementById('ec-tips-container');
		if (tipsContainer) {
			tipsContainer.textContent = '';
			const activeTips = [];

			if (imagesMissingAlt > 0) {
				activeTips.push({ text: l10n.tipMissingAlt, type: 'warn' });
			}
			if (needsParagraphs) {
				activeTips.push({ text: l10n.tipNoParagraphs, type: 'warn' });
			}
			if (longSentencesCount > 0) {
				activeTips.push({ text: l10n.tipLongSentences, type: 'info' });
			}
			if (hasShouting) {
				activeTips.push({ text: l10n.tipShouting, type: 'info' });
			}
			if (hashtagCount > 5) {
				activeTips.push({ text: l10n.tipTooManyHashtags, type: 'info' });
			}
			if (hasEmojiFlood) {
				activeTips.push({ text: l10n.tipEmojiFlood, type: 'info' });
			}

			if (activeTips.length === 0) {
				const successDiv = document.createElement('div');
				successDiv.className = 'ec-tip ec-tip-success';
				successDiv.textContent = l10n.tipExcellent;
				tipsContainer.appendChild(successDiv);
			} else {
				activeTips.forEach(tip => {
					const tipDiv = document.createElement('div');
					tipDiv.className = `ec-tip ec-tip-${tip.type}`;
					tipDiv.textContent = tip.text;
					tipsContainer.appendChild(tipDiv);
				});
			}
		}
	}

	function updateCharacterCountSlot() {
		try {
			const nativeCounter = document.getElementById('character-counter');
			const ecSlot = document.getElementById('ec-char-count-slot');
			if (nativeCounter && ecSlot) {
				const countVal = nativeCounter.textContent.trim();
				const suffix = l10n.chars;
				ecSlot.textContent = `${countVal} ${suffix}`;
			}
		} catch (e) { }
	}

	function updateIndicator(id, score, label) {
		const valSpan = document.getElementById(`ec-val-${id}`);
		const barFill = document.getElementById(`ec-bar-${id}`);

		if (valSpan) {
			valSpan.textContent = label;
		}

		if (barFill) {
			barFill.style.width = `${score}%`;

			barFill.classList.remove('fill-green', 'fill-yellow', 'fill-red');
			if (score >= 80) {
				barFill.classList.add('fill-green');
			} else if (score >= 50) {
				barFill.classList.add('fill-yellow');
			} else {
				barFill.classList.add('fill-red');
			}
		}
	}

	// Attach to composer on load and periodically to handle AJAX-loaded editors
	initInterval = setInterval(initAdvancedComposer, 1000);
	initAdvancedComposer();

	// Cleanup: stop all intervals when the page is unloaded to prevent memory leaks
	// in browsers that keep the JS context alive across navigation (bfcache).
	window.addEventListener('pagehide', function () {
		if (initInterval) {
			clearInterval(initInterval);
			initInterval = null;
		}
	});
})();
