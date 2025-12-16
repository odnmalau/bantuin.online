import { useState, useCallback, useRef, useEffect } from "react";
import { useTranslation } from "react-i18next";
import imageCompression from "browser-image-compression";

import { Upload, Download, Trash2, Image } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Slider } from "@/components/ui/slider";
import { Label } from "@/components/ui/label";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

const ImageCompressor = () => {
  const { t } = useTranslation();
  const { toast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  
  const [originalFile, setOriginalFile] = useState<File | null>(null);
  const [originalPreview, setOriginalPreview] = useState<string>("");
  const [compressedFile, setCompressedFile] = useState<File | null>(null);
  const [compressedPreview, setCompressedPreview] = useState<string>("");
  const [quality, setQuality] = useState(80);
  const [isCompressing, setIsCompressing] = useState(false);
  const [isDragging, setIsDragging] = useState(false);

  // Cleanup URLs on unmount
  useEffect(() => {
    return () => {
      if (originalPreview) URL.revokeObjectURL(originalPreview);
      if (compressedPreview) URL.revokeObjectURL(compressedPreview);
    };
  }, [originalPreview, compressedPreview]);

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  };

  const calculateSavings = (): number => {
    if (!originalFile || !compressedFile) return 0;
    return Math.round((1 - compressedFile.size / originalFile.size) * 100);
  };

  const compressImage = useCallback(async (file: File, qualityValue: number) => {
    setIsCompressing(true);
    
    try {
      const options = {
        maxSizeMB: 10,
        maxWidthOrHeight: 1920,
        useWebWorker: true,
        initialQuality: qualityValue / 100,
      };

      const compressedBlob = await imageCompression(file, options);
      const compressedFileResult = new File([compressedBlob], file.name, {
        type: compressedBlob.type,
      });

      // Revoke old preview URL
      if (compressedPreview) URL.revokeObjectURL(compressedPreview);
      
      setCompressedFile(compressedFileResult);
      setCompressedPreview(URL.createObjectURL(compressedFileResult));
    } catch (error) {
      toast({
        title: t('compress.toast_fail'),
        description: t('compress.toast_fail_desc'),
        variant: "destructive",
      });
    } finally {
      setIsCompressing(false);
    }
  }, [compressedPreview, toast, t]);

  const handleFileSelect = useCallback(async (file: File) => {
    if (!file.type.startsWith("image/")) {
      toast({
        title: t('compress.toast_format'),
        description: t('compress.toast_format_desc'),
        variant: "destructive",
      });
      return;
    }

    // Revoke old preview URLs
    if (originalPreview) URL.revokeObjectURL(originalPreview);
    if (compressedPreview) URL.revokeObjectURL(compressedPreview);

    setOriginalFile(file);
    setOriginalPreview(URL.createObjectURL(file));
    setCompressedFile(null);
    setCompressedPreview("");

    await compressImage(file, quality);
  }, [originalPreview, compressedPreview, quality, compressImage, toast, t]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) handleFileSelect(file);
  };

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    const file = e.dataTransfer.files?.[0];
    if (file) handleFileSelect(file);
  }, [handleFileSelect]);

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleQualityChange = async (value: number[]) => {
    const newQuality = value[0];
    setQuality(newQuality);
    if (originalFile) {
      await compressImage(originalFile, newQuality);
    }
  };

  const handleDownload = () => {
    if (!compressedFile) return;
    
    const link = document.createElement("a");
    link.href = URL.createObjectURL(compressedFile);
    link.download = `compressed_${compressedFile.name}`;
    link.click();
    URL.revokeObjectURL(link.href);

    toast({
      title: t('compress.toast_success'),
      description: t('compress.toast_success_desc'),
    });
  };

  const handleReset = () => {
    if (originalPreview) URL.revokeObjectURL(originalPreview);
    if (compressedPreview) URL.revokeObjectURL(compressedPreview);
    
    setOriginalFile(null);
    setOriginalPreview("");
    setCompressedFile(null);
    setCompressedPreview("");
    setQuality(80);
    
    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
  };

  return (
    <ToolPageLayout
      toolNumber="02"
      title={t('tool_items.compress.title')}
      subtitle={t('compress.subtitle')}
      description={t('tool_items.compress.desc')}
    >
      <SEOHead 
        title={t('compress.meta.title')}
        description={t('compress.meta.description')}
        path="/tools/compress"
        keywords={t('compress.meta.keywords', { returnObjects: true }) as string[]}
      />
      <div className="space-y-6">
        {/* Upload Card */}
        <Card className="animate-fade-in-up stagger-1 rounded-sm border-border">
          <CardHeader>
            <CardTitle className="font-display flex items-center gap-2 text-xl">
              <Upload className="h-5 w-5 text-primary" />
              {t('compress.title_upload')}
            </CardTitle>
            <CardDescription>
              {t('compress.desc_upload')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div
              onClick={() => fileInputRef.current?.click()}
              onDrop={handleDrop}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              className={`flex min-h-[160px] cursor-pointer flex-col items-center justify-center rounded-sm border-2 border-dashed transition-colors ${
                isDragging
                  ? "border-primary bg-primary/5"
                  : "border-border hover:border-primary/50 hover:bg-muted/50"
              }`}
            >
              <Image className="mb-3 h-10 w-10 text-muted-foreground" />
              <p className="text-sm font-medium text-foreground">
                {t('compress.drag_drop')}
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                {t('compress.format_info')}
              </p>
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              onChange={handleInputChange}
              className="hidden"
            />
          </CardContent>
        </Card>

        {/* Quality Settings */}
        {originalFile && (
          <Card className="animate-fade-in-up stagger-2 rounded-sm border-border">
            <CardHeader>
              <CardTitle className="font-display text-xl">{t('compress.title_quality')}</CardTitle>
              <CardDescription>
                {t('compress.desc_quality')}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <Label className="font-medium">{t('compress.label_quality')}</Label>
                  <span className="font-display text-sm font-semibold text-primary">{quality}%</span>
                </div>
                <Slider
                  value={[quality]}
                  onValueChange={handleQualityChange}
                  min={10}
                  max={100}
                  step={5}
                  disabled={isCompressing}
                  className="**:[[role=slider]]:rounded-sm"
                />
                <div className="flex justify-between text-xs text-muted-foreground">
                  <span>{t('compress.quality_small')}</span>
                  <span>{t('compress.quality_high')}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Results */}
        {originalFile && compressedFile && (
          <Card className="animate-fade-in-up stagger-3 rounded-sm border-border">
            <CardHeader>
              <CardTitle className="font-display text-xl">{t('compress.title_result')}</CardTitle>
              <CardDescription>
                {t('compress.desc_result')}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Image Previews - aspect-ratio prevents CLS */}
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <p className="text-sm font-medium text-muted-foreground">{t('compress.label_original')}</p>
                  <div className="aspect-4/3 overflow-hidden rounded-sm border border-border bg-muted/30">
                    <img
                      src={originalPreview}
                      alt="Original"
                      className="h-full w-full object-contain"
                      loading="lazy"
                    />
                  </div>
                  <p className="text-sm text-muted-foreground">
                    {formatFileSize(originalFile.size)}
                  </p>
                </div>
                <div className="space-y-2">
                  <p className="text-sm font-medium text-muted-foreground">{t('compress.label_result')}</p>
                  <div className="aspect-4/3 overflow-hidden rounded-sm border border-border bg-muted/30">
                    <img
                      src={compressedPreview}
                      alt="Compressed"
                      className="h-full w-full object-contain"
                      loading="lazy"
                    />
                  </div>
                  <p className="text-sm text-muted-foreground">
                    {formatFileSize(compressedFile.size)}
                  </p>
                </div>
              </div>

              {/* Savings Badge */}
              {calculateSavings() > 0 && (
                <div className="flex items-center justify-center">
                  <div className="rounded-sm bg-primary/10 px-4 py-2">
                    <p className="font-display text-sm font-semibold text-primary">
                      {t('compress.savings')} {calculateSavings()}%!
                    </p>
                  </div>
                </div>
              )}

              {/* Action Buttons */}
              <div className="flex flex-col gap-3 sm:flex-row">
                <Button
                  onClick={handleDownload}
                  className="flex-1 rounded-sm"
                  disabled={isCompressing}
                >
                  <Download className="mr-2 h-4 w-4" />
                  {t('compress.btn_download')}
                </Button>
                <Button
                  onClick={handleReset}
                  variant="outline"
                  className="flex-1 rounded-sm"
                >
                  <Trash2 className="mr-2 h-4 w-4" />
                  {t('compress.btn_reset')}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Loading State */}
        {isCompressing && (
          <div className="flex items-center justify-center py-8">
            <div className="flex items-center gap-3">
              <div className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              <p className="text-sm text-muted-foreground">{t('compress.loading')}</p>
            </div>
          </div>
        )}

        {/* Tips */}
        <div className="animate-fade-in-up stagger-4 border-l-2 border-primary bg-muted/30 p-4 pl-6">
          <h3 className="mb-2 font-display text-sm font-semibold uppercase tracking-wide text-foreground">{t('compress.tips_title')}</h3>
          <p className="text-sm text-muted-foreground">
            {t('compress.tips_desc')}
          </p>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default ImageCompressor;
