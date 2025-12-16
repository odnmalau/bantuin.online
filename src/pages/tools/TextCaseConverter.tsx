import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Type, Copy, RotateCcw, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

const TextCaseConverter = () => {
  const { t } = useTranslation();
  const [text, setText] = useState("");
  const [copied, setCopied] = useState(false);
  const { toast } = useToast();

  const convertCase = (type: string) => {
    if (!text.trim()) {
      toast({
        title: t('text_case.toast_empty'),
        description: t('text_case.toast_empty_desc'),
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
      title: t('text_case.toast_copied'),
      description: t('text_case.toast_copied_desc'),
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

  return (
    <ToolPageLayout
      toolNumber="06"
      title={t('text_case.title')}
      subtitle={t('text_case.subtitle')}
      description={t('text_case.desc_page')}
    >
      <SEOHead 
        title={t('text_case.meta.title')}
        description={t('text_case.meta.description')}
        path="/tools/text-case"
        keywords={t('text_case.meta.keywords', { returnObjects: true }) as string[]}
      />
      <div className="space-y-6">
        {/* Input Area */}
        <div className="space-y-2">
          <label className="text-sm font-medium text-foreground">
            {t('text_case.label_input')}
          </label>
          <Textarea
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder={t('text_case.placeholder_input')}
            className="min-h-[200px] resize-none font-body"
          />
          <p className="text-xs text-muted-foreground">
            {text.length} {t('text_case.text_chars')}
          </p>
        </div>

        {/* Case Buttons */}
        <div className="space-y-2">
          <label className="text-sm font-medium text-foreground">
            {t('text_case.label_select')}
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
            {copied ? t('text_case.btn_copied') : t('text_case.btn_copy')}
          </Button>
          <Button
            variant="outline"
            onClick={handleReset}
            disabled={!text.trim()}
          >
            <RotateCcw className="mr-2 h-4 w-4" />
            {t('text_case.btn_reset')}
          </Button>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default TextCaseConverter;
