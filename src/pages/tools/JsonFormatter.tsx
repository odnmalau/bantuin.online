import { useState } from "react";
import { Braces, Copy, Check, RotateCcw, AlertCircle, CheckCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

const JsonFormatter = () => {
  const [input, setInput] = useState("");
  const [output, setOutput] = useState("");
  const [error, setError] = useState("");
  const [isValid, setIsValid] = useState<boolean | null>(null);
  const [copied, setCopied] = useState(false);
  const { toast } = useToast();

  const formatJson = (indent: number = 2) => {
    if (!input.trim()) {
      toast({
        title: "Input kosong",
        description: "Masukkan JSON terlebih dahulu",
        variant: "destructive",
      });
      return;
    }

    try {
      const parsed = JSON.parse(input);
      const formatted = JSON.stringify(parsed, null, indent);
      setOutput(formatted);
      setError("");
      setIsValid(true);
    } catch (e) {
      const errorMessage = e instanceof Error ? e.message : "Invalid JSON";
      setError(errorMessage);
      setOutput("");
      setIsValid(false);
    }
  };

  const minifyJson = () => {
    if (!input.trim()) {
      toast({
        title: "Input kosong",
        description: "Masukkan JSON terlebih dahulu",
        variant: "destructive",
      });
      return;
    }

    try {
      const parsed = JSON.parse(input);
      const minified = JSON.stringify(parsed);
      setOutput(minified);
      setError("");
      setIsValid(true);
    } catch (e) {
      const errorMessage = e instanceof Error ? e.message : "Invalid JSON";
      setError(errorMessage);
      setOutput("");
      setIsValid(false);
    }
  };

  const validateJson = () => {
    if (!input.trim()) {
      toast({
        title: "Input kosong",
        description: "Masukkan JSON terlebih dahulu",
        variant: "destructive",
      });
      return;
    }

    try {
      JSON.parse(input);
      setIsValid(true);
      setError("");
      toast({
        title: "JSON Valid ✓",
        description: "Struktur JSON sudah benar",
      });
    } catch (e) {
      const errorMessage = e instanceof Error ? e.message : "Invalid JSON";
      setError(errorMessage);
      setIsValid(false);
    }
  };

  const handleCopy = async () => {
    if (!output.trim()) return;

    await navigator.clipboard.writeText(output);
    setCopied(true);
    toast({
      title: "Tersalin!",
      description: "JSON berhasil disalin ke clipboard",
    });
    setTimeout(() => setCopied(false), 2000);
  };

  const handleReset = () => {
    setInput("");
    setOutput("");
    setError("");
    setIsValid(null);
  };

  const meta = toolsMetadata.json;

  return (
    <ToolPageLayout
      toolNumber="09"
      title="JSON Formatter"
      subtitle="Format & Validasi JSON"
      description="Format, minify, dan validasi JSON dengan mudah. Cocok untuk developer."
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
            Input JSON
          </label>
          <Textarea
            value={input}
            onChange={(e) => {
              setInput(e.target.value);
              setIsValid(null);
              setError("");
            }}
            placeholder='{"name": "John", "age": 30}'
            className="min-h-[150px] resize-none font-mono text-sm"
          />
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap gap-2">
          <Button onClick={() => formatJson(2)} variant="outline" size="sm">
            Format (2 space)
          </Button>
          <Button onClick={() => formatJson(4)} variant="outline" size="sm">
            Format (4 space)
          </Button>
          <Button onClick={minifyJson} variant="outline" size="sm">
            Minify
          </Button>
          <Button onClick={validateJson} variant="outline" size="sm">
            Validasi
          </Button>
        </div>

        {/* Validation Status */}
        {isValid !== null && (
          <div
            className={`flex items-center gap-2 rounded-lg border p-3 ${
              isValid
                ? "border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400"
                : "border-destructive/30 bg-destructive/10 text-destructive"
            }`}
          >
            {isValid ? (
              <>
                <CheckCircle className="h-4 w-4" />
                <span className="text-sm font-medium">JSON Valid</span>
              </>
            ) : (
              <>
                <AlertCircle className="h-4 w-4" />
                <span className="text-sm">{error}</span>
              </>
            )}
          </div>
        )}

        {/* Output Area */}
        {output && (
          <div className="space-y-2">
            <label className="text-sm font-medium text-foreground">
              Output
            </label>
            <Textarea
              value={output}
              readOnly
              className="min-h-[200px] resize-none bg-secondary/30 font-mono text-sm"
            />
          </div>
        )}

        {/* Copy & Reset Buttons */}
        <div className="flex gap-3">
          <Button
            onClick={handleCopy}
            disabled={!output.trim()}
            className="flex-1"
          >
            {copied ? (
              <Check className="mr-2 h-4 w-4" />
            ) : (
              <Copy className="mr-2 h-4 w-4" />
            )}
            {copied ? "Tersalin!" : "Salin Output"}
          </Button>
          <Button
            variant="outline"
            onClick={handleReset}
            disabled={!input.trim() && !output.trim()}
          >
            <RotateCcw className="mr-2 h-4 w-4" />
            Reset
          </Button>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default JsonFormatter;
