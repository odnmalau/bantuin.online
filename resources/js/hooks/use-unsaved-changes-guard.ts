import { router } from '@inertiajs/react';
import { useEffect } from 'react';

const warning = 'You have unsaved changes. Leave without saving?';

export function useUnsavedChangesGuard(hasUnsavedChanges: boolean) {
    useEffect(() => {
        if (!hasUnsavedChanges) {
            return;
        }

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };
        const removeBeforeListener = router.on('before', (event) => {
            if (!window.confirm(warning)) {
                event.preventDefault();
            }
        });

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            removeBeforeListener();
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [hasUnsavedChanges]);
}

export function UnsavedChangesGuard({ active }: { active: boolean }) {
    useUnsavedChangesGuard(active);

    return null;
}
