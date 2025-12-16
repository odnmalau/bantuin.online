import { useState, useRef, useCallback } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import {
	Shuffle,
	Clipboard,
	Trash2,
	MessageCircle,
	RotateCcw,
	Trophy,
	Sparkles,
} from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";

interface Winner {
	name: string;
	timestamp: Date;
}

const RandomPicker = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [namesInput, setNamesInput] = useState("");
	const [names, setNames] = useState<Array<string>>([]);
	const [winners, setWinners] = useState<Array<Winner>>([]);
	const [winnerCount, setWinnerCount] = useState(1);
	const [removeWinner, setRemoveWinner] = useState(true);
	const [isSpinning, setIsSpinning] = useState(false);
	const [currentDisplay, setCurrentDisplay] = useState<Array<string>>([]);
	const [showConfetti, setShowConfetti] = useState(false);
	const spinIntervalRef = useRef<NodeJS.Timeout | null>(null);

	const parseNames = useCallback((input: string): Array<string> => {
		const parsed = input
			.split(/[,\n]/)
			.map((name) => name.trim())
			.filter((name) => name.length > 0);
		return [...new Set(parsed)]; // Remove duplicates
	}, []);

	const handleInputChange = (value: string): void => {
		setNamesInput(value);
		setNames(parseNames(value));
	};

	const handlePaste = async (): Promise<void> => {
		try {
			const text = await navigator.clipboard.readText();
			const newInput = namesInput ? `${namesInput}\n${text}` : text;
			handleInputChange(newInput);
			toast.success(t("random_picker.toast_paste_success"));
		} catch {
			toast.error(t("random_picker.toast_paste_error"));
		}
	};

	const handleClear = (): void => {
		setNamesInput("");
		setNames([]);
		setWinners([]);
		setCurrentDisplay([]);
	};

	const getRandomNames = (
		pool: Array<string>,
		count: number
	): Array<string> => {
		const shuffled = [...pool];
		const result: Array<string> = [];
		const actualCount = Math.min(count, shuffled.length);

		for (let index = 0; index < actualCount; index++) {
			const randomIndex = Math.floor(Math.random() * shuffled.length);
			result.push(shuffled[randomIndex]!);
			shuffled.splice(randomIndex, 1);
		}
		return result;
	};

	const startSpin = (): void => {
		if (names.length === 0) {
			toast.error(t("random_picker.toast_empty_names"));
			return;
		}

		if (names.length < winnerCount) {
			toast.error(
				t("random_picker.toast_count_error", {
					names: names.length,
					winners: winnerCount,
				})
			);
			return;
		}

		setIsSpinning(true);
		setShowConfetti(false);

		let spinCount = 0;
		const maxSpins = 20;
		const spinSpeed = 100;

		spinIntervalRef.current = setInterval(() => {
			const randomDisplay = getRandomNames(names, winnerCount);
			setCurrentDisplay(randomDisplay);
			spinCount++;

			if (spinCount >= maxSpins) {
				clearInterval(spinIntervalRef.current!);

				// Final selection
				const finalWinners = getRandomNames(names, winnerCount);
				setCurrentDisplay(finalWinners);

				// Add to history
				const newWinners = finalWinners.map((name) => ({
					name,
					timestamp: new Date(),
				}));
				setWinners((previous) => [...newWinners, ...previous]);

				// Remove from list if enabled
				if (removeWinner) {
					const remainingNames = names.filter((n) => !finalWinners.includes(n));
					const remainingInput = remainingNames.join("\n");
					setNamesInput(remainingInput);
					setNames(remainingNames);
				}

				setIsSpinning(false);
				setShowConfetti(true);

				// Play celebration sound (optional)
				toast.success(
					t("random_picker.toast_congrats", { names: finalWinners.join(", ") })
				);

				// Hide confetti after animation
				setTimeout((): void => {
					setShowConfetti(false);
				}, 3000);
			}
		}, spinSpeed);
	};

	const shareToWhatsApp = (): void => {
		if (currentDisplay.length === 0) return;

		const winnersList = currentDisplay
			.map((w, index) => `${index + 1}. ${w}`)
			.join("\n");

		const text = t("random_picker.whatsapp_template", {
			winners: winnersList,
			interpolation: { escapeValue: false },
		});

		const url = `https://wa.me/?text=${encodeURIComponent(text)}`;
		window.open(url, "_blank", "noopener,noreferrer");
	};

	const resetWinners = (): void => {
		setWinners([]);
		setCurrentDisplay([]);
	};

	return (
		<ToolPageLayout
			description={t("random_picker.desc_page")}
			subtitle={t("random_picker.subtitle")}
			title={t("random_picker.title")}
			toolNumber="17"
		>
			<SEOHead
				description={t("random.meta.description")}
				path="/tools/random-picker"
				title={t("random.meta.title")}
				keywords={
					t("random.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="mx-auto max-w-2xl space-y-6">
				{/* Input Section */}
				<div className="space-y-4 rounded-lg border border-border bg-card p-6">
					<div className="flex items-center justify-between">
						<Label className="text-base font-medium">
							{t("random_picker.label_names")}
						</Label>
						<div className="flex gap-2">
							<Button size="sm" variant="outline" onClick={handlePaste}>
								<Clipboard className="mr-2 h-4 w-4" />
								{t("random_picker.btn_paste")}
							</Button>
							<Button size="sm" variant="outline" onClick={handleClear}>
								<Trash2 className="mr-2 h-4 w-4" />
								{t("random_picker.btn_clear")}
							</Button>
						</div>
					</div>
					<Textarea
						className="min-h-[150px] font-mono text-sm"
						placeholder={t("random_picker.placeholder_names")}
						value={namesInput}
						onChange={(event: React.ChangeEvent<HTMLTextAreaElement>): void => {
							handleInputChange(event.target.value);
						}}
					/>
					<p className="text-sm text-muted-foreground">
						{t("random_picker.text_total")}{" "}
						<span className="font-semibold text-foreground">
							{names.length}
						</span>{" "}
						{t("random_picker.text_names")}
					</p>
				</div>

				{/* Options */}
				<div className="grid gap-4 rounded-lg border border-border bg-card p-6 sm:grid-cols-2">
					<div className="space-y-2">
						<Label htmlFor="winnerCount">
							{t("random_picker.label_winner_count")}
						</Label>
						<Input
							id="winnerCount"
							max={names.length || 10}
							min={1}
							type="number"
							value={winnerCount}
							onChange={(event: React.ChangeEvent<HTMLInputElement>): void => {
								setWinnerCount(Math.max(1, parseInt(event.target.value) || 1));
							}}
						/>
					</div>
					<div className="flex items-center justify-between rounded-lg border border-border p-4">
						<div>
							<Label className="cursor-pointer" htmlFor="removeWinner">
								{t("random_picker.label_remove_winner")}
							</Label>
							<p className="text-xs text-muted-foreground">
								{t("random_picker.hint_remove_winner")}
							</p>
						</div>
						<Switch
							checked={removeWinner}
							id="removeWinner"
							onCheckedChange={setRemoveWinner}
						/>
					</div>
				</div>

				{/* Spin Button & Display */}
				<div className="relative overflow-hidden rounded-lg border border-border bg-card p-8">
					{/* Confetti Animation */}
					{showConfetti && (
						<div className="pointer-events-none absolute inset-0 z-10">
							{Array.from({ length: 50 }).map((_, index) => (
								<div
									key={index}
									className="absolute confetti-piece"
									style={{
										left: `${Math.random() * 100}%`,
										animationDelay: `${Math.random() * 0.5}s`,
										backgroundColor: [
											"#FF6B6B",
											"#4ECDC4",
											"#45B7D1",
											"#FED766",
											"#F8B500",
										][Math.floor(Math.random() * 5)],
									}}
								/>
							))}
						</div>
					)}

					{/* Winner Display */}
					<div className="mb-6 min-h-[80px] flex items-center justify-center">
						{currentDisplay.length > 0 ? (
							<div className="text-center">
								{currentDisplay.map((name, index) => (
									<div
										key={index}
										className={`text-2xl font-display font-bold text-primary md:text-3xl ${
											isSpinning ? "animate-pulse blur-[1px]" : ""
										}`}
									>
										{isSpinning ? "🎰" : "🏆"} {name}
									</div>
								))}
							</div>
						) : (
							<p className="text-muted-foreground">
								{t("random_picker.text_empty_state")}
							</p>
						)}
					</div>

					<Button
						className="w-full text-lg"
						disabled={isSpinning || names.length === 0}
						size="lg"
						onClick={startSpin}
					>
						{isSpinning ? (
							<>
								<Sparkles className="mr-2 h-5 w-5 animate-spin" />
								{t("random_picker.btn_shuffling")}
							</>
						) : (
							<>
								<Shuffle className="mr-2 h-5 w-5" />
								{t("random_picker.btn_shuffle")}
							</>
						)}
					</Button>

					{currentDisplay.length > 0 && !isSpinning && (
						<Button
							className="mt-4 w-full"
							variant="outline"
							onClick={shareToWhatsApp}
						>
							<MessageCircle className="mr-2 h-4 w-4" />
							{t("random_picker.btn_whatsapp")}
						</Button>
					)}
				</div>

				{/* History */}
				{winners.length > 0 && (
					<div className="rounded-lg border border-border bg-card p-6">
						<div className="mb-4 flex items-center justify-between">
							<div className="flex items-center gap-2">
								<Trophy className="h-5 w-5 text-primary" />
								<h3 className="font-display font-semibold">
									{t("random_picker.card_history")}
								</h3>
							</div>
							<Button size="sm" variant="ghost" onClick={resetWinners}>
								<RotateCcw className="mr-2 h-4 w-4" />
								{t("random_picker.btn_reset")}
							</Button>
						</div>
						<div className="space-y-2">
							{winners.slice(0, 10).map((winner, index) => (
								<div
									key={index}
									className="flex items-center justify-between rounded-lg bg-secondary/50 px-4 py-2"
								>
									<span className="font-medium">{winner.name}</span>
									<span className="text-xs text-muted-foreground">
										{winner.timestamp.toLocaleTimeString("id-ID")}
									</span>
								</div>
							))}
						</div>
					</div>
				)}
			</div>
		</ToolPageLayout>
	);
};

export default RandomPicker;
