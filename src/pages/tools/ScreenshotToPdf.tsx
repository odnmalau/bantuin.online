import { useState, useRef } from "react";
import { Images, Upload, Download, Trash2, GripVertical, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { PDFDocument } from "pdf-lib";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

interface ImageFile {
  id: string;
  file: File;
  preview: string;
}

const ScreenshotToPdf = () => {
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
          title: "File tidak valid",
          description: `${file.name} bukan file gambar`,
          variant: "destructive",
        });
        return;
      }

      if (file.size > 10 * 1024 * 1024) {
        toast({
          title: "File terlalu besar",
          description: `${file.name} melebihi 10MB`,
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
        title: "Tidak ada gambar",
        description: "Tambahkan gambar terlebih dahulu",
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
        title: "PDF Berhasil Dibuat!",
        description: `${images.length} gambar berhasil digabung menjadi PDF`,
      });
    } catch (error) {
      console.error("Error generating PDF:", error);
      toast({
        title: "Gagal membuat PDF",
        description: "Terjadi kesalahan saat memproses gambar",
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

  const meta = toolsMetadata.screenshot;

  return (
    <ToolPageLayout
      toolNumber="11"
      title="Screenshot to PDF"
      subtitle="Gabung Gambar ke PDF"
      description="Gabungkan beberapa screenshot atau gambar menjadi satu file PDF."
    >
      <SEOHead 
        title={meta.title}
        description={meta.description}
        path={meta.path}
        keywords={meta.keywords}
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
            Klik untuk upload gambar
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            JPG, PNG, WebP (Maks. 10MB per file)
          </p>
        </div>

        {/* Images List */}
        {images.length > 0 && (
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="font-display text-sm font-semibold text-foreground">
                Gambar ({images.length})
              </h3>
              <Button variant="ghost" size="sm" onClick={clearAll}>
                <Trash2 className="mr-1 h-4 w-4" />
                Hapus Semua
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
              Tambah Gambar Lagi
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
              Memproses...
            </>
          ) : (
            <>
              <Download className="mr-2 h-4 w-4" />
              Buat PDF ({images.length} gambar)
            </>
          )}
        </Button>

        {/* Empty State Info */}
        {images.length === 0 && (
          <div className="rounded-lg border border-border bg-secondary/20 p-6">
            <h3 className="mb-2 font-display text-sm font-semibold text-foreground">
              Tips:
            </h3>
            <ul className="space-y-1 text-sm text-muted-foreground">
              <li>• Upload beberapa screenshot sekaligus</li>
              <li>• Atur urutan gambar dengan tombol panah</li>
              <li>• Setiap gambar = 1 halaman PDF</li>
              <li>• Ukuran halaman menyesuaikan gambar</li>
            </ul>
          </div>
        )}
      </div>
    </ToolPageLayout>
  );
};

export default ScreenshotToPdf;
