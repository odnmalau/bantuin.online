import { Github } from "lucide-react";
import ThemeToggle from "./ThemeToggle";
import LanguageSwitcher from "./LanguageSwitcher";
import { cn } from "@/lib/utilities";
import { useEffect, useState, type ReactNode } from "react";
import { useTranslation } from "react-i18next";

interface HeaderProps {
	navLeft?: ReactNode;
	actionRight?: ReactNode;
}

const Header = ({ navLeft, actionRight }: HeaderProps): React.JSX.Element => {
	const { t } = useTranslation();
	const [scrolled, setScrolled] = useState(false);

	useEffect((): (() => void) => {
		const handleScroll = (): void => {
			setScrolled(window.scrollY > 20);
		};
		window.addEventListener("scroll", handleScroll);
		return (): void => {
			window.removeEventListener("scroll", handleScroll);
		};
	}, []);

	return (
		<header className="fixed top-0 left-0 right-0 z-50 flex justify-center p-4">
			<div
				className={cn(
					"relative flex items-center justify-between transition-all duration-300 ease-in-out",
					"w-full md:max-w-5xl",
					// Mobile styles (Sticky top feeling)
					"rounded-xl bg-background/70 backdrop-blur-xl border border-white/10 shadow-sm",
					// Desktop styles (Floating Island)
					"md:rounded-full md:px-6 md:py-3",
					scrolled
						? "bg-background/80 shadow-md border-border/50"
						: "bg-background/40 border-transparent"
				)}
			>
				{/* Left Section: Logo + Optional Nav */}
				<div className="flex items-center gap-3">
					{/* Logo - Always Visible */}
					<a className="group flex items-baseline gap-0.5 px-2" href="/">
						<span className="font-display text-xl font-bold tracking-tight text-foreground md:text-2xl group-hover:text-primary transition-colors">
							Bantuin
						</span>
						<span className="font-display text-xl font-bold text-primary md:text-2xl animate-pulse">
							.
						</span>
						<span className="font-body text-lg font-medium text-muted-foreground md:text-xl group-hover:text-foreground transition-colors">
							online
						</span>
					</a>

					{/* Custom Left Nav (e.g. Back Button) - Separated from Logo */}
					{navLeft && (
						<div className="flex items-center gap-3">
							<div className="h-4 w-px bg-border/60" /> {/* Small separator */}
							{navLeft}
						</div>
					)}
				</div>

				{/* Title removed as requested */}

				{/* Right Section: Actions */}
				<div className="flex items-center gap-1 md:gap-2 pr-1">
					{actionRight ? (
						// Custom Action (e.g. Share)
						<>
							<LanguageSwitcher />
							<ThemeToggle />

							<div className="h-6 w-px bg-border/50 mx-1 hidden md:block" />
							{actionRight}
						</>
					) : (
						// Default Actions
						<>
							<LanguageSwitcher />
							<ThemeToggle />

							<div className="h-6 w-px bg-border/50 mx-1 hidden md:block" />
							<a
								className="flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-foreground transition-all hover:bg-muted md:hover:bg-primary/10 md:hover:text-primary"
								href="https://github.com/odnmalau/bantuin.online"
								rel="noopener noreferrer"
								target="_blank"
							>
								<Github className="h-4 w-4" />
								<span className="hidden sm:inline">{t("header.github")}</span>
							</a>
						</>
					)}
				</div>
			</div>
		</header>
	);
};

export default Header;
