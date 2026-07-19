<?php
/**
 * @var array<string, mixed> $room
 * @var string $messagesHtml pre-rendered by the controller (a template can't call back into TemplateEngine::render() itself — see ChatController::room())
 * @var int $lastMessageId
 * @var array<int, array<string, mixed>> $members
 * @var string $csrfToken
 */
$roomId = (int) $room['id'];
?>
<h1><?= e($room['name']) ?></h1>
<?php if (($room['topic'] ?? null) !== null): ?>
    <p style="color:#666;"><?= e($room['topic']) ?></p>
<?php endif; ?>
<p style="color:#999; font-size:0.85rem;">
    <?= $room['visibility'] === 'private' ? 'Private room' : 'Public room' ?>
    &middot; <?= count($members) ?> member<?= count($members) === 1 ? '' : 's' ?>
</p>

<div id="strat-chat-messages" style="border:1px solid #eee; border-radius:6px; padding:0.75rem; height:22rem; overflow-y:auto; margin-bottom:0.75rem; background:var(--strat-card-bg, #fff);">
    <?= $messagesHtml ?>
</div>

<form id="strat-chat-form" style="display:flex; gap:0.5rem; margin-bottom:1rem;">
    <input type="text" id="strat-chat-input" placeholder="Type a message, or /me does an action..." style="flex:1;" autocomplete="off">
    <button type="submit">Send</button>
</form>

<form method="post" action="<?= e(route('/chat/rooms/' . $roomId . '/leave')) ?>" style="display:inline;">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <button type="submit">Leave room</button>
</form>

<?php if ($room['visibility'] === 'public'): ?>
    <form method="post" action="<?= e(route('/chat/rooms/' . $roomId . '/invite')) ?>" style="display:inline; margin-left:0.5rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="text" name="username" placeholder="Invite by username" required style="width:12rem;">
        <button type="submit">Invite</button>
    </form>
<?php endif; ?>

<?php if ($members !== []): ?>
    <h2>Members</h2>
    <ul>
        <?php foreach ($members as $member): ?>
            <li><?= e($member['username']) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<script>
(function () {
    var roomId = <?= (int) $roomId ?>;
    var csrfToken = <?= json_encode($csrfToken) ?>;
    var lastId = <?= (int) $lastMessageId ?>;
    var messagesEl = document.getElementById('strat-chat-messages');
    var form = document.getElementById('strat-chat-form');
    var input = document.getElementById('strat-chat-input');

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }
    scrollToBottom();

    function appendHtml(html) {
        if (!html) {
            return;
        }
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        while (wrapper.firstChild) {
            messagesEl.appendChild(wrapper.firstChild);
        }
        scrollToBottom();
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = input.value.trim();
        if (!body) {
            return;
        }
        fetch('<?= e(route('/chat/rooms/' . $roomId . '/messages')) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _csrf: csrfToken, body: body })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.html) {
                    appendHtml(data.html);
                    lastId = data.lastId;
                    input.value = '';
                }
            });
    });

    setInterval(function () {
        fetch('<?= e(route('/chat/rooms/' . $roomId . '/messages')) ?>?after=' + lastId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.html) {
                    appendHtml(data.html);
                }
                if (data.lastId) {
                    lastId = data.lastId;
                }
            });
    }, 4000);
})();
</script>
