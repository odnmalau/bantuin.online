import { useState, useMemo } from "react";
import { Copy, ExternalLink, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

const WhatsAppGenerator = () => {
  const [phoneNumber, setPhoneNumber] = useState("");
  const [message, setMessage] = useState("");
  const [copied, setCopied] = useState(false);
  const { toast } = useToast();

  // Format phone number: replace leading 0 with 62
  const formattedPhone = useMemo(() => {
    const cleaned = phoneNumber.replace(/\D/g, "");
    if (cleaned.startsWith("0")) {
      return "62" + cleaned.slice(1);
    }
    return cleaned;
  }, [phoneNumber]);

  // Generate WhatsApp link in real-time
  const whatsappLink = useMemo(() => {
    if (!formattedPhone) return "";
    const encodedMessage = encodeURIComponent(message.trim());
    return `https://wa.me/${formattedPhone}${encodedMessage ? `?text=${encodedMessage}` : ""}`;
  }, [formattedPhone, message]);

  const handleCopyLink = async () => {
    if (!whatsappLink) {
      toast({
        title: "Nomor HP kosong",
        description: "Masukkan nomor HP terlebih dahulu",
        variant: "destructive",
      });
      return;
    }

    try {
      await navigator.clipboard.writeText(whatsappLink);
      setCopied(true);
      toast({
        title: "Link berhasil disalin!",
        description: "Link WhatsApp sudah ada di clipboard kamu",
      });
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast({
        title: "Gagal menyalin",
        description: "Coba salin secara manual",
        variant: "destructive",
      });
    }
  };

  const handleTestLink = () => {
    if (!whatsappLink) {
      toast({
        title: "Nomor HP kosong",
        description: "Masukkan nomor HP terlebih dahulu",
        variant: "destructive",
      });
      return;
    }
    window.open(whatsappLink, "_blank", "noopener,noreferrer");
  };

  const meta = toolsMetadata.whatsapp;

  return (
    <ToolPageLayout
      toolNumber="01"
      title="WhatsApp Link"
      subtitle="Generator"
      description="Buat link chat WhatsApp dengan pesan siap pakai — Generate wa.me links instantly"
    >
      <SEOHead 
        title={meta.title}
        description={meta.description}
        path={meta.path}
        keywords={meta.keywords}
      />
      <div className="space-y-8">
        <Card className="animate-fade-in-up stagger-1 rounded-sm border-border bg-card">
          <CardHeader>
            <CardTitle className="font-display text-xl">Buat Link WhatsApp</CardTitle>
            <CardDescription>
              Masukkan nomor HP dan pesan, link akan dibuat otomatis
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Phone Number Input */}
            <div className="space-y-2">
              <Label htmlFor="phone" className="font-medium">Nomor HP</Label>
              <Input
                id="phone"
                type="tel"
                placeholder="08123456789"
                value={phoneNumber}
                onChange={(e) => setPhoneNumber(e.target.value)}
                className="rounded-sm border-border bg-background"
              />
              {formattedPhone && (
                <p className="text-sm text-muted-foreground">
                  Format internasional: <span className="font-medium text-primary">+{formattedPhone}</span>
                </p>
              )}
            </div>

            {/* Message Textarea */}
            <div className="space-y-2">
              <Label htmlFor="message" className="font-medium">Pesan (Opsional)</Label>
              <Textarea
                id="message"
                placeholder="Halo, saya tertarik dengan produk Anda..."
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                rows={4}
                className="resize-none rounded-sm border-border bg-background"
              />
            </div>

            {/* Generated Link Preview */}
            {whatsappLink && (
              <div className="space-y-2">
                <Label className="font-medium">Link WhatsApp</Label>
                <div className="rounded-sm border border-border bg-muted/50 p-3">
                  <p className="break-all font-mono text-sm text-muted-foreground">
                    {whatsappLink}
                  </p>
                </div>
              </div>
            )}

            {/* Action Buttons */}
            <div className="flex flex-col gap-3 sm:flex-row">
              <Button
                onClick={handleCopyLink}
                className="flex-1 gap-2 rounded-sm"
                disabled={!formattedPhone}
              >
                {copied ? (
                  <Check className="h-4 w-4" />
                ) : (
                  <Copy className="h-4 w-4" />
                )}
                {copied ? "Tersalin!" : "Copy Link"}
              </Button>
              <Button
                onClick={handleTestLink}
                variant="outline"
                className="flex-1 gap-2 rounded-sm"
                disabled={!formattedPhone}
              >
                <ExternalLink className="h-4 w-4" />
                Test Link
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Tips Section */}
        <div className="animate-fade-in-up stagger-2 border-l-2 border-primary bg-muted/30 p-4 pl-6">
          <h3 className="mb-2 font-display text-sm font-semibold uppercase tracking-wide text-foreground">Tips</h3>
          <ul className="space-y-1 text-sm text-muted-foreground">
            <li>• Nomor yang diawali 0 akan otomatis diubah ke kode +62 (Indonesia)</li>
            <li>• Link bisa langsung dibagikan ke sosial media atau website</li>
            <li>• Pesan bersifat opsional, bisa dikosongkan</li>
          </ul>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default WhatsAppGenerator;
