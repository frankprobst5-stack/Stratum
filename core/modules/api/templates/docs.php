<?php
/**
 * Lives at core/modules/api/templates/ (not core/api/templates/, where
 * the actual controllers live) purely because TemplateEngine::resolve()
 * only special-cases 'admin' outside of core/modules/{id}/templates/ —
 * "api" isn't a real ModuleManager-registered module (no module.json,
 * never discovered/booted), this directory exists solely so this one
 * page resolves through the existing lookup unchanged. See
 * core/api/routes.php.
 *
 * @var string $baseUrl
 */
?>
<h1>API Documentation</h1>

<p class="strat-muted">
    Built in slices, proven against real resources before growing further
    — not exhaustive coverage of every module yet. More endpoints follow
    the same pattern established here.
</p>

<h2>Authentication</h2>
<p>
    Create a personal API token from your <a href="<?= e(route('/profile')) ?>">profile page</a>,
    then send it as a Bearer token on every request:
</p>
<pre style="background:var(--strat-card-bg); border:1px solid var(--strat-card-border); border-radius:var(--strat-radius-sm); padding:0.75rem; overflow-x:auto;">curl -H "Authorization: Bearer strat_&lt;your token&gt;" <?= e($baseUrl) ?>/api/v1/forum/boards</pre>
<p class="strat-muted">Read endpoints below are public and need no token at all — the same content anyone can already see on the site. Only the reply endpoint requires one.</p>

<h2>Response shape</h2>
<p>Success: <code>{"data": ...}</code> — list endpoints add <code>"meta": {"page", "per_page", "total"}</code>.</p>
<p>Errors: <code>{"error": {"message", "code"}}</code> with a matching HTTP status (401 unauthenticated, 403 forbidden, 404 not found, 422 validation, 429 reserved).</p>

<h2>Endpoints</h2>
<div class="strat-list">
    <?php
    $endpoints = [
        ['GET', '/api/v1/articles', 'List published articles (paginated)'],
        ['GET', '/api/v1/articles/{slug}', 'One article'],
        ['GET', '/api/v1/forum/boards', 'Board list'],
        ['GET', '/api/v1/forum/boards/{slug}/topics', 'Topic list for a board (paginated)'],
        ['GET', '/api/v1/forum/topics/{id}', 'One topic and its posts'],
        ['POST', '/api/v1/forum/topics/{id}/reply', 'Post a reply — requires a Bearer token'],
        ['GET', '/api/v1/calendar/events', 'Upcoming events (paginated)'],
        ['GET', '/api/v1/calendar/events/{id}', 'One event'],
        ['GET', '/api/v1/wiki', 'List wiki pages (paginated)'],
        ['GET', '/api/v1/wiki/{slug}', 'One wiki page, with its current body'],
        ['GET', '/api/v1/downloads', 'List downloadable files (paginated)'],
        ['GET', '/api/v1/downloads/{id}', 'One file, with its current version and mirrors'],
        ['GET', '/api/v1/gallery/albums', 'List gallery albums (paginated)'],
        ['GET', '/api/v1/gallery/albums/{id}/photos', 'One album and its photos'],
    ];
    ?>
    <?php foreach ($endpoints as [$method, $path, $desc]): ?>
        <div class="strat-list-row">
            <span class="strat-pill" data-tone="<?= $method === 'GET' ? 'neutral' : 'accent' ?>"><?= e($method) ?></span>
            <div class="strat-list-row-main">
                <div class="strat-list-row-title"><code><?= e($path) ?></code></div>
                <div class="strat-list-row-meta"><?= e($desc) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
