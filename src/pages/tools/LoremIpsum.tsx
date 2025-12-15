import { useState } from "react";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Switch } from "@/components/ui/switch";
import { Copy, Check, RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

const LOREM_WORDS = [
  "lorem", "ipsum", "dolor", "sit", "amet", "consectetur", "adipiscing", "elit",
  "sed", "do", "eiusmod", "tempor", "incididunt", "ut", "labore", "et", "dolore",
  "magna", "aliqua", "enim", "ad", "minim", "veniam", "quis", "nostrud",
  "exercitation", "ullamco", "laboris", "nisi", "aliquip", "ex", "ea", "commodo",
  "consequat", "duis", "aute", "irure", "in", "reprehenderit", "voluptate",
  "velit", "esse", "cillum", "fugiat", "nulla", "pariatur", "excepteur", "sint",
  "occaecat", "cupidatat", "non", "proident", "sunt", "culpa", "qui", "officia",
  "deserunt", "mollit", "anim", "id", "est", "laborum"
];

const INDO_WORDS = [
  "dan", "yang", "di", "dari", "untuk", "dengan", "pada", "dalam", "ini", "itu",
  "adalah", "ke", "akan", "oleh", "tidak", "juga", "sudah", "bisa", "satu", "lebih",
  "kami", "mereka", "kita", "ada", "telah", "sangat", "banyak", "bahwa", "hanya",
  "karena", "seperti", "sebuah", "dapat", "atau", "menjadi", "antara", "setelah",
  "tersebut", "namun", "tetapi", "serta", "secara", "melalui", "hingga", "ketika",
  "memberikan", "melakukan", "menggunakan", "berbagai", "memiliki", "terhadap"
];

const LoremIpsum = () => {
  const [type, setType] = useState<"paragraphs" | "sentences" | "words">("paragraphs");
  const [count, setCount] = useState(3);
  const [language, setLanguage] = useState<"latin" | "indo">("latin");
  const [startWithLorem, setStartWithLorem] = useState(true);
  const [result, setResult] = useState("");
  const [copied, setCopied] = useState(false);

  const getRandomWord = (words: string[]) => words[Math.floor(Math.random() * words.length)];

  const capitalizeFirst = (str: string) => str.charAt(0).toUpperCase() + str.slice(1);

  const generateSentence = (words: string[], minWords = 8, maxWords = 15, isFirst = false) => {
    const sentenceLength = Math.floor(Math.random() * (maxWords - minWords + 1)) + minWords;
    const sentence: string[] = [];
    
    if (isFirst && startWithLorem && language === "latin") {
      sentence.push("Lorem", "ipsum", "dolor", "sit", "amet");
      for (let i = 5; i < sentenceLength; i++) {
        sentence.push(getRandomWord(words));
      }
    } else {
      for (let i = 0; i < sentenceLength; i++) {
        sentence.push(getRandomWord(words));
      }
    }
    
    return capitalizeFirst(sentence.join(" ")) + ".";
  };

  const generateParagraph = (words: string[], isFirst = false) => {
    const sentenceCount = Math.floor(Math.random() * 3) + 4;
    const sentences: string[] = [];
    
    for (let i = 0; i < sentenceCount; i++) {
      sentences.push(generateSentence(words, 8, 15, isFirst && i === 0));
    }
    
    return sentences.join(" ");
  };

  const generate = () => {
    const words = language === "latin" ? LOREM_WORDS : INDO_WORDS;
    let output = "";

    switch (type) {
      case "paragraphs":
        const paragraphs: string[] = [];
        for (let i = 0; i < count; i++) {
          paragraphs.push(generateParagraph(words, i === 0));
        }
        output = paragraphs.join("\n\n");
        break;
      case "sentences":
        const sentences: string[] = [];
        for (let i = 0; i < count; i++) {
          sentences.push(generateSentence(words, 8, 15, i === 0));
        }
        output = sentences.join(" ");
        break;
      case "words":
        const wordList: string[] = [];
        if (startWithLorem && language === "latin") {
          wordList.push("Lorem", "ipsum");
          for (let i = 2; i < count; i++) {
            wordList.push(getRandomWord(words));
          }
        } else {
          for (let i = 0; i < count; i++) {
            wordList.push(getRandomWord(words));
          }
        }
        output = capitalizeFirst(wordList.join(" ")) + ".";
        break;
    }

    setResult(output);
    setCopied(false);
  };

  const copyToClipboard = async () => {
    if (!result) return;
    await navigator.clipboard.writeText(result);
    setCopied(true);
    toast.success("Teks disalin!");
    setTimeout(() => setCopied(false), 2000);
  };

  const getMaxCount = () => {
    switch (type) {
      case "paragraphs": return 20;
      case "sentences": return 50;
      case "words": return 500;
    }
  };

  const getTypeLabel = () => {
    switch (type) {
      case "paragraphs": return "Paragraf";
      case "sentences": return "Kalimat";
      case "words": return "Kata";
    }
  };

  const meta = toolsMetadata.lorem;

  return (
    <ToolPageLayout
      toolNumber="14"
      title="Lorem Ipsum"
      subtitle="Teks Placeholder"
      description="Generate teks dummy untuk desain dalam bahasa Latin atau Indonesia"
    >
      <SEOHead title={meta.title} description={meta.description} path={meta.path} keywords={meta.keywords} />
      <div className="mx-auto max-w-2xl space-y-6">
        {/* Settings */}
        <Card className="p-6 space-y-6">
          {/* Type Selection */}
          <Tabs value={type} onValueChange={(v) => setType(v as typeof type)}>
            <TabsList className="grid w-full grid-cols-3">
              <TabsTrigger value="paragraphs">Paragraf</TabsTrigger>
              <TabsTrigger value="sentences">Kalimat</TabsTrigger>
              <TabsTrigger value="words">Kata</TabsTrigger>
            </TabsList>
          </Tabs>

          {/* Count */}
          <div className="flex items-center gap-4">
            <Label htmlFor="count" className="whitespace-nowrap">Jumlah {getTypeLabel()}</Label>
            <Input
              id="count"
              type="number"
              min={1}
              max={getMaxCount()}
              value={count}
              onChange={(e) => setCount(Math.min(getMaxCount(), Math.max(1, parseInt(e.target.value) || 1)))}
              className="w-24"
            />
          </div>

          {/* Language */}
          <div className="space-y-3">
            <Label>Bahasa</Label>
            <div className="flex gap-2">
              <Button
                variant={language === "latin" ? "default" : "outline"}
                onClick={() => setLanguage("latin")}
                className="flex-1"
              >
                Latin Klasik
              </Button>
              <Button
                variant={language === "indo" ? "default" : "outline"}
                onClick={() => setLanguage("indo")}
                className="flex-1"
              >
                Indonesia
              </Button>
            </div>
          </div>

          {/* Start with Lorem */}
          {language === "latin" && (
            <div className="flex items-center justify-between rounded-lg border border-border p-3">
              <Label htmlFor="startLorem" className="cursor-pointer">
                Mulai dengan "Lorem ipsum..."
              </Label>
              <Switch
                id="startLorem"
                checked={startWithLorem}
                onCheckedChange={setStartWithLorem}
              />
            </div>
          )}

          {/* Generate Button */}
          <Button onClick={generate} className="w-full" size="lg">
            <RefreshCw className="mr-2 h-4 w-4" />
            Generate Teks
          </Button>
        </Card>

        {/* Result */}
        {result && (
          <Card className="p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="font-semibold text-foreground">Hasil</h3>
              <Button variant="outline" size="sm" onClick={copyToClipboard}>
                {copied ? (
                  <>
                    <Check className="mr-2 h-4 w-4 text-green-500" />
                    Disalin
                  </>
                ) : (
                  <>
                    <Copy className="mr-2 h-4 w-4" />
                    Salin
                  </>
                )}
              </Button>
            </div>
            <div className="max-h-96 overflow-y-auto rounded-lg border border-border bg-muted/30 p-4">
              <p className="whitespace-pre-wrap text-sm leading-relaxed text-foreground">
                {result}
              </p>
            </div>
            <p className="text-xs text-muted-foreground">
              {result.split(/\s+/).length} kata • {result.length} karakter
            </p>
          </Card>
        )}
      </div>
    </ToolPageLayout>
  );
};

export default LoremIpsum;
