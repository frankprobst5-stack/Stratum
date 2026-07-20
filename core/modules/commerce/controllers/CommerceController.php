<?php

declare(strict_types=1);

namespace Stratum\Modules\Commerce;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Downloads\DownloadService;

final class CommerceController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new CommerceService($this->app->db);

        $content = $this->app->templates->render('commerce', 'index', [
            'products' => $service->listProducts(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function product(Request $request): Response
    {
        $service = new CommerceService($this->app->db);
        $product = $service->findProduct((int) $request->param('id', '0'));
        if ($product === null) {
            return Response::notFound();
        }

        $currentUser = $this->app->auth->user();
        $myPurchases = $currentUser !== null
            ? $service->listPurchasesForUserAndProduct((int) $currentUser['id'], (int) $product['id'])
            : [];

        $content = $this->app->templates->render('commerce', 'product', [
            'product' => $product,
            'hasPurchased' => $currentUser !== null && $service->hasPurchased((int) $currentUser['id'], (int) $product['id']),
            'hasPending' => count(array_filter($myPurchases, static fn (array $p): bool => $p['status'] === 'pending')) > 0,
            'canPurchase' => $this->app->auth->check(),
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function recordIntent(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new CommerceService($this->app->db);
        $productId = (int) $request->param('id', '0');
        if ($service->findProduct($productId) === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $service->recordIntent($productId, (int) $user['id']);

        return Response::redirect('/shop/products/' . $productId);
    }

    /**
     * The actual gate: only ever streams the file after a confirmed
     * purchase. Reuses DownloadService's existing public API to locate the
     * stored file — no changes to the downloads module itself.
     */
    public function downloadFile(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $service = new CommerceService($this->app->db);
        $productId = (int) $request->param('id', '0');
        $product = $service->findProduct($productId);
        if ($product === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        if (!$service->hasPurchased((int) $user['id'], $productId)) {
            return Response::forbidden();
        }

        $downloads = new DownloadService($this->app->db, $this->app->rootDir . '/storage/uploads/downloads');
        $file = $downloads->findFile((int) $product['download_file_id']);
        $version = $file !== null ? $downloads->currentVersion((int) $file['id']) : null;
        if ($version === null) {
            return Response::notFound();
        }

        if ($version['scan_status'] === 'infected') {
            return Response::html('This file failed a virus scan and is not available for download.', 403);
        }

        $path = $downloads->absolutePath($version);
        if (!is_file($path)) {
            return Response::notFound();
        }

        return Response::file((string) file_get_contents($path), $version['mime_type'], $version['original_name']);
    }
}
