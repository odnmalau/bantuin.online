export function exitSecureExamFullscreen(): void {
    if (
        typeof document === 'undefined' ||
        document.fullscreenElement === null
    ) {
        return;
    }

    void document.exitFullscreen?.();
}
