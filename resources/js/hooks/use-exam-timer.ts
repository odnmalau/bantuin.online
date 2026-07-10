import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

export function useExamTimer(expiresAt: string | null): {
    remainingSeconds: number | null;
    isExpired: boolean;
    isPending: boolean;
} {
    const [now, setNow] = useState<number | null>(null);

    useEffect(() => {
        if (expiresAt === null) {
            setNow(null);

            return;
        }

        setNow(Date.now());

        const interval = window.setInterval(() => {
            setNow(Date.now());
        }, 1000);

        return () => {
            window.clearInterval(interval);
        };
    }, [expiresAt]);

    const remainingSeconds = useMemo(
        () => computeRemaining(expiresAt, now),
        [expiresAt, now],
    );

    return {
        remainingSeconds,
        isPending: expiresAt !== null && now === null,
        isExpired:
            expiresAt !== null &&
            remainingSeconds !== null &&
            remainingSeconds <= 0,
    };
}

function computeRemaining(
    expiresAt: string | null,
    nowMs: number | null,
): number | null {
    if (expiresAt === null || nowMs === null) {
        return null;
    }

    const end = new Date(expiresAt).getTime();
    const diff = Math.ceil((end - nowMs) / 1000);

    return Math.max(0, diff);
}

export function formatExamTimer(
    seconds: number | null,
    isPending = false,
): string {
    if (isPending) {
        return '--:--';
    }

    if (seconds === null) {
        return 'No time limit';
    }

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return `${minutes}:${rest.toString().padStart(2, '0')}`;
}

export function useSectionExpiryReload(isExpired: boolean): void {
    useEffect(() => {
        if (!isExpired) {
            return;
        }

        router.reload({
            only: ['examSession', 'currentSection', 'questions', 'sections'],
        });
    }, [isExpired]);
}
