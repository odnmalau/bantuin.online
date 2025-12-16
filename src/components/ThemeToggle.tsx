import { Moon, Sun } from "lucide-react";
import { useTheme } from "next-themes";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import {
	Tooltip,
	TooltipContent,
	TooltipTrigger,
} from "@/components/ui/tooltip";

const ThemeToggle = (): React.JSX.Element | null => {
	const { theme, setTheme } = useTheme();
	const [mounted, setMounted] = useState(false);

	// Prevent hydration mismatch
	useEffect(() => {
		// eslint-disable-next-line react-hooks/set-state-in-effect
		setMounted(true);
	}, []);

	if (!mounted) {
		return (
			<Button disabled className="h-9 w-9" size="icon" variant="ghost">
				<span className="h-4 w-4" />
			</Button>
		);
	}

	const isDark = theme === "dark";

	return (
		<Tooltip>
			<TooltipTrigger asChild>
				<Button
					aria-label={isDark ? "Aktifkan mode terang" : "Aktifkan mode gelap"}
					className="h-9 w-9 transition-transform duration-200 hover:scale-110"
					size="icon"
					variant="ghost"
					onClick={() => {
						setTheme(isDark ? "light" : "dark");
					}}
				>
					<Sun
						className={`h-4 w-4 transition-all duration-300 ${
							isDark
								? "rotate-0 scale-100 opacity-100"
								: "rotate-90 scale-0 opacity-0"
						} absolute`}
					/>
					<Moon
						className={`h-4 w-4 transition-all duration-300 ${
							isDark
								? "-rotate-90 scale-0 opacity-0"
								: "rotate-0 scale-100 opacity-100"
						}`}
					/>
				</Button>
			</TooltipTrigger>
			<TooltipContent>
				<p>{isDark ? "Mode Terang" : "Mode Gelap"}</p>
			</TooltipContent>
		</Tooltip>
	);
};

export default ThemeToggle;
