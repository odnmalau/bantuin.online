import { useState, useMemo } from "react";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ArrowLeftRight, GitCompare, Plus, Minus, Equal, Copy, Check } from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

type DiffMode = "line" | "word" | "char";
type DiffType = "added" | "removed" | "unchanged";

interface DiffPart {
  text: string;
  type: DiffType;
}

const diffLines = (oldText: string, newText: string, ignoreWhitespace: boolean, ignoreCase: boolean): DiffPart[] => {
  let oldLines = oldText.split("\n");
  let newLines = newText.split("\n");

  if (ignoreWhitespace) {
    oldLines = oldLines.map((l) => l.trim());
    newLines = newLines.map((l) => l.trim());
  }

  if (ignoreCase) {
    oldLines = oldLines.map((l) => l.toLowerCase());
    newLines = newLines.map((l) => l.toLowerCase());
  }

  const result: DiffPart[] = [];
  const oldSet = new Set(oldLines);
  const newSet = new Set(newLines);

  // Simple diff algorithm
  let oldIdx = 0;
  let newIdx = 0;

  while (oldIdx < oldLines.length || newIdx < newLines.length) {
    const oldLine = oldLines[oldIdx];
    const newLine = newLines[newIdx];

    if (oldIdx >= oldLines.length) {
      result.push({ text: newText.split("\n")[newIdx], type: "added" });
      newIdx++;
    } else if (newIdx >= newLines.length) {
      result.push({ text: oldText.split("\n")[oldIdx], type: "removed" });
      oldIdx++;
    } else if (oldLine === newLine) {
      result.push({ text: oldText.split("\n")[oldIdx], type: "unchanged" });
      oldIdx++;
      newIdx++;
    } else if (newSet.has(oldLine)) {
      result.push({ text: newText.split("\n")[newIdx], type: "added" });
      newIdx++;
    } else if (oldSet.has(newLine)) {
      result.push({ text: oldText.split("\n")[oldIdx], type: "removed" });
      oldIdx++;
    } else {
      result.push({ text: oldText.split("\n")[oldIdx], type: "removed" });
      result.push({ text: newText.split("\n")[newIdx], type: "added" });
      oldIdx++;
      newIdx++;
    }
  }

  return result;
};

const diffWords = (oldText: string, newText: string, ignoreWhitespace: boolean, ignoreCase: boolean): DiffPart[] => {
  let oldWords = oldText.split(/(\s+)/);
  let newWords = newText.split(/(\s+)/);

  if (ignoreWhitespace) {
    oldWords = oldWords.filter((w) => w.trim());
    newWords = newWords.filter((w) => w.trim());
  }

  const compareOld = ignoreCase ? oldWords.map((w) => w.toLowerCase()) : oldWords;
  const compareNew = ignoreCase ? newWords.map((w) => w.toLowerCase()) : newWords;

  const result: DiffPart[] = [];
  const newSet = new Set(compareNew);
  const oldSet = new Set(compareOld);

  let oldIdx = 0;
  let newIdx = 0;

  while (oldIdx < compareOld.length || newIdx < compareNew.length) {
    const oldWord = compareOld[oldIdx];
    const newWord = compareNew[newIdx];

    if (oldIdx >= compareOld.length) {
      result.push({ text: newWords[newIdx], type: "added" });
      newIdx++;
    } else if (newIdx >= compareNew.length) {
      result.push({ text: oldWords[oldIdx], type: "removed" });
      oldIdx++;
    } else if (oldWord === newWord) {
      result.push({ text: oldWords[oldIdx], type: "unchanged" });
      oldIdx++;
      newIdx++;
    } else if (newSet.has(oldWord)) {
      result.push({ text: newWords[newIdx], type: "added" });
      newIdx++;
    } else if (oldSet.has(newWord)) {
      result.push({ text: oldWords[oldIdx], type: "removed" });
      oldIdx++;
    } else {
      result.push({ text: oldWords[oldIdx], type: "removed" });
      result.push({ text: newWords[newIdx], type: "added" });
      oldIdx++;
      newIdx++;
    }
  }

  return result;
};

const diffChars = (oldText: string, newText: string, ignoreCase: boolean): DiffPart[] => {
  const compareOld = ignoreCase ? oldText.toLowerCase() : oldText;
  const compareNew = ignoreCase ? newText.toLowerCase() : newText;

  const result: DiffPart[] = [];
  let oldIdx = 0;
  let newIdx = 0;

  while (oldIdx < compareOld.length || newIdx < compareNew.length) {
    if (oldIdx >= compareOld.length) {
      result.push({ text: newText[newIdx], type: "added" });
      newIdx++;
    } else if (newIdx >= compareNew.length) {
      result.push({ text: oldText[oldIdx], type: "removed" });
      oldIdx++;
    } else if (compareOld[oldIdx] === compareNew[newIdx]) {
      result.push({ text: oldText[oldIdx], type: "unchanged" });
      oldIdx++;
      newIdx++;
    } else {
      result.push({ text: oldText[oldIdx], type: "removed" });
      result.push({ text: newText[newIdx], type: "added" });
      oldIdx++;
      newIdx++;
    }
  }

  // Merge consecutive same-type parts
  const merged: DiffPart[] = [];
  for (const part of result) {
    if (merged.length > 0 && merged[merged.length - 1].type === part.type) {
      merged[merged.length - 1].text += part.text;
    } else {
      merged.push({ ...part });
    }
  }

  return merged;
};

const DiffChecker = () => {
  const [oldText, setOldText] = useState("");
  const [newText, setNewText] = useState("");
  const [diffMode, setDiffMode] = useState<DiffMode>("line");
  const [ignoreWhitespace, setIgnoreWhitespace] = useState(false);
  const [ignoreCase, setIgnoreCase] = useState(false);
  const [copied, setCopied] = useState(false);

  const diffResult = useMemo(() => {
    if (!oldText && !newText) return [];

    switch (diffMode) {
      case "line":
        return diffLines(oldText, newText, ignoreWhitespace, ignoreCase);
      case "word":
        return diffWords(oldText, newText, ignoreWhitespace, ignoreCase);
      case "char":
        return diffChars(oldText, newText, ignoreCase);
      default:
        return [];
    }
  }, [oldText, newText, diffMode, ignoreWhitespace, ignoreCase]);

  const stats = useMemo(() => {
    const added = diffResult.filter((d) => d.type === "added").length;
    const removed = diffResult.filter((d) => d.type === "removed").length;
    const unchanged = diffResult.filter((d) => d.type === "unchanged").length;
    return { added, removed, unchanged };
  }, [diffResult]);

  const handleSwap = () => {
    setOldText(newText);
    setNewText(oldText);
  };

  const handleClear = () => {
    setOldText("");
    setNewText("");
  };

  const handleCopyResult = async () => {
    const text = diffResult.map((d) => {
      const prefix = d.type === "added" ? "+ " : d.type === "removed" ? "- " : "  ";
      return prefix + d.text;
    }).join(diffMode === "line" ? "\n" : "");

    await navigator.clipboard.writeText(text);
    setCopied(true);
    toast.success("Hasil diff disalin!");
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <ToolPageLayout
      toolNumber="22"
      title="Diff Checker"
      subtitle="Bandingkan Teks"
      description="Bandingkan dua teks dan lihat perbedaannya secara visual. Cocok untuk mengecek revisi dokumen atau kode."
    >
      <SEOHead
        title={toolsMetadata.diff.title}
        description={toolsMetadata.diff.description}
        path={toolsMetadata.diff.path}
        keywords={toolsMetadata.diff.keywords}
      />
      <div className="mx-auto max-w-5xl space-y-6">
        {/* Input Section */}
        <div className="grid gap-4 md:grid-cols-2">
          <div className="space-y-2">
            <Label className="text-base font-medium">Teks Lama</Label>
            <Textarea
              value={oldText}
              onChange={(e) => setOldText(e.target.value)}
              placeholder="Paste teks versi lama di sini..."
              className="min-h-[200px] font-mono text-sm"
            />
          </div>
          <div className="space-y-2">
            <Label className="text-base font-medium">Teks Baru</Label>
            <Textarea
              value={newText}
              onChange={(e) => setNewText(e.target.value)}
              placeholder="Paste teks versi baru di sini..."
              className="min-h-[200px] font-mono text-sm"
            />
          </div>
        </div>

        {/* Actions & Options */}
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-border bg-card p-4">
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" onClick={handleSwap}>
              <ArrowLeftRight className="mr-2 h-4 w-4" />
              Tukar
            </Button>
            <Button variant="outline" size="sm" onClick={handleClear}>
              Hapus Semua
            </Button>
          </div>

          <div className="flex flex-wrap items-center gap-4">
            <RadioGroup
              value={diffMode}
              onValueChange={(v) => setDiffMode(v as DiffMode)}
              className="flex gap-4"
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="line" id="line" />
                <Label htmlFor="line" className="cursor-pointer font-normal">Baris</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="word" id="word" />
                <Label htmlFor="word" className="cursor-pointer font-normal">Kata</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="char" id="char" />
                <Label htmlFor="char" className="cursor-pointer font-normal">Karakter</Label>
              </div>
            </RadioGroup>
          </div>
        </div>

        {/* Toggle Options */}
        <div className="flex flex-wrap gap-6">
          <div className="flex items-center space-x-2">
            <Switch
              id="ignore-whitespace"
              checked={ignoreWhitespace}
              onCheckedChange={setIgnoreWhitespace}
            />
            <Label htmlFor="ignore-whitespace" className="cursor-pointer">Abaikan spasi ekstra</Label>
          </div>
          <div className="flex items-center space-x-2">
            <Switch
              id="ignore-case"
              checked={ignoreCase}
              onCheckedChange={setIgnoreCase}
            />
            <Label htmlFor="ignore-case" className="cursor-pointer">Abaikan huruf besar/kecil</Label>
          </div>
        </div>

        {/* Stats */}
        {diffResult.length > 0 && (
          <div className="flex flex-wrap gap-4">
            <div className="flex items-center gap-2 rounded-lg bg-green-500/10 px-3 py-2 text-green-600 dark:text-green-400">
              <Plus className="h-4 w-4" />
              <span className="text-sm font-medium">{stats.added} ditambah</span>
            </div>
            <div className="flex items-center gap-2 rounded-lg bg-red-500/10 px-3 py-2 text-red-600 dark:text-red-400">
              <Minus className="h-4 w-4" />
              <span className="text-sm font-medium">{stats.removed} dihapus</span>
            </div>
            <div className="flex items-center gap-2 rounded-lg bg-secondary px-3 py-2 text-muted-foreground">
              <Equal className="h-4 w-4" />
              <span className="text-sm font-medium">{stats.unchanged} tidak berubah</span>
            </div>
          </div>
        )}

        {/* Result */}
        {diffResult.length > 0 && (
          <div className="space-y-3 rounded-lg border border-border bg-card">
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
              <div className="flex items-center gap-2">
                <GitCompare className="h-5 w-5 text-primary" />
                <Label className="text-base font-medium">Hasil Perbandingan</Label>
              </div>
              <Button variant="outline" size="sm" onClick={handleCopyResult}>
                {copied ? (
                  <>
                    <Check className="mr-2 h-4 w-4" />
                    Disalin!
                  </>
                ) : (
                  <>
                    <Copy className="mr-2 h-4 w-4" />
                    Salin
                  </>
                )}
              </Button>
            </div>
            <div className="max-h-[400px] overflow-auto p-4">
              <div className={`font-mono text-sm ${diffMode === "line" ? "space-y-1" : "leading-relaxed"}`}>
                {diffResult.map((part, index) => {
                  let className = "";
                  if (part.type === "added") {
                    className = "bg-green-500/20 text-green-700 dark:text-green-300";
                  } else if (part.type === "removed") {
                    className = "bg-red-500/20 text-red-700 dark:text-red-300 line-through";
                  }

                  if (diffMode === "line") {
                    return (
                      <div
                        key={index}
                        className={`rounded px-2 py-0.5 ${className}`}
                      >
                        <span className="mr-2 text-muted-foreground">
                          {part.type === "added" ? "+" : part.type === "removed" ? "-" : " "}
                        </span>
                        {part.text || " "}
                      </div>
                    );
                  }

                  return (
                    <span key={index} className={`${className} ${className ? "rounded px-0.5" : ""}`}>
                      {part.text}
                    </span>
                  );
                })}
              </div>
            </div>
          </div>
        )}

        {/* Empty State */}
        {!oldText && !newText && (
          <div className="rounded-lg border border-dashed border-border p-12 text-center">
            <GitCompare className="mx-auto mb-4 h-12 w-12 text-muted-foreground/50" />
            <h3 className="text-lg font-medium">Mulai Membandingkan</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Masukkan teks di kedua kolom untuk melihat perbedaannya
            </p>
          </div>
        )}
      </div>
    </ToolPageLayout>
  );
};

export default DiffChecker;
