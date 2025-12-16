import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import QRCode from "qrcode";

import { Download, Copy, QrCode } from "lucide-react";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { useToast } from "@/hooks/use-toast";
import { SEOHead } from "@/components/SEOHead";

const sizes = [
  { label: "Kecil", value: 128 },
  { label: "Sedang", value: 256 },
  { label: "Besar", value: 512 },
] as const;

const QRCodeGenerator = () => {
  const { t } = useTranslation();
  const [text, setText] = useState("");
  const [size, setSize] = useState<128 | 256 | 512>(256);
  const [fgColor, setFgColor] = useState("#000000");
  const [bgColor, setBgColor] = useState("#FFFFFF");
  const [qrDataUrl, setQrDataUrl] = useState("");
  const { toast } = useToast();

  useEffect(() => {
    if (!text.trim()) {
      if (qrDataUrl) setQrDataUrl("");
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
      title: t('qrcode.toast_success'),
      description: t('qrcode.toast_download_desc'),
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
        title: t('qrcode.toast_success'),
        description: t('qrcode.toast_copy_desc'),
      });
    } catch {
      toast({
        title: t('qrcode.toast_copy_fail'),
        description: t('qrcode.toast_copy_fail_desc'),
        variant: "destructive",
      });
    }
  };

  return (
    <ToolPageLayout
      toolNumber="05"
      title={t('tool_items.qr_code.title')}
      subtitle={t('qrcode.subtitle')}
      description={t('tool_items.qr_code.desc')}
    >
      <SEOHead 
        title={t('qrcode.meta.title')}
        description={t('qrcode.meta.description')}
        path="/tools/qrcode"
        keywords={t('qrcode.meta.keywords', { returnObjects: true }) as string[]}
      />
      <div className="space-y-6">
        {/* Input Text/URL */}
        <div className="space-y-2">
          <Label htmlFor="qr-text">{t('qrcode.label_text')}</Label>
          <Input
            id="qr-text"
            type="text"
            placeholder={t('qrcode.placeholder_text')}
            value={text}
            onChange={(e) => setText(e.target.value)}
            className="text-base"
          />
        </div>

        {/* Size Options */}
        <div className="space-y-2">
          <Label>{t('qrcode.label_size')}</Label>
          <div className="flex gap-2">
            {sizes.map((s) => (
              <Button
                key={s.value}
                variant={size === s.value ? "default" : "outline"}
                size="sm"
                onClick={() => setSize(s.value)}
              >
                {t(s.label === "Kecil" ? 'qrcode.size_small' : s.label === "Sedang" ? 'qrcode.size_medium' : 'qrcode.size_large')}
              </Button>
            ))}
          </div>
        </div>

        {/* Color Options */}
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="fg-color">{t('qrcode.label_color_qr')}</Label>
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
            <Label htmlFor="bg-color">{t('qrcode.label_color_bg')}</Label>
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
                <p className="text-sm">{t('qrcode.empty_state')}</p>
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
            {t('qrcode.btn_download')}
          </Button>
          <Button
            onClick={handleCopy}
            disabled={!qrDataUrl}
            variant="outline"
            className="flex-1"
          >
            <Copy className="mr-2 h-4 w-4" />
            {t('qrcode.btn_copy')}
          </Button>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default QRCodeGenerator;
