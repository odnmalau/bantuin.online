import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Copy, RotateCcw, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

const TextCaseConverter = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [text, setText] = useState("");
	const [copied, setCopied] = useState(false);
	const { toast } = useToast();

	const convertCase = (type: string): void => {
		if (!text.trim()) {
			toast({
				title: t("text_case.toast_empty"),
				description: t("text_case.toast_empty_desc"),
				variant: "destructive",
			});
			return;
		}

		let result = "";
		switch (type) {
			case "upper":
				result = text.toUpperCase();
				break;
			case "lower":
				result = text.toLowerCase();
				break;
			case "title":
				result = text
					.toLowerCase()
					.split(" ")
					.map((word) => word.charAt(0).toUpperCase() + word.slice(1))
					.join(" ");
				break;
			case "sentence":
				result = text
					.toLowerCase()
					.replace(/(^\s*\w|[.!?]\s*\w)/g, (c: string): string =>
						c.toUpperCase()
					);
				break;
			case "camel":
				result = text
					.toLowerCase()
					.replace(/[^a-zA-Z0-9]+(.)/g, (_: string, char: string): string =>
						char.toUpperCase()
					);
				break;
			case "snake":
				result = text
					.toLowerCase()
					.replace(/\s+/g, "_")
					.replace(/[^a-zA-Z0-9_]/g, "");
				break;
			case "kebab":
				result = text
					.toLowerCase()
					.replace(/\s+/g, "-")
					.replace(/[^a-zA-Z0-9-]/g, "");
				break;
			default:
				result = text;
		}
		setText(result);
	};

	const handleCopy = async (): Promise<void> => {
		if (!text.trim()) return;

		await navigator.clipboard.writeText(text);
		setCopied(true);
		toast({
			title: t("text_case.toast_copied"),
			description: t("text_case.toast_copied_desc"),
		});
		setTimeout((): void => {
			setCopied(false);
		}, 2000);
	};

	const handleReset = (): void => {
		setText("");
	};

	const caseButtons = [
		{ type: "upper", label: "UPPERCASE" },
		{ type: "lower", label: "lowercase" },
		{ type: "title", label: "Title Case" },
		{ type: "sentence", label: "Sentence case" },
		{ type: "camel", label: "camelCase" },
		{ type: "snake", label: "snake_case" },
		{ type: "kebab", label: "kebab-case" },
	];

	return (
		<ToolPageLayout
			description={t("text_case.desc_page")}
			subtitle={t("text_case.subtitle")}
			title={t("text_case.title")}
			toolNumber="06"
		>
			<SEOHead
				description={t("text_case.meta.description")}
				path="/tools/text-case"
				title={t("text_case.meta.title")}
				keywords={
					t("text_case.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="space-y-6">
				{/* Input Area */}
				<div className="space-y-2">
					<label className="text-sm font-medium text-foreground">
						{t("text_case.label_input")}
					</label>
					<Textarea
						className="min-h-[200px] resize-none font-body"
						placeholder={t("text_case.placeholder_input")}
						value={text}
						onChange={(event: React.ChangeEvent<HTMLTextAreaElement>): void => {
							setText(event.target.value);
						}}
					/>
					<p className="text-xs text-muted-foreground">
						{text.length} {t("text_case.text_chars")}
					</p>
				</div>

				{/* Case Buttons */}
				<div className="space-y-2">
					<label className="text-sm font-medium text-foreground">
						{t("text_case.label_select")}
					</label>
					<div className="flex flex-wrap gap-2">
						{caseButtons.map((button) => (
							<Button
								key={button.type}
								className="font-mono text-xs"
								size="sm"
								variant="outline"
								onClick={(): void => {
									convertCase(button.type);
								}}
							>
								{button.label}
							</Button>
						))}
					</div>
				</div>

				{/* Action Buttons */}
				<div className="flex gap-3">
					<Button
						className="flex-1"
						disabled={!text.trim()}
						onClick={handleCopy}
					>
						{copied ? (
							<Check className="mr-2 h-4 w-4" />
						) : (
							<Copy className="mr-2 h-4 w-4" />
						)}
						{copied ? t("text_case.btn_copied") : t("text_case.btn_copy")}
					</Button>
					<Button
						disabled={!text.trim()}
						variant="outline"
						onClick={handleReset}
					>
						<RotateCcw className="mr-2 h-4 w-4" />
						{t("text_case.btn_reset")}
					</Button>
				</div>
			</div>
		</ToolPageLayout>
	);
};

export default TextCaseConverter;
