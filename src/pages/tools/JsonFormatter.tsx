import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Copy, Check, RotateCcw, AlertCircle, CheckCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

const JsonFormatter = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [input, setInput] = useState("");
	const [output, setOutput] = useState("");
	const [error, setError] = useState("");
	const [isValid, setIsValid] = useState<boolean | null>(null);
	const [copied, setCopied] = useState(false);
	const { toast } = useToast();

	const formatJson = (indent: number = 2): void => {
		if (!input.trim()) {
			toast({
				title: t("json_formatter.toast_input_empty"),
				description: t("json_formatter.toast_input_empty_desc"),
				variant: "destructive",
			});
			return;
		}

		try {
			const parsed = JSON.parse(input) as unknown;
			const formatted = JSON.stringify(parsed, null, indent);
			setOutput(formatted);
			setError("");
			setIsValid(true);
		} catch (error: unknown) {
			const errorMessage =
				error instanceof Error ? error.message : "Invalid JSON";
			setError(errorMessage);
			setOutput("");
			setIsValid(false);
		}
	};

	const minifyJson = (): void => {
		if (!input.trim()) {
			toast({
				title: t("json_formatter.toast_input_empty"),
				description: t("json_formatter.toast_input_empty_desc"),
				variant: "destructive",
			});
			return;
		}

		try {
			const parsed = JSON.parse(input) as unknown;
			const minified = JSON.stringify(parsed);
			setOutput(minified);
			setError("");
			setIsValid(true);
		} catch (error: unknown) {
			const errorMessage =
				error instanceof Error ? error.message : "Invalid JSON";
			setError(errorMessage);
			setOutput("");
			setIsValid(false);
		}
	};

	const validateJson = (): void => {
		if (!input.trim()) {
			toast({
				title: t("json_formatter.toast_input_empty"),
				description: t("json_formatter.toast_input_empty_desc"),
				variant: "destructive",
			});
			return;
		}

		try {
			JSON.parse(input);
			setIsValid(true);
			setError("");
			toast({
				title: t("json_formatter.toast_valid_title"),
				description: t("json_formatter.toast_valid_desc"),
			});
		} catch (error: unknown) {
			const errorMessage =
				error instanceof Error ? error.message : "Invalid JSON";
			setError(errorMessage);
			setIsValid(false);
		}
	};

	const handleCopy = async (): Promise<void> => {
		if (!output.trim()) return;

		await navigator.clipboard.writeText(output);
		setCopied(true);
		toast({
			title: t("json_formatter.toast_copied"),
			description: t("json_formatter.toast_copied_desc"),
		});
		setTimeout((): void => {
			setCopied(false);
		}, 2000);
	};

	const handleReset = (): void => {
		setInput("");
		setOutput("");
		setError("");
		setIsValid(null);
	};

	return (
		<ToolPageLayout
			description={t("json_formatter.desc_page")}
			subtitle={t("json_formatter.subtitle")}
			title={t("json_formatter.title")}
			toolNumber="09"
		>
			<SEOHead
				description={t("json_formatter.meta.description")}
				path="/tools/json"
				title={t("json_formatter.meta.title")}
				keywords={
					t("json_formatter.meta.keywords", {
						returnObjects: true,
					}) as Array<string>
				}
			/>
			<div className="space-y-6">
				{/* Input Area */}
				<div className="space-y-2">
					<label className="text-sm font-medium text-foreground">
						{t("json_formatter.label_input")}
					</label>
					<Textarea
						className="min-h-[150px] resize-none font-mono text-sm"
						placeholder={t("json_formatter.placeholder_input")}
						value={input}
						onChange={(event: React.ChangeEvent<HTMLTextAreaElement>): void => {
							setInput(event.target.value);
							setIsValid(null);
							setError("");
						}}
					/>
				</div>

				{/* Action Buttons */}
				<div className="flex flex-wrap gap-2">
					<Button
						size="sm"
						variant="outline"
						onClick={(): void => {
							formatJson(2);
						}}
					>
						{t("json_formatter.btn_format_2")}
					</Button>
					<Button
						size="sm"
						variant="outline"
						onClick={(): void => {
							formatJson(4);
						}}
					>
						{t("json_formatter.btn_format_4")}
					</Button>
					<Button size="sm" variant="outline" onClick={minifyJson}>
						{t("json_formatter.btn_minify")}
					</Button>
					<Button size="sm" variant="outline" onClick={validateJson}>
						{t("json_formatter.btn_validate")}
					</Button>
				</div>

				{/* Validation Status */}
				{isValid !== null && (
					<div
						className={`flex items-center gap-2 rounded-lg border p-3 ${
							isValid
								? "border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400"
								: "border-destructive/30 bg-destructive/10 text-destructive"
						}`}
					>
						{isValid ? (
							<>
								<CheckCircle className="h-4 w-4" />
								<span className="text-sm font-medium">
									{t("json_formatter.status_valid")}
								</span>
							</>
						) : (
							<>
								<AlertCircle className="h-4 w-4" />
								<span className="text-sm">{error}</span>
							</>
						)}
					</div>
				)}

				{/* Output Area */}
				{output && (
					<div className="space-y-2">
						<label className="text-sm font-medium text-foreground">
							{t("json_formatter.label_output")}
						</label>
						<Textarea
							readOnly
							className="min-h-[200px] resize-none bg-secondary/30 font-mono text-sm"
							value={output}
						/>
					</div>
				)}

				{/* Copy & Reset Buttons */}
				<div className="flex gap-3">
					<Button
						className="flex-1"
						disabled={!output.trim()}
						onClick={handleCopy}
					>
						{copied ? (
							<Check className="mr-2 h-4 w-4" />
						) : (
							<Copy className="mr-2 h-4 w-4" />
						)}
						{copied
							? t("json_formatter.toast_copied")
							: t("json_formatter.btn_copy_output")}
					</Button>
					<Button
						disabled={!input.trim() && !output.trim()}
						variant="outline"
						onClick={handleReset}
					>
						<RotateCcw className="mr-2 h-4 w-4" />
						{t("json_formatter.btn_reset")}
					</Button>
				</div>
			</div>
		</ToolPageLayout>
	);
};

export default JsonFormatter;
