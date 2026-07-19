<?php
/** @var array<string, mixed> $page */
?>
<article>
    <h1><?= e($page['title']) ?></h1>
    <div><?= raw($page['body']) ?></div>
</article>
