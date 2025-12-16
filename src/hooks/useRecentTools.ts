import { useState, useCallback } from "react";

const STORAGE_KEY = "bantuin-recent-tools";
const MAX_RECENT = 5;

export interface RecentTool {
	href: string;
	title: string;
	visitedAt: number;
}

export const useRecentTools = (): {
	recentTools: Array<RecentTool>;
	addRecentTool: (href: string, title: string) => void;
	clearRecentTools: () => void;
} => {
	const [recentTools, setRecentTools] = useState<Array<RecentTool>>(() => {
		const stored = localStorage.getItem(STORAGE_KEY);
		if (stored) {
			try {
				return JSON.parse(stored) as Array<RecentTool>;
			} catch {
				return [];
			}
		}
		return [];
	});

	const addRecentTool = useCallback((href: string, title: string) => {
		setRecentTools((previous) => {
			const filtered = previous.filter((t) => t.href !== href);
			const updated = [
				{ href, title, visitedAt: Date.now() },
				...filtered,
			].slice(0, MAX_RECENT);
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
