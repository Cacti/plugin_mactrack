/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * MacTrack Dashboard card controller. Wires the card grid on the Dashboard tab:
 * drag/keyboard reordering, the per-card tools (show more/less, maximize,
 * refresh, remove) and the "Add card" catalogue. Layout changes persist
 * server-side (settings_user) so they survive page revisits. The card markup
 * is rendered by mactrack_view_dashboard.php; this file only drives behaviour.
 */

var mactrackDashboardDragging = null;

function initMactrackDashboard() {
	$(function() {
		var grid = document.getElementById('mactrack_dashboard');

		if (!grid) {
			return;
		}

		grid.querySelectorAll('.mtdashCard').forEach(function(card) {
			mactrackDashboardBindCard(grid, card);
		});

		grid.addEventListener('dragover', function(event) {
			if (!mactrackDashboardDragging) {
				return;
			}

			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';

			var before = mactrackDashboardDragReference(grid, event.clientX, event.clientY);

			if (before == null) {
				grid.appendChild(mactrackDashboardDragging);
			} else if (before !== mactrackDashboardDragging) {
				grid.insertBefore(mactrackDashboardDragging, before);
			}
		});

		// Per-card tool buttons bubble to the grid.
		$(grid).off('click.mtdash').on('click.mtdash', '.mtdashCardTool', function() {
			var card = this.closest('.mtdashCard');

			if (!card) {
				return;
			}

			switch (this.dataset.tool) {
				case 'expand':   card.classList.add('mtdashCardExpanded'); mactrackDashboardSaveLayout(grid); break;
				case 'collapse': card.classList.remove('mtdashCardExpanded'); mactrackDashboardSaveLayout(grid); break;
				case 'maximize': mactrackDashboardMaximize(card); break;
				case 'refresh':  mactrackDashboardRefreshCard(grid, card); break;
				case 'remove':   mactrackDashboardRemoveCard(grid, card); break;
			}
		});

		var add = document.getElementById('mactrack_dashboard_add');

		if (add) {
			$(add).off('change.mtdash').on('change.mtdash', function() {
				var key = this.value;
				this.value = '';

				if (key) {
					mactrackDashboardAddCard(grid, key);
				}
			});
		}
	});
}

/** Wire a single card's drag handle (mouse + keyboard) and drag events. */
function mactrackDashboardBindCard(grid, card) {
	var handle = card.querySelector('.mtdashCardDrag');

	if (!handle) {
		return;
	}

	// Arm HTML5 drag only from the handle, and disarm on release when no drag
	// began (a plain click/tap) so selecting content elsewhere can't drag the card.
	var disarm = function() { card.draggable = false; };

	handle.addEventListener('mousedown', function() {
		card.draggable = true;
		document.addEventListener('mouseup', disarm, {once: true});
	});

	handle.addEventListener('touchstart', function() {
		card.draggable = true;
		document.addEventListener('touchend', disarm, {once: true});
		document.addEventListener('touchcancel', disarm, {once: true});
	}, {passive: true});

	// Keyboard-accessible reordering: move the card with the arrow keys.
	handle.addEventListener('keydown', function(event) {
		var back = event.key === 'ArrowLeft' || event.key === 'ArrowUp';
		var fwd  = event.key === 'ArrowRight' || event.key === 'ArrowDown';

		if (!back && !fwd) {
			return;
		}

		event.preventDefault();

		if (back && card.previousElementSibling) {
			grid.insertBefore(card, card.previousElementSibling);
		} else if (fwd && card.nextElementSibling) {
			grid.insertBefore(card.nextElementSibling, card);
		} else {
			return;
		}

		handle.focus();
		mactrackDashboardSaveLayout(grid);
	});

	card.addEventListener('dragstart', function(event) {
		mactrackDashboardDragging = card;
		card.classList.add('mtdashCardDragging');
		event.dataTransfer.effectAllowed = 'move';
		try { event.dataTransfer.setData('text/plain', card.dataset.card || ''); } catch (error) { /* IE guard */ }
	});

	card.addEventListener('dragend', function() {
		card.draggable = false;
		card.classList.remove('mtdashCardDragging');

		if (mactrackDashboardDragging) {
			mactrackDashboardDragging = null;
			mactrackDashboardSaveLayout(grid);
		}
	});
}

/** The card the dragged card should be inserted before for the current pointer
 *  position, or null to append at the end. */
function mactrackDashboardDragReference(grid, x, y) {
	var cards = Array.prototype.slice.call(grid.querySelectorAll('.mtdashCard:not(.mtdashCardDragging)'));

	for (var i = 0; i < cards.length; i++) {
		var rect = cards[i].getBoundingClientRect();

		if (y < rect.top - 1) {
			return cards[i];
		}

		if (y <= rect.bottom && x < rect.left + rect.width / 2) {
			return cards[i];
		}
	}

	return null;
}

/** Add a card from the catalogue: fetch its HTML, append it, persist and
 *  refresh the "Add" options. */
function mactrackDashboardAddCard(grid, key) {
	if (mactrackDashboardHasCard(grid, key)) {
		return;
	}

	$.post('mactrack_view_dashboard.php', {action: 'dash_card', card: key, __csrf_magic: csrfMagicToken}, null, 'json').done(function(data) {
		if (!data || !data.html || mactrackDashboardHasCard(grid, key)) {
			return;
		}

		var card = mactrackDashboardParseCard(data.html);

		if (!card) {
			return;
		}

		grid.appendChild(card);
		mactrackDashboardBindCard(grid, card);
		mactrackDashboardSaveLayout(grid);
		mactrackDashboardSyncAddOptions(grid);
	});
}

/** Whether a card with the given key is currently on the grid. */
function mactrackDashboardHasCard(grid, key) {
	var present = false;

	grid.querySelectorAll('.mtdashCard').forEach(function(card) {
		if (card.dataset.card === key) {
			present = true;
		}
	});

	return present;
}

/** Remove a card from the page and return it to the "Add" catalogue. */
function mactrackDashboardRemoveCard(grid, card) {
	card.parentNode.removeChild(card);
	mactrackDashboardSaveLayout(grid);
	mactrackDashboardSyncAddOptions(grid);
}

/** Re-fetch a single card's HTML and swap it in place, keeping expanded state. */
function mactrackDashboardRefreshCard(grid, card, done) {
	var icon = card.querySelector('.mtdashCardTool[data-tool="refresh"] .fa');

	if (icon) {
		icon.classList.add('fa-spin');
	}

	var settle = function() {
		if (icon) {
			icon.classList.remove('fa-spin');
		}

		if (done) {
			done();
		}
	};

	var expanded = card.classList.contains('mtdashCardExpanded') ? '1' : '0';

	$.post('mactrack_view_dashboard.php', {action: 'dash_card', card: card.dataset.card, expanded: expanded, __csrf_magic: csrfMagicToken}, null, 'json').done(function(data) {
		if (!data || !data.html || !card.parentNode) {
			settle();
			return;
		}

		var fresh = mactrackDashboardParseCard(data.html);

		if (!fresh) {
			settle();
			return;
		}

		card.parentNode.replaceChild(fresh, card);
		mactrackDashboardBindCard(grid, fresh);

		if (done) {
			done();
		}
	}).fail(settle);
}

/** Open a copy of a card's body in a large modal dialog. */
function mactrackDashboardMaximize(card) {
	var dialog = document.getElementById('mactrack_dashboard_dialog');

	if (!dialog) {
		return;
	}

	var title = card.querySelector('.mtdashCardTitle');
	var body  = card.querySelector('.mtdashCardBody');

	dialog.innerHTML = '<div class="mtdashDialogBody">' + (body ? body.innerHTML : '') + '</div>';

	$(dialog).dialog({
		modal: true,
		appendTo: 'body',
		width: Math.min(900, $(window).width() - 40),
		title: title ? title.textContent : ''
	});
}

/** Parse a card HTML string into its <section> element. */
function mactrackDashboardParseCard(html) {
	var tmp = document.createElement('div');
	tmp.innerHTML = html;

	return tmp.querySelector('.mtdashCard');
}

/** Rebuild the "Add" dropdown so it lists only cards not currently on the page. */
function mactrackDashboardSyncAddOptions(grid) {
	var add = document.getElementById('mactrack_dashboard_add');

	if (!add) {
		return;
	}

	var present = {};

	grid.querySelectorAll('.mtdashCard').forEach(function(card) {
		if (card.dataset.card) {
			present[card.dataset.card] = true;
		}
	});

	// Options for present cards are removed; absent ones are kept/restored by
	// leaving the server-rendered option list and only hiding present ones.
	for (var i = 1; i < add.options.length; i++) {
		add.options[i].hidden = !!present[add.options[i].value];
	}
}

/** Serialize layout POSTs so rapid actions can't persist out of order. */
var mactrackDashboardSaveInFlight = false;
var mactrackDashboardSaveQueued = false;

function mactrackDashboardSaveLayout(grid) {
	if (mactrackDashboardSaveInFlight) {
		mactrackDashboardSaveQueued = true;
		return;
	}

	mactrackDashboardSaveInFlight = true;

	var order = [];
	var expanded = {};

	grid.querySelectorAll('.mtdashCard').forEach(function(card) {
		if (!card.dataset.card) {
			return;
		}

		order.push(card.dataset.card);

		if (card.classList.contains('mtdashCardExpanded')) {
			expanded[card.dataset.card] = true;
		}
	});

	$.post('mactrack_view_dashboard.php', {
		action: 'dash_layout',
		layout: JSON.stringify({order: order, expanded: expanded}),
		__csrf_magic: csrfMagicToken
	}, null, 'json').always(function() {
		mactrackDashboardSaveInFlight = false;

		if (mactrackDashboardSaveQueued) {
			mactrackDashboardSaveQueued = false;
			mactrackDashboardSaveLayout(grid);
		}
	});
}
