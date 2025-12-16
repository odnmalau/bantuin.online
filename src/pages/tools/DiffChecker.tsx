import { useState, useMemo } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";

import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
	ArrowLeftRight,
	GitCompare,
	Plus,
	Minus,
	Equal,
	Copy,
	Check,
} from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";

type DiffMode = "line" | "word" | "char";
type DiffType = "added" | "removed" | "unchanged";

interface DiffPart {
	text: string;
	type: DiffType;
}

const diffLines = (
	oldText: string,
	newText: string,
	ignoreWhitespace: boolean,
	ignoreCase: boolean
): Array<DiffPart> => {
	const originalOldLines = oldText.split("\n");
	const originalNewLines = newText.split("\n");
	let oldLines = [...originalOldLines];
	let newLines = [...originalNewLines];

	if (ignoreWhitespace) {
		oldLines = oldLines.map((l) => l.trim());
		newLines = newLines.map((l) => l.trim());
	}

	if (ignoreCase) {
		oldLines = oldLines.map((l) => l.toLowerCase());
		newLines = newLines.map((l) => l.toLowerCase());
	}

	const result: Array<DiffPart> = [];
	const oldSet = new Set(oldLines);
	const newSet = new Set(newLines);

	// Simple diff algorithm
	let oldIndex = 0;
	let newIndex = 0;

	while (oldIndex < oldLines.length || newIndex < newLines.length) {
		const oldLine = oldLines[oldIndex];
		const newLine = newLines[newIndex];

		if (oldIndex >= oldLines.length) {
			result.push({ text: originalNewLines[newIndex] ?? "", type: "added" });
			newIndex++;
		} else if (newIndex >= newLines.length) {
			result.push({ text: originalOldLines[oldIndex] ?? "", type: "removed" });
			oldIndex++;
		} else if (oldLine === newLine) {
			result.push({
				text: originalOldLines[oldIndex] ?? "",
				type: "unchanged",
			});
			oldIndex++;
			newIndex++;
		} else if (newSet.has(oldLine!) && oldLine !== undefined) {
			result.push({ text: originalNewLines[newIndex] ?? "", type: "added" });
			newIndex++;
		} else if (oldSet.has(newLine!) && newLine !== undefined) {
			result.push({ text: originalOldLines[oldIndex] ?? "", type: "removed" });
			oldIndex++;
		} else {
			result.push({ text: originalOldLines[oldIndex] ?? "", type: "removed" });
			result.push({ text: originalNewLines[newIndex] ?? "", type: "added" });
			oldIndex++;
			newIndex++;
		}
	}

	return result;
};

const diffWords = (
	oldText: string,
	newText: string,
	ignoreWhitespace: boolean,
	ignoreCase: boolean
): Array<DiffPart> => {
	const originalOldWords = oldText.split(/(\s+)/);
	const originalNewWords = newText.split(/(\s+)/);
	let oldWords = [...originalOldWords];
	let newWords = [...originalNewWords];

	if (ignoreWhitespace) {
		oldWords = oldWords.filter((w) => w.trim());
		newWords = newWords.filter((w) => w.trim());
		// Note: Filtering changes indices, so we can't directly map back to original words comfortably by index if we filter.
		// However, the original code logic was flawed if it filtered but tried to access by index.
		// Let's assume for now we just want to compare filtered versions.
		// Actually, if we filter, we lose 1:1 mapping. The previous logic was:
		// oldWords = oldWords.filter((w) => w.trim());
		// result.push({ text: oldWords[oldIndex], ... })
		// So it was using the FILTERED words for display too?
		// "result.push({ text: oldWords[oldIndex], type: "removed" });"
		// Yes, it was using the filtered array for display. So we don't need "originalOldWords" if we filter.
		// But wait, the original code had:
		// let oldWords = oldText.split(/(\s+)/);
		// if (ignoreWhitespace) oldWords = oldWords.filter((w) => w.trim());
		// then used oldWords[oldIndex]
		// So we can just use the processed arrays for display too.
	}

	const compareOld = ignoreCase
		? oldWords.map((w) => w.toLowerCase())
		: oldWords;
	const compareNew = ignoreCase
		? newWords.map((w) => w.toLowerCase())
		: newWords;

	const result: Array<DiffPart> = [];
	const newSet = new Set(compareNew);
	const oldSet = new Set(compareOld);

	let oldIndex = 0;
	let newIndex = 0;

	while (oldIndex < compareOld.length || newIndex < compareNew.length) {
		const oldWord = compareOld[oldIndex];
		const newWord = compareNew[newIndex];

		if (oldIndex >= compareOld.length) {
			result.push({ text: newWords[newIndex] ?? "", type: "added" });
			newIndex++;
		} else if (newIndex >= compareNew.length) {
			result.push({ text: oldWords[oldIndex] ?? "", type: "removed" });
			oldIndex++;
		} else if (oldWord === newWord) {
			result.push({ text: oldWords[oldIndex] ?? "", type: "unchanged" });
			oldIndex++;
			newIndex++;
		} else if (newSet.has(oldWord!) && oldWord !== undefined) {
			result.push({ text: newWords[newIndex] ?? "", type: "added" });
			newIndex++;
		} else if (oldSet.has(newWord!) && newWord !== undefined) {
			result.push({ text: oldWords[oldIndex] ?? "", type: "removed" });
			oldIndex++;
		} else {
			result.push({ text: oldWords[oldIndex] ?? "", type: "removed" });
			result.push({ text: newWords[newIndex] ?? "", type: "added" });
			oldIndex++;
			newIndex++;
		}
	}

	return result;
};

const diffChars = (
	oldText: string,
	newText: string,
	ignoreCase: boolean
): Array<DiffPart> => {
	const compareOld = ignoreCase ? oldText.toLowerCase() : oldText;
	const compareNew = ignoreCase ? newText.toLowerCase() : newText;

	const result: Array<DiffPart> = [];
	let oldIndex = 0;
	let newIndex = 0;

	while (oldIndex < compareOld.length || newIndex < compareNew.length) {
		if (oldIndex >= compareOld.length) {
			result.push({ text: newText[newIndex] ?? "", type: "added" });
			newIndex++;
		} else if (newIndex >= compareNew.length) {
			result.push({ text: oldText[oldIndex] ?? "", type: "removed" });
			oldIndex++;
		} else if (compareOld[oldIndex] === compareNew[newIndex]) {
			result.push({ text: oldText[oldIndex] ?? "", type: "unchanged" });
			oldIndex++;
			newIndex++;
		} else {
			result.push({ text: oldText[oldIndex] ?? "", type: "removed" });
			result.push({ text: newText[newIndex] ?? "", type: "added" });
			oldIndex++;
			newIndex++;
		}
	}

	// Merge consecutive same-type parts
	const merged: Array<DiffPart> = [];
	for (const part of result) {
		const lastMerged = merged[merged.length - 1];
		if (lastMerged && lastMerged.type === part.type) {
			lastMerged.text += part.text;
		} else {
			merged.push({ ...part });
		}
	}

	return merged;
};

const DiffChecker = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [oldText, setOldText] = useState("");
	const [newText, setNewText] = useState("");
	const [diffMode, setDiffMode] = useState<DiffMode>("line");
	const [ignoreWhitespace, setIgnoreWhitespace] = useState(false);
	const [ignoreCase, setIgnoreCase] = useState(false);
	const [copied, setCopied] = useState(false);

	const diffResult = useMemo(() => {
		if (!oldText && !newText) return [];

		switch (diffMode) {
			case "line":
				return diffLines(oldText, newText, ignoreWhitespace, ignoreCase);
			case "word":
				return diffWords(oldText, newText, ignoreWhitespace, ignoreCase);
			case "char":
				return diffChars(oldText, newText, ignoreCase);
			default:
				return [];
		}
	}, [oldText, newText, diffMode, ignoreWhitespace, ignoreCase]);

	const stats = useMemo(() => {
		const added = diffResult.filter((d) => d.type === "added").length;
		const removed = diffResult.filter((d) => d.type === "removed").length;
		const unchanged = diffResult.filter((d) => d.type === "unchanged").length;
		return { added, removed, unchanged };
	}, [diffResult]);

	const handleSwap = (): void => {
		setOldText(newText);
		setNewText(oldText);
	};

	const handleClear = (): void => {
		setOldText("");
		setNewText("");
	};

	const handleCopyResult = async (): Promise<void> => {
		const text = diffResult
			.map((d) => {
				const prefix =
					d.type === "added" ? "+ " : d.type === "removed" ? "- " : "  ";
				return prefix + d.text;
			})
			.join(diffMode === "line" ? "\n" : "");

		await navigator.clipboard.writeText(text);
		setCopied(true);
		toast.success(t("diff.toast_copy"));
		setTimeout(() => {
			setCopied(false);
		}, 2000);
	};

	return (
		<ToolPageLayout
			description={t("tool_items.diff_checker.desc")}
			subtitle={t("diff.subtitle")}
			title={t("tool_items.diff_checker.title")}
			toolNumber="22"
		>
			<SEOHead
				description={t("diff.meta.description")}
				path="/tools/diff-checker"
				title={t("diff.meta.title")}
				keywords={
					t("diff.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="mx-auto max-w-5xl space-y-6">
				{/* Input Section */}
				<div className="grid gap-4 md:grid-cols-2">
					<div className="space-y-2">
						<Label className="text-base font-medium">
							{t("diff.label_old")}
						</Label>
						<Textarea
							className="min-h-[200px] font-mono text-sm"
							placeholder={t("diff.placeholder_old")}
							value={oldText}
							onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) => {
								setOldText(event.target.value);
							}}
						/>
					</div>
					<div className="space-y-2">
						<Label className="text-base font-medium">
							{t("diff.label_new")}
						</Label>
						<Textarea
							className="min-h-[200px] font-mono text-sm"
							placeholder={t("diff.placeholder_new")}
							value={newText}
							onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) => {
								setNewText(event.target.value);
							}}
						/>
					</div>
				</div>

				{/* Actions & Options */}
				<div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-border bg-card p-4">
					<div className="flex flex-wrap gap-2">
						<Button size="sm" variant="outline" onClick={handleSwap}>
							<ArrowLeftRight className="mr-2 h-4 w-4" />
							{t("diff.btn_swap")}
						</Button>
						<Button size="sm" variant="outline" onClick={handleClear}>
							{t("diff.btn_clear")}
						</Button>
					</div>

					<div className="flex flex-wrap items-center gap-4">
						<RadioGroup
							className="flex gap-4"
							value={diffMode}
							onValueChange={(v) => {
								setDiffMode(v as DiffMode);
							}}
						>
							<div className="flex items-center space-x-2">
								<RadioGroupItem id="line" value="line" />
								<Label className="cursor-pointer font-normal" htmlFor="line">
									{t("diff.mode_line")}
								</Label>
							</div>
							<div className="flex items-center space-x-2">
								<RadioGroupItem id="word" value="word" />
								<Label className="cursor-pointer font-normal" htmlFor="word">
									{t("diff.mode_word")}
								</Label>
							</div>
							<div className="flex items-center space-x-2">
								<RadioGroupItem id="char" value="char" />
								<Label className="cursor-pointer font-normal" htmlFor="char">
									{t("diff.mode_char")}
								</Label>
							</div>
						</RadioGroup>
					</div>
				</div>

				{/* Toggle Options */}
				<div className="flex flex-wrap gap-6">
					<div className="flex items-center space-x-2">
						<Switch
							checked={ignoreWhitespace}
							id="ignore-whitespace"
							onCheckedChange={setIgnoreWhitespace}
						/>
						<Label className="cursor-pointer" htmlFor="ignore-whitespace">
							{t("diff.opt_space")}
						</Label>
					</div>
					<div className="flex items-center space-x-2">
						<Switch
							checked={ignoreCase}
							id="ignore-case"
							onCheckedChange={setIgnoreCase}
						/>
						<Label className="cursor-pointer" htmlFor="ignore-case">
							{t("diff.opt_case")}
						</Label>
					</div>
				</div>

				{/* Stats */}
				{diffResult.length > 0 && (
					<div className="flex flex-wrap gap-4">
						<div className="flex items-center gap-2 rounded-lg bg-green-500/10 px-3 py-2 text-green-600 dark:text-green-400">
							<Plus className="h-4 w-4" />
							<span className="text-sm font-medium">
								{stats.added} {t("diff.stat_added")}
							</span>
						</div>
						<div className="flex items-center gap-2 rounded-lg bg-red-500/10 px-3 py-2 text-red-600 dark:text-red-400">
							<Minus className="h-4 w-4" />
							<span className="text-sm font-medium">
								{stats.removed} {t("diff.stat_removed")}
							</span>
						</div>
						<div className="flex items-center gap-2 rounded-lg bg-secondary px-3 py-2 text-muted-foreground">
							<Equal className="h-4 w-4" />
							<span className="text-sm font-medium">
								{stats.unchanged} {t("diff.stat_unchanged")}
							</span>
						</div>
					</div>
				)}

				{/* Result */}
				{diffResult.length > 0 && (
					<div className="space-y-3 rounded-lg border border-border bg-card">
						<div className="flex items-center justify-between border-b border-border px-4 py-3">
							<div className="flex items-center gap-2">
								<GitCompare className="h-5 w-5 text-primary" />
								<Label className="text-base font-medium">
									{t("diff.res_title")}
								</Label>
							</div>
							<Button size="sm" variant="outline" onClick={handleCopyResult}>
								{copied ? (
									<>
										<Check className="mr-2 h-4 w-4" />
										{t("diff.btn_copied")}
									</>
								) : (
									<>
										<Copy className="mr-2 h-4 w-4" />
										{t("diff.btn_copy")}
									</>
								)}
							</Button>
						</div>
						<div className="max-h-[400px] overflow-auto p-4">
							<div
								className={`font-mono text-sm ${diffMode === "line" ? "space-y-1" : "leading-relaxed"}`}
							>
								{diffResult.map((part, index) => {
									let className = "";
									if (part.type === "added") {
										className =
											"bg-green-500/20 text-green-700 dark:text-green-300";
									} else if (part.type === "removed") {
										className =
											"bg-red-500/20 text-red-700 dark:text-red-300 line-through";
									}

									if (diffMode === "line") {
										return (
											<div
												key={index}
												className={`rounded px-2 py-0.5 ${className}`}
											>
												<span className="mr-2 text-muted-foreground">
													{part.type === "added"
														? "+"
														: part.type === "removed"
															? "-"
															: " "}
												</span>
												{part.text || " "}
											</div>
										);
									}

									return (
										<span
											key={index}
											className={`${className} ${className ? "rounded px-0.5" : ""}`}
										>
											{part.text}
										</span>
									);
								})}
							</div>
						</div>
					</div>
				)}

				{/* Empty State */}
				{!oldText && !newText && (
					<div className="rounded-lg border border-dashed border-border p-12 text-center">
						<GitCompare className="mx-auto mb-4 h-12 w-12 text-muted-foreground/50" />
						<h3 className="text-lg font-medium">{t("diff.empty_title")}</h3>
						<p className="mt-1 text-sm text-muted-foreground">
							{t("diff.empty_desc")}
						</p>
					</div>
				)}
			</div>
		</ToolPageLayout>
	);
};

export default DiffChecker;
