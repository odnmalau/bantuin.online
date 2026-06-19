import { Head } from '@inertiajs/react';
import GoogleIcon from '@/components/google-icon';
import { Button } from '@/components/ui/button';
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
    description: 'Sign in with your Google account to continue',
};
