import { useState, useRef, useCallback, useEffect } from "react";
import ReactCrop, { type Crop, type PixelCrop, centerCrop, makeAspectCrop } from "react-image-crop";
import "react-image-crop/dist/ReactCrop.css";
import { Upload, Download, RotateCcw, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { useToast } from "@/hooks/use-toast";
import { getCroppedImg, getAspectRatio, PAS_FOTO_SIZES } from "@/utils/cropImage";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

const PasFoto = () => {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [imgSrc, setImgSrc] = useState<string>("");
  const [crop, setCrop] = useState<Crop>();
  const [completedCrop, setCompletedCrop] = useState<PixelCrop>();
  const [selectedSize, setSelectedSize] = useState<string>("3x4");
  const [isProcessing, setIsProcessing] = useState(false);
  const [previewUrl, setPreviewUrl] = useState<string>("");
  
  const imgRef = useRef<HTMLImageElement>(null);
  const { toast } = useToast();

  // Cleanup blob URLs on unmount to prevent memory leaks
  useEffect(() => {
    return () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
    };
  }, [previewUrl]);

  const onSelectFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const file = e.target.files[0];
      setSelectedFile(file);
      setPreviewUrl("");
      
      const reader = new FileReader();
      reader.addEventListener("load", () => {
        setImgSrc(reader.result?.toString() || "");
      });
      reader.readAsDataURL(file);
    }
  };

  const onImageLoad = useCallback((e: React.SyntheticEvent<HTMLImageElement>) => {
    const { width, height } = e.currentTarget;
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
  }, [selectedSize]);

  const handleSizeChange = (size: string) => {
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

  const handleGeneratePreview = async () => {
    if (!imgRef.current || !completedCrop) {
      toast({
        title: "Pilih area crop terlebih dahulu",
        description: "Sesuaikan area crop pada gambar",
        variant: "destructive",
      });
      return;
    }

    setIsProcessing(true);
    try {
      const blob = await getCroppedImg(imgRef.current, completedCrop, selectedSize);
      const url = URL.createObjectURL(blob);
      setPreviewUrl(url);
      
      toast({
        title: "Preview siap!",
        description: "Klik tombol unduh untuk menyimpan hasil",
      });
    } catch (error) {
      toast({
        title: "Gagal memproses gambar",
        description: "Silakan coba lagi",
        variant: "destructive",
      });
    } finally {
      setIsProcessing(false);
    }
  };

  const handleDownload = async () => {
    if (!imgRef.current || !completedCrop) return;

    setIsProcessing(true);
    try {
      const blob = await getCroppedImg(imgRef.current, completedCrop, selectedSize);
      const url = URL.createObjectURL(blob);
      
      const link = document.createElement("a");
      link.href = url;
      link.download = `pasfoto_${selectedSize}_${selectedFile?.name || "photo"}.jpg`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      URL.revokeObjectURL(url);
      
      toast({
        title: "Berhasil diunduh!",
        description: `Pas foto ${PAS_FOTO_SIZES[selectedSize].label} tersimpan`,
      });
    } catch (error) {
      toast({
        title: "Gagal mengunduh",
        description: "Silakan coba lagi",
        variant: "destructive",
      });
    } finally {
      setIsProcessing(false);
    }
  };

  const handleReset = () => {
    // Revoke URL before clearing state to prevent memory leak
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
    }
    setSelectedFile(null);
    setImgSrc("");
    setCrop(undefined);
    setCompletedCrop(undefined);
    setPreviewUrl("");
  };

  const handleDrop = (e: React.DragEvent<HTMLLabelElement>) => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith("image/")) {
      setSelectedFile(file);
      setPreviewUrl("");
      
      const reader = new FileReader();
      reader.addEventListener("load", () => {
        setImgSrc(reader.result?.toString() || "");
      });
      reader.readAsDataURL(file);
    }
  };

  const handleDragOver = (e: React.DragEvent<HTMLLabelElement>) => {
    e.preventDefault();
  };

  const meta = toolsMetadata.pasfoto;

  return (
    <ToolPageLayout
      toolNumber="03"
      title="Pas Foto"
      subtitle="Photo Resize Tool"
      description="Upload foto dan pilih ukuran pas foto standar Indonesia — Create passport photos"
    >
      <SEOHead 
        title={meta.title}
        description={meta.description}
        path={meta.path}
        keywords={meta.keywords}
      />
      <div className="space-y-6">
        {/* Main Card */}
        <Card className="animate-fade-in-up stagger-1 rounded-sm border-border bg-card">
          <CardHeader>
            <CardTitle className="font-display text-xl text-foreground">Buat Pas Foto</CardTitle>
            <CardDescription>
              Pilih ukuran lalu upload foto untuk di-crop sesuai rasio
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Size Selector */}
            <div className="space-y-3">
              <Label className="font-medium text-foreground">Pilih Ukuran — Select Size</Label>
              <RadioGroup
                value={selectedSize}
                onValueChange={handleSizeChange}
                className="flex flex-wrap gap-4"
              >
                {Object.entries(PAS_FOTO_SIZES).map(([key, config]) => (
                  <div key={key} className="flex items-center space-x-2">
                    <RadioGroupItem value={key} id={key} className="border-border" />
                    <Label
                      htmlFor={key}
                      className="cursor-pointer text-sm font-medium text-foreground"
                    >
                      {config.label}
                    </Label>
                  </div>
                ))}
              </RadioGroup>
            </div>

            {/* Upload Area */}
            {!imgSrc && (
              <label
                className="flex min-h-[200px] cursor-pointer flex-col items-center justify-center rounded-sm border-2 border-dashed border-border bg-muted/30 p-8 transition-colors hover:border-primary/50 hover:bg-muted/50"
                onDrop={handleDrop}
                onDragOver={handleDragOver}
              >
                <Upload className="mb-4 h-12 w-12 text-muted-foreground" />
                <p className="mb-2 text-center font-medium text-foreground">
                  Klik atau drag & drop foto di sini
                </p>
                <p className="text-center text-sm text-muted-foreground">
                  Supports JPG, PNG, WEBP
                </p>
                <input
                  type="file"
                  accept="image/*"
                  onChange={onSelectFile}
                  className="hidden"
                />
              </label>
            )}

            {/* Crop Area */}
            {imgSrc && (
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <Label className="font-medium text-foreground">Sesuaikan Area Crop — Adjust Crop Area</Label>
                  <Button variant="outline" size="sm" onClick={handleReset} className="rounded-sm">
                    <RotateCcw className="mr-2 h-4 w-4" />
                    Reset
                  </Button>
                </div>
                
                <div className="flex justify-center overflow-hidden rounded-sm border border-border bg-muted/30 p-4">
                  <ReactCrop
                    crop={crop}
                    onChange={(_, percentCrop) => setCrop(percentCrop)}
                    onComplete={(c) => setCompletedCrop(c)}
                    aspect={getAspectRatio(selectedSize)}
                    className="max-h-[400px]"
                  >
                    <img
                      ref={imgRef}
                      alt="Crop preview"
                      src={imgSrc}
                      onLoad={onImageLoad}
                      className="max-h-[400px] w-auto"
                    />
                  </ReactCrop>
                </div>

                {/* Action Buttons */}
                <div className="flex flex-col gap-3 sm:flex-row">
                  <Button
                    onClick={handleGeneratePreview}
                    disabled={isProcessing || !completedCrop}
                    className="flex-1 rounded-sm"
                    variant="outline"
                  >
                    <Check className="mr-2 h-4 w-4" />
                    Lihat Preview
                  </Button>
                  <Button
                    onClick={handleDownload}
                    disabled={isProcessing || !completedCrop}
                    className="flex-1 rounded-sm"
                  >
                    <Download className="mr-2 h-4 w-4" />
                    {isProcessing ? "Memproses..." : "Unduh Hasil"}
                  </Button>
                </div>
              </div>
            )}

            {/* Preview Result */}
            {previewUrl && (
              <div className="space-y-3">
                <Label className="font-medium text-foreground">Preview Hasil — Result Preview</Label>
                <div className="flex justify-center rounded-sm border border-border bg-muted/30 p-4">
                  <img
                    src={previewUrl}
                    alt="Cropped preview"
                    className="max-h-[300px] rounded-sm"
                  />
                </div>
                <p className="text-center text-sm text-muted-foreground">
                  Ukuran: {PAS_FOTO_SIZES[selectedSize].label} • Kualitas tinggi (95%)
                </p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Tips Section */}
        <div className="animate-fade-in-up stagger-2 border-l-2 border-primary bg-muted/30 p-4 pl-6">
          <h3 className="mb-2 font-display text-sm font-semibold uppercase tracking-wide text-foreground">Tips Pas Foto</h3>
          <ul className="space-y-2 text-sm text-muted-foreground">
            <li>• Gunakan foto dengan pencahayaan yang baik dan wajah terlihat jelas</li>
            <li>• Pastikan background foto polos (putih, biru, atau merah)</li>
            <li>• Ukuran 2x3 untuk lamaran kerja, 3x4 untuk KTP/kartu pelajar, 4x6 untuk visa/paspor</li>
            <li>• Semua proses dilakukan di browser — foto kamu 100% aman dan privat</li>
          </ul>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default PasFoto;
