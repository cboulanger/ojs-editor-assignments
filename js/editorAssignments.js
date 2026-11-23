/**
 * plugins/generic/editorAssignments/js/editorAssignments.js
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Forms with data-er-confirm ask before they are submitted. The handler is delegated
 * to document because OJS's Vue app replaces the page's DOM after loading.
 */
(function () {
	'use strict';
	document.addEventListener('submit', function (e) {
		var message = e.target.getAttribute && e.target.getAttribute('data-er-confirm');
		if (message && !window.confirm(message)) {
			e.preventDefault();
		}
	}, true);
})();
