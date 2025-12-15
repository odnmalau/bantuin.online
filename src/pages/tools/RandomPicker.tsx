import { useState, useRef, useCallback } from "react";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import {
  Shuffle,
  Clipboard,
  Trash2,
  MessageCircle,
  RotateCcw,
  Trophy,
  Sparkles,
} from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

interface Winner {
  name: string;
  timestamp: Date;
}

const RandomPicker = () => {
  const [namesInput, setNamesInput] = useState("");
  const [names, setNames] = useState<string[]>([]);
  const [winners, setWinners] = useState<Winner[]>([]);
  const [winnerCount, setWinnerCount] = useState(1);
  const [removeWinner, setRemoveWinner] = useState(true);
  const [isSpinning, setIsSpinning] = useState(false);
  const [currentDisplay, setCurrentDisplay] = useState<string[]>([]);
  const [showConfetti, setShowConfetti] = useState(false);
  const spinIntervalRef = useRef<NodeJS.Timeout | null>(null);

  const parseNames = useCallback((input: string): string[] => {
    const parsed = input
      .split(/[,\n]/)
      .map((name) => name.trim())
      .filter((name) => name.length > 0);
    return [...new Set(parsed)]; // Remove duplicates
  }, []);

  const handleInputChange = (value: string) => {
    setNamesInput(value);
    setNames(parseNames(value));
  };

  const handlePaste = async () => {
    try {
      const text = await navigator.clipboard.readText();
      const newInput = namesInput ? `${namesInput}\n${text}` : text;
      handleInputChange(newInput);
      toast.success("Berhasil paste dari clipboard!");
    } catch {
      toast.error("Gagal membaca clipboard");
    }
  };

  const handleClear = () => {
    setNamesInput("");
    setNames([]);
    setWinners([]);
    setCurrentDisplay([]);
  };

  const getRandomNames = (pool: string[], count: number): string[] => {
    const shuffled = [...pool];
    const result: string[] = [];
    const actualCount = Math.min(count, shuffled.length);

    for (let i = 0; i < actualCount; i++) {
      const randomIndex = Math.floor(Math.random() * shuffled.length);
      result.push(shuffled[randomIndex]);
      shuffled.splice(randomIndex, 1);
    }
    return result;
  };

  const startSpin = () => {
    if (names.length === 0) {
      toast.error("Masukkan nama terlebih dahulu!");
      return;
    }

    if (names.length < winnerCount) {
      toast.error(
        `Jumlah nama (${names.length}) kurang dari jumlah pemenang (${winnerCount})`
      );
      return;
    }

    setIsSpinning(true);
    setShowConfetti(false);

    let spinCount = 0;
    const maxSpins = 20;
    const spinSpeed = 100;

    spinIntervalRef.current = setInterval(() => {
      const randomDisplay = getRandomNames(names, winnerCount);
      setCurrentDisplay(randomDisplay);
      spinCount++;

      if (spinCount >= maxSpins) {
        clearInterval(spinIntervalRef.current!);

        // Final selection
        const finalWinners = getRandomNames(names, winnerCount);
        setCurrentDisplay(finalWinners);

        // Add to history
        const newWinners = finalWinners.map((name) => ({
          name,
          timestamp: new Date(),
        }));
        setWinners((prev) => [...newWinners, ...prev]);

        // Remove from list if enabled
        if (removeWinner) {
          const remainingNames = names.filter((n) => !finalWinners.includes(n));
          const remainingInput = remainingNames.join("\n");
          setNamesInput(remainingInput);
          setNames(remainingNames);
        }

        setIsSpinning(false);
        setShowConfetti(true);

        // Play celebration sound (optional)
        toast.success(`🎉 Selamat kepada ${finalWinners.join(", ")}!`);

        // Hide confetti after animation
        setTimeout(() => setShowConfetti(false), 3000);
      }
    }, spinSpeed);
  };

  const shareToWhatsApp = () => {
    if (currentDisplay.length === 0) return;
    const text = `🎉 *HASIL KOCOK ARISAN*\n\nPemenang:\n${currentDisplay
      .map((w, i) => `${i + 1}. ${w}`)
      .join("\n")}\n\nDikocok menggunakan Bantuin.online`;
    const url = `https://wa.me/?text=${encodeURIComponent(text)}`;
    window.open(url, "_blank", "noopener,noreferrer");
  };

  const resetWinners = () => {
    setWinners([]);
    setCurrentDisplay([]);
  };

  return (
    <ToolPageLayout
      toolNumber="17"
      title="Kocok Arisan"
      subtitle="Random Picker"
      description="Pilih pemenang arisan, doorprize, atau pembagian kelompok secara acak dengan animasi seru!"
    >
      <SEOHead
        title={toolsMetadata.random.title}
        description={toolsMetadata.random.description}
        path={toolsMetadata.random.path}
        keywords={toolsMetadata.random.keywords}
      />
      <div className="mx-auto max-w-2xl space-y-6">
        {/* Input Section */}
        <div className="space-y-4 rounded-lg border border-border bg-card p-6">
          <div className="flex items-center justify-between">
            <Label className="text-base font-medium">Daftar Nama</Label>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={handlePaste}>
                <Clipboard className="mr-2 h-4 w-4" />
                Paste
              </Button>
              <Button variant="outline" size="sm" onClick={handleClear}>
                <Trash2 className="mr-2 h-4 w-4" />
                Hapus
              </Button>
            </div>
          </div>
          <Textarea
            placeholder="Masukkan nama (satu per baris atau pisahkan dengan koma)&#10;Contoh:&#10;Budi&#10;Ani&#10;Dewi, Rudi, Siti"
            value={namesInput}
            onChange={(e) => handleInputChange(e.target.value)}
            className="min-h-[150px] font-mono text-sm"
          />
          <p className="text-sm text-muted-foreground">
            Total:{" "}
            <span className="font-semibold text-foreground">
              {names.length}
            </span>{" "}
            nama
          </p>
        </div>

        {/* Options */}
        <div className="grid gap-4 rounded-lg border border-border bg-card p-6 sm:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="winnerCount">Jumlah Pemenang</Label>
            <Input
              id="winnerCount"
              type="number"
              min={1}
              max={names.length || 10}
              value={winnerCount}
              onChange={(e) =>
                setWinnerCount(Math.max(1, parseInt(e.target.value) || 1))
              }
            />
          </div>
          <div className="flex items-center justify-between rounded-lg border border-border p-4">
            <div>
              <Label htmlFor="removeWinner" className="cursor-pointer">
                Hapus Pemenang dari List
              </Label>
              <p className="text-xs text-muted-foreground">
                Untuk arisan bergilir
              </p>
            </div>
            <Switch
              id="removeWinner"
              checked={removeWinner}
              onCheckedChange={setRemoveWinner}
            />
          </div>
        </div>

        {/* Spin Button & Display */}
        <div className="relative overflow-hidden rounded-lg border border-border bg-card p-8">
          {/* Confetti Animation */}
          {showConfetti && (
            <div className="pointer-events-none absolute inset-0 z-10">
              {[...Array(50)].map((_, i) => (
                <div
                  key={i}
                  className="absolute confetti-piece"
                  style={{
                    left: `${Math.random() * 100}%`,
                    animationDelay: `${Math.random() * 0.5}s`,
                    backgroundColor: [
                      "#FF6B6B",
                      "#4ECDC4",
                      "#45B7D1",
                      "#FED766",
                      "#F8B500",
                    ][Math.floor(Math.random() * 5)],
                  }}
                />
              ))}
            </div>
          )}

          {/* Winner Display */}
          <div className="mb-6 min-h-[80px] flex items-center justify-center">
            {currentDisplay.length > 0 ? (
              <div className="text-center">
                {currentDisplay.map((name, idx) => (
                  <div
                    key={idx}
                    className={`text-2xl font-display font-bold text-primary md:text-3xl ${
                      isSpinning ? "animate-pulse blur-[1px]" : ""
                    }`}
                  >
                    {isSpinning ? "🎰" : "🏆"} {name}
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-muted-foreground">
                Tekan tombol untuk mulai mengocok...
              </p>
            )}
          </div>

          <Button
            onClick={startSpin}
            disabled={isSpinning || names.length === 0}
            size="lg"
            className="w-full text-lg"
          >
            {isSpinning ? (
              <>
                <Sparkles className="mr-2 h-5 w-5 animate-spin" />
                Mengocok...
              </>
            ) : (
              <>
                <Shuffle className="mr-2 h-5 w-5" />
                Kocok Sekarang!
              </>
            )}
          </Button>

          {currentDisplay.length > 0 && !isSpinning && (
            <Button
              variant="outline"
              onClick={shareToWhatsApp}
              className="mt-4 w-full"
            >
              <MessageCircle className="mr-2 h-4 w-4" />
              Bagikan ke WhatsApp
            </Button>
          )}
        </div>

        {/* History */}
        {winners.length > 0 && (
          <div className="rounded-lg border border-border bg-card p-6">
            <div className="mb-4 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Trophy className="h-5 w-5 text-primary" />
                <h3 className="font-display font-semibold">Riwayat Pemenang</h3>
              </div>
              <Button variant="ghost" size="sm" onClick={resetWinners}>
                <RotateCcw className="mr-2 h-4 w-4" />
                Reset
              </Button>
            </div>
            <div className="space-y-2">
              {winners.slice(0, 10).map((winner, idx) => (
                <div
                  key={idx}
                  className="flex items-center justify-between rounded-lg bg-secondary/50 px-4 py-2"
                >
                  <span className="font-medium">{winner.name}</span>
                  <span className="text-xs text-muted-foreground">
                    {winner.timestamp.toLocaleTimeString("id-ID")}
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </ToolPageLayout>
  );
};

export default RandomPicker;
