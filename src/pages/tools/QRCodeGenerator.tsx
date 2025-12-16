import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import QRCode from "qrcode";

import { Download, Copy, QrCode } from "lucide-react";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { useToast } from "@/hooks/use-toast";
import { SEOHead } from "@/components/SEOHead";

const sizes = [
	{ label: "Kecil", value: 128 },
	{ label: "Sedang", value: 256 },
	{ label: "Besar", value: 512 },
] as const;

const QRCodeGenerator = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [text, setText] = useState("");
	const [size, setSize] = useState<128 | 256 | 512>(256);
	const [fgColor, setFgColor] = useState("#000000");
	const [bgColor, setBgColor] = useState("#FFFFFF");
	const [qrDataUrl, setQrDataUrl] = useState("");
	const { toast } = useToast();

	useEffect((): (() => void) | undefined => {
		const generateQR = async (): Promise<void> => {
			try {
				const url = await QRCode.toDataURL(text, {
					width: size,
					margin: 2,
					color: { dark: fgColor, light: bgColor },
				});
				setQrDataUrl(url);
			} catch (error: unknown) {
				console.error("QR generation error:", error);
			}
		};

		const timeout = setTimeout((): void => {
			void generateQR();
		}, 300);
		return (): void => {
			clearTimeout(timeout);
		};
	}, [text, size, fgColor, bgColor]);

	const handleDownload = (): void => {
		if (!qrDataUrl) return;
		const link = document.createElement("a");
		link.download = "qrcode.png";
		link.href = qrDataUrl;
		link.click();
		toast({
			title: t("qrcode.toast_success"),
			description: t("qrcode.toast_download_desc"),
		});
	};

	const handleCopy = async (): Promise<void> => {
		if (!qrDataUrl) return;
		try {
			const blob = await fetch(qrDataUrl).then((r): Promise<Blob> => r.blob());
			await navigator.clipboard.write([
				new ClipboardItem({ "image/png": blob }),
			]);
			toast({
				title: t("qrcode.toast_success"),
				description: t("qrcode.toast_copy_desc"),
			});
		} catch {
			toast({
				title: t("qrcode.toast_copy_fail"),
				description: t("qrcode.toast_copy_fail_desc"),
				variant: "destructive",
			});
		}
	};

	return (
		<ToolPageLayout
			description={t("tool_items.qr_code.desc")}
			subtitle={t("qrcode.subtitle")}
			title={t("tool_items.qr_code.title")}
			toolNumber="05"
		>
			<SEOHead
				description={t("qrcode.meta.description")}
				path="/tools/qrcode"
				title={t("qrcode.meta.title")}
				keywords={
					t("qrcode.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="space-y-6">
				{/* Input Text/URL */}
				<div className="space-y-2">
					<Label htmlFor="qr-text">{t("qrcode.label_text")}</Label>
					<Input
						className="text-base"
						id="qr-text"
						placeholder={t("qrcode.placeholder_text")}
						type="text"
						value={text}
						onChange={(event: React.ChangeEvent<HTMLInputElement>): void => {
							const newText = event.target.value;
							setText(newText);
							if (!newText.trim()) setQrDataUrl("");
						}}
					/>
				</div>

				{/* Size Options */}
				<div className="space-y-2">
					<Label>{t("qrcode.label_size")}</Label>
					<div className="flex gap-2">
						{sizes.map((s) => (
							<Button
								key={s.value}
								size="sm"
								variant={size === s.value ? "default" : "outline"}
								onClick={(): void => {
									setSize(s.value);
								}}
							>
								{t(
									s.label === "Kecil"
										? "qrcode.size_small"
										: s.label === "Sedang"
											? "qrcode.size_medium"
											: "qrcode.size_large"
								)}
							</Button>
						))}
					</div>
				</div>

				{/* Color Options */}
				<div className="grid grid-cols-2 gap-4">
					<div className="space-y-2">
						<Label htmlFor="fg-color">{t("qrcode.label_color_qr")}</Label>
						<div className="flex items-center gap-2">
							<input
								className="h-10 w-14 cursor-pointer rounded border border-border bg-transparent"
								id="fg-color"
								type="color"
								value={fgColor}
								onChange={(
									event: React.ChangeEvent<HTMLInputElement>
								): void => {
									setFgColor(event.target.value);
								}}
							/>
							<span className="font-mono text-sm text-muted-foreground">
								{fgColor}
							</span>
						</div>
					</div>
					<div className="space-y-2">
						<Label htmlFor="bg-color">{t("qrcode.label_color_bg")}</Label>
						<div className="flex items-center gap-2">
							<input
								className="h-10 w-14 cursor-pointer rounded border border-border bg-transparent"
								id="bg-color"
								type="color"
								value={bgColor}
								onChange={(
									event: React.ChangeEvent<HTMLInputElement>
								): void => {
									setBgColor(event.target.value);
								}}
							/>
							<span className="font-mono text-sm text-muted-foreground">
								{bgColor}
							</span>
						</div>
					</div>
				</div>

				{/* QR Preview */}
				<div className="flex flex-col items-center rounded-lg border border-border bg-card p-6">
					{qrDataUrl ? (
						<img
							alt="Generated QR Code"
							className="max-w-full"
							src={qrDataUrl}
							style={{ width: size, height: size }}
						/>
					) : (
						<div
							className="flex items-center justify-center rounded border-2 border-dashed border-border bg-muted/30"
							style={{ width: size, height: size }}
						>
							<div className="text-center text-muted-foreground">
								<QrCode className="mx-auto mb-2 h-12 w-12 opacity-50" />
								<p className="text-sm">{t("qrcode.empty_state")}</p>
							</div>
						</div>
					)}
				</div>

				{/* Action Buttons */}
				<div className="flex gap-3">
					<Button
						className="flex-1"
						disabled={!qrDataUrl}
						onClick={handleDownload}
					>
						<Download className="mr-2 h-4 w-4" />
						{t("qrcode.btn_download")}
					</Button>
					<Button
						className="flex-1"
						disabled={!qrDataUrl}
						variant="outline"
						onClick={handleCopy}
					>
						<Copy className="mr-2 h-4 w-4" />
						{t("qrcode.btn_copy")}
					</Button>
				</div>
			</div>
		</ToolPageLayout>
	);
};

export default QRCodeGenerator;
