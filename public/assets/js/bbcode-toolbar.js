/**
 * Lightweight, dependency-free BBCode helper toolbar. Finds every
 * `textarea[data-bbcode-toolbar]` on the page and inserts a row of buttons
 * above it that wrap the current selection in the matching bracket tags.
 * Output is still plain BBCode text — nothing here changes how content is
 * stored or rendered, it's purely an editing convenience. See
 * docs/coding-standards.md.
 */
(function () {
    var BUTTONS = [
        { label: 'B', open: '[b]', close: '[/b]' },
        { label: 'I', open: '[i]', close: '[/i]' },
        { label: 'U', open: '[u]', close: '[/u]' },
        { label: 'Quote', open: '[quote]', close: '[/quote]' },
        { label: 'Code', open: '[code]', close: '[/code]' },
        { label: 'Link', open: '[url=https://]', close: '[/url]' }
    ];

    function wrapSelection(textarea, open, close) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var selected = textarea.value.slice(start, end);
        var replacement = open + selected + close;

        if (typeof textarea.setRangeText === 'function') {
            textarea.setRangeText(replacement, start, end, 'end');
        } else {
            textarea.value = textarea.value.slice(0, start) + replacement + textarea.value.slice(end);
        }

        textarea.focus();
    }

    function buildToolbar(textarea) {
        var toolbar = document.createElement('div');
        toolbar.className = 'bbcode-toolbar';

        BUTTONS.forEach(function (button) {
            var el = document.createElement('button');
            el.type = 'button';
            el.textContent = button.label;
            el.addEventListener('click', function () {
                wrapSelection(textarea, button.open, button.close);
            });
            toolbar.appendChild(el);
        });

        textarea.parentNode.insertBefore(toolbar, textarea);
    }

    document.querySelectorAll('textarea[data-bbcode-toolbar]').forEach(buildToolbar);
})();
