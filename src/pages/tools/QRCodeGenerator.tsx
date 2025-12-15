import { useState, useEffect } from "react";
import QRCode from "qrcode";
import { Download, Copy, QrCode } from "lucide-react";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { useToast } from "@/hooks/use-toast";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

const sizes = [
  { label: "Kecil", value: 128 },
  { label: "Sedang", value: 256 },
  { label: "Besar", value: 512 },
] as const;

const QRCodeGenerator = () => {
  const [text, setText] = useState("");
  const [size, setSize] = useState<128 | 256 | 512>(256);
  const [fgColor, setFgColor] = useState("#000000");
  const [bgColor, setBgColor] = useState("#FFFFFF");
  const [qrDataUrl, setQrDataUrl] = useState("");
  const { toast } = useToast();

  useEffect(() => {
    if (!text.trim()) {
      setQrDataUrl("");
      return;
    }

    const generateQR = async () => {
      try {
        const url = await QRCode.toDataURL(text, {
          width: size,
          margin: 2,
          color: { dark: fgColor, light: bgColor },
        });
        setQrDataUrl(url);
      } catch (err) {
        console.error("QR generation error:", err);
      }
    };

    const timeout = setTimeout(generateQR, 300);
    return () => clearTimeout(timeout);
  }, [text, size, fgColor, bgColor]);

  const handleDownload = () => {
    if (!qrDataUrl) return;
    const link = document.createElement("a");
    link.download = "qrcode.png";
    link.href = qrDataUrl;
    link.click();
    toast({
      title: "Berhasil!",
      description: "QR Code berhasil diunduh",
    });
  };

  const handleCopy = async () => {
    if (!qrDataUrl) return;
    try {
      const blob = await fetch(qrDataUrl).then((r) => r.blob());
      await navigator.clipboard.write([
        new ClipboardItem({ "image/png": blob }),
      ]);
      toast({
        title: "Berhasil!",
        description: "QR Code disalin ke clipboard",
      });
    } catch {
      toast({
        title: "Gagal menyalin",
        description: "Browser tidak mendukung fitur ini",
        variant: "destructive",
      });
    }
  };

  const meta = toolsMetadata.qrcode;

  return (
    <ToolPageLayout
      toolNumber="05"
      title="QR Code"
      subtitle="Generator"
      description="Buat QR code dari teks atau URL secara instan. 100% diproses di browser, data kamu aman."
    >
      <SEOHead 
        title={meta.title}
        description={meta.description}
        path={meta.path}
        keywords={meta.keywords}
      />
      <div className="space-y-6">
        {/* Input Text/URL */}
        <div className="space-y-2">
          <Label htmlFor="qr-text">Teks atau URL</Label>
          <Input
            id="qr-text"
            type="text"
            placeholder="Masukkan teks atau URL..."
            value={text}
            onChange={(e) => setText(e.target.value)}
            className="text-base"
          />
        </div>

        {/* Size Options */}
        <div className="space-y-2">
          <Label>Ukuran</Label>
          <div className="flex gap-2">
            {sizes.map((s) => (
              <Button
                key={s.value}
                variant={size === s.value ? "default" : "outline"}
                size="sm"
                onClick={() => setSize(s.value)}
              >
                {s.label}
              </Button>
            ))}
          </div>
        </div>

        {/* Color Options */}
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="fg-color">Warna QR</Label>
            <div className="flex items-center gap-2">
              <input
                id="fg-color"
                type="color"
                value={fgColor}
                onChange={(e) => setFgColor(e.target.value)}
                className="h-10 w-14 cursor-pointer rounded border border-border bg-transparent"
              />
              <span className="font-mono text-sm text-muted-foreground">
                {fgColor}
              </span>
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="bg-color">Warna Background</Label>
            <div className="flex items-center gap-2">
              <input
                id="bg-color"
                type="color"
                value={bgColor}
                onChange={(e) => setBgColor(e.target.value)}
                className="h-10 w-14 cursor-pointer rounded border border-border bg-transparent"
              />
              <span className="font-mono text-sm text-muted-foreground">
                {bgColor}
              </span>
            </div>
          </div>
        </div>

        {/* QR Preview */}
        <div className="flex flex-col items-center rounded-lg border border-border bg-card p-6">
          {qrDataUrl ? (
            <img
              src={qrDataUrl}
              alt="Generated QR Code"
              className="max-w-full"
              style={{ width: size, height: size }}
            />
          ) : (
            <div
              className="flex items-center justify-center rounded border-2 border-dashed border-border bg-muted/30"
              style={{ width: size, height: size }}
            >
              <div className="text-center text-muted-foreground">
                <QrCode className="mx-auto mb-2 h-12 w-12 opacity-50" />
                <p className="text-sm">Masukkan teks untuk generate QR</p>
              </div>
            </div>
          )}
        </div>

        {/* Action Buttons */}
        <div className="flex gap-3">
          <Button
            onClick={handleDownload}
            disabled={!qrDataUrl}
            className="flex-1"
          >
            <Download className="mr-2 h-4 w-4" />
            Download PNG
          </Button>
          <Button
            onClick={handleCopy}
            disabled={!qrDataUrl}
            variant="outline"
            className="flex-1"
          >
            <Copy className="mr-2 h-4 w-4" />
            Salin
          </Button>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default QRCodeGenerator;
