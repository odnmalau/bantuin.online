import { useState, useMemo } from "react";
import { Cake, Calendar } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";
import { toolsMetadata } from "@/data/toolsMetadata";

const AgeCalculator = () => {
  const [birthDate, setBirthDate] = useState("");

  const result = useMemo(() => {
    if (!birthDate) return null;

    const birth = new Date(birthDate);
    const today = new Date();
    
    if (birth > today) return null;

    // Calculate age
    let years = today.getFullYear() - birth.getFullYear();
    let months = today.getMonth() - birth.getMonth();
    let days = today.getDate() - birth.getDate();

    if (days < 0) {
      months--;
      const lastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
      days += lastMonth.getDate();
    }

    if (months < 0) {
      years--;
      months += 12;
    }

    // Total days lived
    const totalDays = Math.floor(
      (today.getTime() - birth.getTime()) / (1000 * 60 * 60 * 24)
    );

    // Next birthday
    const nextBirthday = new Date(
      today.getFullYear(),
      birth.getMonth(),
      birth.getDate()
    );
    if (nextBirthday <= today) {
      nextBirthday.setFullYear(today.getFullYear() + 1);
    }
    const daysUntilBirthday = Math.ceil(
      (nextBirthday.getTime() - today.getTime()) / (1000 * 60 * 60 * 24)
    );

    // Western Zodiac
    const month = birth.getMonth() + 1;
    const day = birth.getDate();
    const zodiacSigns = [
      { name: "Capricorn", emoji: "♑", start: [1, 1], end: [1, 19] },
      { name: "Aquarius", emoji: "♒", start: [1, 20], end: [2, 18] },
      { name: "Pisces", emoji: "♓", start: [2, 19], end: [3, 20] },
      { name: "Aries", emoji: "♈", start: [3, 21], end: [4, 19] },
      { name: "Taurus", emoji: "♉", start: [4, 20], end: [5, 20] },
      { name: "Gemini", emoji: "♊", start: [5, 21], end: [6, 20] },
      { name: "Cancer", emoji: "♋", start: [6, 21], end: [7, 22] },
      { name: "Leo", emoji: "♌", start: [7, 23], end: [8, 22] },
      { name: "Virgo", emoji: "♍", start: [8, 23], end: [9, 22] },
      { name: "Libra", emoji: "♎", start: [9, 23], end: [10, 22] },
      { name: "Scorpio", emoji: "♏", start: [10, 23], end: [11, 21] },
      { name: "Sagittarius", emoji: "♐", start: [11, 22], end: [12, 21] },
      { name: "Capricorn", emoji: "♑", start: [12, 22], end: [12, 31] },
    ];

    let westernZodiac = zodiacSigns[0];
    for (const sign of zodiacSigns) {
      const [startM, startD] = sign.start;
      const [endM, endD] = sign.end;
      if (
        (month === startM && day >= startD) ||
        (month === endM && day <= endD)
      ) {
        westernZodiac = sign;
        break;
      }
    }

    // Chinese Zodiac (Shio)
    const chineseZodiacs = [
      { name: "Tikus", emoji: "🐀" },
      { name: "Kerbau", emoji: "🐂" },
      { name: "Macan", emoji: "🐅" },
      { name: "Kelinci", emoji: "🐇" },
      { name: "Naga", emoji: "🐉" },
      { name: "Ular", emoji: "🐍" },
      { name: "Kuda", emoji: "🐴" },
      { name: "Kambing", emoji: "🐐" },
      { name: "Monyet", emoji: "🐵" },
      { name: "Ayam", emoji: "🐓" },
      { name: "Anjing", emoji: "🐕" },
      { name: "Babi", emoji: "🐷" },
    ];
    const chineseZodiac = chineseZodiacs[(birth.getFullYear() - 4) % 12];

    return {
      years,
      months,
      days,
      totalDays,
      daysUntilBirthday,
      westernZodiac,
      chineseZodiac,
    };
  }, [birthDate]);

  const meta = toolsMetadata.age;

  return (
    <ToolPageLayout
      toolNumber="08"
      title="Kalkulator Usia"
      subtitle="Hitung Usia Lengkap"
      description="Hitung usia dari tanggal lahir, termasuk zodiak dan shio."
    >
      <SEOHead 
        title={meta.title}
        description={meta.description}
        path={meta.path}
        keywords={meta.keywords}
      />
      <div className="space-y-6">
        {/* Date Input */}
        <div className="space-y-2">
          <label className="text-sm font-medium text-foreground">
            Tanggal Lahir
          </label>
          <Input
            type="date"
            value={birthDate}
            onChange={(e) => setBirthDate(e.target.value)}
            max={new Date().toISOString().split("T")[0]}
            className="font-body"
          />
        </div>

        {/* Results */}
        {result && (
          <div className="animate-fade-in space-y-4">
            {/* Main Age Display */}
            <div className="rounded-lg border border-primary/20 bg-primary/5 p-6 text-center">
              <p className="mb-2 text-sm text-muted-foreground">Usia Anda</p>
              <div className="flex items-center justify-center gap-4">
                <div>
                  <span className="font-display text-4xl font-bold text-primary">
                    {result.years}
                  </span>
                  <span className="ml-1 text-sm text-muted-foreground">
                    tahun
                  </span>
                </div>
                <div>
                  <span className="font-display text-2xl font-semibold text-foreground">
                    {result.months}
                  </span>
                  <span className="ml-1 text-sm text-muted-foreground">
                    bulan
                  </span>
                </div>
                <div>
                  <span className="font-display text-2xl font-semibold text-foreground">
                    {result.days}
                  </span>
                  <span className="ml-1 text-sm text-muted-foreground">
                    hari
                  </span>
                </div>
              </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-2 gap-3">
              <div className="rounded-lg border border-border bg-card p-4 text-center">
                <Calendar className="mx-auto mb-2 h-5 w-5 text-primary" />
                <p className="font-display text-xl font-bold text-foreground">
                  {result.totalDays.toLocaleString()}
                </p>
                <p className="text-xs text-muted-foreground">hari sudah hidup</p>
              </div>
              <div className="rounded-lg border border-border bg-card p-4 text-center">
                <Cake className="mx-auto mb-2 h-5 w-5 text-primary" />
                <p className="font-display text-xl font-bold text-foreground">
                  {result.daysUntilBirthday}
                </p>
                <p className="text-xs text-muted-foreground">
                  hari lagi ulang tahun
                </p>
              </div>
            </div>

            {/* Zodiac */}
            <div className="rounded-lg border border-border bg-secondary/30 p-4">
              <h3 className="mb-3 font-display text-sm font-semibold text-foreground">
                Zodiak
              </h3>
              <div className="grid grid-cols-2 gap-4">
                <div className="flex items-center gap-3">
                  <span className="text-2xl">{result.westernZodiac.emoji}</span>
                  <div>
                    <p className="font-medium text-foreground">
                      {result.westernZodiac.name}
                    </p>
                    <p className="text-xs text-muted-foreground">Zodiak Barat</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-2xl">{result.chineseZodiac.emoji}</span>
                  <div>
                    <p className="font-medium text-foreground">
                      {result.chineseZodiac.name}
                    </p>
                    <p className="text-xs text-muted-foreground">Shio</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Empty State */}
        {!result && (
          <div className="rounded-lg border border-dashed border-border p-8 text-center">
            <Cake className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">
              Masukkan tanggal lahir untuk melihat hasil
            </p>
          </div>
        )}
      </div>
    </ToolPageLayout>
  );
};

export default AgeCalculator;
