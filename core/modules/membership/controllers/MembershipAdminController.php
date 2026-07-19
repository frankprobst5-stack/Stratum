<?php

declare(strict_types=1);

namespace Stratum\Modules\Membership;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class MembershipAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('membership.manage')) !== null) {
            return $guard;
        }

        $applicationService = new MembershipApplicationService($this->app->db);
        $fields = (new MembershipFieldService($this->app->db))->listFields();
        $fieldsById = [];
        foreach ($fields as $field) {
            $fieldsById[(int) $field['id']] = $field['label'];
        }

        $decorateAnswers = static function (array $application) use ($fieldsById): array {
            $answers = json_decode((string) ($application['answers_json'] ?? '{}'), true) ?: [];
            $decorated = [];
            foreach ($answers as $fieldId => $value) {
                $decorated[] = ['label' => $fieldsById[(int) $fieldId] ?? "Field #{$fieldId}", 'value' => $value];
            }

            return $application + ['decoratedAnswers' => $decorated];
        };

        $content = $this->app->templates->render('membership', 'admin-index', [
            'fields' => $fields,
            'pending' => array_map($decorateAnswers, $applicationService->listPending()),
            'reviewed' => $applicationService->listReviewed(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createField(Request $request): Response
    {
        if (($guard = $this->guard('membership.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $label = trim((string) $request->input('label', ''));
        $fieldType = (string) $request->input('field_type', 'text');
        $isRequired = $request->input('is_required') !== null;
        $weight = (int) $request->input('weight', '0');
        $optionsRaw = (string) $request->input('options', '');
        $options = array_values(array_filter(array_map('trim', explode("\n", $optionsRaw))));

        if ($label !== '') {
            (new MembershipFieldService($this->app->db))->createField($label, $fieldType, $options, $isRequired, $weight);
        }

        return Response::redirect('/admin/membership');
    }

    public function deleteField(Request $request): Response
    {
        if (($guard = $this->guard('membership.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        if ($id > 0) {
            (new MembershipFieldService($this->app->db))->deleteField($id);
        }

        return Response::redirect('/admin/membership');
    }

    public function approve(Request $request): Response
    {
        if (($guard = $this->guard('membership.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        $reviewer = $this->app->auth->user();

        if ($id > 0 && $reviewer !== null) {
            $newUserId = (new MembershipApplicationService($this->app->db))->approve(
                $id,
                (int) $reviewer['id'],
                new AuthService($this->app->db),
                $this->app->permissions
            );

            if ($newUserId !== null) {
                // Rejections have no strat_users row, so only approvals can
                // be notified in-app. Actor deliberately omitted: the new
                // member doesn't need to know which admin clicked approve.
                $this->app->notify([
                    'user_id' => $newUserId,
                    'type' => 'membership.approved',
                    'message' => 'Your membership application was approved — welcome!',
                    'url' => '/profile',
                ]);
            }
        }

        return Response::redirect('/admin/membership');
    }

    public function reject(Request $request): Response
    {
        if (($guard = $this->guard('membership.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        $reviewer = $this->app->auth->user();

        if ($id > 0 && $reviewer !== null) {
            (new MembershipApplicationService($this->app->db))->reject($id, (int) $reviewer['id']);
        }

        return Response::redirect('/admin/membership');
    }
}
