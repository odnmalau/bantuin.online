import { useState, useEffect } from "react";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card } from "@/components/ui/card";
import { Copy, Check, Shuffle } from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

interface ColorFormats {
  hex: string;
  rgb: { r: number; g: number; b: number };
  hsl: { h: number; s: number; l: number };
  cmyk: { c: number; m: number; y: number; k: number };
}

const ColorPicker = () => {
  const [color, setColor] = useState("#2563EB");
  const [formats, setFormats] = useState<ColorFormats | null>(null);
  const [copiedFormat, setCopiedFormat] = useState<string | null>(null);

  const hexToRgb = (hex: string) => {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
      r: parseInt(result[1], 16),
      g: parseInt(result[2], 16),
      b: parseInt(result[3], 16)
    } : { r: 0, g: 0, b: 0 };
  };

  const rgbToHsl = (r: number, g: number, b: number) => {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h = 0, s = 0;
    const l = (max + min) / 2;

    if (max !== min) {
      const d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      switch (max) {
        case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
        case g: h = ((b - r) / d + 2) / 6; break;
        case b: h = ((r - g) / d + 4) / 6; break;
      }
    }

    return {
      h: Math.round(h * 360),
      s: Math.round(s * 100),
      l: Math.round(l * 100)
    };
  };

  const rgbToCmyk = (r: number, g: number, b: number) => {
    if (r === 0 && g === 0 && b === 0) {
      return { c: 0, m: 0, y: 0, k: 100 };
    }
    const rNorm = r / 255, gNorm = g / 255, bNorm = b / 255;
    const k = 1 - Math.max(rNorm, gNorm, bNorm);
    const c = (1 - rNorm - k) / (1 - k);
    const m = (1 - gNorm - k) / (1 - k);
    const y = (1 - bNorm - k) / (1 - k);
    return {
      c: Math.round(c * 100),
      m: Math.round(m * 100),
      y: Math.round(y * 100),
      k: Math.round(k * 100)
    };
  };

  useEffect(() => {
    if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
      const rgb = hexToRgb(color);
      const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
      const cmyk = rgbToCmyk(rgb.r, rgb.g, rgb.b);
      setFormats({ hex: color.toUpperCase(), rgb, hsl, cmyk });
    }
  }, [color]);

  const generateRandomColor = () => {
    const randomHex = "#" + Math.floor(Math.random() * 16777215).toString(16).padStart(6, "0");
    setColor(randomHex);
  };

  const copyToClipboard = async (text: string, format: string) => {
    await navigator.clipboard.writeText(text);
    setCopiedFormat(format);
    toast.success("Warna disalin!");
    setTimeout(() => setCopiedFormat(null), 2000);
  };

  const getContrastColor = (hex: string) => {
    const rgb = hexToRgb(hex);
    const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
    return brightness > 128 ? "#000000" : "#FFFFFF";
  };

  const getComplementary = (hex: string) => {
    const rgb = hexToRgb(hex);
    return `#${(255 - rgb.r).toString(16).padStart(2, "0")}${(255 - rgb.g).toString(16).padStart(2, "0")}${(255 - rgb.b).toString(16).padStart(2, "0")}`.toUpperCase();
  };

  const getAnalogous = (hex: string) => {
    const rgb = hexToRgb(hex);
    const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    const h1 = (hsl.h + 30) % 360;
    const h2 = (hsl.h - 30 + 360) % 360;
    return [hslToHex(h1, hsl.s, hsl.l), hslToHex(h2, hsl.s, hsl.l)];
  };

  const hslToHex = (h: number, s: number, l: number) => {
    s /= 100; l /= 100;
    const a = s * Math.min(l, 1 - l);
    const f = (n: number) => {
      const k = (n + h / 30) % 12;
      const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
      return Math.round(255 * color).toString(16).padStart(2, "0");
    };
    return `#${f(0)}${f(8)}${f(4)}`.toUpperCase();
  };

  const formatStrings = formats ? {
    hex: formats.hex,
    rgb: `rgb(${formats.rgb.r}, ${formats.rgb.g}, ${formats.rgb.b})`,
    hsl: `hsl(${formats.hsl.h}, ${formats.hsl.s}%, ${formats.hsl.l}%)`,
    cmyk: `cmyk(${formats.cmyk.c}%, ${formats.cmyk.m}%, ${formats.cmyk.y}%, ${formats.cmyk.k}%)`
  } : null;

  const meta = toolsMetadata.color;

  return (
    <ToolPageLayout
      toolNumber="13"
      title="Color Picker"
      subtitle="Alat Desain"
      description="Pilih warna dan konversi ke berbagai format (HEX, RGB, HSL, CMYK)"
    >
      <SEOHead title={meta.title} description={meta.description} path={meta.path} keywords={meta.keywords} />
      <div className="mx-auto max-w-2xl space-y-6">
        {/* Color Input */}
        <Card className="p-6">
          <div className="flex flex-col sm:flex-row gap-4 items-center">
            <div
              className="w-full sm:w-40 h-32 rounded-lg border-4 border-border shadow-inner transition-colors"
              style={{ backgroundColor: color }}
            >
              <div 
                className="flex items-center justify-center h-full text-lg font-mono font-bold"
                style={{ color: getContrastColor(color) }}
              >
                {color.toUpperCase()}
              </div>
            </div>
            <div className="flex-1 space-y-4 w-full">
              <div className="space-y-2">
                <Label htmlFor="colorPicker">Pilih Warna</Label>
                <div className="flex gap-2">
                  <Input
                    id="colorPicker"
                    type="color"
                    value={color}
                    onChange={(e) => setColor(e.target.value)}
                    className="w-16 h-10 p-1 cursor-pointer"
                  />
                  <Input
                    type="text"
                    value={color.toUpperCase()}
                    onChange={(e) => {
                      const val = e.target.value;
                      if (/^#[0-9A-Fa-f]{0,6}$/.test(val)) setColor(val);
                    }}
                    placeholder="#000000"
                    className="flex-1 font-mono"
                    maxLength={7}
                  />
                </div>
              </div>
              <Button onClick={generateRandomColor} variant="outline" className="w-full">
                <Shuffle className="mr-2 h-4 w-4" />
                Warna Random
              </Button>
            </div>
          </div>
        </Card>

        {/* Color Formats */}
        {formatStrings && (
          <Card className="p-6 space-y-4">
            <h3 className="font-semibold text-foreground">Format Warna</h3>
            <div className="grid gap-3">
              {Object.entries(formatStrings).map(([format, value]) => (
                <div key={format} className="flex items-center gap-3 p-3 rounded-lg border border-border bg-muted/30">
                  <span className="w-12 text-xs font-semibold uppercase text-muted-foreground">{format}</span>
                  <code className="flex-1 font-mono text-sm text-foreground">{value}</code>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => copyToClipboard(value, format)}
                  >
                    {copiedFormat === format ? (
                      <Check className="h-4 w-4 text-green-500" />
                    ) : (
                      <Copy className="h-4 w-4" />
                    )}
                  </Button>
                </div>
              ))}
            </div>
          </Card>
        )}

        {/* Color Palette */}
        <Card className="p-6 space-y-4">
          <h3 className="font-semibold text-foreground">Palet Warna</h3>
          <div className="space-y-3">
            {/* Complementary */}
            <div>
              <Label className="text-xs text-muted-foreground mb-2 block">Komplementer</Label>
              <div className="flex gap-2">
                <button
                  className="flex-1 h-12 rounded-lg border border-border transition-transform hover:scale-105"
                  style={{ backgroundColor: color }}
                  onClick={() => copyToClipboard(color.toUpperCase(), "comp1")}
                  title={color.toUpperCase()}
                />
                <button
                  className="flex-1 h-12 rounded-lg border border-border transition-transform hover:scale-105"
                  style={{ backgroundColor: getComplementary(color) }}
                  onClick={() => copyToClipboard(getComplementary(color), "comp2")}
                  title={getComplementary(color)}
                />
              </div>
            </div>
            {/* Analogous */}
            <div>
              <Label className="text-xs text-muted-foreground mb-2 block">Analogus</Label>
              <div className="flex gap-2">
                {getAnalogous(color).map((c, i) => (
                  <button
                    key={i}
                    className="flex-1 h-12 rounded-lg border border-border transition-transform hover:scale-105"
                    style={{ backgroundColor: c }}
                    onClick={() => copyToClipboard(c, `analog${i}`)}
                    title={c}
                  />
                ))}
                <button
                  className="flex-1 h-12 rounded-lg border border-border transition-transform hover:scale-105"
                  style={{ backgroundColor: color }}
                  onClick={() => copyToClipboard(color.toUpperCase(), "analog-main")}
                  title={color.toUpperCase()}
                />
              </div>
            </div>
          </div>
        </Card>
      </div>
    </ToolPageLayout>
  );
};

export default ColorPicker;
