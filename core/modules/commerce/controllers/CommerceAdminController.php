<?php

declare(strict_types=1);

namespace Stratum\Modules\Commerce;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Downloads\DownloadService;
use Stratum\Modules\Users\AuthService;

final class CommerceAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('commerce.manage')) !== null) {
            return $guard;
        }

        $service = new CommerceService($this->app->db);
        $authors = new AuthService($this->app->db);
        $downloads = new DownloadService($this->app->db, $this->app->rootDir . '/storage/uploads/downloads');

        // Only files with no existing product yet — a download can be sold
        // at most once as a product (creating a second product for the same
        // file would just split its purchase history across two rows for
        // no reason).
        $soldFileIds = array_column($service->listProducts(false), 'download_file_id');
        $availableFiles = array_values(array_filter(
            $this->allDownloadFiles($downloads),
            static fn (array $f): bool => !in_array((int) $f['id'], array_map('intval', $soldFileIds), true)
        ));

        $decorate = fn (array $purchase): array => $purchase + [
            'purchaserName' => $this->purchaserName($authors, $purchase),
        ];

        $content = $this->app->templates->render('commerce', 'admin-index', [
            'products' => $service->listProducts(false),
            'availableFiles' => $availableFiles,
            'pending' => array_map($decorate, $service->listPending()),
            'confirmed' => array_map($decorate, $service->listConfirmed()),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createProduct(Request $request): Response
    {
        if (($guard = $this->guard('commerce.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $downloadFileId = (int) $request->input('download_file_id', '0');
        $price = trim((string) $request->input('price', ''));
        $paymentUrl = trim((string) $request->input('payment_url', ''));

        if ($downloadFileId === 0 || $price === '' || $paymentUrl === '') {
            return Response::redirect('/admin/commerce');
        }

        $service = new CommerceService($this->app->db);
        $created = $service->createProduct($downloadFileId, $price, $paymentUrl);

        if (!$created) {
            return Response::html('Payment link must be a valid http:// or https:// URL.', 422);
        }

        return Response::redirect('/admin/commerce');
    }

    public function toggleProductActive(Request $request): Response
    {
        if (($guard = $this->guard('commerce.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new CommerceService($this->app->db);
        $product = $service->findProduct((int) $request->param('id', '0'));
        if ($product !== null) {
            $service->setProductActive((int) $product['id'], !$product['is_active']);
        }

        return Response::redirect('/admin/commerce');
    }

    public function confirmPurchase(Request $request): Response
    {
        if (($guard = $this->guard('commerce.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new CommerceService($this->app->db);
        $purchase = $service->findPurchase((int) $request->param('id', '0'));
        if ($purchase === null) {
            return Response::notFound();
        }

        $admin = $this->app->auth->user();
        $service->confirmPurchase(
            (int) $purchase['id'],
            (int) $admin['id'],
            (string) $request->input('amount', ''),
            (string) $request->input('notes', '')
        );

        $product = $service->findProduct((int) $purchase['product_id']);
        $this->app->notify([
            'user_id' => $purchase['user_id'] !== null ? (int) $purchase['user_id'] : null,
            'type' => 'commerce.purchase_confirmed',
            'message' => $product !== null
                ? 'Your purchase of "' . $product['download_title'] . '" was confirmed — you can now download it.'
                : 'Your purchase was confirmed.',
            'url' => '/shop/products/' . $purchase['product_id'],
        ]);

        return Response::redirect('/admin/commerce');
    }

    /** @return array<int, array<string, mixed>> every downloads_files row, across all categories */
    private function allDownloadFiles(DownloadService $downloads): array
    {
        $files = [];
        foreach ($downloads->listCategories() as $category) {
            foreach ($downloads->listFiles((int) $category['id']) as $file) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /** @param array<string, mixed> $purchase */
    private function purchaserName(AuthService $authors, array $purchase): string
    {
        if ($purchase['user_id'] === null) {
            return 'Unknown';
        }

        $user = $authors->findById((int) $purchase['user_id']);

        return $user['username'] ?? 'Unknown';
    }
}
