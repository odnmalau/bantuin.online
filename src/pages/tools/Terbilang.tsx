import { useState, useMemo } from "react";
import { Link } from "react-router-dom";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Copy, Check, Receipt, ArrowRight } from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

type CaseFormat = "title" | "upper" | "lower";

const satuan = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan"];
const belasan = ["Sepuluh", "Sebelas", "Dua Belas", "Tiga Belas", "Empat Belas", "Lima Belas", "Enam Belas", "Tujuh Belas", "Delapan Belas", "Sembilan Belas"];

const convertToWords = (num: number): string => {
  if (num === 0) return "Nol";
  if (num < 0) return "Minus " + convertToWords(Math.abs(num));
  
  if (num < 10) return satuan[num];
  if (num < 20) return belasan[num - 10];
  if (num < 100) {
    const tens = Math.floor(num / 10);
    const ones = num % 10;
    return satuan[tens] + " Puluh" + (ones > 0 ? " " + satuan[ones] : "");
  }
  if (num < 200) return "Seratus" + (num % 100 > 0 ? " " + convertToWords(num % 100) : "");
  if (num < 1000) {
    const hundreds = Math.floor(num / 100);
    const remainder = num % 100;
    return satuan[hundreds] + " Ratus" + (remainder > 0 ? " " + convertToWords(remainder) : "");
  }
  if (num < 2000) return "Seribu" + (num % 1000 > 0 ? " " + convertToWords(num % 1000) : "");
  if (num < 1000000) {
    const thousands = Math.floor(num / 1000);
    const remainder = num % 1000;
    return convertToWords(thousands) + " Ribu" + (remainder > 0 ? " " + convertToWords(remainder) : "");
  }
  if (num < 1000000000) {
    const millions = Math.floor(num / 1000000);
    const remainder = num % 1000000;
    return convertToWords(millions) + " Juta" + (remainder > 0 ? " " + convertToWords(remainder) : "");
  }
  if (num < 1000000000000) {
    const billions = Math.floor(num / 1000000000);
    const remainder = num % 1000000000;
    return convertToWords(billions) + " Miliar" + (remainder > 0 ? " " + convertToWords(remainder) : "");
  }
  if (num < 1000000000000000) {
    const trillions = Math.floor(num / 1000000000000);
    const remainder = num % 1000000000000;
    return convertToWords(trillions) + " Triliun" + (remainder > 0 ? " " + convertToWords(remainder) : "");
  }
  
  return "Angka terlalu besar";
};

const formatCase = (text: string, format: CaseFormat): string => {
  switch (format) {
    case "upper":
      return text.toUpperCase();
    case "lower":
      return text.toLowerCase();
    case "title":
    default:
      return text;
  }
};

const formatNumber = (value: string): string => {
  const num = value.replace(/\D/g, "");
  return num.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
};

const Terbilang = () => {
  const [inputValue, setInputValue] = useState("");
  const [addRupiah, setAddRupiah] = useState(true);
  const [caseFormat, setCaseFormat] = useState<CaseFormat>("title");
  const [copied, setCopied] = useState(false);

  const numericValue = useMemo(() => {
    return parseInt(inputValue.replace(/\D/g, "")) || 0;
  }, [inputValue]);

  const result = useMemo(() => {
    if (numericValue === 0 && inputValue.replace(/\D/g, "").length === 0) {
      return "";
    }
    let text = convertToWords(numericValue);
    if (addRupiah) {
      text += " Rupiah";
    }
    return formatCase(text, caseFormat);
  }, [numericValue, addRupiah, caseFormat, inputValue]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const formatted = formatNumber(e.target.value);
    setInputValue(formatted);
  };

  const handleCopy = async () => {
    if (!result) return;
    await navigator.clipboard.writeText(result);
    setCopied(true);
    toast.success("Berhasil disalin!");
    setTimeout(() => setCopied(false), 2000);
  };

  const handleClear = () => {
    setInputValue("");
  };

  return (
    <ToolPageLayout
      toolNumber="18"
      title="Terbilang"
      subtitle="Angka ke Huruf"
      description="Konversi angka menjadi teks terbilang dalam Bahasa Indonesia. Cocok untuk kuitansi, invoice, dan dokumen resmi."
    >
      <SEOHead
        title={toolsMetadata.terbilang.title}
        description={toolsMetadata.terbilang.description}
        path={toolsMetadata.terbilang.path}
        keywords={toolsMetadata.terbilang.keywords}
      />
      <div className="mx-auto max-w-2xl space-y-6">
        {/* Input Section */}
        <div className="space-y-4 rounded-lg border border-border bg-card p-6">
          <div className="flex items-center justify-between">
            <Label htmlFor="amount" className="text-base font-medium">Masukkan Angka</Label>
            {inputValue && (
              <Button variant="ghost" size="sm" onClick={handleClear}>
                Hapus
              </Button>
            )}
          </div>
          <div className="relative">
            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground font-medium">
              Rp
            </span>
            <Input
              id="amount"
              type="text"
              inputMode="numeric"
              placeholder="0"
              value={inputValue}
              onChange={handleInputChange}
              className="pl-12 text-2xl font-semibold h-14"
            />
          </div>
          <p className="text-sm text-muted-foreground">
            Nilai: <span className="font-semibold text-foreground">{numericValue.toLocaleString("id-ID")}</span>
          </p>
        </div>

        {/* Options */}
        <div className="grid gap-4 rounded-lg border border-border bg-card p-6 sm:grid-cols-2">
          <div className="flex items-center justify-between rounded-lg border border-border p-4">
            <Label htmlFor="addRupiah" className="cursor-pointer">Tambah "Rupiah"</Label>
            <Switch
              id="addRupiah"
              checked={addRupiah}
              onCheckedChange={setAddRupiah}
            />
          </div>
          <div className="space-y-3">
            <Label>Format Huruf</Label>
            <RadioGroup
              value={caseFormat}
              onValueChange={(v) => setCaseFormat(v as CaseFormat)}
              className="flex gap-4"
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="title" id="title" />
                <Label htmlFor="title" className="cursor-pointer font-normal">Title Case</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="upper" id="upper" />
                <Label htmlFor="upper" className="cursor-pointer font-normal">HURUF BESAR</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="lower" id="lower" />
                <Label htmlFor="lower" className="cursor-pointer font-normal">huruf kecil</Label>
              </div>
            </RadioGroup>
          </div>
        </div>

        {/* Result */}
        <div className="rounded-lg border border-border bg-card p-6">
          <div className="mb-4 flex items-center justify-between">
            <Label className="text-base font-medium">Hasil Terbilang</Label>
            <Button
              variant="outline"
              size="sm"
              onClick={handleCopy}
              disabled={!result}
            >
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
          <div className="min-h-[80px] rounded-lg bg-secondary/50 p-4">
            {result ? (
              <p className="text-lg font-medium leading-relaxed text-foreground">{result}</p>
            ) : (
              <p className="text-muted-foreground">Ketik angka untuk melihat hasil terbilang...</p>
            )}
          </div>
        </div>

        {/* Quick Examples */}
        <div className="rounded-lg border border-border bg-card p-6">
          <Label className="mb-4 block text-base font-medium">Contoh Cepat</Label>
          <div className="flex flex-wrap gap-2">
            {["100.000", "500.000", "1.000.000", "2.500.000", "10.000.000"].map((val) => (
              <Button
                key={val}
                variant="outline"
                size="sm"
                onClick={() => setInputValue(val)}
              >
                {val}
              </Button>
            ))}
          </div>
        </div>

        {/* Link to Invoice Generator */}
        <Link
          to="/tools/invoice-generator"
          className="flex items-center justify-between rounded-lg border border-border bg-card p-4 transition-colors hover:bg-secondary/50"
        >
          <div className="flex items-center gap-3">
            <Receipt className="h-5 w-5 text-primary" />
            <div>
              <p className="font-medium">Invoice Generator</p>
              <p className="text-sm text-muted-foreground">Buat nota penjualan dengan terbilang otomatis</p>
            </div>
          </div>
          <ArrowRight className="h-5 w-5 text-muted-foreground" />
        </Link>
      </div>
    </ToolPageLayout>
  );
};

export default Terbilang;
