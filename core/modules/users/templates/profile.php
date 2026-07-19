<?php
/**
 * @var array<string, mixed> $user
 * @var ?string $rankName
 * @var string $csrfToken
 * @var bool $saved
 * @var ?string $deleteError
 */
?>
<h1>My Profile</h1>

<?php if ($saved): ?>
    <p style="color:#0a7d2c;">Saved.</p>
<?php endif; ?>

<?php if ($deleteError !== null): ?>
    <p style="color:#b00020;"><?= e($deleteError) ?></p>
<?php endif; ?>

<?php if (!empty($user['banner_url'])): ?>
    <div style="width:100%; max-height:180px; overflow:hidden; border-radius:8px; margin-bottom:0.75rem;">
        <img src="<?= e($user['banner_url']) ?>" alt="" style="width:100%; display:block;">
    </div>
<?php endif; ?>

<p><strong>Username:</strong> <?= e($user['username']) ?><br>
   <strong>Email:</strong> <?= e($user['email']) ?>
   <?php if ($rankName !== null): ?>
       <br><strong>Rank:</strong> <?= e($rankName) ?> &middot; <?= (int) ($user['points'] ?? 0) ?> points
   <?php endif; ?>
</p>

<p><a href="<?= e(route('/members/' . $user['username'])) ?>">View public profile</a> &middot; <a href="<?= e(route('/friends')) ?>">Friends</a></p>

<form method="post" action="<?= e(route('/profile')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="about_me">About me</label><br>
        <textarea id="about_me" name="about_me" rows="4" cols="50"><?= e($user['about_me'] ?? '') ?></textarea>
    </p>

    <p>
        <label for="avatar_url">Avatar URL</label><br>
        <input type="text" id="avatar_url" name="avatar_url" value="<?= e($user['avatar_url'] ?? '') ?>" style="width:100%;max-width:400px;">
    </p>

    <p>
        <label for="banner_url">Profile banner image URL</label><br>
        <input type="text" id="banner_url" name="banner_url" value="<?= e($user['banner_url'] ?? '') ?>" style="width:100%;max-width:400px;">
        <br><small style="color:#666;">A wide header image shown at the top of your profile — distinct from your avatar.</small>
    </p>

    <p>
        <label for="signature">Forum signature</label><br>
        <textarea id="signature" name="signature" rows="2" cols="50" maxlength="500"><?= e($user['signature'] ?? '') ?></textarea><br>
        <small style="color:#666;">Shown under your forum posts. Supports [b] [i] [u] [url] [quote] [code]</small>
    </p>

    <button type="submit">Save</button>
</form>

<hr>

<h2>Your data</h2>
<p><a href="<?= e(route('/profile/export')) ?>">Download a copy of your data</a> — your account fields plus a list of content you've created.</p>

<details>
    <summary style="cursor:pointer; color:#b00020;">Delete my account</summary>
    <p style="color:#666; font-size:0.9rem; max-width:500px;">
        This deactivates your account — you'll be logged out and unable to log back in.
        Content you've posted stays as-is, it isn't deleted with your account.
    </p>
    <form method="post" action="<?= e(route('/profile/delete')) ?>" onsubmit="return confirm('Are you sure you want to delete your account? This cannot be undone by you.');">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <label for="delete_password">Confirm your password</label><br>
            <input type="password" id="delete_password" name="password" required>
        </p>
        <button type="submit">Delete my account</button>
    </form>
</details>
