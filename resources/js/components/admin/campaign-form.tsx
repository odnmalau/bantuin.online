import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { UnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { RouteFormDefinition } from '@/wayfinder';

export type CampaignFormValues = {
    id?: number;
    title: string;
    role_title: string;
    seniority: string | null;
    job_description: string | null;
    required_skills: string[];
    language: string | null;
    threshold_score: number;
};

type CampaignFormData = {
    title: string;
    role_title: string;
    seniority: string;
    job_description: string;
    required_skills: string;
    language: string;
    threshold_score: number;
};

type Props = {
    action: RouteFormDefinition<'post'> | RouteFormDefinition<'patch'>;
    submitLabel: string;
    campaign?: CampaignFormValues;
    onSuccess?: () => void;
    onCancel?: () => void;
    showCancel?: boolean;
    className?: string;
    bodyClassName?: string;
    footerClassName?: string;
};

function focusFirstError(errors: Record<string, string | undefined>) {
    window.requestAnimationFrame(() => {
        const firstError = Object.keys(errors)[0];
        const control = firstError
            ? document.querySelector<HTMLElement>(
                  `[name="${CSS.escape(firstError)}"]`,
              )
            : null;

        control?.focus();
    });
}

export default function CampaignForm({
    action,
    submitLabel,
    campaign,
    onSuccess,
    onCancel,
    showCancel = true,
    className,
    bodyClassName,
    footerClassName,
}: Props) {
    return (
        <Form<CampaignFormData>
            {...action}
            onSuccess={onSuccess}
            onError={focusFirstError}
            options={{
                preserveScroll: true,
            }}
            className={cn('flex flex-col gap-6', className)}
        >
            {({ errors, isDirty, processing }) => (
                <>
                    <UnsavedChangesGuard active={isDirty && !processing} />
                    <div className={cn('flex flex-col gap-6', bodyClassName)}>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Campaign Title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    autoComplete="off"
                                    defaultValue={campaign?.title}
                                    required
                                    placeholder="Example: Backend Engineer Screening…"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="role_title">Role Title</Label>
                                <Input
                                    id="role_title"
                                    name="role_title"
                                    autoComplete="off"
                                    defaultValue={campaign?.role_title}
                                    required
                                    placeholder="Example: Backend Engineer…"
                                />
                                <InputError message={errors.role_title} />
                            </div>
                        </div>

                        <div className="grid gap-6 sm:grid-cols-[1fr_180px]">
                            <div className="grid gap-2">
                                <Label htmlFor="seniority">Seniority</Label>
                                <Input
                                    id="seniority"
                                    name="seniority"
                                    autoComplete="off"
                                    defaultValue={campaign?.seniority ?? ''}
                                    placeholder="Example: Mid-level…"
                                />
                                <InputError message={errors.seniority} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="threshold_score">
                                    Threshold
                                </Label>
                                <Input
                                    id="threshold_score"
                                    name="threshold_score"
                                    type="number"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    min={0}
                                    max={100}
                                    defaultValue={
                                        campaign?.threshold_score ?? 75
                                    }
                                    required
                                />
                                <InputError message={errors.threshold_score} />
                            </div>
                        </div>

                        <div className="grid gap-2 sm:max-w-xs">
                            <Label htmlFor="language">
                                Assessment Language
                            </Label>
                            <Input
                                id="language"
                                name="language"
                                autoComplete="off"
                                defaultValue={campaign?.language ?? 'English'}
                                required
                                placeholder="Example: English…"
                            />
                            <InputError message={errors.language} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="required_skills">
                                Required Skills
                            </Label>
                            <Textarea
                                id="required_skills"
                                name="required_skills"
                                autoComplete="off"
                                defaultValue={
                                    campaign?.required_skills?.join('\n') ?? ''
                                }
                                rows={4}
                                className="min-h-32"
                                placeholder="Example: Laravel&#10;PostgreSQL&#10;Queue workers…"
                            />
                            <InputError message={errors.required_skills} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="job_description">
                                Job Description
                            </Label>
                            <Textarea
                                id="job_description"
                                name="job_description"
                                autoComplete="off"
                                defaultValue={campaign?.job_description ?? ''}
                                rows={8}
                                className="min-h-32"
                                placeholder="Example: Paste the role context or hiring brief…"
                            />
                            <InputError message={errors.job_description} />
                        </div>
                    </div>

                    <div
                        className={cn(
                            'flex items-center justify-between gap-3',
                            footerClassName,
                        )}
                    >
                        {showCancel ? (
                            onCancel ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onCancel}
                                >
                                    Cancel
                                </Button>
                            ) : (
                                <Button type="button" variant="outline" asChild>
                                    <Link href={admin.campaigns.index()}>
                                        Cancel
                                    </Link>
                                </Button>
                            )
                        ) : (
                            <span />
                        )}
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
