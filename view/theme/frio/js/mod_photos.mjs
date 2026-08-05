// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later
/**
 * @file view/theme/frio/js/mod_photos.mjs
 * JavaScript for the photos module
 * ES Module version
 */

// Import global dependencies from window scope
const $ = window.jQuery || window.$;
const onDocumentReady = window.onDocumentReady || (window.jQuery ? window.jQuery.fn.ready : (fn => { document.addEventListener('DOMContentLoaded', fn); }));
const onWindowLoad = window.onWindowLoad || (fn => { window.addEventListener('load', fn); });
const addToModal = window.addToModal;


onDocumentReady(function () {
	$("#contact_allow, #contact_deny, #circle_allow, #circle_deny")
		.change(function () {
			var selstr;
			$(
				"#contact_allow option:selected, #contact_deny option:selected, #circle_allow option:selected, #circle_deny option:selected",
			).each(function () {
				selstr = $(this).html();
				$("#jot-perms-icon").removeClass("unlock").addClass("lock");
				$("#jot-public").hide();
			});
			if (selstr == null) {
				$("#jot-perms-icon").removeClass("lock").addClass("unlock");
				$("#jot-public").show();
			}
		})
		.trigger("change");

	// Click event listener for the album edit link/button.
	$("body").on("click", "#album-edit-link", function () {
		var modalUrl = $(this).attr("data-modal-url");

		if (typeof modalUrl !== "undefined") {
			addToModal(modalUrl, "photo-album-edit-wrapper");
		}
	});

	// Click event listener for the album drop link/button.
	$("body").on("click", "#album-drop-link", function () {
		var modalUrl = $(this).attr("data-modal-url");

		if (typeof modalUrl !== "undefined") {
			addToModal(modalUrl);
		}
	});

	// Click event listener for the photo delete link/button.
	$("body").on("click", "#photo-delete-link", function () {
		var modalUrl = $(this).attr("data-modal-url");

		if (typeof modalUrl !== "undefined") {
			addToModal(modalUrl);
		}
	});
});
// @license-end
