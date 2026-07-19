<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\BlockConfigForm;
use Stratum\Core\BlockPlacementService;
use Stratum\Core\ConfigurableBlock;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class BlockPlacementsController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('blocks.manage')) !== null) {
            return $guard;
        }

        $service = new BlockPlacementService($this->app->db);
        $csrfToken = $this->app->session->csrfToken();

        $renderedCardsByRegion = [];
        foreach ($service->listGroupedByRegion() as $regionKey => $placements) {
            foreach ($placements as $placement) {
                $renderedCardsByRegion[$regionKey][] = $this->renderCard($placement, $csrfToken);
            }
        }

        $content = $this->app->templates->render('admin', 'block-placements', [
            'regions' => $service->listRegions(),
            'renderedCardsByRegion' => $renderedCardsByRegion,
            'blockTypes' => $this->app->blocks->registeredTypes(),
            'csrfToken' => $csrfToken,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /**
     * AJAX — a block dragged from the palette and dropped onto a region.
     * Created with no config yet (filled in via the settings form that
     * comes back in the response, same shape as every existing
     * placement's card). Returns the rendered card HTML directly so the
     * client only ever needs to insert a string into the DOM, never
     * duplicate this app's own template logic in JS.
     */
    public function apiCreate(Request $request): Response
    {
        if (($guard = $this->guard('blocks.manage')) !== null) {
            return Response::json(['error' => 'forbidden'], 403);
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::json(['error' => 'invalid_csrf'], 400);
        }

        $blockType = (string) $request->input('block_type', '');
        $regionId = (int) $request->input('region_id', '0');
        $weight = (int) $request->input('weight', '10');

        if ($blockType === '' || $regionId <= 0 || $this->app->blocks->make($blockType) === null) {
            return Response::json(['error' => 'invalid_block'], 400);
        }

        $service = new BlockPlacementService($this->app->db);
        $newId = $service->create($blockType, $regionId, 'site_wide', $weight, '');
        if ($newId === false) {
            return Response::json(['error' => 'invalid_config'], 400);
        }

        $placement = $service->find($newId);
        if ($placement === null) {
            return Response::json(['error' => 'not_found'], 500);
        }

        return Response::json([
            'id' => $newId,
            'cardHtml' => $this->renderCard($placement, $this->app->session->csrfToken()),
        ]);
    }

    /** AJAX — persists a drag-and-drop move (reorder within a region, or drop into a different one). */
    public function apiMove(Request $request): Response
    {
        if (($guard = $this->guard('blocks.manage')) !== null) {
            return Response::json(['error' => 'forbidden'], 403);
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::json(['error' => 'invalid_csrf'], 400);
        }

        $id = (int) $request->input('id', '0');
        $regionId = (int) $request->input('region_id', '0');
        $weight = (int) $request->input('weight', '0');

        if ($id <= 0 || $regionId <= 0) {
            return Response::json(['error' => 'invalid_request'], 400);
        }

        (new BlockPlacementService($this->app->db))->updateRegionAndWeight($id, $regionId, $weight);

        return Response::json(['success' => true]);
    }

    /**
     * Saves a placement's page_scope + real config fields (generated
     * from its block's own `configFields()`, see `BlockConfigForm`) —
     * a normal form POST, not AJAX, since saving settings isn't a drag
     * gesture and every other save action in this app is a plain form.
     */
    public function saveConfig(Request $request): Response
    {
        if (($guard = $this->guard('blocks.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        $service = new BlockPlacementService($this->app->db);
        $placement = $service->find($id);
        if ($placement === null) {
            return Response::redirect('/admin/blocks');
        }

        $block = $this->app->blocks->make((string) $placement['block_type']);
        $fields = $block instanceof ConfigurableBlock ? $block->configFields() : [];
        $config = BlockConfigForm::extractConfig($fields, $this->postDataFor($request, $fields));

        $pageScope = trim((string) $request->input('page_scope', 'site_wide'));
        $service->updatePageScope($id, $pageScope !== '' ? $pageScope : 'site_wide');
        $service->updateConfig($id, $config === [] ? '' : (string) json_encode($config, JSON_UNESCAPED_SLASHES));

        return Response::redirect('/admin/blocks');
    }

    public function toggle(Request $request): Response
    {
        if (($guard = $this->guard('blocks.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new BlockPlacementService($this->app->db);
        $placement = $service->find((int) $request->param('id', '0'));
        if ($placement !== null) {
            $service->setEnabled((int) $placement['id'], !$placement['is_enabled']);
        }

        return Response::redirect('/admin/blocks');
    }

    public function moveUp(Request $request): Response
    {
        if (($guard = $this->guard('blocks.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new BlockPlacementService($this->app->db))->moveUp((int) $request->param('id', '0'));

        return Response::redirect('/admin/blocks');
    }

    public function moveDown(Request $request): Response
    {
        if (($guard = $this->guard('blocks.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new BlockPlacementService($this->app->db))->moveDown((int) $request->param('id', '0'));

        return Response::redirect('/admin/blocks');
    }

    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('blocks.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new BlockPlacementService($this->app->db))->delete((int) $request->param('id', '0'));

        return Response::redirect('/admin/blocks');
    }

    /** @param array<string, mixed> $placement */
    private function renderCard(array $placement, string $csrfToken): string
    {
        $block = $this->app->blocks->make((string) $placement['block_type']);
        $fields = $block instanceof ConfigurableBlock ? $block->configFields() : [];
        $currentConfig = $placement['config_json'] !== null
            ? (json_decode((string) $placement['config_json'], true) ?: [])
            : [];

        return $this->app->templates->render('admin', 'block-placement-card', [
            'placement' => $placement,
            'configFields' => $fields,
            'currentConfig' => $currentConfig,
            'csrfToken' => $csrfToken,
        ]);
    }

    /**
     * `Request::input()` only reads `field_x` values that are actually
     * present in the request body — since a block's fields come from its
     * own `configFields()`, this just reads each declared field's raw
     * POST value directly rather than needing a generic "give me the
     * whole body array" accessor `Request` doesn't otherwise expose.
     *
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, string>
     */
    private function postDataFor(Request $request, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $key = 'field_' . $field['name'];
            $data[$key] = (string) $request->input($key, '');
        }

        return $data;
    }
}
