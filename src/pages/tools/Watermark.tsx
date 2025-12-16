import { useState, useRef, useEffect, useCallback } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Upload, Download, Type, ImageIcon, RotateCcw, Grid3X3 } from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";

type Position = "top-left" | "top-center" | "top-right" | "center-left" | "center" | "center-right" | "bottom-left" | "bottom-center" | "bottom-right";
type WatermarkType = "text" | "image";

const positionMap: Record<Position, { x: number; y: number }> = {
  "top-left": { x: 0.1, y: 0.1 },
  "top-center": { x: 0.5, y: 0.1 },
  "top-right": { x: 0.9, y: 0.1 },
  "center-left": { x: 0.1, y: 0.5 },
  "center": { x: 0.5, y: 0.5 },
  "center-right": { x: 0.9, y: 0.5 },
  "bottom-left": { x: 0.1, y: 0.9 },
  "bottom-center": { x: 0.5, y: 0.9 },
  "bottom-right": { x: 0.9, y: 0.9 },
};

const Watermark = () => {
  const { t } = useTranslation();
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [mainImage, setMainImage] = useState<HTMLImageElement | null>(null);
  const [mainImageUrl, setMainImageUrl] = useState<string>("");
  const [watermarkType, setWatermarkType] = useState<WatermarkType>("text");
  const [watermarkText, setWatermarkText] = useState("© Bantuin.online");
  const [watermarkImage, setWatermarkImage] = useState<HTMLImageElement | null>(null);
  const [position, setPosition] = useState<Position>("bottom-right");
  const [opacity, setOpacity] = useState(50);
  const [rotation, setRotation] = useState(0);
  const [fontSize, setFontSize] = useState(24);
  const [fontColor, setFontColor] = useState("#ffffff");
  const [tileMode, setTileMode] = useState(false);
  const [watermarkScale, setWatermarkScale] = useState(20);

  const drawCanvas = useCallback(() => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext("2d");
    if (!canvas || !ctx || !mainImage) return;

    canvas.width = mainImage.width;
    canvas.height = mainImage.height;

    // Draw main image
    ctx.drawImage(mainImage, 0, 0);

    // Set watermark opacity
    ctx.globalAlpha = opacity / 100;

    if (tileMode) {
      // Tile mode
      const tileSize = Math.min(canvas.width, canvas.height) * 0.15;
      const gap = tileSize * 2;

      ctx.save();
      ctx.translate(canvas.width / 2, canvas.height / 2);
      ctx.rotate((rotation * Math.PI) / 180);
      ctx.translate(-canvas.width / 2, -canvas.height / 2);

      for (let y = -canvas.height; y < canvas.height * 2; y += gap) {
        for (let x = -canvas.width; x < canvas.width * 2; x += gap) {
          if (watermarkType === "text") {
            ctx.font = `${fontSize}px Arial`;
            ctx.fillStyle = fontColor;
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText(watermarkText, x, y);
          } else if (watermarkImage) {
            const scale = tileSize / Math.max(watermarkImage.width, watermarkImage.height);
            const w = watermarkImage.width * scale;
            const h = watermarkImage.height * scale;
            ctx.drawImage(watermarkImage, x - w / 2, y - h / 2, w, h);
          }
        }
      }
      ctx.restore();
    } else {
      // Single watermark
      const pos = positionMap[position];
      const x = canvas.width * pos.x;
      const y = canvas.height * pos.y;

      ctx.save();
      ctx.translate(x, y);
      ctx.rotate((rotation * Math.PI) / 180);

      if (watermarkType === "text") {
        ctx.font = `${fontSize}px Arial`;
        ctx.fillStyle = fontColor;
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(watermarkText, 0, 0);
      } else if (watermarkImage) {
        const maxSize = Math.min(canvas.width, canvas.height) * (watermarkScale / 100);
        const scale = maxSize / Math.max(watermarkImage.width, watermarkImage.height);
        const w = watermarkImage.width * scale;
        const h = watermarkImage.height * scale;
        ctx.drawImage(watermarkImage, -w / 2, -h / 2, w, h);
      }
      ctx.restore();
    }

    ctx.globalAlpha = 1;
  }, [mainImage, watermarkType, watermarkText, watermarkImage, position, opacity, rotation, fontSize, fontColor, tileMode, watermarkScale]);

  useEffect(() => {
    drawCanvas();
  }, [drawCanvas]);

  const handleMainImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const img = new window.Image();
    img.onload = () => {
      setMainImage(img);
      setMainImageUrl(URL.createObjectURL(file));
    };
    img.src = URL.createObjectURL(file);
  };

  const handleWatermarkImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const img = new window.Image();
    img.onload = () => setWatermarkImage(img);
    img.src = URL.createObjectURL(file);
    toast.success(t('watermark.toast_logo_success'));
  };

  const handleDownload = (format: "png" | "jpeg") => {
    const canvas = canvasRef.current;
    if (!canvas || !mainImage) {
      toast.error(t('watermark.toast_upload_first'));
      return;
    }

    const link = document.createElement("a");
    link.download = `watermarked-${Date.now()}.${format === "jpeg" ? "jpg" : "png"}`;
    link.href = canvas.toDataURL(`image/${format}`, 0.9);
    link.click();
    toast.success(t('watermark.toast_download_success'));
  };

  const resetSettings = () => {
    setWatermarkText("© Bantuin.online");
    setPosition("bottom-right");
    setOpacity(50);
    setRotation(0);
    setFontSize(24);
    setFontColor("#ffffff");
    setTileMode(false);
    setWatermarkScale(20);
  };

  return (
    <ToolPageLayout
      toolNumber="21"
      title={t('tool_items.watermark.title')}
      subtitle={t('watermark.subtitle')}
      description={t('tool_items.watermark.desc')}
    >
      <SEOHead
        title={t('watermark.meta.title')}
        description={t('watermark.meta.description')}
        path="/tools/watermark"
        keywords={t('watermark.meta.keywords', { returnObjects: true }) as string[]}
      />
      <div className="mx-auto max-w-4xl space-y-6">
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Preview */}
          <div className="space-y-4">
            <Label className="text-base font-medium">{t('watermark.label_preview')}</Label>
            <div className="overflow-hidden rounded-lg border border-border bg-secondary/30">
              {mainImage ? (
                <canvas
                  ref={canvasRef}
                  className="h-auto w-full"
                  style={{ maxHeight: "400px", objectFit: "contain" }}
                />
              ) : (
                <label
                  htmlFor="main-image"
                  className="flex aspect-video cursor-pointer flex-col items-center justify-center gap-4 p-8"
                >
                  <Upload className="h-12 w-12 text-muted-foreground" />
                  <div className="text-center">
                    <p className="font-medium">{t('watermark.label_upload_main')}</p>
                    <p className="text-sm text-muted-foreground">{t('watermark.text_upload_main_hint')}</p>
                  </div>
                  <input
                    type="file"
                    id="main-image"
                    accept="image/*"
                    onChange={handleMainImageUpload}
                    className="hidden"
                  />
                </label>
              )}
            </div>
            {mainImage && (
              <div className="flex gap-2">
                <label className="flex-1">
                  <Button variant="outline" className="w-full" asChild>
                    <span>
                      <Upload className="mr-2 h-4 w-4" />
                      {t('watermark.btn_change_photo')}
                    </span>
                  </Button>
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handleMainImageUpload}
                    className="hidden"
                  />
                </label>
                <Button variant="outline" onClick={resetSettings}>
                  <RotateCcw className="mr-2 h-4 w-4" />
                  {t('watermark.btn_reset')}
                </Button>
              </div>
            )}
          </div>

          {/* Settings */}
          <div className="space-y-4 rounded-lg border border-border bg-card p-6">
            <Tabs value={watermarkType} onValueChange={(v) => setWatermarkType(v as WatermarkType)}>
              <TabsList className="w-full">
                <TabsTrigger value="text" className="flex-1">
                  <Type className="mr-2 h-4 w-4" />
                  {t('watermark.tab_text')}
                </TabsTrigger>
                <TabsTrigger value="image" className="flex-1">
                  <ImageIcon className="mr-2 h-4 w-4" />
                  {t('watermark.tab_logo')}
                </TabsTrigger>
              </TabsList>

              <TabsContent value="text" className="mt-4 space-y-4">
                <div className="space-y-2">
                  <Label>{t('watermark.label_watermark_text')}</Label>
                  <Input
                    value={watermarkText}
                    onChange={(e) => setWatermarkText(e.target.value)}
                    placeholder={t('watermark.placeholder_watermark_text')}
                  />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>{t('watermark.label_font_size')}</Label>
                    <Slider
                      value={[fontSize]}
                      onValueChange={(v) => setFontSize(v[0])}
                      min={12}
                      max={72}
                    />
                    <p className="text-xs text-muted-foreground">{fontSize}px</p>
                  </div>
                  <div className="space-y-2">
                    <Label>{t('watermark.label_color')}</Label>
                    <div className="flex gap-2">
                      <Input
                        type="color"
                        value={fontColor}
                        onChange={(e) => setFontColor(e.target.value)}
                        className="h-10 w-14 cursor-pointer p-1"
                      />
                      <Input
                        value={fontColor}
                        onChange={(e) => setFontColor(e.target.value)}
                        className="flex-1 font-mono"
                      />
                    </div>
                  </div>
                </div>
              </TabsContent>

              <TabsContent value="image" className="mt-4 space-y-4">
                <div className="space-y-2">
                  <Label>{t('watermark.label_upload_logo')}</Label>
                  <label className="flex cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-border p-4 transition-colors hover:border-primary/50">
                    {watermarkImage ? (
                      <div className="flex items-center gap-3">
                        <img
                          src={watermarkImage.src}
                          alt="Logo"
                          className="h-12 w-12 object-contain"
                        />
                        <span className="text-sm text-muted-foreground">{t('watermark.text_click_change')}</span>
                      </div>
                    ) : (
                      <div className="text-center">
                        <ImageIcon className="mx-auto h-8 w-8 text-muted-foreground" />
                        <p className="mt-2 text-sm">{t('watermark.text_upload_logo_hint')}</p>
                      </div>
                    )}
                    <input
                      type="file"
                      accept="image/*"
                      onChange={handleWatermarkImageUpload}
                      className="hidden"
                    />
                  </label>
                </div>
                <div className="space-y-2">
                  <Label>{t('watermark.label_logo_size')}</Label>
                  <Slider
                    value={[watermarkScale]}
                    onValueChange={(v) => setWatermarkScale(v[0])}
                    min={5}
                    max={50}
                  />
                  <p className="text-xs text-muted-foreground">{watermarkScale}%</p>
                </div>
              </TabsContent>
            </Tabs>

            <div className="space-y-4 border-t border-border pt-4">
              <div className="space-y-2">
                <Label>{t('watermark.label_position')}</Label>
                <Select value={position} onValueChange={(v) => setPosition(v as Position)} disabled={tileMode}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="top-left">{t('watermark.pos_top_left')}</SelectItem>
                    <SelectItem value="top-center">{t('watermark.pos_top_center')}</SelectItem>
                    <SelectItem value="top-right">{t('watermark.pos_top_right')}</SelectItem>
                    <SelectItem value="center-left">{t('watermark.pos_center_left')}</SelectItem>
                    <SelectItem value="center">{t('watermark.pos_center')}</SelectItem>
                    <SelectItem value="center-right">{t('watermark.pos_center_right')}</SelectItem>
                    <SelectItem value="bottom-left">{t('watermark.pos_bottom_left')}</SelectItem>
                    <SelectItem value="bottom-center">{t('watermark.pos_bottom_center')}</SelectItem>
                    <SelectItem value="bottom-right">{t('watermark.pos_bottom_right')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label>{t('watermark.label_opacity')}</Label>
                  <span className="text-sm text-muted-foreground">{opacity}%</span>
                </div>
                <Slider
                  value={[opacity]}
                  onValueChange={(v) => setOpacity(v[0])}
                  min={10}
                  max={100}
                />
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label>{t('watermark.label_rotation')}</Label>
                  <span className="text-sm text-muted-foreground">{rotation}°</span>
                </div>
                <Slider
                  value={[rotation]}
                  onValueChange={(v) => setRotation(v[0])}
                  min={-45}
                  max={45}
                />
              </div>

              <div className="flex items-center justify-between rounded-lg border border-border p-3">
                <div className="flex items-center gap-2">
                  <Grid3X3 className="h-4 w-4 text-muted-foreground" />
                  <Label htmlFor="tile-mode" className="cursor-pointer">{t('watermark.label_tile_mode')}</Label>
                </div>
                <Switch
                  id="tile-mode"
                  checked={tileMode}
                  onCheckedChange={setTileMode}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Download Buttons */}
        {mainImage && (
          <div className="flex gap-3">
            <Button onClick={() => handleDownload("jpeg")} className="flex-1">
              <Download className="mr-2 h-4 w-4" />
              {t('watermark.btn_download_jpg')}
            </Button>
            <Button onClick={() => handleDownload("png")} variant="outline" className="flex-1">
              <Download className="mr-2 h-4 w-4" />
              {t('watermark.btn_download_png')}
            </Button>
          </div>
        )}
      </div>
    </ToolPageLayout>
  );
};

export default Watermark;
