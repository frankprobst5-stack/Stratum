<?php

declare(strict_types=1);

namespace Stratum\Modules\Forms;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class FormsController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new FormService($this->app->db);

        $content = $this->app->templates->render('forms', 'index', [
            'forms' => $service->listPublished(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function show(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $service = new FormService($this->app->db);
        $form = $service->findBySlug((string) $request->param('slug', ''));
        if ($form === null || $form['status'] !== 'published') {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $alreadySubmitted = $service->hasSubmitted((int) $form['id'], (int) $user['id']);

        $content = $this->app->templates->render('forms', 'show', [
            'form' => $form,
            'fields' => $alreadySubmitted ? [] : $service->listFields((int) $form['id']),
            'alreadySubmitted' => $alreadySubmitted,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function submit(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new FormService($this->app->db);
        $slug = (string) $request->param('slug', '');
        $form = $service->findBySlug($slug);
        if ($form === null || $form['status'] !== 'published') {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        if ($service->hasSubmitted((int) $form['id'], (int) $user['id'])) {
            return Response::redirect('/forms/' . $slug);
        }

        $fields = $service->listFields((int) $form['id']);
        $answers = [];
        foreach ($fields as $field) {
            $key = 'field_' . $field['id'];

            if ($field['type'] === 'checkbox') {
                $answers[$field['id']] = $request->inputArray($key);
                continue;
            }

            $value = trim((string) $request->input($key, ''));
            if ($field['required'] && $value === '') {
                return Response::redirect('/forms/' . $slug . '?error=missing');
            }

            $answers[$field['id']] = $value;
        }

        $service->submit((int) $form['id'], (int) $user['id'], $answers);

        return Response::redirect('/forms/' . $slug);
    }
}
