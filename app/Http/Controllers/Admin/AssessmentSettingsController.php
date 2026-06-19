<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAssessmentSettingsRequest;
use App\Services\AssessmentSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentSettingsController extends Controller
{
    public function edit(AssessmentSettings $settings): Response
    {
        return Inertia::render('admin/assessment-settings/edit', [
            'settings' => [
                'passing_score' => $settings->passingScore(),
                'config_default_passing_score' => (int) config('assessment.threshold', 75),
            ],
        ]);
    }

    public function update(UpdateAssessmentSettingsRequest $request, AssessmentSettings $settings): RedirectResponse
    {
        $settings->updatePassingScore((int) $request->validated('passing_score'));

        return to_route('admin.assessment-settings.edit');
    }
}
