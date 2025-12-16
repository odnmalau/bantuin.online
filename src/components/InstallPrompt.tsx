import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { Button } from "@/components/ui/button";

import { Download, X } from "lucide-react";

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: "accepted" | "dismissed" }>;
}

const InstallPrompt = () => {
  const { t } = useTranslation();
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
  const [showPrompt, setShowPrompt] = useState(false);
  const [dismissed, setDismissed] = useState(() => {
      // Check if already dismissed during initialization to avoid effect sync state updates
      return !!localStorage.getItem("bantuin-install-dismissed");
  });

  useEffect(() => {
    if (dismissed) return;

    // Check if already installed
    if (window.matchMedia("(display-mode: standalone)").matches) {
      return;
    }

    const handleBeforeInstall = (e: Event) => {
      e.preventDefault();
      setDeferredPrompt(e as BeforeInstallPromptEvent);
      
      // Show prompt after a delay (2nd page view or after 3 seconds)
      const visitCount = parseInt(localStorage.getItem("bantuin-visit-count") || "0") + 1;
      localStorage.setItem("bantuin-visit-count", String(visitCount));
      
      if (visitCount >= 2) {
        setTimeout(() => setShowPrompt(true), 1000);
      } else {
        setTimeout(() => setShowPrompt(true), 5000);
      }
    };

    window.addEventListener("beforeinstallprompt", handleBeforeInstall);

    return () => {
      window.removeEventListener("beforeinstallprompt", handleBeforeInstall);
    };
  }, []);

  const handleInstall = async () => {
    if (!deferredPrompt) return;

    await deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    
    if (outcome === "accepted") {
      setShowPrompt(false);
    }
    setDeferredPrompt(null);
  };

  const handleDismiss = () => {
    setShowPrompt(false);
    setDismissed(true);
    localStorage.setItem("bantuin-install-dismissed", "true");
  };

  if (!showPrompt || dismissed || !deferredPrompt) return null;

  return (
    <div className="fixed bottom-4 left-4 right-4 z-50 animate-fade-in md:left-auto md:right-4 md:max-w-sm">
      <div className="rounded-lg border border-border bg-card p-4 shadow-lg">
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
            <Download className="h-5 w-5 text-primary" />
          </div>
          <div className="flex-1">
            <h3 className="font-semibold text-foreground">{t('install_prompt.title')}</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              {t('install_prompt.desc')}
            </p>
            <div className="mt-3 flex gap-2">
              <Button size="sm" onClick={handleInstall}>
                {t('install_prompt.install')}
              </Button>
              <Button size="sm" variant="ghost" onClick={handleDismiss}>
                {t('install_prompt.later')}
              </Button>
            </div>
          </div>
          <button
            onClick={handleDismiss}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  );
};

export default InstallPrompt;
