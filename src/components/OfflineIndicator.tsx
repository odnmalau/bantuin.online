import { useState, useEffect } from "react";
import { WifiOff, Wifi } from "lucide-react";

const OfflineIndicator = () => {
  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [showReconnected, setShowReconnected] = useState(false);

  useEffect(() => {
    const handleOnline = () => {
      setIsOnline(true);
      setShowReconnected(true);
      setTimeout(() => setShowReconnected(false), 3000);
    };

    const handleOffline = () => {
      setIsOnline(false);
      setShowReconnected(false);
    };

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);

    return () => {
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
    };
  }, []);

  if (isOnline && !showReconnected) return null;

  return (
    <div
      className={`fixed top-0 left-0 right-0 z-50 flex items-center justify-center gap-2 py-2 px-4 text-sm font-medium transition-all ${
        isOnline
          ? "bg-green-500 text-white animate-fade-in"
          : "bg-yellow-500 text-yellow-950 animate-fade-in"
      }`}
    >
      {isOnline ? (
        <>
          <Wifi className="h-4 w-4" />
          <span>Koneksi kembali!</span>
        </>
      ) : (
        <>
          <WifiOff className="h-4 w-4" />
          <span>Kamu sedang offline. Beberapa fitur tetap bisa digunakan.</span>
        </>
      )}
    </div>
  );
};

export default OfflineIndicator;
