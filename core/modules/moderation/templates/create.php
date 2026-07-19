<?php
/**
 * @var string $type
 * @var int $id
 * @var array{title: string, url: string} $target
 * @var string $csrfToken
 */
?>
<h1>Report content</h1>

<p>You are reporting: <a href="<?= e($target['url']) ?>"><?= e($target['title']) ?></a></p>

<form method="post" action="<?= e(route('/reports')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="reportable_type" value="<?= e($type) ?>">
    <input type="hidden" name="reportable_id" value="<?= (int) $id ?>">
    <p>
        <label for="reason">Why are you reporting this?</label><br>
        <textarea id="reason" name="reason" rows="4" cols="60" maxlength="500" required></textarea><br>
        <small style="color:#666;">A moderator will review your report. You'll be notified when it's handled.</small>
    </p>
    <button type="submit">Submit report</button>
    <a href="<?= e($target['url']) ?>" style="margin-left:0.75rem;">Cancel</a>
</form>
