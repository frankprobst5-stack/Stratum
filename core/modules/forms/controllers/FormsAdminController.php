<?php

declare(strict_types=1);

namespace Stratum\Modules\Forms;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class FormsAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        $service = new FormService($this->app->db);
        $forms = array_map(
            fn (array $f): array => $f + ['submissionCount' => $service->submissionCount((int) $f['id'])],
            $service->listAll()
        );

        $content = $this->app->templates->render('forms', 'admin-index', [
            'forms' => $forms,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('forms', 'admin-create', [
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return Response::redirect('/admin/forms/create');
        }

        $admin = $this->app->auth->user();
        $formId = (new FormService($this->app->db))->createForm(
            $title,
            (string) $request->input('description', ''),
            (int) $admin['id']
        );

        return Response::redirect('/admin/forms/' . $formId);
    }

    public function edit(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        $service = new FormService($this->app->db);
        $form = $service->findById((int) $request->param('id', '0'));
        if ($form === null) {
            return Response::notFound();
        }

        $content = $this->app->templates->render('forms', 'admin-edit', [
            'form' => $form,
            'fields' => $service->listFields((int) $form['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function addField(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $formId = (int) $request->param('id', '0');
        $label = trim((string) $request->input('label', ''));
        $type = (string) $request->input('type', 'text');
        $options = (string) $request->input('options', '');
        $required = $request->input('required', '') === '1';

        if ($label !== '' && in_array($type, ['text', 'textarea', 'select', 'checkbox'], true)) {
            (new FormService($this->app->db))->addField($formId, $label, $type, $options, $required);
        }

        return Response::redirect('/admin/forms/' . $formId);
    }

    public function deleteField(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new FormService($this->app->db))->deleteField((int) $request->param('fieldId', '0'));

        return Response::redirect('/admin/forms/' . (int) $request->param('id', '0'));
    }

    public function publish(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $formId = (int) $request->param('id', '0');
        (new FormService($this->app->db))->setStatus($formId, 'published');

        return Response::redirect('/admin/forms/' . $formId);
    }

    public function close(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $formId = (int) $request->param('id', '0');
        (new FormService($this->app->db))->setStatus($formId, 'closed');

        return Response::redirect('/admin/forms/' . $formId);
    }

    public function deleteForm(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new FormService($this->app->db))->softDeleteForm((int) $request->param('id', '0'));

        return Response::redirect('/admin/forms');
    }

    public function results(Request $request): Response
    {
        if (($guard = $this->guard('forms.manage')) !== null) {
            return $guard;
        }

        $service = new FormService($this->app->db);
        $form = $service->findById((int) $request->param('id', '0'));
        if ($form === null) {
            return Response::notFound();
        }

        $fields = $service->listFields((int) $form['id']);
        $tallies = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], ['select', 'checkbox'], true)) {
                $tallies[$field['id']] = $service->tally((int) $field['id']);
            }
        }

        $content = $this->app->templates->render('forms', 'admin-results', [
            'form' => $form,
            'fields' => $fields,
            'tallies' => $tallies,
            'submissions' => $service->listSubmissions((int) $form['id']),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }
}
