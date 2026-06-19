import { Form, Head } from '@inertiajs/react';
import AssessmentSettingsController from '@/actions/App/Http/Controllers/Admin/AssessmentSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import admin from '@/routes/admin';

type Props = {
    settings: {
        passing_score: number;
        config_default_passing_score: number;
    };
};

export default function AdminAssessmentSettingsEdit({ settings }: Props) {
    return (
        <>
            <Head title="Assessment Settings" />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title="Assessment Settings"
                    description="Configure evaluation rules used by the AI assessment pipeline."
                />

                <section className="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <Form
                        {...AssessmentSettingsController.update.form.patch()}
                        className="space-y-5"
                    >
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="passing_score">
                                        Passing score threshold
                                    </Label>
                                    <Input
                                        id="passing_score"
                                        name="passing_score"
                                        type="number"
                                        min={0}
                                        max={100}
                                        step={1}
                                        defaultValue={settings.passing_score}
                                    />
                                    <InputError
                                        message={errors.passing_score}
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Default for assessments without a
                                        campaign. When a candidate submits
                                        against a campaign, that campaign&apos;s
                                        threshold score is used instead.
                                    </p>
                                </div>

                                <div className="rounded-md border border-sidebar-border/70 bg-muted/30 p-3 text-sm text-muted-foreground dark:border-sidebar-border">
                                    Config fallback:{' '}
                                    {settings.config_default_passing_score}
                                </div>

                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Save settings
                                    </Button>
                                    {recentlySuccessful && (
                                        <p className="text-sm text-muted-foreground">
                                            Saved.
                                        </p>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}

AdminAssessmentSettingsEdit.layout = {
    breadcrumbs: [
        {
            title: 'Assessment Settings',
            href: admin.assessmentSettings.edit(),
        },
    ],
};
