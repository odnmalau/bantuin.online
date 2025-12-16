import { useState, useRef, useEffect, useCallback } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";

import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from "@/components/ui/select";
import { Download, Trash2, Undo2, Info } from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";

type InkColor = "black" | "blue" | "red";
type LineWidth = "thin" | "medium" | "thick";
type OutputSize = "small" | "medium" | "large";

interface Point {
	x: number;
	y: number;
}

interface Stroke {
	points: Array<Point>;
	color: string;
	width: number;
}

const colorMap: Record<InkColor, string> = {
	black: "#1a1a1a",
	blue: "#1e3a8a",
	red: "#b91c1c",
};

const widthMap: Record<LineWidth, number> = {
	thin: 2,
	medium: 4,
	thick: 6,
};

const sizeMap: Record<OutputSize, number> = {
	small: 200,
	medium: 400,
	large: 600,
};

const SignaturePad = (): React.JSX.Element => {
	const { t } = useTranslation();
	const canvasRef = useRef<HTMLCanvasElement>(null);
	const [isDrawing, setIsDrawing] = useState(false);
	const [inkColor, setInkColor] = useState<InkColor>("black");
	const [lineWidth, setLineWidth] = useState<LineWidth>("medium");
	const [outputSize, setOutputSize] = useState<OutputSize>("medium");
	const [strokes, setStrokes] = useState<Array<Stroke>>([]);
	const [currentStroke, setCurrentStroke] = useState<Array<Point>>([]);
	const [hasSignature, setHasSignature] = useState(false);

	const getCanvasCoords = useCallback(
		(event: React.MouseEvent | React.TouchEvent): Point => {
			const canvas = canvasRef.current;
			if (!canvas) return { x: 0, y: 0 };

			const rect = canvas.getBoundingClientRect();
			const scaleX = canvas.width / rect.width;
			const scaleY = canvas.height / rect.height;

			if ("touches" in event) {
				const touch = event.touches[0];
				if (!touch) return { x: 0, y: 0 };
				return {
					x: (touch.clientX - rect.left) * scaleX,
					y: (touch.clientY - rect.top) * scaleY,
				};
			} else {
				return {
					x: (event.clientX - rect.left) * scaleX,
					y: (event.clientY - rect.top) * scaleY,
				};
			}
		},
		[]
	);

	const redrawCanvas = useCallback((): void => {
		const canvas = canvasRef.current;
		const context = canvas?.getContext("2d");
		if (!canvas || !context) return;

		context.clearRect(0, 0, canvas.width, canvas.height);

		// Draw all strokes
		strokes.forEach((stroke): void => {
			if (stroke.points.length < 2) return;
			context.beginPath();
			context.strokeStyle = stroke.color;
			context.lineWidth = stroke.width;
			context.lineCap = "round";
			context.lineJoin = "round";

			const firstPoint = stroke.points[0]!;
			context.moveTo(firstPoint.x, firstPoint.y);
			for (let index = 1; index < stroke.points.length; index++) {
				const point = stroke.points[index]!;
				context.lineTo(point.x, point.y);
			}
			context.stroke();
		});

		// Draw current stroke
		if (currentStroke.length > 1) {
			context.beginPath();
			context.strokeStyle = colorMap[inkColor];
			context.lineWidth = widthMap[lineWidth];
			context.lineCap = "round";
			context.lineJoin = "round";

			const firstPoint = currentStroke[0]!;
			context.moveTo(firstPoint.x, firstPoint.y);
			for (let index = 1; index < currentStroke.length; index++) {
				const point = currentStroke[index]!;
				context.lineTo(point.x, point.y);
			}
			context.stroke();
		}
	}, [strokes, currentStroke, inkColor, lineWidth]);

	useEffect((): void => {
		redrawCanvas();
	}, [redrawCanvas]);

	const startDrawing = (event: React.MouseEvent | React.TouchEvent): void => {
		event.preventDefault();
		const coords = getCanvasCoords(event);
		setIsDrawing(true);
		setCurrentStroke([coords]);
	};

	const draw = (event: React.MouseEvent | React.TouchEvent): void => {
		event.preventDefault();
		if (!isDrawing) return;

		const coords = getCanvasCoords(event);
		setCurrentStroke((previous) => [...previous, coords]);
	};

	const stopDrawing = (): void => {
		if (!isDrawing) return;
		setIsDrawing(false);

		if (currentStroke.length > 1) {
			const newStroke: Stroke = {
				points: currentStroke,
				color: colorMap[inkColor],
				width: widthMap[lineWidth],
			};
			setStrokes((previous) => [...previous, newStroke]);
			setHasSignature(true);
		}
		setCurrentStroke([]);
	};

	const handleUndo = (): void => {
		if (strokes.length === 0) return;
		setStrokes((previous) => previous.slice(0, -1));
		if (strokes.length <= 1) setHasSignature(false);
	};

	const handleClear = (): void => {
		setStrokes([]);
		setCurrentStroke([]);
		setHasSignature(false);
		toast.success(t("signature.toast_clear"));
	};

	const handleDownload = (): void => {
		if (!hasSignature) {
			toast.error(t("signature.toast_empty"));
			return;
		}

		const canvas = canvasRef.current;
		if (!canvas) return;

		// Create export canvas with desired size
		const exportCanvas = document.createElement("canvas");
		const size = sizeMap[outputSize];
		exportCanvas.width = size;
		exportCanvas.height = size / 2; // 2:1 aspect ratio

		const exportContext = exportCanvas.getContext("2d");
		if (!exportContext) return;

		// Keep transparent background
		exportContext.clearRect(0, 0, exportCanvas.width, exportCanvas.height);

		// Scale and draw strokes
		const scaleX = exportCanvas.width / canvas.width;
		const scaleY = exportCanvas.height / canvas.height;

		strokes.forEach((stroke): void => {
			if (stroke.points.length < 2) return;
			exportContext.beginPath();
			exportContext.strokeStyle = stroke.color;
			exportContext.lineWidth = stroke.width * scaleX;
			exportContext.lineCap = "round";
			exportContext.lineJoin = "round";

			const firstPoint = stroke.points[0]!;
			exportContext.moveTo(firstPoint.x * scaleX, firstPoint.y * scaleY);
			for (let index = 1; index < stroke.points.length; index++) {
				const point = stroke.points[index]!;
				exportContext.lineTo(point.x * scaleX, point.y * scaleY);
			}
			exportContext.stroke();
		});

		// Download
		const link = document.createElement("a");
		link.download = `tanda-tangan-${Date.now()}.png`;
		link.href = exportCanvas.toDataURL("image/png");
		link.click();
		toast.success(t("signature.toast_download"));
	};

	return (
		<ToolPageLayout
			description={t("tool_items.signature.desc")}
			subtitle={t("signature.subtitle")}
			title={t("tool_items.signature.title")}
			toolNumber="19"
		>
			<SEOHead
				description={t("signature.meta.description")}
				path="/tools/signature"
				title={t("signature.meta.title")}
				keywords={
					t("signature.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="mx-auto max-w-2xl space-y-6">
				{/* Instructions */}
				<div className="flex items-start gap-3 rounded-lg border border-border bg-secondary/30 p-4">
					<Info className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
					<div className="text-sm text-muted-foreground">
						<p className="font-medium text-foreground">
							{t("signature.how_to_title")}
						</p>
						<ol className="mt-1 list-inside list-decimal space-y-1">
							<li>{t("signature.how_to_1")}</li>
							<li>{t("signature.how_to_2")}</li>
							<li>{t("signature.how_to_3")}</li>
						</ol>
					</div>
				</div>

				{/* Canvas */}
				<div className="overflow-hidden rounded-lg border border-border bg-card">
					<div className="border-b border-border bg-secondary/30 px-4 py-2">
						<p className="text-sm text-muted-foreground">
							{t("signature.canvas_placeholder")}
						</p>
					</div>
					<div
						className="relative"
						style={{
							backgroundImage: `
                linear-gradient(45deg, hsl(var(--muted)) 25%, transparent 25%),
                linear-gradient(-45deg, hsl(var(--muted)) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, hsl(var(--muted)) 75%),
                linear-gradient(-45deg, transparent 75%, hsl(var(--muted)) 75%)
              `,
							backgroundSize: "20px 20px",
							backgroundPosition: "0 0, 0 10px, 10px -10px, -10px 0px",
						}}
					>
						<canvas
							ref={canvasRef}
							className="w-full cursor-crosshair touch-none"
							height={300}
							width={600}
							onMouseDown={startDrawing}
							onMouseLeave={stopDrawing}
							onMouseMove={draw}
							onMouseUp={stopDrawing}
							onTouchEnd={stopDrawing}
							onTouchMove={draw}
							onTouchStart={startDrawing}
						/>
					</div>
					<div className="flex gap-2 border-t border-border bg-secondary/30 p-3">
						<Button
							disabled={strokes.length === 0}
							size="sm"
							variant="outline"
							onClick={handleUndo}
						>
							<Undo2 className="mr-2 h-4 w-4" />
							{t("signature.btn_undo")}
						</Button>
						<Button
							disabled={!hasSignature}
							size="sm"
							variant="outline"
							onClick={handleClear}
						>
							<Trash2 className="mr-2 h-4 w-4" />
							{t("signature.btn_clear")}
						</Button>
					</div>
				</div>

				{/* Options */}
				<div className="grid gap-4 rounded-lg border border-border bg-card p-6 sm:grid-cols-3">
					<div className="space-y-3">
						<Label>{t("signature.label_ink")}</Label>
						<RadioGroup
							className="flex gap-3"
							value={inkColor}
							onValueChange={(v: string): void => {
								setInkColor(v as InkColor);
							}}
						>
							<div className="flex items-center space-x-2">
								<RadioGroupItem id="black" value="black" />
								<Label
									className="flex cursor-pointer items-center gap-2 font-normal"
									htmlFor="black"
								>
									<span className="h-4 w-4 rounded-full bg-gray-900" />
									{t("signature.color_black")}
								</Label>
							</div>
							<div className="flex items-center space-x-2">
								<RadioGroupItem id="blue" value="blue" />
								<Label
									className="flex cursor-pointer items-center gap-2 font-normal"
									htmlFor="blue"
								>
									<span className="h-4 w-4 rounded-full bg-blue-900" />
									{t("signature.color_blue")}
								</Label>
							</div>
							<div className="flex items-center space-x-2">
								<RadioGroupItem id="red" value="red" />
								<Label
									className="flex cursor-pointer items-center gap-2 font-normal"
									htmlFor="red"
								>
									<span className="h-4 w-4 rounded-full bg-red-700" />
									{t("signature.color_red")}
								</Label>
							</div>
						</RadioGroup>
					</div>

					<div className="space-y-3">
						<Label>{t("signature.label_width")}</Label>
						<Select
							value={lineWidth}
							onValueChange={(v: string): void => {
								setLineWidth(v as LineWidth);
							}}
						>
							<SelectTrigger>
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								<SelectItem value="thin">
									{t("signature.width_thin")}
								</SelectItem>
								<SelectItem value="medium">
									{t("signature.width_medium")}
								</SelectItem>
								<SelectItem value="thick">
									{t("signature.width_thick")}
								</SelectItem>
							</SelectContent>
						</Select>
					</div>

					<div className="space-y-3">
						<Label>{t("signature.label_size")}</Label>
						<Select
							value={outputSize}
							onValueChange={(v: string): void => {
								setOutputSize(v as OutputSize);
							}}
						>
							<SelectTrigger>
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								<SelectItem value="small">
									{t("signature.size_small")}
								</SelectItem>
								<SelectItem value="medium">
									{t("signature.size_medium")}
								</SelectItem>
								<SelectItem value="large">
									{t("signature.size_large")}
								</SelectItem>
							</SelectContent>
						</Select>
					</div>
				</div>

				{/* Download Button */}
				<Button
					className="w-full"
					disabled={!hasSignature}
					size="lg"
					onClick={handleDownload}
				>
					<Download className="mr-2 h-5 w-5" />
					{t("signature.btn_download")}
				</Button>
			</div>
		</ToolPageLayout>
	);
};

export default SignaturePad;
