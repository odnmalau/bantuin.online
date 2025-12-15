import { useState, useMemo, useEffect } from "react";
import { Link } from "react-router-dom";
import { 
  MessageCircle, ImageDown, Camera, Files, QrCode, Type, FileText, Cake, 
  Braces, Image, Images, ArrowRight, KeyRound, Palette, Text, ArrowLeftRight, 
  Receipt, Search, X, Clock, Trash2, Shuffle, Hash, PenTool, FileImage, Stamp, GitCompare
} from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useRecentTools } from "@/hooks/useRecentTools";
import { ToolGridSkeleton } from "@/components/ToolCardSkeleton";

type Category = "all" | "media" | "document" | "text" | "utility" | "developer" | "fun";

interface Tool {
  title: string;
  description: string;
  icon: React.ElementType;
  href: string;
  category: Category;
}

const tools: Tool[] = [
  {
    title: "WhatsApp Link",
    description: "Buat link WhatsApp chat dengan pesan siap pakai",
    icon: MessageCircle,
    href: "/tools/whatsapp",
    category: "utility",
  },
  {
    title: "Kompres Foto",
    description: "Kompres ukuran foto tanpa kehilangan kualitas",
    icon: ImageDown,
    href: "/tools/compress",
    category: "media",
  },
  {
    title: "Pas Foto",
    description: "Buat pas foto ukuran standar (2x3, 3x4, 4x6)",
    icon: Camera,
    href: "/tools/pas-foto",
    category: "media",
  },
  {
    title: "Gabung PDF",
    description: "Gabungkan beberapa PDF jadi satu file",
    icon: Files,
    href: "/tools/pdf-merge",
    category: "document",
  },
  {
    title: "QR Code",
    description: "Buat QR code dari teks atau URL secara instan",
    icon: QrCode,
    href: "/tools/qr-code",
    category: "utility",
  },
  {
    title: "Text Case",
    description: "Ubah huruf besar/kecil, title case, dan lainnya",
    icon: Type,
    href: "/tools/text-case",
    category: "text",
  },
  {
    title: "Hitung Kata",
    description: "Hitung kata, karakter, dan estimasi waktu baca",
    icon: FileText,
    href: "/tools/word-counter",
    category: "text",
  },
  {
    title: "Kalkulator Usia",
    description: "Hitung usia lengkap dari tanggal lahir",
    icon: Cake,
    href: "/tools/age-calculator",
    category: "utility",
  },
  {
    title: "JSON Formatter",
    description: "Format, minify, dan validasi JSON dengan mudah",
    icon: Braces,
    href: "/tools/json-formatter",
    category: "developer",
  },
  {
    title: "Image to Base64",
    description: "Konversi gambar ke Base64 string",
    icon: Image,
    href: "/tools/image-to-base64",
    category: "developer",
  },
  {
    title: "Screenshot to PDF",
    description: "Gabung beberapa screenshot menjadi satu PDF",
    icon: Images,
    href: "/tools/screenshot-to-pdf",
    category: "document",
  },
  {
    title: "Password Generator",
    description: "Buat password acak yang kuat dan aman",
    icon: KeyRound,
    href: "/tools/password-generator",
    category: "utility",
  },
  {
    title: "Color Picker",
    description: "Pilih warna dan konversi ke berbagai format",
    icon: Palette,
    href: "/tools/color-picker",
    category: "developer",
  },
  {
    title: "Lorem Ipsum",
    description: "Generate teks placeholder untuk desain",
    icon: Text,
    href: "/tools/lorem-ipsum",
    category: "text",
  },
  {
    title: "Unit Converter",
    description: "Konversi satuan panjang, berat, suhu, dan lainnya",
    icon: ArrowLeftRight,
    href: "/tools/unit-converter",
    category: "utility",
  },
  {
    title: "Invoice Generator",
    description: "Buat nota penjualan sederhana dan ekspor ke PDF",
    icon: Receipt,
    href: "/tools/invoice-generator",
    category: "document",
  },
  {
    title: "Kocok Arisan",
    description: "Pilih pemenang arisan atau doorprize secara acak",
    icon: Shuffle,
    href: "/tools/random-picker",
    category: "fun",
  },
  {
    title: "Terbilang",
    description: "Konversi angka menjadi teks terbilang Indonesia",
    icon: Hash,
    href: "/tools/terbilang",
    category: "utility",
  },
  {
    title: "Tanda Tangan Digital",
    description: "Buat tanda tangan PNG transparan untuk dokumen",
    icon: PenTool,
    href: "/tools/signature-pad",
    category: "document",
  },
  {
    title: "Image Converter",
    description: "Konversi format gambar WebP, PNG, JPG",
    icon: FileImage,
    href: "/tools/image-converter",
    category: "media",
  },
  {
    title: "Watermark Foto",
    description: "Tambahkan watermark teks atau logo ke foto",
    icon: Stamp,
    href: "/tools/watermark",
    category: "media",
  },
  {
    title: "Diff Checker",
    description: "Bandingkan dua teks dan lihat perbedaannya",
    icon: GitCompare,
    href: "/tools/diff-checker",
    category: "developer",
  },
];

const categories: { value: Category; label: string }[] = [
  { value: "all", label: "Semua" },
  { value: "fun", label: "🎲 Fun" },
  { value: "media", label: "📸 Media" },
  { value: "document", label: "📄 Dokumen" },
  { value: "text", label: "🔤 Teks" },
  { value: "utility", label: "🧮 Utilitas" },
  { value: "developer", label: "💻 Developer" },
];

const ToolsGrid = () => {
  const [searchQuery, setSearchQuery] = useState("");

  const [activeCategory, setActiveCategory] = useState<Category>("all");
  const { recentTools, addRecentTool, clearRecentTools } = useRecentTools();
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Simulate short loading delay for skeleton effect
    const timer = setTimeout(() => setIsLoading(false), 500);
    return () => clearTimeout(timer);
  }, []);

  const filteredTools = useMemo(() => {
    return tools.filter((tool) => {
      const matchesSearch = 
        tool.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
        tool.description.toLowerCase().includes(searchQuery.toLowerCase());
      const matchesCategory = activeCategory === "all" || tool.category === activeCategory;
      return matchesSearch && matchesCategory;
    });
  }, [searchQuery, activeCategory]);

  const recentToolsData = useMemo(() => {
    return recentTools
      .map((recent) => tools.find((t) => t.href === recent.href))
      .filter(Boolean) as Tool[];
  }, [recentTools]);

  const handleToolClick = (href: string, title: string) => {
    addRecentTool(href, title);
  };

  return (
    <section className="py-8 md:py-12">
      <div className="container mx-auto px-4">
        <div className="mx-auto max-w-4xl space-y-6">
          {/* Search Bar */}
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              type="text"
              placeholder="Cari tools..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10 pr-10"
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery("")}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>

          {/* Category Tabs */}
          <div className="flex flex-wrap gap-2">
            {categories.map((cat) => (
              <button
                key={cat.value}
                onClick={() => setActiveCategory(cat.value)}
                className={`rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
                  activeCategory === cat.value
                    ? "bg-primary text-primary-foreground"
                    : "bg-secondary text-secondary-foreground hover:bg-secondary/80"
                }`}
              >
                {cat.label}
              </button>
            ))}
          </div>

          {/* Recent Tools */}
          {recentToolsData.length > 0 && !searchQuery && activeCategory === "all" && (
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Clock className="h-4 w-4" />
                  <span>Terakhir digunakan</span>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={clearRecentTools}
                  className="h-8 text-xs text-muted-foreground hover:text-destructive"
                >
                  <Trash2 className="mr-1 h-3 w-3" />
                  Hapus
                </Button>
              </div>
              <div className="flex flex-wrap gap-2">
                {recentToolsData.map((tool) => (
                  <Link
                    key={tool.href}
                    to={tool.href}
                    onClick={() => handleToolClick(tool.href, tool.title)}
                    className="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm transition-colors hover:bg-secondary/50"
                  >
                    <tool.icon className="h-4 w-4 text-primary" />
                    <span className="text-foreground">{tool.title}</span>
                  </Link>
                ))}
              </div>
            </div>
          )}

          {/* Tools Grid */}
          {isLoading ? (
            <ToolGridSkeleton count={6} />
          ) : filteredTools.length > 0 ? (
            <div className="grid gap-4 md:grid-cols-2 md:gap-6">
              {filteredTools.map((tool, index) => (
                <Link
                  key={tool.title}
                  to={tool.href}
                  onClick={() => handleToolClick(tool.href, tool.title)}
                  className={`card-accent-border group animate-fade-in-up stagger-${(index % 10) + 1} block border border-border bg-card p-6 transition-all duration-200 hover:bg-secondary/50 md:p-8`}
                >
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="mb-2 flex items-center gap-3">
                        <tool.icon className="h-5 w-5 text-primary" />
                        <h3 className="font-display text-lg font-semibold text-foreground md:text-xl">
                          {tool.title}
                        </h3>
                      </div>
                      <p className="font-body text-sm leading-relaxed text-muted-foreground">
                        {tool.description}
                      </p>
                    </div>
                    <ArrowRight className="h-5 w-5 text-muted-foreground transition-all duration-200 group-hover:translate-x-1 group-hover:text-primary" />
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <Search className="mb-4 h-12 w-12 text-muted-foreground/50" />
              <h3 className="text-lg font-semibold text-foreground">Tidak ada hasil</h3>
              <p className="mt-1 text-sm text-muted-foreground">
                Coba kata kunci lain atau reset filter
              </p>
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setSearchQuery("");
                  setActiveCategory("all");
                }}
                className="mt-4"
              >
                Reset Filter
              </Button>
            </div>
          )}
        </div>
      </div>
    </section>
  );
};

export default ToolsGrid;
