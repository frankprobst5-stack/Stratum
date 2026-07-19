<?php
/**
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/admin/forms')) ?>">&larr; Surveys & Forms</a></p>
<h1>New Form</h1>

<form method="post" action="<?= e(route('/admin/forms/create')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" required>
    </p>

    <p>
        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="3" cols="50"></textarea>
    </p>

    <button type="submit">Create</button>
</form>
