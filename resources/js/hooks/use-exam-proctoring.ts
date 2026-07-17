import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import ExamSessionController from '@/actions/App/Http/Controllers/Candidate/ExamSessionController';
import { exitSecureExamFullscreen } from '@/lib/secure-exam-fullscreen';

type SecureExamConfig = {
    require_fullscreen: boolean;
    block_copy_paste: boolean;
};

type Options = {
    campaignId: number;
    sessionId: number;
    enabled: boolean;
    secureExam: SecureExamConfig;
    onMaxWarnings?: () => void;
};

export function useExamProctoring({
    campaignId,
    sessionId,
    enabled,
    secureExam,
}: Options): void {
    const reportedRef = useRef<Set<string>>(new Set());

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const report = (type: string): void => {
            const key = `${type}:${Math.floor(Date.now() / 5000)}`;

            if (reportedRef.current.has(key)) {
                return;
            }

            reportedRef.current.add(key);

            router.post(
                ExamSessionController.storeViolation.url([
                    campaignId,
                    sessionId,
                ]),
                { type },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: (page) => {
                        if (page.component === 'candidate/assessments/show') {
                            exitSecureExamFullscreen();
                        }
                    },
                },
            );
        };

        const onVisibility = (): void => {
            if (document.hidden) {
                report('tab_hidden');
            }
        };

        const onBlur = (): void => {
            report('window_blur');
        };

        const onCopy = (event: ClipboardEvent): void => {
            if (!secureExam.block_copy_paste) {
                return;
            }

            event.preventDefault();
            report('copy');
        };

        const onPaste = (event: ClipboardEvent): void => {
            if (!secureExam.block_copy_paste) {
                return;
            }

            event.preventDefault();
            report('paste');
        };

        const onCut = (event: ClipboardEvent): void => {
            if (!secureExam.block_copy_paste) {
                return;
            }

            event.preventDefault();
            report('cut');
        };

        const onContextMenu = (event: MouseEvent): void => {
            event.preventDefault();
            report('context_menu');
        };

        const onFullscreenChange = (): void => {
            if (
                secureExam.require_fullscreen &&
                document.fullscreenElement === null
            ) {
                report('fullscreen_exit');
            }
        };

        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('blur', onBlur);
        document.addEventListener('copy', onCopy);
        document.addEventListener('paste', onPaste);
        document.addEventListener('cut', onCut);
        document.addEventListener('contextmenu', onContextMenu);
        document.addEventListener('fullscreenchange', onFullscreenChange);

        return () => {
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('blur', onBlur);
            document.removeEventListener('copy', onCopy);
            document.removeEventListener('paste', onPaste);
            document.removeEventListener('cut', onCut);
            document.removeEventListener('contextmenu', onContextMenu);
            document.removeEventListener(
                'fullscreenchange',
                onFullscreenChange,
            );
        };
    }, [campaignId, enabled, secureExam, sessionId]);
}
