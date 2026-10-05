/* Corcovado Foundation Core — admin helpers (vanilla JS + wp.media). */
(function ($) {
	'use strict';
	var L = window.CF_ADMIN || {};

	function frame(title, type, onSelect) {
		var f = wp.media({ title: title, button: { text: L.use || 'Use' }, library: type ? { type: type } : {}, multiple: false });
		f.on('select', function () { onSelect(f.state().get('selection').first().toJSON()); });
		f.open();
	}

	$(document).on('click', '.cf-pick-image', function (e) {
		e.preventDefault();
		var box = $(this).closest('.cf-image');
		frame(L.chooseImage, 'image', function (a) {
			box.find('.cf-image-id').val(a.id);
			var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
			box.find('.cf-preview').attr('src', url).prop('hidden', false);
		});
	});

	$(document).on('click', '.cf-clear-image', function (e) {
		e.preventDefault();
		var box = $(this).closest('.cf-image');
		box.find('.cf-image-id').val('');
		box.find('.cf-preview').attr('src', '').prop('hidden', true);
	});

	$(document).on('click', '.cf-pick-file', function (e) {
		e.preventDefault();
		var input = $(this).closest('.cf-url').find('input');
		frame(L.chooseFile, null, function (a) { input.val('media:' + a.id); });
	});

	$(document).on('click', '.cf-up, .cf-down', function (e) {
		e.preventDefault();
		var item = $(this).closest('.cf-item');
		if ($(this).hasClass('cf-up')) { item.prev('.cf-item').before(item); } else { item.next('.cf-item').after(item); }
	});

	$(document).on('click', '.cf-del', function (e) {
		e.preventDefault();
		if (window.confirm(L.confirmDel || 'Remove?')) { $(this).closest('.cf-item').remove(); }
	});

	$(document).on('click', '.cf-dup', function (e) {
		e.preventDefault();
		var item = $(this).closest('.cf-item');
		var base = item.parent('.cf-list-items').data('base');
		var oldUid = item.attr('data-uid');
		var newUid = 'n' + Date.now().toString(36) + Math.floor(Math.random() * 1e6).toString(36);
		var from = base + '[' + oldUid + ']';
		var to = base + '[' + newUid + ']';
		var copy = item.clone();
		copy.attr('data-uid', newUid);
		copy.find('[name]').each(function () {
			var n = this.getAttribute('name');
			if (n.indexOf(from) === 0) { this.setAttribute('name', to + n.slice(from.length)); }
		});
		copy.find('[id]').each(function () { this.id = this.id + '-' + newUid; });
		copy.find('label[for]').each(function () { this.htmlFor = this.htmlFor + '-' + newUid; });
		item.after(copy);
	});

	// Translation picker on term screens / language selects need no JS.
})(jQuery);
