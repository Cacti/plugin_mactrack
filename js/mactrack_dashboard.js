/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * MacTrack Dashboard adapter for the shared card chrome (js/cards.js). The
 * controller (window.CactiCards) owns drag/keyboard reorder and the per-card
 * tools; this adapter maps those to MacTrack's server endpoints
 * (mactrack_view_dashboard.php) and keeps the "Add card" catalogue in sync.
 * The card markup is rendered by mactrack_view_dashboard.php using the shared
 * .cc* contract.
 */

/** Hide the "Add" options for cards already on the grid; show the rest. */
function mactrackDashboardSyncAddOptions(grid) {
	var add = document.getElementById('mactrack_dashboard_add');

	if (!add) {
		return;
	}

	var present = {};

	grid.querySelectorAll('.ccCard').forEach(function(card) {
		if (card.dataset.card) {
			present[card.dataset.card] = true;
		}
	});

	for (var i = 1; i < add.options.length; i++) {
		add.options[i].hidden = !!present[add.options[i].value];
	}
}

/** Open a copy of a card's body in a large modal dialog. */
function mactrackDashboardMaximize(card) {
	var dialog = document.getElementById('mactrack_dashboard_dialog');

	if (!dialog) {
		return;
	}

	var title = card.querySelector('.ccTitle');
	var body  = card.querySelector('.ccCardBody');

	dialog.innerHTML = '<div class="ccDialogBody">' + (body ? body.innerHTML : '') + '</div>';

	$(dialog).dialog({
		modal: true,
		appendTo: 'body',
		width: Math.min(900, $(window).width() - 40),
		title: title ? title.textContent : ''
	});
}

/*
 * Persist the layout, serialized so a reorder started after an earlier one can
 * never be overwritten by that earlier request completing last. The dash_layout
 * endpoint replaces the entire saved layout, so write order must be preserved;
 * chaining each POST after the previous one settles keeps the newest layout
 * authoritative (the shared controller only debounces bursts within 250 ms).
 */
function mactrackDashboardSaveLayout(grid, state) {
	var expanded = {};

	state.order.forEach(function(id) {
		if (state.cards[id] && state.cards[id].expanded) {
			expanded[id] = true;
		}
	});

	var data = {
		action: 'dash_layout',
		layout: JSON.stringify({order: state.order, expanded: expanded}),
		__csrf_magic: csrfMagicToken
	};

	var post = function() {
		return $.post('mactrack_view_dashboard.php', data, null, 'json');
	};

	var pending = grid.__mtLayoutSave || $.Deferred().resolve().promise();

	/* Continue the chain whether the previous save resolved or rejected. */
	grid.__mtLayoutSave = pending.then(post, post);
}

/** Wire the MacTrack dashboard grid to the shared card chrome. */
function initMactrackDashboard() {
	$(function() {
		var grid = document.getElementById('mactrack_dashboard');

		if (!grid || !window.CactiCards) {
			return;
		}

		CactiCards.init(grid, {
			addControl: 'mactrack_dashboard_add',

			/* Refresh: re-fetch a single card's HTML and swap it in place. */
			fetchCard: function(id, state) {
				return $.post('mactrack_view_dashboard.php', {
					action: 'dash_card',
					card: id,
					expanded: state && state.expanded ? '1' : '0',
					__csrf_magic: csrfMagicToken
				}, null, 'json').then(function(data) {
					return data && data.html ? data.html : null;
				});
			},

			/* Add a card from the catalogue. */
			onAddCard: function(id) {
				return $.post('mactrack_view_dashboard.php', {
					action: 'dash_card',
					card: id,
					__csrf_magic: csrfMagicToken
				}, null, 'json').then(function(data) {
					return data && data.html ? data.html : null;
				});
			},

			/* Persist order + expanded state and resync the "Add" catalogue. */
			onLayoutChange: function(state) {
				mactrackDashboardSaveLayout(grid, state);
				mactrackDashboardSyncAddOptions(grid);
			},

			maximize: function(id, mode, card) {
				mactrackDashboardMaximize(card);
			}
		});

		mactrackDashboardSyncAddOptions(grid);
	});
}
