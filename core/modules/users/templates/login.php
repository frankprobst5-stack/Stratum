<?php
/**
 * @var string|null $error
 * @var string $csrfToken
 */
?>
<h1><?= e(t('login.title')) ?></h1>

<?php if ($error !== null): ?>
    <p style="color:#b00020;"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(route('/login')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="login"><?= e(t('login.field_login')) ?></label><br>
        <input type="text" id="login" name="login" required autofocus>
    </p>

    <p>
        <label for="password"><?= e(t('login.field_password')) ?></label><br>
        <input type="password" id="password" name="password" required>
    </p>

    <button type="submit"><?= e(t('login.submit')) ?></button>
</form>
