import { useState, useCallback } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";

import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from "@/components/ui/select";
import { Slider } from "@/components/ui/slider";
import {
	ArrowRight,
	Download,
	Image as ImageIcon,
	Loader2,
	Upload,
	X,
} from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";

type OutputFormat = "jpeg" | "png" | "webp";

interface ConvertedImage {
	id: string;
	originalName: string;
	originalSize: number;
	originalFormat: string;
	convertedBlob: Blob;
	convertedSize: number;
	previewUrl: string;
}

const formatMimeMap: Record<OutputFormat, string> = {
	jpeg: "image/jpeg",
	png: "image/png",
	webp: "image/webp",
};

const formatExtensionMap: Record<OutputFormat, string> = {
	jpeg: ".jpg",
	png: ".png",
	webp: ".webp",
};

const formatBytes = (bytes: number): string => {
	if (bytes === 0) return "0 Bytes";
	const k = 1024;
	const sizes = ["Bytes", "KB", "MB"];
	const index = Math.floor(Math.log(bytes) / Math.log(k));
	return (
		parseFloat((bytes / Math.pow(k, index)).toFixed(2)) + " " + sizes[index]
	);
};

const ImageConverter = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [files, setFiles] = useState<Array<File>>([]);
	const [outputFormat, setOutputFormat] = useState<OutputFormat>("jpeg");
	const [quality, setQuality] = useState(80);
	const [convertedImages, setConvertedImages] = useState<Array<ConvertedImage>>(
		[]
	);
	const [isConverting, setIsConverting] = useState(false);
	const [isDragging, setIsDragging] = useState(false);

	const handleFileSelect = useCallback(
		(selectedFiles: FileList | null): void => {
			if (!selectedFiles) return;

			const imageFiles = Array.from(selectedFiles).filter((file) =>
				file.type.startsWith("image/")
			);

			if (imageFiles.length === 0) {
				toast.error(t("converter.toast_error_select"));
				return;
			}

			setFiles((previous) => [...previous, ...imageFiles]);
			setConvertedImages([]);
		},
		[t]
	);

	const handleDrop = useCallback(
		(event: React.DragEvent): void => {
			event.preventDefault();
			setIsDragging(false);
			handleFileSelect(event.dataTransfer.files);
		},
		[handleFileSelect]
	);

	const removeFile = (index: number): void => {
		setFiles((previous) => previous.filter((_, index_) => index_ !== index));
		setConvertedImages([]);
	};

	const convertImage = async (file: File): Promise<ConvertedImage> => {
		return new Promise((resolve, reject) => {
			const img = new window.Image();
			const canvas = document.createElement("canvas");
			const context = canvas.getContext("2d");

			img.onload = (): void => {
				canvas.width = img.width;
				canvas.height = img.height;

				if (!context) {
					reject(new Error("Canvas context not available"));
					return;
				}

				// For JPEG, fill white background (no transparency)
				if (outputFormat === "jpeg") {
					context.fillStyle = "#ffffff";
					context.fillRect(0, 0, canvas.width, canvas.height);
				}

				context.drawImage(img, 0, 0);

				canvas.toBlob(
					(blob) => {
						if (!blob) {
							reject(new Error("Failed to convert image"));
							return;
						}

						const previewUrl = URL.createObjectURL(blob);
						resolve({
							id: Math.random().toString(36).substr(2, 9),
							originalName: file.name,
							originalSize: file.size,
							originalFormat: (file.type.split("/")[1] ?? "").toUpperCase(),
							convertedBlob: blob,
							convertedSize: blob.size,
							previewUrl,
						});
					},
					formatMimeMap[outputFormat],
					quality / 100
				);
			};

			img.onerror = (): void => {
				reject(new Error("Failed to load image"));
			};
			img.src = URL.createObjectURL(file);
		});
	};

	const handleConvert = async (): Promise<void> => {
		if (files.length === 0) {
			toast.error(t("converter.toast_error_select"));
			return;
		}

		setIsConverting(true);

		try {
			const results = await Promise.all(files.map(convertImage));
			setConvertedImages(results);
			toast.success(`${results.length} ${t("converter.toast_success")}`);
		} catch {
			toast.error(t("converter.toast_fail"));
		} finally {
			setIsConverting(false);
		}
	};

	const downloadImage = (image: ConvertedImage): void => {
		const link = document.createElement("a");
		const newName = image.originalName.replace(
			/\.[^.]+$/,
			formatExtensionMap[outputFormat]
		);
		link.download = newName;
		link.href = image.previewUrl;
		link.click();
	};

	const downloadAll = (): void => {
		convertedImages.forEach((img, index): void => {
			setTimeout((): void => {
				downloadImage(img);
			}, index * 200);
		});
		toast.success(t("converter.toast_download_all"));
	};

	const clearAll = (): void => {
		// Cleanup URLs
		convertedImages.forEach((img): void => {
			URL.revokeObjectURL(img.previewUrl);
		});
		setFiles([]);
		setConvertedImages([]);
	};

	return (
		<ToolPageLayout
			description={t("tool_items.image_converter.desc")}
			subtitle={t("converter.subtitle")}
			title={t("tool_items.image_converter.title")}
			toolNumber="20"
		>
			<SEOHead
				description={t("converter.meta.description")}
				path="/tools/image-converter"
				title={t("converter.meta.title")}
				keywords={
					t("converter.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="mx-auto max-w-3xl space-y-6">
				{/* Upload Area */}
				<div
					className={`rounded-lg border-2 border-dashed p-8 text-center transition-colors ${
						isDragging
							? "border-primary bg-primary/5"
							: "border-border hover:border-primary/50"
					}`}
					onDrop={handleDrop}
					onDragLeave={(): void => {
						setIsDragging(false);
					}}
					onDragOver={(event: React.DragEvent): void => {
						event.preventDefault();
						setIsDragging(true);
					}}
				>
					<input
						multiple
						accept="image/*"
						className="hidden"
						id="file-input"
						type="file"
						onChange={(event: React.ChangeEvent<HTMLInputElement>): void => {
							handleFileSelect(event.target.files);
						}}
					/>
					<label className="cursor-pointer" htmlFor="file-input">
						<Upload className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
						<p className="text-lg font-medium">{t("converter.drop_text")}</p>
						<p className="mt-1 text-sm text-muted-foreground">
							{t("converter.support_text")}
						</p>
					</label>
				</div>

				{/* File List */}
				{files.length > 0 && (
					<div className="space-y-3 rounded-lg border border-border bg-card p-4">
						<div className="flex items-center justify-between">
							<Label className="text-base font-medium">
								{files.length} {t("converter.files_selected")}
							</Label>
							<Button size="sm" variant="ghost" onClick={clearAll}>
								{t("converter.btn_clear")}
							</Button>
						</div>
						<div className="max-h-[200px] space-y-2 overflow-y-auto">
							{files.map((file, index) => (
								<div
									key={index}
									className="flex items-center justify-between rounded-lg bg-secondary/50 px-3 py-2"
								>
									<div className="flex items-center gap-3 truncate">
										<ImageIcon
											aria-hidden="true"
											className="h-4 w-4 shrink-0 text-primary"
										/>
										<span className="truncate text-sm">{file.name}</span>
										<span className="text-xs text-muted-foreground">
											({formatBytes(file.size)})
										</span>
									</div>
									<Button
										size="icon"
										variant="ghost"
										onClick={(): void => {
											removeFile(index);
										}}
									>
										<X className="h-4 w-4" />
									</Button>
								</div>
							))}
						</div>
					</div>
				)}

				{/* Options */}
				<div className="grid gap-4 rounded-lg border border-border bg-card p-6 sm:grid-cols-2">
					<div className="space-y-3">
						<Label>{t("converter.format_output")}</Label>
						<Select
							value={outputFormat}
							onValueChange={(v: string): void => {
								setOutputFormat(v as OutputFormat);
							}}
						>
							<SelectTrigger>
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								<SelectItem value="jpeg">JPG / JPEG</SelectItem>
								<SelectItem value="png">PNG</SelectItem>
								<SelectItem value="webp">WebP</SelectItem>
							</SelectContent>
						</Select>
					</div>

					<div className="space-y-3">
						<div className="flex items-center justify-between">
							<Label>{t("converter.label_quality")}</Label>
							<span className="text-sm font-medium text-primary">
								{quality}%
							</span>
						</div>
						<Slider
							disabled={outputFormat === "png"}
							max={100}
							min={10}
							step={5}
							value={[quality]}
							onValueChange={(v: Array<number>): void => {
								setQuality(v[0]!);
							}}
						/>
						{outputFormat === "png" && (
							<p className="text-xs text-muted-foreground">
								{t("converter.png_info")}
							</p>
						)}
					</div>
				</div>

				{/* Convert Button */}
				<Button
					className="w-full"
					disabled={files.length === 0 || isConverting}
					size="lg"
					onClick={handleConvert}
				>
					{isConverting ? (
						<>
							<Loader2 className="mr-2 h-5 w-5 animate-spin" />
							{t("converter.converting")}
						</>
					) : (
						<>
							<ArrowRight className="mr-2 h-5 w-5" />
							{t("converter.btn_convert")}
						</>
					)}
				</Button>

				{/* Results */}
				{convertedImages.length > 0 && (
					<div className="space-y-4 rounded-lg border border-border bg-card p-6">
						<div className="flex items-center justify-between">
							<Label className="text-base font-medium">
								{t("converter.res_title")}
							</Label>
							{convertedImages.length > 1 && (
								<Button size="sm" variant="outline" onClick={downloadAll}>
									<Download className="mr-2 h-4 w-4" />
									{t("converter.btn_download_all")}
								</Button>
							)}
						</div>

						<div className="grid gap-4 sm:grid-cols-2">
							{convertedImages.map((img) => (
								<div
									key={img.id}
									className="overflow-hidden rounded-lg border border-border"
								>
									<div className="aspect-video bg-secondary/30">
										<img
											alt={img.originalName}
											className="h-full w-full object-contain"
											src={img.previewUrl}
										/>
									</div>
									<div className="p-3">
										<p className="truncate text-sm font-medium">
											{img.originalName}
										</p>
										<p className="mt-1 text-xs text-muted-foreground">
											{img.originalFormat} ({formatBytes(img.originalSize)})
											<ArrowRight className="mx-1 inline h-3 w-3" />
											{outputFormat.toUpperCase()} (
											{formatBytes(img.convertedSize)})
										</p>
										<Button
											className="mt-3 w-full"
											size="sm"
											variant="outline"
											onClick={(): void => {
												downloadImage(img);
											}}
										>
											<Download className="mr-2 h-4 w-4" />
											{t("converter.btn_download")}
										</Button>
									</div>
								</div>
							))}
						</div>
					</div>
				)}
			</div>
		</ToolPageLayout>
	);
};

export default ImageConverter;
