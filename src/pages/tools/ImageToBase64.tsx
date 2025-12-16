import { useState, useRef } from "react";
import { useTranslation } from "react-i18next";
import { Upload, Copy, Check, RotateCcw, FileImage } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

const ImageToBase64 = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [base64, setBase64] = useState("");
	const [preview, setPreview] = useState("");
	const [fileName, setFileName] = useState("");
	const [fileSize, setFileSize] = useState("");
	const [copied, setCopied] = useState(false);
	const [copiedDataUrl, setCopiedDataUrl] = useState(false);
	const fileInputRef = useRef<HTMLInputElement>(null);
	const { toast } = useToast();

	const formatFileSize = (bytes: number): string => {
		if (bytes < 1024) return bytes + " B";
		if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + " KB";
		return (bytes / (1024 * 1024)).toFixed(2) + " MB";
	};

	const handleFileChange = (
		event: React.ChangeEvent<HTMLInputElement>
	): void => {
		const file = event.target.files?.[0];
		if (!file) return;

		if (!file.type.startsWith("image/")) {
			toast({
				title: t("base64.toast_invalid"),
				description: t("base64.toast_invalid_desc"),
				variant: "destructive",
			});
			return;
		}

		if (file.size > 5 * 1024 * 1024) {
			toast({
				title: t("base64.toast_large"),
				description: t("base64.toast_large_desc"),
				variant: "destructive",
			});
			return;
		}

		setFileName(file.name);
		setFileSize(formatFileSize(file.size));

		const reader = new FileReader();
		reader.onload = (event): void => {
			const result = event.target?.result as string;
			setPreview(result);
			// Extract base64 without the data URL prefix
			const base64Data = result.split(",")[1]!;
			setBase64(base64Data);
		};
		reader.readAsDataURL(file);
	};

	const handleCopyBase64 = async (): Promise<void> => {
		if (!base64) return;

		await navigator.clipboard.writeText(base64);
		setCopied(true);
		toast({
			title: t("base64.toast_copied"),
			description: t("base64.toast_base64_desc"),
		});
		setTimeout((): void => {
			setCopied(false);
		}, 2000);
	};

	const handleCopyDataUrl = async (): Promise<void> => {
		if (!preview) return;

		await navigator.clipboard.writeText(preview);
		setCopiedDataUrl(true);
		toast({
			title: t("base64.toast_copied"),
			description: t("base64.toast_url_desc"),
		});
		setTimeout((): void => {
			setCopiedDataUrl(false);
		}, 2000);
	};

	const handleReset = (): void => {
		setBase64("");
		setPreview("");
		setFileName("");
		setFileSize("");
		if (fileInputRef.current) {
			fileInputRef.current.value = "";
		}
	};

	return (
		<ToolPageLayout
			description={t("tool_items.image_base64.desc")}
			subtitle={t("base64.subtitle")}
			title={t("tool_items.image_base64.title")}
			toolNumber="10"
		>
			<SEOHead
				description={t("base64.meta.description")}
				path="/tools/base64"
				title={t("base64.meta.title")}
				keywords={
					t("base64.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="space-y-6">
				{/* Upload Area */}
				<div
					className="cursor-pointer rounded-lg border-2 border-dashed border-border bg-secondary/20 p-8 text-center transition-colors hover:border-primary/50 hover:bg-secondary/40"
					onClick={(): void => fileInputRef.current?.click()}
				>
					<input
						ref={fileInputRef}
						accept="image/*"
						className="hidden"
						type="file"
						onChange={handleFileChange}
					/>
					<Upload className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
					<p className="text-sm font-medium text-foreground">
						{t("base64.click_upload")}
					</p>
					<p className="mt-1 text-xs text-muted-foreground">
						{t("base64.format_info")}
					</p>
				</div>

				{/* Preview */}
				{preview && (
					<div className="animate-fade-in space-y-4">
						{/* Image Preview */}
						<div className="rounded-lg border border-border bg-card p-4">
							<div className="mb-3 flex items-center justify-between">
								<div className="flex items-center gap-2">
									<FileImage className="h-4 w-4 text-primary" />
									<span className="text-sm font-medium text-foreground">
										{fileName}
									</span>
								</div>
								<span className="text-xs text-muted-foreground">
									{fileSize}
								</span>
							</div>
							<div className="flex justify-center rounded-lg bg-secondary/30 p-4">
								<img
									alt="Preview"
									className="max-h-48 max-w-full rounded object-contain"
									src={preview}
								/>
							</div>
						</div>

						{/* Base64 Output */}
						<div className="space-y-2">
							<div className="flex items-center justify-between">
								<label className="text-sm font-medium text-foreground">
									{t("base64.label_base64")}
								</label>
								<span className="text-xs text-muted-foreground">
									{base64.length.toLocaleString()}{" "}
									{t("word_counter.stats_chars") || "chars"}
								</span>
							</div>
							<Textarea
								readOnly
								className="min-h-[120px] resize-none bg-secondary/30 font-mono text-xs"
								value={base64}
							/>
						</div>

						{/* Action Buttons */}
						<div className="grid grid-cols-2 gap-3">
							<Button variant="outline" onClick={handleCopyBase64}>
								{copied ? (
									<Check className="mr-2 h-4 w-4" />
								) : (
									<Copy className="mr-2 h-4 w-4" />
								)}
								{copied
									? t("base64.toast_copied")
									: t("base64.btn_copy_base64")}
							</Button>
							<Button variant="outline" onClick={handleCopyDataUrl}>
								{copiedDataUrl ? (
									<Check className="mr-2 h-4 w-4" />
								) : (
									<Copy className="mr-2 h-4 w-4" />
								)}
								{copiedDataUrl
									? t("base64.toast_copied")
									: t("base64.btn_copy_url")}
							</Button>
						</div>

						{/* Reset Button */}
						<Button className="w-full" variant="outline" onClick={handleReset}>
							<RotateCcw className="mr-2 h-4 w-4" />
							{t("base64.btn_reset")}
						</Button>
					</div>
				)}

				{/* Empty State */}
				{!preview && (
					<div className="rounded-lg border border-border bg-secondary/20 p-6">
						<h3 className="mb-2 font-display text-sm font-semibold text-foreground">
							{t("base64.use_title")}
						</h3>
						<ul className="space-y-1 text-sm text-muted-foreground">
							<li>{t("base64.use_1")}</li>
							<li>{t("base64.use_2")}</li>
							<li>{t("base64.use_3")}</li>
							<li>{t("base64.use_4")}</li>
						</ul>
					</div>
				)}
			</div>
		</ToolPageLayout>
	);
};

export default ImageToBase64;
