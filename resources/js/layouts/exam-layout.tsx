import type { ReactNode } from 'react';

type Props = {
    children: ReactNode;
};

export default function ExamLayout({ children }: Props) {
    return (
        <div className="min-h-svh bg-background text-foreground">
            {children}
        </div>
    );
}
