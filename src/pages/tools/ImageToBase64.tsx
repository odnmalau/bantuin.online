import { useState, useRef } from "react";
import { Image, Upload, Copy, Check, RotateCcw, FileImage } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

const ImageToBase64 = () => {
  const [base64, setBase64] = useState("");
  const [preview, setPreview] = useState("");
  const [fileName, setFileName] = useState("");
  const [fileSize, setFileSize] = useState("");
  const [copied, setCopied] = useState(false);
  const [copiedDataUrl, setCopiedDataUrl] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const { toast } = useToast();

  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + " KB";
    return (bytes / (1024 * 1024)).toFixed(2) + " MB";
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith("image/")) {
      toast({
        title: "File tidak valid",
        description: "Pilih file gambar (JPG, PNG, GIF, WebP, dll)",
        variant: "destructive",
      });
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      toast({
        title: "File terlalu besar",
        description: "Maksimal ukuran file adalah 5MB",
        variant: "destructive",
      });
      return;
    }

    setFileName(file.name);
    setFileSize(formatFileSize(file.size));

    const reader = new FileReader();
    reader.onload = (event) => {
      const result = event.target?.result as string;
      setPreview(result);
      // Extract base64 without the data URL prefix
      const base64Data = result.split(",")[1];
      setBase64(base64Data);
    };
    reader.readAsDataURL(file);
  };

  const handleCopyBase64 = async () => {
    if (!base64) return;

    await navigator.clipboard.writeText(base64);
    setCopied(true);
    toast({
      title: "Tersalin!",
      description: "Base64 string berhasil disalin",
    });
    setTimeout(() => setCopied(false), 2000);
  };

  const handleCopyDataUrl = async () => {
    if (!preview) return;

    await navigator.clipboard.writeText(preview);
    setCopiedDataUrl(true);
    toast({
      title: "Tersalin!",
      description: "Data URL berhasil disalin",
    });
    setTimeout(() => setCopiedDataUrl(false), 2000);
  };

  const handleReset = () => {
    setBase64("");
    setPreview("");
    setFileName("");
    setFileSize("");
    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
  };

  const meta = toolsMetadata.base64;

  return (
    <ToolPageLayout
      toolNumber="10"
      title="Image to Base64"
      subtitle="Konversi Gambar ke Base64"
      description="Konversi gambar ke format Base64 string untuk digunakan di CSS, HTML, atau API."
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
            onChange={handleFileChange}
            className="hidden"
          />
          <Upload className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
          <p className="text-sm font-medium text-foreground">
            Klik untuk upload gambar
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            JPG, PNG, GIF, WebP (Maks. 5MB)
          </p>
        </div>

        {/* Preview */}
        {preview && (
          <div className="animate-fade-in space-y-4">
            {/* Image Preview */}
            <div className="rounded-lg border border-border bg-card p-4">
              <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <FileImage className="h-4 w-4 text-primary" />
                  <span className="text-sm font-medium text-foreground">
                    {fileName}
                  </span>
                </div>
                <span className="text-xs text-muted-foreground">{fileSize}</span>
              </div>
              <div className="flex justify-center rounded-lg bg-secondary/30 p-4">
                <img
                  src={preview}
                  alt="Preview"
                  className="max-h-48 max-w-full rounded object-contain"
                />
              </div>
            </div>

            {/* Base64 Output */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label className="text-sm font-medium text-foreground">
                  Base64 String
                </label>
                <span className="text-xs text-muted-foreground">
                  {base64.length.toLocaleString()} karakter
                </span>
              </div>
              <Textarea
                value={base64}
                readOnly
                className="min-h-[120px] resize-none bg-secondary/30 font-mono text-xs"
              />
            </div>

            {/* Action Buttons */}
            <div className="grid grid-cols-2 gap-3">
              <Button onClick={handleCopyBase64} variant="outline">
                {copied ? (
                  <Check className="mr-2 h-4 w-4" />
                ) : (
                  <Copy className="mr-2 h-4 w-4" />
                )}
                {copied ? "Tersalin!" : "Salin Base64"}
              </Button>
              <Button onClick={handleCopyDataUrl} variant="outline">
                {copiedDataUrl ? (
                  <Check className="mr-2 h-4 w-4" />
                ) : (
                  <Copy className="mr-2 h-4 w-4" />
                )}
                {copiedDataUrl ? "Tersalin!" : "Salin Data URL"}
              </Button>
            </div>

            {/* Reset Button */}
            <Button variant="outline" onClick={handleReset} className="w-full">
              <RotateCcw className="mr-2 h-4 w-4" />
              Upload Gambar Lain
            </Button>
          </div>
        )}

        {/* Empty State */}
        {!preview && (
          <div className="rounded-lg border border-border bg-secondary/20 p-6">
            <h3 className="mb-2 font-display text-sm font-semibold text-foreground">
              Kegunaan Base64:
            </h3>
            <ul className="space-y-1 text-sm text-muted-foreground">
              <li>• Embed gambar langsung di CSS/HTML</li>
              <li>• Kirim gambar via JSON API</li>
              <li>• Simpan gambar di localStorage</li>
              <li>• Hindari request HTTP tambahan</li>
            </ul>
          </div>
        )}
      </div>
    </ToolPageLayout>
  );
};

export default ImageToBase64;
