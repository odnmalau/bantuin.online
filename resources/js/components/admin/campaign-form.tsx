import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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

const textareaClass =
    'flex min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';

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
            options={{
                preserveScroll: true,
            }}
            className={cn('flex flex-col gap-6', className)}
        >
            {({ errors, processing }) => (
                <>
                    <div className={cn('flex flex-col gap-6', bodyClassName)}>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Campaign title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={campaign?.title}
                                    required
                                    placeholder="Backend Engineer Screening"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="role_title">Role title</Label>
                                <Input
                                    id="role_title"
                                    name="role_title"
                                    defaultValue={campaign?.role_title}
                                    required
                                    placeholder="Backend Engineer"
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
                                    defaultValue={campaign?.seniority ?? ''}
                                    placeholder="Mid-level"
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
                                Assessment language
                            </Label>
                            <Input
                                id="language"
                                name="language"
                                defaultValue={campaign?.language ?? 'English'}
                                required
                                placeholder="English"
                            />
                            <InputError message={errors.language} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="required_skills">
                                Required skills
                            </Label>
                            <textarea
                                id="required_skills"
                                name="required_skills"
                                defaultValue={
                                    campaign?.required_skills?.join('\n') ?? ''
                                }
                                rows={4}
                                className={textareaClass}
                                placeholder="Laravel&#10;PostgreSQL&#10;Queue workers"
                            />
                            <InputError message={errors.required_skills} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="job_description">
                                Job description
                            </Label>
                            <textarea
                                id="job_description"
                                name="job_description"
                                defaultValue={campaign?.job_description ?? ''}
                                rows={8}
                                className={textareaClass}
                                placeholder="Paste the role context or hiring brief."
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
