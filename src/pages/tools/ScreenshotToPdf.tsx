import { useState, useRef } from "react";
import { useTranslation } from "react-i18next";
import { Images, Upload, Download, Trash2, GripVertical, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { PDFDocument } from "pdf-lib";
import { SEOHead } from "@/components/SEOHead";

interface ImageFile {
  id: string;
  file: File;
  preview: string;
}

const ScreenshotToPdf = () => {
  const { t } = useTranslation();
  const [images, setImages] = useState<ImageFile[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const { toast } = useToast();

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files) return;

    const validFiles: ImageFile[] = [];

    Array.from(files).forEach((file) => {
      if (!file.type.startsWith("image/")) {
        toast({
          title: t('screenshot_to_pdf.toast_invalid_file'),
          description: t('screenshot_to_pdf.toast_invalid_file_desc', { name: file.name }),
          variant: "destructive",
        });
        return;
      }

      if (file.size > 10 * 1024 * 1024) {
        toast({
          title: t('screenshot_to_pdf.toast_file_too_large'),
          description: t('screenshot_to_pdf.toast_file_too_large_desc', { name: file.name }),
          variant: "destructive",
        });
        return;
      }

      validFiles.push({
        id: Math.random().toString(36).substr(2, 9),
        file,
        preview: URL.createObjectURL(file),
      });
    });

    setImages((prev) => [...prev, ...validFiles]);

    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
  };

  const removeImage = (id: string) => {
    setImages((prev) => {
      const img = prev.find((i) => i.id === id);
      if (img) URL.revokeObjectURL(img.preview);
      return prev.filter((i) => i.id !== id);
    });
  };

  const moveImage = (index: number, direction: "up" | "down") => {
    const newImages = [...images];
    const newIndex = direction === "up" ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= images.length) return;
    [newImages[index], newImages[newIndex]] = [newImages[newIndex], newImages[index]];
    setImages(newImages);
  };

  const generatePdf = async () => {
    if (images.length === 0) {
      toast({
        title: t('screenshot_to_pdf.toast_no_images'),
        description: t('screenshot_to_pdf.toast_no_images_desc'),
        variant: "destructive",
      });
      return;
    }

    setIsProcessing(true);

    try {
      const pdfDoc = await PDFDocument.create();

      for (const img of images) {
        const arrayBuffer = await img.file.arrayBuffer();
        const uint8Array = new Uint8Array(arrayBuffer);

        let embeddedImage;
        if (img.file.type === "image/png") {
          embeddedImage = await pdfDoc.embedPng(uint8Array);
        } else if (img.file.type === "image/jpeg" || img.file.type === "image/jpg") {
          embeddedImage = await pdfDoc.embedJpg(uint8Array);
        } else {
          // Convert other formats to PNG via canvas
          const canvas = document.createElement("canvas");
          const ctx = canvas.getContext("2d");
          const imgEl = new Image();

          await new Promise<void>((resolve, reject) => {
            imgEl.onload = () => {
              canvas.width = imgEl.width;
              canvas.height = imgEl.height;
              ctx?.drawImage(imgEl, 0, 0);
              resolve();
            };
            imgEl.onerror = reject;
            imgEl.src = img.preview;
          });

          const pngDataUrl = canvas.toDataURL("image/png");
          const pngData = pngDataUrl.split(",")[1];
          const pngBytes = Uint8Array.from(atob(pngData), (c) => c.charCodeAt(0));
          embeddedImage = await pdfDoc.embedPng(pngBytes);
        }

        const page = pdfDoc.addPage([embeddedImage.width, embeddedImage.height]);
        page.drawImage(embeddedImage, {
          x: 0,
          y: 0,
          width: embeddedImage.width,
          height: embeddedImage.height,
        });
      }

      const pdfBytes = await pdfDoc.save();
      const blob = new Blob([new Uint8Array(pdfBytes)], { type: "application/pdf" });
      const url = URL.createObjectURL(blob);

      toast({
        title: t('screenshot_to_pdf.toast_success'),
        description: t('screenshot_to_pdf.toast_success_desc', { count: images.length }),
      });
      
      const link = document.createElement("a");
      link.href = url;
      link.download = `screenshot-to-pdf-${Date.now()}.pdf`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);

    } catch (error) {
      console.error("Error generating PDF:", error);
      toast({
        title: t('screenshot_to_pdf.toast_error'),
        description: t('screenshot_to_pdf.toast_error_desc'),
        variant: "destructive",
      });
    } finally {
      setIsProcessing(false);
    }
  };

  const clearAll = () => {
    images.forEach((img) => URL.revokeObjectURL(img.preview));
    setImages([]);
  };



  return (
    <ToolPageLayout
      toolNumber="11"
      title={t('screenshot_to_pdf.title')}
      subtitle={t('screenshot_to_pdf.subtitle')}
      description={t('screenshot_to_pdf.desc_page')}
    >
      <SEOHead 
        title={t('screenshot_to_pdf.meta.title')}
        description={t('screenshot_to_pdf.meta.description')}
        path="/tools/screenshot-to-pdf"
        keywords={t('screenshot_to_pdf.meta.keywords', { returnObjects: true }) as string[]}
      />
      <div className="space-y-6">
        {/* Upload Area */}
        <div
          onClick={() => fileInputRef.current?.click()}
          className="cursor-pointer rounded-lg border-2 border-dashed border-border bg-secondary/20 p-8 text-center transition-colors hover:border-primary/50 hover:bg-secondary/40"
        >
          <input
            ref={fileInputRef}
            type="file"
            accept="image/*"
            multiple
            onChange={handleFileChange}
            className="hidden"
          />
          <Upload className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
          <p className="text-sm font-medium text-foreground">
            {t('screenshot_to_pdf.upload_hint')}
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            {t('screenshot_to_pdf.upload_subhint')}
          </p>
        </div>

        {/* Images List */}
        {images.length > 0 && (
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="font-display text-sm font-semibold text-foreground">
                {t('screenshot_to_pdf.list_title', { count: images.length })}
              </h3>
              <Button variant="ghost" size="sm" onClick={clearAll}>
                <Trash2 className="mr-1 h-4 w-4" />
                {t('screenshot_to_pdf.btn_clear_all')}
              </Button>
            </div>

            <div className="space-y-2">
              {images.map((img, index) => (
                <div
                  key={img.id}
                  className="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
                >
                  <div className="flex flex-col gap-1">
                    <button
                      onClick={() => moveImage(index, "up")}
                      disabled={index === 0}
                      className="rounded p-1 hover:bg-secondary disabled:opacity-30"
                    >
                      <GripVertical className="h-3 w-3 rotate-90" />
                    </button>
                    <button
                      onClick={() => moveImage(index, "down")}
                      disabled={index === images.length - 1}
                      className="rounded p-1 hover:bg-secondary disabled:opacity-30"
                    >
                      <GripVertical className="h-3 w-3 -rotate-90" />
                    </button>
                  </div>

                  <span className="w-6 text-center text-xs text-muted-foreground">
                    {index + 1}
                  </span>

                  <img
                    src={img.preview}
                    alt={img.file.name}
                    className="h-12 w-12 rounded object-cover"
                  />

                  <div className="flex-1 truncate text-sm text-foreground">
                    {img.file.name}
                  </div>

                  <button
                    onClick={() => removeImage(img.id)}
                    className="rounded p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ))}
            </div>

            {/* Add More Button */}
            <Button
              variant="outline"
              className="w-full"
              onClick={() => fileInputRef.current?.click()}
            >
              <Plus className="mr-2 h-4 w-4" />
              {t('screenshot_to_pdf.btn_add_more')}
            </Button>
          </div>
        )}

        {/* Generate PDF Button */}
        <Button
          onClick={generatePdf}
          disabled={images.length === 0 || isProcessing}
          className="w-full"
          size="lg"
        >
          {isProcessing ? (
            <>
              <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-primary-foreground border-t-transparent" />
              {t('screenshot_to_pdf.btn_processing')}
            </>
          ) : (
            <>
              <Download className="mr-2 h-4 w-4" />
              {t('screenshot_to_pdf.btn_create_pdf', { count: images.length })}
            </>
          )}
        </Button>

        {/* Empty State Info */}
        {images.length === 0 && (
          <div className="rounded-lg border border-border bg-secondary/20 p-6">
            <h3 className="mb-2 font-display text-sm font-semibold text-foreground">
              {t('screenshot_to_pdf.tips_title')}
            </h3>
            <ul className="space-y-1 text-sm text-muted-foreground">
              <li>• {t('screenshot_to_pdf.tips_list_1')}</li>
              <li>• {t('screenshot_to_pdf.tips_list_2')}</li>
              <li>• {t('screenshot_to_pdf.tips_list_3')}</li>
              <li>• {t('screenshot_to_pdf.tips_list_4')}</li>
            </ul>
          </div>
        )}
      </div>
    </ToolPageLayout>
  );
};

export default ScreenshotToPdf;
