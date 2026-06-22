import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export function useExamNavigationGuard(enabled: boolean): void {
    useEffect(() => {
        if (!enabled) {
            return;
        }

        const onBeforeUnload = (event: BeforeUnloadEvent): void => {
            event.preventDefault();
            event.returnValue = '';
        };

        const onPopState = (): void => {
            window.history.pushState(null, '', window.location.href);
        };

        window.history.pushState(null, '', window.location.href);
        window.addEventListener('beforeunload', onBeforeUnload);
        window.addEventListener('popstate', onPopState);

        const removeListener = router.on('before', (event) => {
            if (event.detail.visit.method === 'get') {
                const confirmed = window.confirm(
                    'Leaving this page will interrupt your exam. Continue?',
                );

                if (!confirmed) {
                    event.preventDefault();
                }
            }
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            window.removeEventListener('popstate', onPopState);
            removeListener();
        };
    }, [enabled]);
}
