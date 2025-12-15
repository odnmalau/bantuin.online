import { useState, useRef, useEffect, useCallback } from "react";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Download, Trash2, Undo2, Info } from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

type InkColor = "black" | "blue" | "red";
type LineWidth = "thin" | "medium" | "thick";
type OutputSize = "small" | "medium" | "large";

interface Point {
  x: number;
  y: number;
}

interface Stroke {
  points: Point[];
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

const SignaturePad = () => {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [isDrawing, setIsDrawing] = useState(false);
  const [inkColor, setInkColor] = useState<InkColor>("black");
  const [lineWidth, setLineWidth] = useState<LineWidth>("medium");
  const [outputSize, setOutputSize] = useState<OutputSize>("medium");
  const [strokes, setStrokes] = useState<Stroke[]>([]);
  const [currentStroke, setCurrentStroke] = useState<Point[]>([]);
  const [hasSignature, setHasSignature] = useState(false);

  const getCanvasCoords = useCallback((e: React.MouseEvent | React.TouchEvent): Point => {
    const canvas = canvasRef.current;
    if (!canvas) return { x: 0, y: 0 };
    
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    if ("touches" in e) {
      const touch = e.touches[0];
      return {
        x: (touch.clientX - rect.left) * scaleX,
        y: (touch.clientY - rect.top) * scaleY,
      };
    } else {
      return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY,
      };
    }
  }, []);

  const redrawCanvas = useCallback(() => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext("2d");
    if (!canvas || !ctx) return;

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Draw all strokes
    strokes.forEach((stroke) => {
      if (stroke.points.length < 2) return;
      ctx.beginPath();
      ctx.strokeStyle = stroke.color;
      ctx.lineWidth = stroke.width;
      ctx.lineCap = "round";
      ctx.lineJoin = "round";
      
      ctx.moveTo(stroke.points[0].x, stroke.points[0].y);
      for (let i = 1; i < stroke.points.length; i++) {
        ctx.lineTo(stroke.points[i].x, stroke.points[i].y);
      }
      ctx.stroke();
    });

    // Draw current stroke
    if (currentStroke.length > 1) {
      ctx.beginPath();
      ctx.strokeStyle = colorMap[inkColor];
      ctx.lineWidth = widthMap[lineWidth];
      ctx.lineCap = "round";
      ctx.lineJoin = "round";
      
      ctx.moveTo(currentStroke[0].x, currentStroke[0].y);
      for (let i = 1; i < currentStroke.length; i++) {
        ctx.lineTo(currentStroke[i].x, currentStroke[i].y);
      }
      ctx.stroke();
    }
  }, [strokes, currentStroke, inkColor, lineWidth]);

  useEffect(() => {
    redrawCanvas();
  }, [redrawCanvas]);

  const startDrawing = (e: React.MouseEvent | React.TouchEvent) => {
    e.preventDefault();
    const coords = getCanvasCoords(e);
    setIsDrawing(true);
    setCurrentStroke([coords]);
  };

  const draw = (e: React.MouseEvent | React.TouchEvent) => {
    e.preventDefault();
    if (!isDrawing) return;
    
    const coords = getCanvasCoords(e);
    setCurrentStroke((prev) => [...prev, coords]);
  };

  const stopDrawing = () => {
    if (!isDrawing) return;
    setIsDrawing(false);
    
    if (currentStroke.length > 1) {
      const newStroke: Stroke = {
        points: currentStroke,
        color: colorMap[inkColor],
        width: widthMap[lineWidth],
      };
      setStrokes((prev) => [...prev, newStroke]);
      setHasSignature(true);
    }
    setCurrentStroke([]);
  };

  const handleUndo = () => {
    if (strokes.length === 0) return;
    setStrokes((prev) => prev.slice(0, -1));
    if (strokes.length <= 1) setHasSignature(false);
  };

  const handleClear = () => {
    setStrokes([]);
    setCurrentStroke([]);
    setHasSignature(false);
    toast.success("Kanvas dibersihkan");
  };

  const handleDownload = () => {
    if (!hasSignature) {
      toast.error("Buat tanda tangan terlebih dahulu!");
      return;
    }

    const canvas = canvasRef.current;
    if (!canvas) return;

    // Create export canvas with desired size
    const exportCanvas = document.createElement("canvas");
    const size = sizeMap[outputSize];
    exportCanvas.width = size;
    exportCanvas.height = size / 2; // 2:1 aspect ratio
    
    const exportCtx = exportCanvas.getContext("2d");
    if (!exportCtx) return;

    // Keep transparent background
    exportCtx.clearRect(0, 0, exportCanvas.width, exportCanvas.height);
    
    // Scale and draw strokes
    const scaleX = exportCanvas.width / canvas.width;
    const scaleY = exportCanvas.height / canvas.height;
    
    strokes.forEach((stroke) => {
      if (stroke.points.length < 2) return;
      exportCtx.beginPath();
      exportCtx.strokeStyle = stroke.color;
      exportCtx.lineWidth = stroke.width * scaleX;
      exportCtx.lineCap = "round";
      exportCtx.lineJoin = "round";
      
      exportCtx.moveTo(stroke.points[0].x * scaleX, stroke.points[0].y * scaleY);
      for (let i = 1; i < stroke.points.length; i++) {
        exportCtx.lineTo(stroke.points[i].x * scaleX, stroke.points[i].y * scaleY);
      }
      exportCtx.stroke();
    });

    // Download
    const link = document.createElement("a");
    link.download = `tanda-tangan-${Date.now()}.png`;
    link.href = exportCanvas.toDataURL("image/png");
    link.click();
    toast.success("Tanda tangan berhasil diunduh!");
  };

  return (
    <ToolPageLayout
      toolNumber="19"
      title="Tanda Tangan Digital"
      subtitle="Signature Pad"
      description="Buat tanda tangan digital dengan latar transparan untuk dokumen Word, PDF, atau formulir online."
    >
      <SEOHead
        title={toolsMetadata.signature.title}
        description={toolsMetadata.signature.description}
        path={toolsMetadata.signature.path}
        keywords={toolsMetadata.signature.keywords}
      />
      <div className="mx-auto max-w-2xl space-y-6">
        {/* Instructions */}
        <div className="flex items-start gap-3 rounded-lg border border-border bg-secondary/30 p-4">
          <Info className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
          <div className="text-sm text-muted-foreground">
            <p className="font-medium text-foreground">Cara Penggunaan:</p>
            <ol className="mt-1 list-inside list-decimal space-y-1">
              <li>Gambar tanda tangan di area kanvas menggunakan mouse atau jari</li>
              <li>Pilih warna dan ketebalan sesuai keinginan</li>
              <li>Klik "Unduh PNG" untuk menyimpan dengan latar transparan</li>
            </ol>
          </div>
        </div>

        {/* Canvas */}
        <div className="overflow-hidden rounded-lg border border-border bg-card">
          <div className="border-b border-border bg-secondary/30 px-4 py-2">
            <p className="text-sm text-muted-foreground">Gambar tanda tangan di bawah ini</p>
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
              width={600}
              height={300}
              className="w-full cursor-crosshair touch-none"
              onMouseDown={startDrawing}
              onMouseMove={draw}
              onMouseUp={stopDrawing}
              onMouseLeave={stopDrawing}
              onTouchStart={startDrawing}
              onTouchMove={draw}
              onTouchEnd={stopDrawing}
            />
          </div>
          <div className="flex gap-2 border-t border-border bg-secondary/30 p-3">
            <Button variant="outline" size="sm" onClick={handleUndo} disabled={strokes.length === 0}>
              <Undo2 className="mr-2 h-4 w-4" />
              Undo
            </Button>
            <Button variant="outline" size="sm" onClick={handleClear} disabled={!hasSignature}>
              <Trash2 className="mr-2 h-4 w-4" />
              Hapus Semua
            </Button>
          </div>
        </div>

        {/* Options */}
        <div className="grid gap-4 rounded-lg border border-border bg-card p-6 sm:grid-cols-3">
          <div className="space-y-3">
            <Label>Warna Tinta</Label>
            <RadioGroup
              value={inkColor}
              onValueChange={(v) => setInkColor(v as InkColor)}
              className="flex gap-3"
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="black" id="black" />
                <Label htmlFor="black" className="flex cursor-pointer items-center gap-2 font-normal">
                  <span className="h-4 w-4 rounded-full bg-gray-900" />
                  Hitam
                </Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="blue" id="blue" />
                <Label htmlFor="blue" className="flex cursor-pointer items-center gap-2 font-normal">
                  <span className="h-4 w-4 rounded-full bg-blue-900" />
                  Biru
                </Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="red" id="red" />
                <Label htmlFor="red" className="flex cursor-pointer items-center gap-2 font-normal">
                  <span className="h-4 w-4 rounded-full bg-red-700" />
                  Merah
                </Label>
              </div>
            </RadioGroup>
          </div>

          <div className="space-y-3">
            <Label>Ketebalan Garis</Label>
            <Select value={lineWidth} onValueChange={(v) => setLineWidth(v as LineWidth)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="thin">Tipis</SelectItem>
                <SelectItem value="medium">Sedang</SelectItem>
                <SelectItem value="thick">Tebal</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-3">
            <Label>Ukuran Output</Label>
            <Select value={outputSize} onValueChange={(v) => setOutputSize(v as OutputSize)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="small">Kecil (200px)</SelectItem>
                <SelectItem value="medium">Sedang (400px)</SelectItem>
                <SelectItem value="large">Besar (600px)</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Download Button */}
        <Button onClick={handleDownload} size="lg" className="w-full" disabled={!hasSignature}>
          <Download className="mr-2 h-5 w-5" />
          Unduh PNG Transparan
        </Button>
      </div>
    </ToolPageLayout>
  );
};

export default SignaturePad;
