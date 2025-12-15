import { useState, useEffect } from "react";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { ArrowLeftRight } from "lucide-react";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

type Category = "length" | "weight" | "temperature" | "data" | "time" | "volume";

interface UnitDef {
  name: string;
  toBase: (v: number) => number;
  fromBase: (v: number) => number;
}

const units: Record<Category, Record<string, UnitDef>> = {
  length: {
    m: { name: "Meter", toBase: (v) => v, fromBase: (v) => v },
    km: { name: "Kilometer", toBase: (v) => v * 1000, fromBase: (v) => v / 1000 },
    cm: { name: "Centimeter", toBase: (v) => v / 100, fromBase: (v) => v * 100 },
    mm: { name: "Milimeter", toBase: (v) => v / 1000, fromBase: (v) => v * 1000 },
    mi: { name: "Mile", toBase: (v) => v * 1609.344, fromBase: (v) => v / 1609.344 },
    ft: { name: "Feet", toBase: (v) => v * 0.3048, fromBase: (v) => v / 0.3048 },
    in: { name: "Inch", toBase: (v) => v * 0.0254, fromBase: (v) => v / 0.0254 },
  },
  weight: {
    kg: { name: "Kilogram", toBase: (v) => v, fromBase: (v) => v },
    g: { name: "Gram", toBase: (v) => v / 1000, fromBase: (v) => v * 1000 },
    mg: { name: "Miligram", toBase: (v) => v / 1000000, fromBase: (v) => v * 1000000 },
    lb: { name: "Pound", toBase: (v) => v * 0.453592, fromBase: (v) => v / 0.453592 },
    oz: { name: "Ounce", toBase: (v) => v * 0.0283495, fromBase: (v) => v / 0.0283495 },
    ton: { name: "Ton", toBase: (v) => v * 1000, fromBase: (v) => v / 1000 },
  },
  temperature: {
    c: { name: "Celsius", toBase: (v) => v, fromBase: (v) => v },
    f: { name: "Fahrenheit", toBase: (v) => (v - 32) * 5/9, fromBase: (v) => v * 9/5 + 32 },
    k: { name: "Kelvin", toBase: (v) => v - 273.15, fromBase: (v) => v + 273.15 },
  },
  data: {
    b: { name: "Byte", toBase: (v) => v, fromBase: (v) => v },
    kb: { name: "Kilobyte", toBase: (v) => v * 1024, fromBase: (v) => v / 1024 },
    mb: { name: "Megabyte", toBase: (v) => v * 1048576, fromBase: (v) => v / 1048576 },
    gb: { name: "Gigabyte", toBase: (v) => v * 1073741824, fromBase: (v) => v / 1073741824 },
    tb: { name: "Terabyte", toBase: (v) => v * 1099511627776, fromBase: (v) => v / 1099511627776 },
  },
  time: {
    s: { name: "Detik", toBase: (v) => v, fromBase: (v) => v },
    min: { name: "Menit", toBase: (v) => v * 60, fromBase: (v) => v / 60 },
    h: { name: "Jam", toBase: (v) => v * 3600, fromBase: (v) => v / 3600 },
    d: { name: "Hari", toBase: (v) => v * 86400, fromBase: (v) => v / 86400 },
    w: { name: "Minggu", toBase: (v) => v * 604800, fromBase: (v) => v / 604800 },
    mo: { name: "Bulan", toBase: (v) => v * 2629746, fromBase: (v) => v / 2629746 },
    y: { name: "Tahun", toBase: (v) => v * 31556952, fromBase: (v) => v / 31556952 },
  },
  volume: {
    l: { name: "Liter", toBase: (v) => v, fromBase: (v) => v },
    ml: { name: "Mililiter", toBase: (v) => v / 1000, fromBase: (v) => v * 1000 },
    gal: { name: "Gallon (US)", toBase: (v) => v * 3.78541, fromBase: (v) => v / 3.78541 },
    qt: { name: "Quart", toBase: (v) => v * 0.946353, fromBase: (v) => v / 0.946353 },
    cup: { name: "Cup", toBase: (v) => v * 0.236588, fromBase: (v) => v / 0.236588 },
    m3: { name: "Meter Kubik", toBase: (v) => v * 1000, fromBase: (v) => v / 1000 },
  },
};

const categoryLabels: Record<Category, string> = {
  length: "Panjang",
  weight: "Berat",
  temperature: "Suhu",
  data: "Data",
  time: "Waktu",
  volume: "Volume",
};

const UnitConverter = () => {
  const [category, setCategory] = useState<Category>("length");
  const [fromUnit, setFromUnit] = useState("m");
  const [toUnit, setToUnit] = useState("km");
  const [fromValue, setFromValue] = useState("1");
  const [toValue, setToValue] = useState("");

  useEffect(() => {
    const catUnits = Object.keys(units[category]);
    setFromUnit(catUnits[0]);
    setToUnit(catUnits[1] || catUnits[0]);
    setFromValue("1");
  }, [category]);

  useEffect(() => {
    const num = parseFloat(fromValue);
    if (isNaN(num)) {
      setToValue("");
      return;
    }
    const baseValue = units[category][fromUnit].toBase(num);
    const result = units[category][toUnit].fromBase(baseValue);
    setToValue(result.toLocaleString("id-ID", { maximumFractionDigits: 10 }));
  }, [fromValue, fromUnit, toUnit, category]);

  const swapUnits = () => {
    setFromUnit(toUnit);
    setToUnit(fromUnit);
    setFromValue(toValue.replace(/\./g, "").replace(",", "."));
  };

  const currentUnits = units[category];

  const meta = toolsMetadata.unit;

  return (
    <ToolPageLayout
      toolNumber="15"
      title="Unit Converter"
      subtitle="Konversi Satuan"
      description="Konversi berbagai satuan panjang, berat, suhu, data, waktu, dan volume"
    >
      <SEOHead title={meta.title} description={meta.description} path={meta.path} keywords={meta.keywords} />
      <div className="mx-auto max-w-2xl space-y-6">
        {/* Category Tabs */}
        <Tabs value={category} onValueChange={(v) => setCategory(v as Category)}>
          <TabsList className="grid w-full grid-cols-3 md:grid-cols-6">
            {Object.entries(categoryLabels).map(([key, label]) => (
              <TabsTrigger key={key} value={key} className="text-xs md:text-sm">
                {label}
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>

        {/* Converter */}
        <Card className="p-6 space-y-6">
          {/* From */}
          <div className="space-y-3">
            <Label>Dari</Label>
            <div className="flex gap-3">
              <Input
                type="number"
                value={fromValue}
                onChange={(e) => setFromValue(e.target.value)}
                placeholder="Masukkan nilai"
                className="flex-1 text-lg"
              />
              <Select value={fromUnit} onValueChange={setFromUnit}>
                <SelectTrigger className="w-36">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent className="bg-popover">
                  {Object.entries(currentUnits).map(([key, unit]) => (
                    <SelectItem key={key} value={key}>
                      {unit.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          {/* Swap Button */}
          <div className="flex justify-center">
            <Button variant="outline" size="icon" onClick={swapUnits}>
              <ArrowLeftRight className="h-4 w-4" />
            </Button>
          </div>

          {/* To */}
          <div className="space-y-3">
            <Label>Ke</Label>
            <div className="flex gap-3">
              <Input
                type="text"
                value={toValue}
                readOnly
                placeholder="Hasil"
                className="flex-1 text-lg bg-muted/50"
              />
              <Select value={toUnit} onValueChange={setToUnit}>
                <SelectTrigger className="w-36">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent className="bg-popover">
                  {Object.entries(currentUnits).map(([key, unit]) => (
                    <SelectItem key={key} value={key}>
                      {unit.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          {/* Result Summary */}
          {toValue && (
            <div className="rounded-lg border border-border bg-primary/5 p-4 text-center">
              <p className="text-lg font-medium text-foreground">
                {fromValue} {currentUnits[fromUnit].name} ={" "}
                <span className="text-primary font-bold">{toValue}</span>{" "}
                {currentUnits[toUnit].name}
              </p>
            </div>
          )}
        </Card>

        {/* Quick Reference */}
        <Card className="p-6">
          <h3 className="font-semibold text-foreground mb-4">Referensi Cepat</h3>
          <div className="grid grid-cols-2 gap-2 text-sm">
            {Object.entries(currentUnits).slice(0, 6).map(([key, unit]) => (
              <div key={key} className="flex justify-between p-2 rounded bg-muted/30">
                <span className="text-muted-foreground">{unit.name}</span>
                <span className="font-mono text-foreground">{key}</span>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </ToolPageLayout>
  );
};

export default UnitConverter;
