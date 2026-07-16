import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = React.ComponentProps<'main'> & {
    variant?: AppVariant;
};

export function AppContent({ variant = 'sidebar', children, ...props }: Props) {
    const content =
        variant === 'sidebar' ? (
            <SidebarInset id="main-content" tabIndex={-1} {...props}>
                {children}
            </SidebarInset>
        ) : (
            <main
                id="main-content"
                tabIndex={-1}
                className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl"
                {...props}
            >
                {children}
            </main>
        );

    return (
        <>
            <a
                href="#main-content"
                className="fixed top-2 left-2 z-50 -translate-y-16 rounded-md bg-background px-3 py-2 text-sm font-medium shadow-popover ring-1 ring-border transition-transform focus-visible:translate-y-0 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                Skip to Content
            </a>
            {content}
        </>
    );
}
