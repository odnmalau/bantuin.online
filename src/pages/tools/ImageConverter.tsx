import { useState, useCallback } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";

import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Slider } from "@/components/ui/slider";
import { Upload, Download, X, Image, ArrowRight, Loader2 } from "lucide-react";
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

const formatExtMap: Record<OutputFormat, string> = {
  jpeg: ".jpg",
  png: ".png",
  webp: ".webp",
};

const formatBytes = (bytes: number): string => {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

const ImageConverter = () => {
  const { t } = useTranslation();
  const [files, setFiles] = useState<File[]>([]);
  const [outputFormat, setOutputFormat] = useState<OutputFormat>("jpeg");
  const [quality, setQuality] = useState(80);
  const [convertedImages, setConvertedImages] = useState<ConvertedImage[]>([]);
  const [isConverting, setIsConverting] = useState(false);
  const [isDragging, setIsDragging] = useState(false);

  const handleFileSelect = useCallback((selectedFiles: FileList | null) => {
    if (!selectedFiles) return;
    
    const imageFiles = Array.from(selectedFiles).filter((file) =>
      file.type.startsWith("image/")
    );
    
    
    if (imageFiles.length === 0) {
      toast.error(t('converter.toast_error_select'));
      return;
    }
    
    setFiles((prev) => [...prev, ...imageFiles]);
    setConvertedImages([]);
  }, [t]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    handleFileSelect(e.dataTransfer.files);
  }, [handleFileSelect]);

  const removeFile = (index: number) => {
    setFiles((prev) => prev.filter((_, i) => i !== index));
    setConvertedImages([]);
  };

  const convertImage = async (file: File): Promise<ConvertedImage> => {
    return new Promise((resolve, reject) => {
      const img = new window.Image();
      const canvas = document.createElement("canvas");
      const ctx = canvas.getContext("2d");
      
      img.onload = () => {
        canvas.width = img.width;
        canvas.height = img.height;
        
        if (!ctx) {
          reject(new Error("Canvas context not available"));
          return;
        }
        
        // For JPEG, fill white background (no transparency)
        if (outputFormat === "jpeg") {
          ctx.fillStyle = "#ffffff";
          ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
        
        ctx.drawImage(img, 0, 0);
        
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
              originalFormat: file.type.split("/")[1].toUpperCase(),
              convertedBlob: blob,
              convertedSize: blob.size,
              previewUrl,
            });
          },
          formatMimeMap[outputFormat],
          quality / 100
        );
      };
      
      img.onerror = () => reject(new Error("Failed to load image"));
      img.src = URL.createObjectURL(file);
    });
  };

  const handleConvert = async () => {
    if (files.length === 0) {
      toast.error(t('converter.toast_error_select'));
      return;
    }
    
    setIsConverting(true);
    
    try {
      const results = await Promise.all(files.map(convertImage));
      setConvertedImages(results);
      toast.success(`${results.length} ${t('converter.toast_success')}`);
    } catch (error) {
      toast.error(t('converter.toast_fail'));
    } finally {
      setIsConverting(false);
    }
  };

  const downloadImage = (image: ConvertedImage) => {
    const link = document.createElement("a");
    const newName = image.originalName.replace(/\.[^.]+$/, formatExtMap[outputFormat]);
    link.download = newName;
    link.href = image.previewUrl;
    link.click();
  };

  const downloadAll = () => {
    convertedImages.forEach((img, index) => {
      setTimeout(() => downloadImage(img), index * 200);
    });
    toast.success(t('converter.toast_download_all'));
  };

  const clearAll = () => {
    // Cleanup URLs
    convertedImages.forEach((img) => URL.revokeObjectURL(img.previewUrl));
    setFiles([]);
    setConvertedImages([]);
  };

  return (
    <ToolPageLayout
      toolNumber="20"
      title={t('tool_items.image_converter.title')}
      subtitle={t('converter.subtitle')}
      description={t('tool_items.image_converter.desc')}
    >
      <SEOHead
        title={t('converter.meta.title')}
        description={t('converter.meta.description')}
        path="/tools/image-converter"
        keywords={t('converter.meta.keywords', { returnObjects: true }) as string[]}
      />
      <div className="mx-auto max-w-3xl space-y-6">
        {/* Upload Area */}
        <div
          className={`rounded-lg border-2 border-dashed p-8 text-center transition-colors ${
            isDragging
              ? "border-primary bg-primary/5"
              : "border-border hover:border-primary/50"
          }`}
          onDragOver={(e) => {
            e.preventDefault();
            setIsDragging(true);
          }}
          onDragLeave={() => setIsDragging(false)}
          onDrop={handleDrop}
        >
          <input
            type="file"
            accept="image/*"
            multiple
            onChange={(e) => handleFileSelect(e.target.files)}
            className="hidden"
            id="file-input"
          />
          <label htmlFor="file-input" className="cursor-pointer">
            <Upload className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
            <p className="text-lg font-medium">{t('converter.drop_text')}</p>
            <p className="mt-1 text-sm text-muted-foreground">
              {t('converter.support_text')}
            </p>
          </label>
        </div>

        {/* File List */}
        {files.length > 0 && (
          <div className="space-y-3 rounded-lg border border-border bg-card p-4">
            <div className="flex items-center justify-between">
              <Label className="text-base font-medium">{files.length} {t('converter.files_selected')}</Label>
              <Button variant="ghost" size="sm" onClick={clearAll}>
                {t('converter.btn_clear')}
              </Button>
            </div>
            <div className="max-h-[200px] space-y-2 overflow-y-auto">
              {files.map((file, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between rounded-lg bg-secondary/50 px-3 py-2"
                >
                  <div className="flex items-center gap-3 truncate">
                    <Image className="h-4 w-4 shrink-0 text-primary" />
                    <span className="truncate text-sm">{file.name}</span>
                    <span className="text-xs text-muted-foreground">
                      ({formatBytes(file.size)})
                    </span>
                  </div>
                  <Button variant="ghost" size="icon" onClick={() => removeFile(index)}>
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
            <Label>{t('converter.format_output')}</Label>
            <Select value={outputFormat} onValueChange={(v) => setOutputFormat(v as OutputFormat)}>
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
              <Label>{t('converter.label_quality')}</Label>
              <span className="text-sm font-medium text-primary">{quality}%</span>
            </div>
            <Slider
              value={[quality]}
              onValueChange={(v) => setQuality(v[0])}
              min={10}
              max={100}
              step={5}
              disabled={outputFormat === "png"}
            />
            {outputFormat === "png" && (
              <p className="text-xs text-muted-foreground">{t('converter.png_info')}</p>
            )}
          </div>
        </div>

        {/* Convert Button */}
        <Button
          onClick={handleConvert}
          size="lg"
          className="w-full"
          disabled={files.length === 0 || isConverting}
        >
          {isConverting ? (
            <>
              <Loader2 className="mr-2 h-5 w-5 animate-spin" />
              {t('converter.converting')}
            </>
          ) : (
            <>
              <ArrowRight className="mr-2 h-5 w-5" />
              {t('converter.btn_convert')}
            </>
          )}
        </Button>

        {/* Results */}
        {convertedImages.length > 0 && (
          <div className="space-y-4 rounded-lg border border-border bg-card p-6">
            <div className="flex items-center justify-between">
              <Label className="text-base font-medium">{t('converter.res_title')}</Label>
              {convertedImages.length > 1 && (
                <Button variant="outline" size="sm" onClick={downloadAll}>
                  <Download className="mr-2 h-4 w-4" />
                  {t('converter.btn_download_all')}
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
                      src={img.previewUrl}
                      alt={img.originalName}
                      className="h-full w-full object-contain"
                    />
                  </div>
                  <div className="p-3">
                    <p className="truncate text-sm font-medium">{img.originalName}</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {img.originalFormat} ({formatBytes(img.originalSize)})
                      <ArrowRight className="mx-1 inline h-3 w-3" />
                      {outputFormat.toUpperCase()} ({formatBytes(img.convertedSize)})
                    </p>
                    <Button
                      variant="outline"
                      size="sm"
                      className="mt-3 w-full"
                      onClick={() => downloadImage(img)}
                    >
                      <Download className="mr-2 h-4 w-4" />
                      {t('converter.btn_download')}
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
