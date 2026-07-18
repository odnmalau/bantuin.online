import { Form, Head } from '@inertiajs/react';
import {
    admin as loginAsDemoAdmin,
    candidate as loginAsDemoCandidate,
} from '@/actions/App/Http/Controllers/Auth/DemoLoginController';
import GoogleIcon from '@/components/google-icon';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { redirect } from '@/routes/auth/google';

type Props = {
    status?: string;
    canUseGoogle?: boolean;
};

export default function Login({ status, canUseGoogle = true }: Props) {
    return (
        <>
            <Head title="Log in" />

            {canUseGoogle ? (
                <Button
                    asChild
                    className="w-full"
                    data-test="google-login-button"
                >
                    <a
                        href={redirect.url()}
                        className="flex items-center justify-center gap-2"
                    >
                        <GoogleIcon className="size-5 shrink-0" />
                        Continue with Google
                    </a>
                </Button>
            ) : (
                <p className="text-center text-sm text-muted-foreground">
                    Google sign-in is not configured. Set{' '}
                    <code className="text-xs">GOOGLE_CLIENT_ID</code> and
                    related env values, then try again.
                </p>
            )}

            <div className="flex items-center gap-3 py-4">
                <Separator className="flex-1" />
                <span className="text-xs text-muted-foreground">
                    Or continue with demo
                </span>
                <Separator className="flex-1" />
            </div>

            <div className="grid grid-cols-2 gap-2">
                <Form {...loginAsDemoAdmin.form()}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="outline"
                            className="w-full"
                            disabled={processing}
                            data-test="demo-admin-login-button"
                        >
                            {processing && <Spinner data-icon="inline-start" />}
                            Demo Admin
                        </Button>
                    )}
                </Form>

                <Form {...loginAsDemoCandidate.form()}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="outline"
                            className="w-full"
                            disabled={processing}
                            data-test="demo-candidate-login-button"
                        >
                            {processing && <Spinner data-icon="inline-start" />}
                            Demo Candidate
                        </Button>
                    )}
                </Form>
            </div>

            {status && (
                <div className="mt-4 text-center text-sm font-medium text-muted-foreground">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Sign in with Google or explore a demo account',
};
