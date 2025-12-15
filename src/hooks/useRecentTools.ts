import { useEffect, useState, useCallback } from "react";

const STORAGE_KEY = "bantuin-recent-tools";
const MAX_RECENT = 5;

export interface RecentTool {
  href: string;
  title: string;
  visitedAt: number;
}

export const useRecentTools = () => {
  const [recentTools, setRecentTools] = useState<RecentTool[]>([]);

  useEffect(() => {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
      try {
        setRecentTools(JSON.parse(stored));
      } catch {
        setRecentTools([]);
      }
    }
  }, []);

  const addRecentTool = useCallback((href: string, title: string) => {
    setRecentTools((prev) => {
      const filtered = prev.filter((t) => t.href !== href);
      const updated = [{ href, title, visitedAt: Date.now() }, ...filtered].slice(0, MAX_RECENT);
      localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
      return updated;
    });
  }, []);

  const clearRecentTools = useCallback(() => {
    localStorage.removeItem(STORAGE_KEY);
    setRecentTools([]);
  }, []);

  return { recentTools, addRecentTool, clearRecentTools };
};
