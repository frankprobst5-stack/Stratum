<?php

declare(strict_types=1);

namespace Stratum\Modules\Articles;

use Stratum\Core\ConfigurableBlock;

/**
 * The one parameterized "front page" block: same query
 * (ArticleService::listPublishedByCategory()), rendered as either a
 * rotating hero slider or a static scrollable list depending on
 * `config['display']` — deliberately one block class covering both the
 * hero and the compact side placements rather than two near-identical
 * ones, per the Stage 8 block-library design (docs/roadmap.md).
 */
final class LatestContentBlock implements ConfigurableBlock
{
    private static int $instanceCounter = 0;

    public function __construct(private readonly ArticleService $articles)
    {
    }

    /**
     * category_id's options are built from real, current categories —
     * this is the concrete case `ConfigurableBlock`'s docblock calls out:
     * dynamic options via the block's own already-injected service, not
     * a separate "dynamic vs static" mechanism.
     *
     * @return array<int, array<string, mixed>>
     */
    public function configFields(): array
    {
        $categoryOptions = ['' => '— any category —'];
        foreach ($this->articles->listCategories() as $category) {
            $categoryOptions[(string) $category['id']] = $category['name'];
        }

        return [
            ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'options' => $categoryOptions, 'default' => ''],
            [
                'name' => 'display', 'label' => 'Display style', 'type' => 'select',
                'options' => ['hero_slider' => 'Hero slider', 'compact_list' => 'Compact list'],
                'default' => 'compact_list',
            ],
            ['name' => 'limit', 'label' => 'Number of articles', 'type' => 'number', 'default' => 5],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $categoryId = !empty($config['category_id']) ? (int) $config['category_id'] : null;
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 5;
        $display = (string) ($config['display'] ?? 'compact_list');

        $articles = $this->articles->listPublishedByCategory($categoryId, $limit);
        if ($articles === []) {
            return '';
        }

        return $display === 'hero_slider'
            ? $this->renderHeroSlider($articles)
            : $this->renderCompactList($articles);
    }

    /** @param array<int, array<string, mixed>> $articles */
    private function renderHeroSlider(array $articles): string
    {
        $id = 'strat-hero-' . (++self::$instanceCounter);
        $slides = '';

        foreach ($articles as $i => $article) {
            $bg = !empty($article['featured_image_url'])
                ? 'background-image:url(\'' . e($article['featured_image_url']) . '\');background-size:cover;background-position:center;'
                : 'background:#12141c;';

            $slides .= '<div class="strat-hero-slide" style="' . $bg . 'display:' . ($i === 0 ? 'block' : 'none') . ';padding:2.5rem 2rem;color:#fff;border-radius:8px;min-height:220px;">'
                . '<h2 style="margin:0 0 0.5rem;"><a href="' . e(route('/articles/' . $article['slug'])) . '" style="color:inherit;text-decoration:none;">' . e($article['title']) . '</a></h2>'
                . (!empty($article['excerpt']) ? '<p style="max-width:32rem;">' . e($article['excerpt']) . '</p>' : '')
                . '<a href="' . e(route('/articles/' . $article['slug'])) . '" style="color:#fff;text-decoration:underline;">Learn More &rarr;</a>'
                . '</div>';
        }

        $dots = '';
        foreach ($articles as $i => $article) {
            $dots .= '<button type="button" class="strat-hero-dot" data-index="' . $i . '" style="width:0.5rem;height:0.5rem;border-radius:50%;border:none;background:' . ($i === 0 ? '#333' : '#ccc') . ';margin:0 0.2rem;cursor:pointer;"></button>';
        }

        $prevNext = count($articles) > 1 ? <<<HTML
            <button type="button" class="strat-hero-prev" style="position:absolute;left:0.5rem;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.4);color:#fff;border:none;border-radius:50%;width:2rem;height:2rem;cursor:pointer;">&lsaquo;</button>
            <button type="button" class="strat-hero-next" style="position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.4);color:#fff;border:none;border-radius:50%;width:2rem;height:2rem;cursor:pointer;">&rsaquo;</button>
            HTML
            : '';

        $rotator = count($articles) > 1 ? <<<JS
            <script>
            (function () {
                var root = document.getElementById('{$id}');
                if (!root) return;
                var slides = root.querySelectorAll('.strat-hero-slide');
                var dots = root.querySelectorAll('.strat-hero-dot');
                var current = 0;
                function show(i) {
                    slides[current].style.display = 'none';
                    dots[current].style.background = '#ccc';
                    current = (i + slides.length) % slides.length;
                    slides[current].style.display = 'block';
                    dots[current].style.background = '#333';
                }
                var prev = root.querySelector('.strat-hero-prev');
                var next = root.querySelector('.strat-hero-next');
                if (prev) prev.addEventListener('click', function () { show(current - 1); });
                if (next) next.addEventListener('click', function () { show(current + 1); });
                dots.forEach(function (dot, i) { dot.addEventListener('click', function () { show(i); }); });
                setInterval(function () { show(current + 1); }, 7000);
            })();
            </script>
            JS
            : '';

        return '<div id="' . $id . '" class="strat-hero-block" style="position:relative;">'
            . $slides
            . $prevNext
            . '<div style="text-align:center;margin-top:0.5rem;">' . $dots . '</div>'
            . '</div>'
            . $rotator;
    }

    /** @param array<int, array<string, mixed>> $articles */
    private function renderCompactList(array $articles): string
    {
        $items = '';
        foreach ($articles as $article) {
            $thumb = !empty($article['featured_image_url'])
                ? '<img src="' . e($article['featured_image_url']) . '" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;flex-shrink:0;">'
                : '';

            $items .= '<div style="display:flex;gap:0.6rem;padding:0.5rem 0;border-bottom:1px solid #eee;">'
                . $thumb
                . '<div>'
                . '<a href="' . e(route('/articles/' . $article['slug'])) . '" style="font-weight:600;text-decoration:none;color:inherit;">' . e($article['title']) . '</a>'
                . (!empty($article['excerpt']) ? '<div style="font-size:0.85rem;color:#666;">' . e($article['excerpt']) . '</div>' : '')
                . '<div style="font-size:0.75rem;color:#999;">' . e((string) $article['published_at']) . '</div>'
                . '</div>'
                . '</div>';
        }

        return '<div class="strat-latest-content-list" style="max-height:24rem;overflow-y:auto;">' . $items . '</div>';
    }
}
