import { useState } from "react";
import { Type, Copy, RotateCcw, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

const TextCaseConverter = () => {
  const [text, setText] = useState("");
  const [copied, setCopied] = useState(false);
  const { toast } = useToast();

  const convertCase = (type: string) => {
    if (!text.trim()) {
      toast({
        title: "Teks kosong",
        description: "Masukkan teks terlebih dahulu",
        variant: "destructive",
      });
      return;
    }

    let result = "";
    switch (type) {
      case "upper":
        result = text.toUpperCase();
        break;
      case "lower":
        result = text.toLowerCase();
        break;
      case "title":
        result = text
          .toLowerCase()
          .split(" ")
          .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
          .join(" ");
        break;
      case "sentence":
        result = text
          .toLowerCase()
          .replace(/(^\s*\w|[.!?]\s*\w)/g, (c) => c.toUpperCase());
        break;
      case "camel":
        result = text
          .toLowerCase()
          .replace(/[^a-zA-Z0-9]+(.)/g, (_, char) => char.toUpperCase());
        break;
      case "snake":
        result = text
          .toLowerCase()
          .replace(/\s+/g, "_")
          .replace(/[^a-zA-Z0-9_]/g, "");
        break;
      case "kebab":
        result = text
          .toLowerCase()
          .replace(/\s+/g, "-")
          .replace(/[^a-zA-Z0-9-]/g, "");
        break;
      default:
        result = text;
    }
    setText(result);
  };

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

  const caseButtons = [
    { type: "upper", label: "UPPERCASE" },
    { type: "lower", label: "lowercase" },
    { type: "title", label: "Title Case" },
    { type: "sentence", label: "Sentence case" },
    { type: "camel", label: "camelCase" },
    { type: "snake", label: "snake_case" },
    { type: "kebab", label: "kebab-case" },
  ];

  const meta = toolsMetadata.textcase;

  return (
    <ToolPageLayout
      toolNumber="06"
      title="Text Case Converter"
      subtitle="Ubah Format Huruf"
      description="Ubah teks ke berbagai format huruf: UPPERCASE, lowercase, Title Case, dan lainnya."
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
          <p className="text-xs text-muted-foreground">
            {text.length} karakter
          </p>
        </div>

        {/* Case Buttons */}
        <div className="space-y-2">
          <label className="text-sm font-medium text-foreground">
            Pilih Format
          </label>
          <div className="flex flex-wrap gap-2">
            {caseButtons.map((btn) => (
              <Button
                key={btn.type}
                variant="outline"
                size="sm"
                onClick={() => convertCase(btn.type)}
                className="font-mono text-xs"
              >
                {btn.label}
              </Button>
            ))}
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

export default TextCaseConverter;
