<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\Ai\AssessmentGenerationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

trait HandlesAssessmentGenerationFailures
{
    /**
     * @throws ValidationException
     */
    protected function throwAssessmentGenerationValidationError(
        AssessmentGenerationException $exception,
        string $field,
    ): never {
        report($exception);

        throw ValidationException::withMessages([
            $field => $exception->toValidationMessage(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    protected function runAssessmentGeneration(string $field, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (AssessmentGenerationException $exception) {
            $this->throwAssessmentGenerationValidationError($exception, $field);
        }
    }

    protected function flashGeneratedDraftQuestionCount(int $count): void
    {
        $this->flashSuccessToast(trans_choice(
            'Generated :count draft question.|Generated :count draft questions.',
            $count,
            ['count' => $count],
        ));
    }

    protected function flashSuccessToast(string $message): void
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    protected function generateDraftsAndRedirect(
        string $field,
        callable $callback,
        string $route,
        mixed $resource,
    ): RedirectResponse {
        $createdQuestions = $this->runAssessmentGeneration($field, $callback);

        $this->flashGeneratedDraftQuestionCount($createdQuestions);

        return to_route($route, $resource);
    }
}
