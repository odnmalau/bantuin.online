import { useState } from "react";
import { FileText, Copy, RotateCcw, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

const WordCounter = () => {
  const [text, setText] = useState("");
  const [copied, setCopied] = useState(false);
  const { toast } = useToast();

  // Calculate statistics
  const charCount = text.length;
  const charNoSpaces = text.replace(/\s/g, "").length;
  const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
  const sentenceCount = text.trim()
    ? (text.match(/[.!?]+/g) || []).length || (text.trim() ? 1 : 0)
    : 0;
  const paragraphCount = text.trim()
    ? text.split(/\n\s*\n/).filter((p) => p.trim()).length
    : 0;
  
  // Average reading speed: 200 words per minute
  const readingTime = Math.ceil(wordCount / 200);
  // Average speaking speed: 150 words per minute
  const speakingTime = Math.ceil(wordCount / 150);

  const handleCopy = async () => {
    if (!text.trim()) return;
    
    await navigator.clipboard.writeText(text);
    setCopied(true);
    toast({
      title: "Tersalin!",
      description: "Teks berhasil disalin ke clipboard",
    });
    setTimeout(() => setCopied(false), 2000);
  };

  const handleReset = () => {
    setText("");
  };

  const stats = [
    { label: "Karakter", value: charCount },
    { label: "Karakter (tanpa spasi)", value: charNoSpaces },
    { label: "Kata", value: wordCount },
    { label: "Kalimat", value: sentenceCount },
    { label: "Paragraf", value: paragraphCount },
  ];

  const meta = toolsMetadata.wordcounter;

  return (
    <ToolPageLayout
      toolNumber="07"
      title="Word Counter"
      subtitle="Hitung Kata & Karakter"
      description="Hitung jumlah kata, karakter, kalimat, dan estimasi waktu baca teks Anda."
    >
      <SEOHead 
        title={meta.title}
        description={meta.description}
        path={meta.path}
        keywords={meta.keywords}
      />
      <div className="space-y-6">
        {/* Input Area */}
        <div className="space-y-2">
          <label className="text-sm font-medium text-foreground">
            Masukkan Teks
          </label>
          <Textarea
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder="Ketik atau tempel teks di sini..."
            className="min-h-[200px] resize-none font-body"
          />
        </div>

        {/* Statistics Grid */}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {stats.map((stat) => (
            <div
              key={stat.label}
              className="rounded-lg border border-border bg-card p-4 text-center"
            >
              <p className="font-display text-2xl font-bold text-primary">
                {stat.value.toLocaleString()}
              </p>
              <p className="text-xs text-muted-foreground">{stat.label}</p>
            </div>
          ))}
        </div>

        {/* Reading Time */}
        <div className="rounded-lg border border-border bg-secondary/30 p-4">
          <h3 className="mb-2 font-display text-sm font-semibold text-foreground">
            Estimasi Waktu
          </h3>
          <div className="flex gap-6 text-sm">
            <div>
              <span className="text-muted-foreground">Baca: </span>
              <span className="font-medium text-foreground">
                {readingTime} menit
              </span>
            </div>
            <div>
              <span className="text-muted-foreground">Bicara: </span>
              <span className="font-medium text-foreground">
                {speakingTime} menit
              </span>
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex gap-3">
          <Button
            onClick={handleCopy}
            disabled={!text.trim()}
            className="flex-1"
          >
            {copied ? (
              <Check className="mr-2 h-4 w-4" />
            ) : (
              <Copy className="mr-2 h-4 w-4" />
            )}
            {copied ? "Tersalin!" : "Salin Teks"}
          </Button>
          <Button
            variant="outline"
            onClick={handleReset}
            disabled={!text.trim()}
          >
            <RotateCcw className="mr-2 h-4 w-4" />
            Reset
          </Button>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default WordCounter;
