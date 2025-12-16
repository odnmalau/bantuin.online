import { useState, useRef, useCallback, useEffect } from "react";
import { useTranslation } from "react-i18next";
import ReactCrop, {
	type Crop,
	type PixelCrop,
	centerCrop,
	makeAspectCrop,
} from "react-image-crop";
import "react-image-crop/dist/ReactCrop.css";
import { Upload, Download, RotateCcw, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
	CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { useToast } from "@/hooks/use-toast";
import {
	getCroppedImg,
	getAspectRatio,
	PAS_FOTO_SIZES,
} from "@/utils/cropImage";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

const PasFoto = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [selectedFile, setSelectedFile] = useState<File | null>(null);
	const [imgSource, setImgSource] = useState<string>("");
	const [crop, setCrop] = useState<Crop>();
	const [completedCrop, setCompletedCrop] = useState<PixelCrop>();
	const [selectedSize, setSelectedSize] = useState<string>("3x4");
	const [isProcessing, setIsProcessing] = useState(false);
	const [previewUrl, setPreviewUrl] = useState<string>("");

	const imgRef = useRef<HTMLImageElement>(null);
	const { toast } = useToast();

	// Cleanup blob URLs on unmount to prevent memory leaks
	useEffect((): (() => void) => {
		return (): void => {
			if (previewUrl) URL.revokeObjectURL(previewUrl);
		};
	}, [previewUrl]);

	const onSelectFile = (event: React.ChangeEvent<HTMLInputElement>): void => {
		if (event.target.files && event.target.files.length > 0) {
			const file = event.target.files[0];
			if (file) setSelectedFile(file);
			setPreviewUrl("");

			const reader = new FileReader();
			reader.addEventListener("load", (): void => {
				if (typeof reader.result === "string") {
					setImgSource(reader.result);
				}
			});
			reader.readAsDataURL(file!);
		}
	};

	const onImageLoad = useCallback(
		(event: React.SyntheticEvent<HTMLImageElement>): void => {
			const { width, height } = event.currentTarget;
			const aspect = getAspectRatio(selectedSize);

			const newCrop = centerCrop(
				makeAspectCrop(
					{
						unit: "%",
						width: 80,
					},
					aspect,
					width,
					height
				),
				width,
				height
			);

			setCrop(newCrop);
		},
		[selectedSize]
	);

	const handleSizeChange = (size: string): void => {
		setSelectedSize(size);
		setPreviewUrl("");

		if (imgRef.current) {
			const { width, height } = imgRef.current;
			const aspect = getAspectRatio(size);

			const newCrop = centerCrop(
				makeAspectCrop(
					{
						unit: "%",
						width: 80,
					},
					aspect,
					width,
					height
				),
				width,
				height
			);

			setCrop(newCrop);
		}
	};

	const handleGeneratePreview = async (): Promise<void> => {
		if (!imgRef.current || !completedCrop) {
			toast({
				title: t("pasfoto.toast_select_crop"),
				description: t("pasfoto.toast_select_crop_desc"),
				variant: "destructive",
			});
			return;
		}

		setIsProcessing(true);
		try {
			const blob = await getCroppedImg(
				imgRef.current,
				completedCrop,
				selectedSize
			);
			const url = URL.createObjectURL(blob);
			setPreviewUrl(url);

			toast({
				title: t("pasfoto.toast_preview_ready"),
				description: t("pasfoto.toast_preview_desc"),
			});
		} catch {
			toast({
				title: t("pasfoto.toast_error"),
				description: t("pasfoto.toast_error_desc"),
				variant: "destructive",
			});
		} finally {
			setIsProcessing(false);
		}
	};

	const handleDownload = async (): Promise<void> => {
		if (!imgRef.current || !completedCrop) return;

		setIsProcessing(true);
		try {
			const blob = await getCroppedImg(
				imgRef.current,
				completedCrop,
				selectedSize
			);
			const url = URL.createObjectURL(blob);

			const link = document.createElement("a");
			link.href = url;
			link.download = `pasfoto_${selectedSize}_${selectedFile?.name || "photo"}.jpg`;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);

			URL.revokeObjectURL(url);

			toast({
				title: t("pasfoto.toast_download_success"),
				description: t("pasfoto.toast_download_desc", {
					size: PAS_FOTO_SIZES[selectedSize]!.label,
				}),
			});
		} catch {
			toast({
				title: t("pasfoto.toast_download_error"),
				description: t("pasfoto.toast_download_error_desc"),
				variant: "destructive",
			});
		} finally {
			setIsProcessing(false);
		}
	};

	const handleReset = (): void => {
		// Revoke URL before clearing state to prevent memory leak
		if (previewUrl) {
			URL.revokeObjectURL(previewUrl);
		}
		setSelectedFile(null);
		setImgSource("");
		setCrop(undefined);
		setCompletedCrop(undefined);
		setPreviewUrl("");
	};

	const handleDrop = (event: React.DragEvent<HTMLLabelElement>): void => {
		event.preventDefault();
		const file = event.dataTransfer.files[0];
		if (file && file.type.startsWith("image/")) {
			setSelectedFile(file);
			setPreviewUrl("");

			const reader = new FileReader();
			reader.addEventListener("load", (): void => {
				if (typeof reader.result === "string") {
					setImgSource(reader.result);
				}
			});
			reader.readAsDataURL(file);
		}
	};

	const handleDragOver = (event: React.DragEvent<HTMLLabelElement>): void => {
		event.preventDefault();
	};

	return (
		<ToolPageLayout
			description={t("pasfoto.desc_page")}
			subtitle={t("pasfoto.subtitle")}
			title={t("pasfoto.title")}
			toolNumber="03"
		>
			<SEOHead
				description={t("pasfoto.meta.description")}
				path="/tools/pas-foto"
				title={t("pasfoto.meta.title")}
				keywords={
					t("pasfoto.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="space-y-6">
				{/* Main Card */}
				<Card className="animate-fade-in-up stagger-1 rounded-sm border-border bg-card">
					<CardHeader>
						<CardTitle className="font-display text-xl text-foreground">
							{t("pasfoto.card_title")}
						</CardTitle>
						<CardDescription>{t("pasfoto.card_desc")}</CardDescription>
					</CardHeader>
					<CardContent className="space-y-6">
						{/* Size Selector */}
						<div className="space-y-3">
							<Label className="font-medium text-foreground">
								{t("pasfoto.label_size")}
							</Label>
							<RadioGroup
								className="flex flex-wrap gap-4"
								value={selectedSize}
								onValueChange={handleSizeChange}
							>
								{Object.entries(PAS_FOTO_SIZES).map(([key]) => (
									<div key={key} className="flex items-center space-x-2">
										<RadioGroupItem
											className="border-border"
											id={key}
											value={key}
										/>
										<Label
											className="cursor-pointer text-sm font-medium text-foreground"
											htmlFor={key}
										>
											{t(`pasfoto.sizes.${key}`)}
										</Label>
									</div>
								))}
							</RadioGroup>
						</div>

						{/* Upload Area */}
						{!imgSource && (
							<label
								className="flex min-h-[200px] cursor-pointer flex-col items-center justify-center rounded-sm border-2 border-dashed border-border bg-muted/30 p-8 transition-colors hover:border-primary/50 hover:bg-muted/50"
								onDragOver={handleDragOver}
								onDrop={handleDrop}
							>
								<Upload className="mb-4 h-12 w-12 text-muted-foreground" />
								<p className="mb-2 text-center font-medium text-foreground">
									{t("pasfoto.upload_hint")}
								</p>
								<p className="text-center text-sm text-muted-foreground">
									{t("pasfoto.upload_subhint")}
								</p>
								<input
									accept="image/*"
									className="hidden"
									type="file"
									onChange={onSelectFile}
								/>
							</label>
						)}

						{/* Crop Area */}
						{imgSource && (
							<div className="space-y-4">
								<div className="flex items-center justify-between">
									<Label className="font-medium text-foreground">
										{t("pasfoto.label_crop")}
									</Label>
									<Button
										className="rounded-sm"
										size="sm"
										variant="outline"
										onClick={handleReset}
									>
										<RotateCcw className="mr-2 h-4 w-4" />
										{t("pasfoto.btn_reset")}
									</Button>
								</div>

								<div className="flex justify-center overflow-hidden rounded-sm border border-border bg-muted/30 p-4">
									<ReactCrop
										aspect={getAspectRatio(selectedSize)}
										className="max-h-[400px]"
										crop={crop}
										onChange={(_, percentCrop): void => {
											setCrop(percentCrop);
										}}
										onComplete={(c): void => {
											setCompletedCrop(c);
										}}
									>
										<img
											ref={imgRef}
											alt="Crop preview"
											className="max-h-[400px] w-auto"
											src={imgSource}
											onLoad={onImageLoad}
										/>
									</ReactCrop>
								</div>

								{/* Action Buttons */}
								<div className="flex flex-col gap-3 sm:flex-row">
									<Button
										className="flex-1 rounded-sm"
										disabled={isProcessing || !completedCrop}
										variant="outline"
										onClick={handleGeneratePreview}
									>
										<Check className="mr-2 h-4 w-4" />
										{t("pasfoto.btn_preview")}
									</Button>
									<Button
										className="flex-1 rounded-sm"
										disabled={isProcessing || !completedCrop}
										onClick={handleDownload}
									>
										<Download className="mr-2 h-4 w-4" />
										{isProcessing
											? t("pasfoto.btn_processing")
											: t("pasfoto.btn_download")}
									</Button>
								</div>
							</div>
						)}

						{/* Preview Result */}
						{previewUrl && (
							<div className="space-y-3">
								<Label className="font-medium text-foreground">
									{t("pasfoto.label_preview")}
								</Label>
								<div className="flex justify-center rounded-sm border border-border bg-muted/30 p-4">
									<img
										alt="Cropped preview"
										className="max-h-[300px] rounded-sm"
										src={previewUrl}
									/>
								</div>
								<p className="text-center text-sm text-muted-foreground">
									{t("pasfoto.preview_info", {
										size: t(`pasfoto.sizes.${selectedSize}`),
									})}
								</p>
							</div>
						)}
					</CardContent>
				</Card>

				{/* Tips Section */}
				<div className="animate-fade-in-up stagger-2 border-l-2 border-primary bg-muted/30 p-4 pl-6">
					<h3 className="mb-2 font-display text-sm font-semibold uppercase tracking-wide text-foreground">
						{t("pasfoto.tips_title")}
					</h3>
					<ul className="space-y-2 text-sm text-muted-foreground">
						<li>• {t("pasfoto.tips_list_1")}</li>
						<li>• {t("pasfoto.tips_list_2")}</li>
						<li>• {t("pasfoto.tips_list_3")}</li>
						<li>• {t("pasfoto.tips_list_4")}</li>
					</ul>
				</div>
			</div>
		</ToolPageLayout>
	);
};

export default PasFoto;
