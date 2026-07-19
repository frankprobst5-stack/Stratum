<?php

declare(strict_types=1);

namespace Stratum\Modules\Forum;

use Stratum\Core\App;
use Stratum\Core\BBCodeParser;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\ReputationService;
use Stratum\Core\SeoService;
use Stratum\Modules\Bookmarks\BookmarkService;
use Stratum\Modules\Tags\TagService;
use Stratum\Modules\Users\AuthService;

final class ForumController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $forum = new ForumService($this->app->db);

        // Nested once over the full flat list, then filtered per category —
        // a sub-board always renders under its parent, wherever the parent's
        // own category lands it, rather than needing every sub-board to be
        // created under the same category_id as its parent to display right.
        $boardTree = $forum->nestBoards($forum->listBoards());
        $categories = [];
        foreach ($forum->listCategories() as $category) {
            $category['boards'] = array_values(array_filter(
                $boardTree,
                static fn (array $b): bool => (int) $b['category_id'] === $category['id']
            ));
            $categories[] = $category;
        }

        $content = $this->app->templates->render('forum', 'index', ['categories' => $categories]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function board(Request $request): Response
    {
        $forum = new ForumService($this->app->db);
        $board = $forum->findBoardBySlug((string) $request->param('slug', ''));

        if ($board === null) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $topics = array_map(
            fn (array $t): array => $t + ['authorName' => $this->authorName($authors, (int) $t['author_id'])],
            $forum->listTopicsForBoard((int) $board['id'])
        );

        $subBoards = array_values(array_filter(
            $forum->listBoards(),
            static fn (array $b): bool => $b['parent_id'] !== null && (int) $b['parent_id'] === (int) $board['id']
        ));

        $content = $this->app->templates->render('forum', 'board', [
            'board' => $board,
            'subBoards' => $subBoards,
            'topics' => $topics,
            'canCreateTopic' => $this->app->auth->can('forum.create_topic'),
            'showTags' => $this->app->modules->isEnabled('tags'),
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function topic(Request $request): Response
    {
        $forum = new ForumService($this->app->db);
        $topic = $forum->findTopic((int) $request->param('id', '0'));

        if ($topic === null) {
            return Response::notFound();
        }

        $board = $forum->findBoard((int) $topic['board_id']);
        $authors = new AuthService($this->app->db);
        $attachments = new AttachmentService($this->app->db, $this->attachmentStorageDir());
        $bbcode = new BBCodeParser();

        $rawPosts = $forum->listPostsForTopic((int) $topic['id']);
        $postIds = array_map(static fn (array $p): int => (int) $p['id'], $rawPosts);
        $likeCounts = $forum->likeCountsForPosts($postIds);
        $currentUser = $this->app->auth->user();
        $likedByMe = $currentUser !== null
            ? $forum->likedPostIds((int) $currentUser['id'], $postIds)
            : [];

        $authorCache = [];
        $posts = array_map(function (array $post) use ($authors, $attachments, $bbcode, $likeCounts, $likedByMe, &$authorCache): array {
            $authorId = (int) $post['author_id'];
            if (!array_key_exists($authorId, $authorCache)) {
                $authorCache[$authorId] = $authors->findById($authorId);
            }
            $author = $authorCache[$authorId];

            return $post + [
                'authorName' => $author['username'] ?? 'Unknown',
                'renderedBody' => $bbcode->render($post['body']),
                // Signatures are member-authored content, so they go through
                // the same escape-then-rewrite BBCode path as post bodies.
                'authorSignature' => ($author['signature'] ?? '') !== '' ? $bbcode->render($author['signature']) : '',
                'attachments' => $attachments->listForPost((int) $post['id']),
                'likeCount' => $likeCounts[(int) $post['id']] ?? 0,
                'likedByMe' => in_array((int) $post['id'], $likedByMe, true),
            ];
        }, $rawPosts);

        $showBookmark = $this->app->modules->isEnabled('bookmarks') && $currentUser !== null;
        $isBookmarked = $showBookmark
            && (new BookmarkService($this->app->db))->isBookmarked('forum_topic', (int) $topic['id'], (int) $currentUser['id']);

        $poll = $forum->findPollForTopic((int) $topic['id']);
        $myPollVote = $poll !== null && $currentUser !== null
            ? $forum->myPollVote($poll['id'], (int) $currentUser['id'])
            : null;

        $tags = $this->app->modules->isEnabled('tags')
            ? (new TagService($this->app->db))->tagsFor('forum_topic', (int) $topic['id'])
            : [];

        $content = $this->app->templates->render('forum', 'topic', [
            'topic' => $topic,
            'board' => $board,
            'posts' => $posts,
            'poll' => $poll,
            'myPollVote' => $myPollVote,
            'tags' => $tags,
            'canReply' => $this->app->auth->can('forum.reply'),
            'canModerate' => $this->app->auth->can('forum.moderate', 'forum_board', (int) $topic['board_id']),
            // isEnabled gate, not a `requires` edge — with moderation off the
            // /reports routes don't exist, so the link must vanish with them
            // (the capability grant itself survives module toggles).
            'canReport' => $this->app->modules->isEnabled('moderation') && $this->app->auth->can('moderation.report'),
            'showBookmark' => $showBookmark,
            'isBookmarked' => $isBookmarked,
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        $seo = [
            'title' => $topic['title'],
            'description' => (new SeoService())->excerpt((string) ($rawPosts[0]['body'] ?? '')),
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }

    public function createTopic(Request $request): Response
    {
        if (($guard = $this->requireCapability('forum.create_topic')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $forum = new ForumService($this->app->db);
        $board = $forum->findBoardBySlug((string) $request->param('slug', ''));
        if ($board === null) {
            return Response::notFound();
        }

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        if ($title === '' || $body === '') {
            return Response::redirect('/forum/boards/' . $board['slug']);
        }

        $user = $this->app->auth->user();
        $ids = $forum->createTopicWithFirstPost((int) $board['id'], (int) $user['id'], $title, $body);

        $this->attachUploadIfPresent($request, $ids['postId']);
        $this->notifyMentions($body, $user, $title, $ids['topicId']);
        $this->createPollIfRequested($request, $forum, $ids['topicId']);
        (new ReputationService($this->app))->award((int) $user['id'], 2);

        if ($this->app->modules->isEnabled('tags')) {
            (new TagService($this->app->db))->setTags('forum_topic', $ids['topicId'], (string) $request->input('tags', ''));
        }

        return Response::redirect('/forum/topics/' . $ids['topicId']);
    }

    /**
     * Poll creation is bundled into the new-topic form, not a standalone
     * screen — a poll question is optional; when blank, everything below
     * it is silently ignored rather than erroring, since a form with a
     * pile of empty option fields left over from indecision is a normal,
     * expected submission, not a mistake. Up to 6 fixed option inputs
     * (poll_option_1..6) rather than a dynamic add/remove-option list —
     * this app's forms are server-rendered with minimal JS everywhere
     * else (the BBCode toolbar is the one real exception), and 6 is
     * already more than most club poll questions need.
     */
    private function createPollIfRequested(Request $request, ForumService $forum, int $topicId): void
    {
        $question = trim((string) $request->input('poll_question', ''));
        if ($question === '') {
            return;
        }

        $options = [];
        for ($i = 1; $i <= 6; $i++) {
            $label = trim((string) $request->input("poll_option_{$i}", ''));
            if ($label !== '') {
                $options[] = $label;
            }
        }

        if (count($options) < 2) {
            return;
        }

        $closesAt = trim((string) $request->input('poll_closes_at', ''));
        $forum->createPoll($topicId, $question, $options, $closesAt !== '' ? $closesAt : null);
    }

    public function reply(Request $request): Response
    {
        if (($guard = $this->requireCapability('forum.reply')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $forum = new ForumService($this->app->db);
        $topicId = (int) $request->param('id', '0');
        $topic = $forum->findTopic($topicId);
        if ($topic === null) {
            return Response::notFound();
        }

        if ((bool) $topic['is_locked'] && !$this->app->auth->can('forum.moderate', 'forum_board', (int) $topic['board_id'])) {
            return Response::html('This topic is locked.', 403);
        }

        $body = trim((string) $request->input('body', ''));
        if ($body !== '') {
            $user = $this->app->auth->user();
            $postId = $forum->reply($topicId, (int) $user['id'], $body);
            $this->attachUploadIfPresent($request, $postId);

            // Self-replies are skipped inside the notification listener.
            $this->app->notify([
                'user_id' => (int) $topic['author_id'],
                'actor_id' => (int) $user['id'],
                'type' => 'forum.reply',
                'message' => $user['username'] . ' replied to your topic "' . $topic['title'] . '"',
                'url' => '/forum/topics/' . $topicId,
            ]);

            // The topic author is excluded here — they just got the reply
            // notification above, and being @-mentioned in a reply to your
            // own topic shouldn't produce two rows for one post.
            $this->notifyMentions($body, $user, $topic['title'], $topicId, (int) $topic['author_id']);
            (new ReputationService($this->app))->award((int) $user['id'], 1);
        }

        return Response::redirect('/forum/topics/' . $topicId);
    }

    /** Any logged-in user can vote — no capability, same as post likes. */
    public function votePoll(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $forum = new ForumService($this->app->db);
        $topicId = (int) $request->param('id', '0');
        $topic = $forum->findTopic($topicId);
        if ($topic === null) {
            return Response::notFound();
        }

        $poll = $forum->findPollForTopic($topicId);
        $optionId = (int) $request->input('option_id', '0');
        $validOption = $poll !== null && !$poll['isClosed']
            && in_array($optionId, array_column($poll['options'], 'id'), true);

        if ($validOption) {
            $user = $this->app->auth->user();
            $forum->vote($poll['id'], $optionId, (int) $user['id']);
        }

        return Response::redirect('/forum/topics/' . $topicId);
    }

    /** Any logged-in user can like — no capability, same as gallery's photo likes. */
    public function toggleLike(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $forum = new ForumService($this->app->db);
        $post = $forum->findPost((int) $request->param('id', '0'));
        if ($post === null || $forum->findTopic((int) $post['topic_id']) === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $liked = $forum->toggleLike((int) $post['id'], (int) $user['id']);

        // Only a genuine new like awards a point, never a removal — and
        // never to yourself, so liking your own post can't be used to
        // farm reputation.
        if ($liked && (int) $post['author_id'] !== (int) $user['id']) {
            (new ReputationService($this->app))->award((int) $post['author_id'], 1);
        }

        return Response::redirect('/forum/topics/' . $post['topic_id']);
    }

    public function pin(Request $request): Response
    {
        return $this->moderateTopic($request, fn (ForumService $f, int $id) => $f->setPinned($id, true));
    }

    public function unpin(Request $request): Response
    {
        return $this->moderateTopic($request, fn (ForumService $f, int $id) => $f->setPinned($id, false));
    }

    public function lock(Request $request): Response
    {
        return $this->moderateTopic($request, fn (ForumService $f, int $id) => $f->setLocked($id, true));
    }

    public function unlock(Request $request): Response
    {
        return $this->moderateTopic($request, fn (ForumService $f, int $id) => $f->setLocked($id, false));
    }

    public function deleteTopic(Request $request): Response
    {
        $forum = new ForumService($this->app->db);
        $topic = $forum->findTopic((int) $request->param('id', '0'));
        if ($topic === null) {
            return Response::notFound();
        }

        if (($guard = $this->requireCapability('forum.moderate', 'forum_board', (int) $topic['board_id'])) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $board = $forum->findBoard((int) $topic['board_id']);
        $forum->softDeleteTopic((int) $topic['id']);

        return Response::redirect('/forum/boards/' . ($board['slug'] ?? ''));
    }

    public function deletePost(Request $request): Response
    {
        $forum = new ForumService($this->app->db);
        $post = $forum->findPost((int) $request->param('id', '0'));
        if ($post === null) {
            return Response::notFound();
        }

        $topic = $forum->findTopic((int) $post['topic_id']);
        $boardId = $topic !== null ? (int) $topic['board_id'] : null;

        if (($guard = $this->requireCapability('forum.moderate', 'forum_board', $boardId)) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $forum->softDeletePost((int) $post['id']);

        return Response::redirect('/forum/topics/' . $post['topic_id']);
    }

    public function downloadAttachment(Request $request): Response
    {
        $attachments = new AttachmentService($this->app->db, $this->attachmentStorageDir());
        $attachment = $attachments->find((int) $request->param('id', '0'));

        if ($attachment === null) {
            return Response::notFound();
        }

        $path = $attachments->absolutePath($attachment);
        if (!is_file($path)) {
            return Response::notFound();
        }

        return Response::file((string) file_get_contents($path), $attachment['mime_type'], $attachment['original_name']);
    }

    /** @param callable(ForumService, int): void $action */
    private function moderateTopic(Request $request, callable $action): Response
    {
        $forum = new ForumService($this->app->db);
        $topicId = (int) $request->param('id', '0');
        $topic = $forum->findTopic($topicId);
        if ($topic === null) {
            return Response::notFound();
        }

        if (($guard = $this->requireCapability('forum.moderate', 'forum_board', (int) $topic['board_id'])) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $action($forum, $topicId);

        return Response::redirect('/forum/topics/' . $topicId);
    }

    private function attachUploadIfPresent(Request $request, int $postId): void
    {
        $file = $request->file('attachment');
        if ($file === null) {
            return;
        }

        $attachments = new AttachmentService($this->app->db, $this->attachmentStorageDir());
        $validated = $attachments->validate($file);
        if ($validated !== null) {
            $attachments->store($validated, $postId);
        }
    }

    private function requireCapability(string $capability, ?string $scopeType = null, ?int $scopeId = null): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can($capability, $scopeType, $scopeId)) {
            return Response::forbidden();
        }

        return null;
    }

    private function authorName(AuthService $authors, int $userId): string
    {
        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }

    /**
     * Notifies users @-mentioned in a post body. Self-mentions are dropped
     * by the notification listener's recipient === actor rule; $excludeUserId
     * lets reply() skip the topic author, who already got a reply
     * notification for the same post.
     *
     * @param array<string, mixed> $actor the posting user's row
     */
    private function notifyMentions(string $body, array $actor, string $topicTitle, int $topicId, ?int $excludeUserId = null): void
    {
        $mentions = new MentionService(new AuthService($this->app->db));

        foreach ($mentions->extractMentionedUsers($body) as $mentioned) {
            if ($excludeUserId !== null && (int) $mentioned['id'] === $excludeUserId) {
                continue;
            }

            $this->app->notify([
                'user_id' => (int) $mentioned['id'],
                'actor_id' => (int) $actor['id'],
                'type' => 'forum.mention',
                'message' => $actor['username'] . ' mentioned you in "' . $topicTitle . '"',
                'url' => '/forum/topics/' . $topicId,
            ]);
        }
    }

    private function attachmentStorageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/forum';
    }
}
