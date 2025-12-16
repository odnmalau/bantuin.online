import { useState } from "react";
import { useTranslation } from "react-i18next";
import { FileText, Copy, RotateCcw, Check } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

const WordCounter = () => {
  const { t } = useTranslation();
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
      title: t('common.copied'),
      description: t('common.success'),
    });
    setTimeout(() => setCopied(false), 2000);
  };

  const handleReset = () => {
    setText("");
  };

  const stats = [
    { label: t('word_counter.stat_char'), value: charCount },
    { label: t('word_counter.stat_char_no_space'), value: charNoSpaces },
    { label: t('word_counter.stat_word'), value: wordCount },
    { label: t('word_counter.stat_sentence'), value: sentenceCount },
    { label: t('word_counter.stat_paragraph'), value: paragraphCount },
  ];

  return (
    <ToolPageLayout
      toolNumber="07"
      title={t('tool_items.word_counter.title')}
      subtitle={t('word_counter.title')}
      description={t('tool_items.word_counter.desc')}
    >
      <SEOHead 
        title={t('word_counter.meta.title')}
        description={t('word_counter.meta.description')}
        path="/tools/word-counter"
        keywords={t('word_counter.meta.keywords', { returnObjects: true }) as string[]}
      />
      <div className="space-y-6">
        {/* Input Area */}
        <div className="space-y-2">
          <label className="text-sm font-medium text-foreground">
            {t('word_counter.label_input')}
          </label>
          <Textarea
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder={t('word_counter.placeholder_input')}
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
            {t('word_counter.est_time')}
          </h3>
          <div className="flex gap-6 text-sm">
            <div>
              <span className="text-muted-foreground">{t('word_counter.read')}: </span>
              <span className="font-medium text-foreground">
                {readingTime} {t('word_counter.min')}
              </span>
            </div>
            <div>
              <span className="text-muted-foreground">{t('word_counter.speak')}: </span>
              <span className="font-medium text-foreground">
                {speakingTime} {t('word_counter.min')}
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
            {copied ? t('word_counter.btn_copied') : t('word_counter.btn_copy')}
          </Button>
          <Button
            variant="outline"
            onClick={handleReset}
            disabled={!text.trim()}
          >
            <RotateCcw className="mr-2 h-4 w-4" />
            {t('word_counter.btn_reset')}
          </Button>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default WordCounter;
