import { useState, useCallback } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";
import { Switch } from "@/components/ui/switch";
import { Card } from "@/components/ui/card";
import { Copy, RefreshCw, Check, Shield, ShieldAlert, ShieldCheck, ShieldX } from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";

const PasswordGenerator = () => {
  const { t } = useTranslation();
  const [length, setLength] = useState(16);
  const [uppercase, setUppercase] = useState(true);
  const [lowercase, setLowercase] = useState(true);
  const [numbers, setNumbers] = useState(true);
  const [symbols, setSymbols] = useState(true);
  const [excludeAmbiguous, setExcludeAmbiguous] = useState(false);
  const [passwords, setPasswords] = useState<string[]>([]);
  const [count, setCount] = useState(1);
  const [copiedIndex, setCopiedIndex] = useState<number | null>(null);

  const generatePassword = useCallback(() => {
    let chars = "";
    const ambiguousChars = "0O1lI";
    
    if (uppercase) chars += "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    if (lowercase) chars += "abcdefghijklmnopqrstuvwxyz";
    if (numbers) chars += "0123456789";
    if (symbols) chars += "!@#$%^&*()_+-=[]{}|;:,.<>?";
    
    if (excludeAmbiguous) {
      chars = chars.split("").filter(c => !ambiguousChars.includes(c)).join("");
    }
    
    if (!chars) {
      toast.error(t('password.toast_select_char'));
      return;
    }
    
    const newPasswords: string[] = [];
    for (let i = 0; i < count; i++) {
      let password = "";
      const array = new Uint32Array(length);
      crypto.getRandomValues(array);
      for (let j = 0; j < length; j++) {
        password += chars[array[j] % chars.length];
      }
      newPasswords.push(password);
    }
    
    setPasswords(newPasswords);
    setCopiedIndex(null);
  }, [length, uppercase, lowercase, numbers, symbols, excludeAmbiguous, count, t]);

  const copyToClipboard = async (password: string, index: number) => {
    await navigator.clipboard.writeText(password);
    setCopiedIndex(index);
    toast.success(t('password.toast_copied'));
    setTimeout(() => setCopiedIndex(null), 2000);
  };

  const getStrength = (password: string) => {
    let score = 0;
    if (password.length >= 12) score++;
    if (password.length >= 16) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    if (score <= 2) return { label: t('password.strength_weak'), color: "text-red-500", bg: "bg-red-500", icon: ShieldX };
    if (score <= 4) return { label: t('password.strength_medium'), color: "text-yellow-500", bg: "bg-yellow-500", icon: ShieldAlert };
    if (score <= 5) return { label: t('password.strength_strong'), color: "text-green-500", bg: "bg-green-500", icon: ShieldCheck };
    return { label: t('password.strength_very_strong'), color: "text-emerald-500", bg: "bg-emerald-500", icon: Shield };
  };



  return (
    <ToolPageLayout
      toolNumber="12"
      title={t('password.title')}
      subtitle={t('password.subtitle')}
      description={t('password.desc_page')}
    >
      <SEOHead 
        title={t('password.meta.title')}
        description={t('password.meta.description')}
        path="/tools/password-generator"
        keywords={t('password.meta.keywords', { returnObjects: true }) as string[]}
      />
      <div className="mx-auto max-w-2xl space-y-6">
        {/* Settings */}
        <Card className="p-6 space-y-6">
          {/* Length Slider */}
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <Label>{t('password.label_length')}</Label>
              <span className="font-mono text-sm font-medium text-primary">{length}</span>
            </div>
            <Slider
              value={[length]}
              onValueChange={(v) => setLength(v[0])}
              min={8}
              max={64}
              step={1}
              className="w-full"
            />
          </div>

          {/* Character Options */}
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="flex items-center justify-between rounded-lg border border-border p-3">
              <Label htmlFor="uppercase" className="cursor-pointer">{t('password.label_uppercase')}</Label>
              <Switch id="uppercase" checked={uppercase} onCheckedChange={setUppercase} />
            </div>
            <div className="flex items-center justify-between rounded-lg border border-border p-3">
              <Label htmlFor="lowercase" className="cursor-pointer">{t('password.label_lowercase')}</Label>
              <Switch id="lowercase" checked={lowercase} onCheckedChange={setLowercase} />
            </div>
            <div className="flex items-center justify-between rounded-lg border border-border p-3">
              <Label htmlFor="numbers" className="cursor-pointer">{t('password.label_numbers')}</Label>
              <Switch id="numbers" checked={numbers} onCheckedChange={setNumbers} />
            </div>
            <div className="flex items-center justify-between rounded-lg border border-border p-3">
              <Label htmlFor="symbols" className="cursor-pointer">{t('password.label_symbols')}</Label>
              <Switch id="symbols" checked={symbols} onCheckedChange={setSymbols} />
            </div>
          </div>

          {/* Exclude Ambiguous */}
          <div className="flex items-center justify-between rounded-lg border border-border p-3">
            <div>
              <Label htmlFor="ambiguous" className="cursor-pointer">{t('password.label_ambiguous')}</Label>
              <p className="text-xs text-muted-foreground mt-1">{t('password.hint_ambiguous')}</p>
            </div>
            <Switch id="ambiguous" checked={excludeAmbiguous} onCheckedChange={setExcludeAmbiguous} />
          </div>

          {/* Count */}
          <div className="flex items-center gap-4">
            <Label htmlFor="count">{t('password.label_count')}</Label>
            <Input
              id="count"
              type="number"
              min={1}
              max={10}
              value={count}
              onChange={(e) => setCount(Math.min(10, Math.max(1, parseInt(e.target.value) || 1)))}
              className="w-20"
            />
          </div>

          {/* Generate Button */}
          <Button onClick={generatePassword} className="w-full" size="lg">
            <RefreshCw className="mr-2 h-4 w-4" />
            {t('password.btn_generate')}
          </Button>
        </Card>

        {/* Results */}
        {passwords.length > 0 && (
          <div className="space-y-3">
            <h3 className="font-semibold text-foreground">{t('password.card_result')}</h3>
            {passwords.map((password, index) => {
              const strength = getStrength(password);
              const StrengthIcon = strength.icon;
              return (
                <Card key={index} className="p-4">
                  <div className="flex items-center gap-3">
                    <div className="flex-1 overflow-hidden">
                      <code className="block truncate font-mono text-sm md:text-base text-foreground">
                        {password}
                      </code>
                    </div>
                    <Button
                      variant="outline"
                      size="icon"
                      onClick={() => copyToClipboard(password, index)}
                    >
                      {copiedIndex === index ? (
                        <Check className="h-4 w-4 text-green-500" />
                      ) : (
                        <Copy className="h-4 w-4" />
                      )}
                    </Button>
                  </div>
                  <div className="mt-3 flex items-center gap-2">
                    <StrengthIcon className={`h-4 w-4 ${strength.color}`} />
                    <span className={`text-sm font-medium ${strength.color}`}>{strength.label}</span>
                    <div className="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                      <div 
                        className={`h-full ${strength.bg} transition-all`} 
                        style={{ width: strength.label === t('password.strength_weak') ? "25%" : strength.label === t('password.strength_medium') ? "50%" : strength.label === t('password.strength_strong') ? "75%" : "100%" }}
                      />
                    </div>
                  </div>
                </Card>
              );
            })}
          </div>
        )}
      </div>
    </ToolPageLayout>
  );
};

export default PasswordGenerator;
