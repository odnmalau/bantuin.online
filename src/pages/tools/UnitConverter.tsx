import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { ArrowLeftRight } from "lucide-react";
import { SEOHead } from "@/components/SEOHead";

type Category = "length" | "weight" | "temperature" | "data" | "time" | "volume";

interface UnitDef {
  nameKey: string;
  toBase: (v: number) => number;
  fromBase: (v: number) => number;
}

const units: Record<Category, Record<string, UnitDef>> = {
  length: {
    m: { nameKey: "unit_converter.units.m", toBase: (v) => v, fromBase: (v) => v },
    km: { nameKey: "unit_converter.units.km", toBase: (v) => v * 1000, fromBase: (v) => v / 1000 },
    cm: { nameKey: "unit_converter.units.cm", toBase: (v) => v / 100, fromBase: (v) => v * 100 },
    mm: { nameKey: "unit_converter.units.mm", toBase: (v) => v / 1000, fromBase: (v) => v * 1000 },
    mi: { nameKey: "unit_converter.units.mi", toBase: (v) => v * 1609.344, fromBase: (v) => v / 1609.344 },
    ft: { nameKey: "unit_converter.units.ft", toBase: (v) => v * 0.3048, fromBase: (v) => v / 0.3048 },
    in: { nameKey: "unit_converter.units.in", toBase: (v) => v * 0.0254, fromBase: (v) => v / 0.0254 },
  },
  weight: {
    kg: { nameKey: "unit_converter.units.kg", toBase: (v) => v, fromBase: (v) => v },
    g: { nameKey: "unit_converter.units.g", toBase: (v) => v / 1000, fromBase: (v) => v * 1000 },
    mg: { nameKey: "unit_converter.units.mg", toBase: (v) => v / 1000000, fromBase: (v) => v * 1000000 },
    lb: { nameKey: "unit_converter.units.lb", toBase: (v) => v * 0.453592, fromBase: (v) => v / 0.453592 },
    oz: { nameKey: "unit_converter.units.oz", toBase: (v) => v * 0.0283495, fromBase: (v) => v / 0.0283495 },
    ton: { nameKey: "unit_converter.units.ton", toBase: (v) => v * 1000, fromBase: (v) => v / 1000 },
  },
  temperature: {
    c: { nameKey: "unit_converter.units.c", toBase: (v) => v, fromBase: (v) => v },
    f: { nameKey: "unit_converter.units.f", toBase: (v) => (v - 32) * 5/9, fromBase: (v) => v * 9/5 + 32 },
    k: { nameKey: "unit_converter.units.k", toBase: (v) => v - 273.15, fromBase: (v) => v + 273.15 },
  },
  data: {
    b: { nameKey: "unit_converter.units.b", toBase: (v) => v, fromBase: (v) => v },
    kb: { nameKey: "unit_converter.units.kb", toBase: (v) => v * 1024, fromBase: (v) => v / 1024 },
    mb: { nameKey: "unit_converter.units.mb", toBase: (v) => v * 1048576, fromBase: (v) => v / 1048576 },
    gb: { nameKey: "unit_converter.units.gb", toBase: (v) => v * 1073741824, fromBase: (v) => v / 1073741824 },
    tb: { nameKey: "unit_converter.units.tb", toBase: (v) => v * 1099511627776, fromBase: (v) => v / 1099511627776 },
  },
  time: {
    s: { nameKey: "unit_converter.units.s", toBase: (v) => v, fromBase: (v) => v },
    min: { nameKey: "unit_converter.units.min", toBase: (v) => v * 60, fromBase: (v) => v / 60 },
    h: { nameKey: "unit_converter.units.h", toBase: (v) => v * 3600, fromBase: (v) => v / 3600 },
    d: { nameKey: "unit_converter.units.d", toBase: (v) => v * 86400, fromBase: (v) => v / 86400 },
    w: { nameKey: "unit_converter.units.w", toBase: (v) => v * 604800, fromBase: (v) => v / 604800 },
    mo: { nameKey: "unit_converter.units.mo", toBase: (v) => v * 2629746, fromBase: (v) => v / 2629746 },
    y: { nameKey: "unit_converter.units.y", toBase: (v) => v * 31556952, fromBase: (v) => v / 31556952 },
  },
  volume: {
    l: { nameKey: "unit_converter.units.l", toBase: (v) => v, fromBase: (v) => v },
    ml: { nameKey: "unit_converter.units.ml", toBase: (v) => v / 1000, fromBase: (v) => v * 1000 },
    gal: { nameKey: "unit_converter.units.gal", toBase: (v) => v * 3.78541, fromBase: (v) => v / 3.78541 },
    qt: { nameKey: "unit_converter.units.qt", toBase: (v) => v * 0.946353, fromBase: (v) => v / 0.946353 },
    cup: { nameKey: "unit_converter.units.cup", toBase: (v) => v * 0.236588, fromBase: (v) => v / 0.236588 },
    m3: { nameKey: "unit_converter.units.m3", toBase: (v) => v * 1000, fromBase: (v) => v / 1000 },
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
  const { t } = useTranslation();
  const [category, setCategory] = useState<Category>("length");
  const [fromUnit, setFromUnit] = useState("m");
  const [toUnit, setToUnit] = useState("km");
  const [fromValue, setFromValue] = useState("1");
  /* Removed state for toValue to use derived state */
  /* const [toValue, setToValue] = useState(""); - REMOVED */

  const handleCategoryChange = (newCategory: Category) => {
    setCategory(newCategory);
    const catUnits = Object.keys(units[newCategory]);
    setFromUnit(catUnits[0]);
    setToUnit(catUnits[1] || catUnits[0]);
    setFromValue("1");
  };

  const calculateResult = () => {
    const num = parseFloat(fromValue);
    if (isNaN(num)) return "";
    const baseValue = units[category][fromUnit].toBase(num);
    const result = units[category][toUnit].fromBase(baseValue);
    return result.toLocaleString("id-ID", { maximumFractionDigits: 10 });
  };

  const toValue = calculateResult();

  const swapUnits = () => {
    setFromUnit(toUnit);
    setToUnit(fromUnit);
    setFromValue(toValue.replace(/\./g, "").replace(",", "."));
  };

  const currentUnits = units[category];

  return (
    <ToolPageLayout
      toolNumber="15"
      title={t('tool_items.unit_converter.title')}
      subtitle={t('unit_converter.subtitle')}
      description={t('tool_items.unit_converter.desc')}
    >
      <SEOHead 
        title={t('unit_converter.meta.title')}
        description={t('unit_converter.meta.description')}
        path="/tools/unit-converter"
        keywords={t('unit_converter.meta.keywords', { returnObjects: true }) as string[]} 
      />
      <div className="mx-auto max-w-2xl space-y-6">
        {/* Category Tabs */}
        <Tabs value={category} onValueChange={(v) => handleCategoryChange(v as Category)}>
          <TabsList className="grid w-full grid-cols-3 md:grid-cols-6">
            <TabsTrigger value="length" className="text-xs md:text-sm">{t('unit_converter.cat_length')}</TabsTrigger>
            <TabsTrigger value="weight" className="text-xs md:text-sm">{t('unit_converter.cat_weight')}</TabsTrigger>
            <TabsTrigger value="temperature" className="text-xs md:text-sm">{t('unit_converter.cat_temperature')}</TabsTrigger>
            <TabsTrigger value="data" className="text-xs md:text-sm">{t('unit_converter.cat_data')}</TabsTrigger>
            <TabsTrigger value="time" className="text-xs md:text-sm">{t('unit_converter.cat_time')}</TabsTrigger>
            <TabsTrigger value="volume" className="text-xs md:text-sm">{t('unit_converter.cat_volume')}</TabsTrigger>
          </TabsList>
        </Tabs>

        {/* Converter */}
        <Card className="p-6 space-y-6">
          {/* From */}
          <div className="space-y-3">
            <Label>{t('unit_converter.label_from')}</Label>
            <div className="flex gap-3">
              <Input
                type="number"
                value={fromValue}
                onChange={(e) => setFromValue(e.target.value)}
                placeholder={t('unit_converter.placeholder_value')}
                className="flex-1 text-lg"
              />
              <Select value={fromUnit} onValueChange={setFromUnit}>
                <SelectTrigger className="w-36">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent className="bg-popover">
                  {Object.entries(currentUnits).map(([key, unit]) => (
                    <SelectItem key={key} value={key}>
                      {t(unit.nameKey)}
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
            <Label>{t('unit_converter.label_to')}</Label>
            <div className="flex gap-3">
              <Input
                type="text"
                value={toValue}
                readOnly
                placeholder={t('unit_converter.placeholder_result')}
                className="flex-1 text-lg bg-muted/50"
              />
              <Select value={toUnit} onValueChange={setToUnit}>
                <SelectTrigger className="w-36">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent className="bg-popover">
                  {Object.entries(currentUnits).map(([key, unit]) => (
                    <SelectItem key={key} value={key}>
                      {t(unit.nameKey)}
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
                {fromValue} {t(currentUnits[fromUnit].nameKey)} ={" "}
                <span className="text-primary font-bold">{toValue}</span>{" "}
                {t(currentUnits[toUnit].nameKey)}
              </p>
            </div>
          )}
        </Card>

        {/* Quick Reference */}
        <Card className="p-6">
          <h3 className="font-semibold text-foreground mb-4">{t('unit_converter.reference_title')}</h3>
          <div className="grid grid-cols-2 gap-2 text-sm">
            {Object.entries(currentUnits).slice(0, 6).map(([key, unit]) => (
              <div key={key} className="flex justify-between p-2 rounded bg-muted/30">
                <span className="text-muted-foreground">{t(unit.nameKey)}</span>
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
