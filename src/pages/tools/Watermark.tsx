import { useState, useRef, useEffect, useCallback } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from "@/components/ui/select";
import {
	Upload,
	Download,
	Type,
	ImageIcon,
	RotateCcw,
	Grid3X3,
} from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";

type Position =
	| "top-left"
	| "top-center"
	| "top-right"
	| "center-left"
	| "center"
	| "center-right"
	| "bottom-left"
	| "bottom-center"
	| "bottom-right";
type WatermarkType = "text" | "image";

const positionMap: Record<Position, { x: number; y: number }> = {
	"top-left": { x: 0.1, y: 0.1 },
	"top-center": { x: 0.5, y: 0.1 },
	"top-right": { x: 0.9, y: 0.1 },
	"center-left": { x: 0.1, y: 0.5 },
	center: { x: 0.5, y: 0.5 },
	"center-right": { x: 0.9, y: 0.5 },
	"bottom-left": { x: 0.1, y: 0.9 },
	"bottom-center": { x: 0.5, y: 0.9 },
	"bottom-right": { x: 0.9, y: 0.9 },
};

const Watermark = (): React.JSX.Element => {
	const { t } = useTranslation();
	const canvasRef = useRef<HTMLCanvasElement>(null);
	const [mainImage, setMainImage] = useState<HTMLImageElement | null>(null);
	// Removed unused mainImageUrl
	const [watermarkType, setWatermarkType] = useState<WatermarkType>("text");
	const [watermarkText, setWatermarkText] = useState("© Bantuin.online");
	const [watermarkImage, setWatermarkImage] = useState<HTMLImageElement | null>(
		null
	);
	const [position, setPosition] = useState<Position>("bottom-right");
	const [opacity, setOpacity] = useState(50);
	const [rotation, setRotation] = useState(0);
	const [fontSize, setFontSize] = useState(24);
	const [fontColor, setFontColor] = useState("#ffffff");
	const [tileMode, setTileMode] = useState(false);
	const [watermarkScale, setWatermarkScale] = useState(20);

	const drawCanvas = useCallback(() => {
		const canvas = canvasRef.current;
		const context = canvas?.getContext("2d");
		if (!canvas || !context || !mainImage) return;

		canvas.width = mainImage.width;
		canvas.height = mainImage.height;

		// Draw main image
		context.drawImage(mainImage, 0, 0);

		// Set watermark opacity
		context.globalAlpha = opacity / 100;

		if (tileMode) {
			// Tile mode
			const tileSize = Math.min(canvas.width, canvas.height) * 0.15;
			const gap = tileSize * 2;

			context.save();
			context.translate(canvas.width / 2, canvas.height / 2);
			context.rotate((rotation * Math.PI) / 180);
			context.translate(-canvas.width / 2, -canvas.height / 2);

			for (let y = -canvas.height; y < canvas.height * 2; y += gap) {
				for (let x = -canvas.width; x < canvas.width * 2; x += gap) {
					if (watermarkType === "text") {
						context.font = `${fontSize}px Arial`;
						context.fillStyle = fontColor;
						context.textAlign = "center";
						context.textBaseline = "middle";
						context.fillText(watermarkText, x, y);
					} else if (watermarkImage) {
						const scale =
							tileSize / Math.max(watermarkImage.width, watermarkImage.height);
						const w = watermarkImage.width * scale;
						const h = watermarkImage.height * scale;
						context.drawImage(watermarkImage, x - w / 2, y - h / 2, w, h);
					}
				}
			}
			context.restore();
		} else {
			// Single watermark
			const pos = positionMap[position];
			const x = canvas.width * pos.x;
			const y = canvas.height * pos.y;

			context.save();
			context.translate(x, y);
			context.rotate((rotation * Math.PI) / 180);

			if (watermarkType === "text") {
				context.font = `${fontSize}px Arial`;
				context.fillStyle = fontColor;
				context.textAlign = "center";
				context.textBaseline = "middle";
				context.fillText(watermarkText, 0, 0);
			} else if (watermarkImage) {
				const maxSize =
					Math.min(canvas.width, canvas.height) * (watermarkScale / 100);
				const scale =
					maxSize / Math.max(watermarkImage.width, watermarkImage.height);
				const w = watermarkImage.width * scale;
				const h = watermarkImage.height * scale;
				context.drawImage(watermarkImage, -w / 2, -h / 2, w, h);
			}
			context.restore();
		}

		context.globalAlpha = 1;
	}, [
		mainImage,
		watermarkType,
		watermarkText,
		watermarkImage,
		position,
		opacity,
		rotation,
		fontSize,
		fontColor,
		tileMode,
		watermarkScale,
	]);

	useEffect(() => {
		drawCanvas();
	}, [drawCanvas]);

	const handleMainImageUpload = (
		event: React.ChangeEvent<HTMLInputElement>
	): void => {
		const file = event.target.files?.[0];
		if (!file) return;

		const img = new window.Image();
		img.onload = (): void => {
			setMainImage(img);
			// setMainImageUrl removed
		};
		img.src = URL.createObjectURL(file);
	};

	const handleWatermarkImageUpload = (
		event: React.ChangeEvent<HTMLInputElement>
	): void => {
		const file = event.target.files?.[0];
		if (!file) return;

		const img = new window.Image();
		img.onload = (): void => {
			setWatermarkImage(img);
		};
		img.src = URL.createObjectURL(file);
		toast.success(t("watermark.toast_logo_success"));
	};

	const handleDownload = (format: "png" | "jpeg"): void => {
		const canvas = canvasRef.current;
		if (!canvas || !mainImage) {
			toast.error(t("watermark.toast_upload_first"));
			return;
		}

		const link = document.createElement("a");
		link.download = `watermarked-${Date.now()}.${format === "jpeg" ? "jpg" : "png"}`;
		link.href = canvas.toDataURL(`image/${format}`, 0.9);
		link.click();
		toast.success(t("watermark.toast_download_success"));
	};

	const resetSettings = (): void => {
		setWatermarkText("© Bantuin.online");
		setPosition("bottom-right");
		setOpacity(50);
		setRotation(0);
		setFontSize(24);
		setFontColor("#ffffff");
		setTileMode(false);
		setWatermarkScale(20);
	};

	return (
		<ToolPageLayout
			description={t("tool_items.watermark.desc")}
			subtitle={t("watermark.subtitle")}
			title={t("tool_items.watermark.title")}
			toolNumber="21"
		>
			<SEOHead
				description={t("watermark.meta.description")}
				path="/tools/watermark"
				title={t("watermark.meta.title")}
				keywords={
					t("watermark.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="mx-auto max-w-4xl space-y-6">
				<div className="grid gap-6 lg:grid-cols-2">
					{/* Preview */}
					<div className="space-y-4">
						<Label className="text-base font-medium">
							{t("watermark.label_preview")}
						</Label>
						<div className="overflow-hidden rounded-lg border border-border bg-secondary/30">
							{mainImage ? (
								<canvas
									ref={canvasRef}
									className="h-auto w-full"
									style={{ maxHeight: "400px", objectFit: "contain" }}
								/>
							) : (
								<label
									className="flex aspect-video cursor-pointer flex-col items-center justify-center gap-4 p-8"
									htmlFor="main-image"
								>
									<Upload className="h-12 w-12 text-muted-foreground" />
									<div className="text-center">
										<p className="font-medium">
											{t("watermark.label_upload_main")}
										</p>
										<p className="text-sm text-muted-foreground">
											{t("watermark.text_upload_main_hint")}
										</p>
									</div>
									<input
										accept="image/*"
										className="hidden"
										id="main-image"
										type="file"
										onChange={handleMainImageUpload}
									/>
								</label>
							)}
						</div>
						{mainImage && (
							<div className="flex gap-2">
								<label className="flex-1">
									<Button asChild className="w-full" variant="outline">
										<span>
											<Upload className="mr-2 h-4 w-4" />
											{t("watermark.btn_change_photo")}
										</span>
									</Button>
									<input
										accept="image/*"
										className="hidden"
										type="file"
										onChange={handleMainImageUpload}
									/>
								</label>
								<Button variant="outline" onClick={resetSettings}>
									<RotateCcw className="mr-2 h-4 w-4" />
									{t("watermark.btn_reset")}
								</Button>
							</div>
						)}
					</div>

					{/* Settings */}
					<div className="space-y-4 rounded-lg border border-border bg-card p-6">
						<Tabs
							value={watermarkType}
							onValueChange={(v) => {
								setWatermarkType(v as WatermarkType);
							}}
						>
							<TabsList className="w-full">
								<TabsTrigger className="flex-1" value="text">
									<Type className="mr-2 h-4 w-4" />
									{t("watermark.tab_text")}
								</TabsTrigger>
								<TabsTrigger className="flex-1" value="image">
									<ImageIcon className="mr-2 h-4 w-4" />
									{t("watermark.tab_logo")}
								</TabsTrigger>
							</TabsList>

							<TabsContent className="mt-4 space-y-4" value="text">
								<div className="space-y-2">
									<Label>{t("watermark.label_watermark_text")}</Label>
									<Input
										placeholder={t("watermark.placeholder_watermark_text")}
										value={watermarkText}
										onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
											setWatermarkText(event.target.value);
										}}
									/>
								</div>
								<div className="grid gap-4 sm:grid-cols-2">
									<div className="space-y-2">
										<Label>{t("watermark.label_font_size")}</Label>
										<Slider
											max={72}
											min={12}
											value={[fontSize]}
											onValueChange={(v: Array<number>) => {
												setFontSize(v[0] ?? 24);
											}}
										/>
										<p className="text-xs text-muted-foreground">
											{fontSize}px
										</p>
									</div>
									<div className="space-y-2">
										<Label>{t("watermark.label_color")}</Label>
										<div className="flex gap-2">
											<Input
												className="h-10 w-14 cursor-pointer p-1"
												type="color"
												value={fontColor}
												onChange={(
													event: React.ChangeEvent<HTMLInputElement>
												) => {
													setFontColor(event.target.value);
												}}
											/>
											<Input
												className="flex-1 font-mono"
												value={fontColor}
												onChange={(
													event: React.ChangeEvent<HTMLInputElement>
												) => {
													setFontColor(event.target.value);
												}}
											/>
										</div>
									</div>
								</div>
							</TabsContent>

							<TabsContent className="mt-4 space-y-4" value="image">
								<div className="space-y-2">
									<Label>{t("watermark.label_upload_logo")}</Label>
									<label className="flex cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-border p-4 transition-colors hover:border-primary/50">
										{watermarkImage ? (
											<div className="flex items-center gap-3">
												<img
													alt="Logo"
													className="h-12 w-12 object-contain"
													src={watermarkImage.src}
												/>
												<span className="text-sm text-muted-foreground">
													{t("watermark.text_click_change")}
												</span>
											</div>
										) : (
											<div className="text-center">
												<ImageIcon className="mx-auto h-8 w-8 text-muted-foreground" />
												<p className="mt-2 text-sm">
													{t("watermark.text_upload_logo_hint")}
												</p>
											</div>
										)}
										<input
											accept="image/*"
											className="hidden"
											type="file"
											onChange={handleWatermarkImageUpload}
										/>
									</label>
								</div>
								<div className="space-y-2">
									<Label>{t("watermark.label_logo_size")}</Label>
									<Slider
										max={50}
										min={5}
										value={[watermarkScale]}
										onValueChange={(v: Array<number>) => {
											setWatermarkScale(v[0] ?? 20);
										}}
									/>
									<p className="text-xs text-muted-foreground">
										{watermarkScale}%
									</p>
								</div>
							</TabsContent>
						</Tabs>

						<div className="space-y-4 border-t border-border pt-4">
							<div className="space-y-2">
								<Label>{t("watermark.label_position")}</Label>
								<Select
									disabled={tileMode}
									value={position}
									onValueChange={(v) => {
										setPosition(v as Position);
									}}
								>
									<SelectTrigger>
										<SelectValue />
									</SelectTrigger>
									<SelectContent>
										<SelectItem value="top-left">
											{t("watermark.pos_top_left")}
										</SelectItem>
										<SelectItem value="top-center">
											{t("watermark.pos_top_center")}
										</SelectItem>
										<SelectItem value="top-right">
											{t("watermark.pos_top_right")}
										</SelectItem>
										<SelectItem value="center-left">
											{t("watermark.pos_center_left")}
										</SelectItem>
										<SelectItem value="center">
											{t("watermark.pos_center")}
										</SelectItem>
										<SelectItem value="center-right">
											{t("watermark.pos_center_right")}
										</SelectItem>
										<SelectItem value="bottom-left">
											{t("watermark.pos_bottom_left")}
										</SelectItem>
										<SelectItem value="bottom-center">
											{t("watermark.pos_bottom_center")}
										</SelectItem>
										<SelectItem value="bottom-right">
											{t("watermark.pos_bottom_right")}
										</SelectItem>
									</SelectContent>
								</Select>
							</div>

							<div className="space-y-2">
								<div className="flex items-center justify-between">
									<Label>{t("watermark.label_opacity")}</Label>
									<span className="text-sm text-muted-foreground">
										{opacity}%
									</span>
								</div>
								<Slider
									max={100}
									min={10}
									value={[opacity]}
									onValueChange={(v: Array<number>) => {
										setOpacity(v[0] ?? 50);
									}}
								/>
							</div>

							<div className="space-y-2">
								<div className="flex items-center justify-between">
									<Label>{t("watermark.label_rotation")}</Label>
									<span className="text-sm text-muted-foreground">
										{rotation}°
									</span>
								</div>
								<Slider
									max={45}
									min={-45}
									value={[rotation]}
									onValueChange={(v: Array<number>) => {
										setRotation(v[0] ?? 0);
									}}
								/>
							</div>

							<div className="flex items-center justify-between rounded-lg border border-border p-3">
								<div className="flex items-center gap-2">
									<Grid3X3 className="h-4 w-4 text-muted-foreground" />
									<Label className="cursor-pointer" htmlFor="tile-mode">
										{t("watermark.label_tile_mode")}
									</Label>
								</div>
								<Switch
									checked={tileMode}
									id="tile-mode"
									onCheckedChange={setTileMode}
								/>
							</div>
						</div>
					</div>
				</div>

				{/* Download Buttons */}
				{mainImage && (
					<div className="flex gap-3">
						<Button
							className="flex-1"
							onClick={() => {
								handleDownload("jpeg");
							}}
						>
							<Download className="mr-2 h-4 w-4" />
							{t("watermark.btn_download_jpg")}
						</Button>
						<Button
							className="flex-1"
							variant="outline"
							onClick={() => {
								handleDownload("png");
							}}
						>
							<Download className="mr-2 h-4 w-4" />
							{t("watermark.btn_download_png")}
						</Button>
					</div>
				)}
			</div>
		</ToolPageLayout>
	);
};

export default Watermark;
