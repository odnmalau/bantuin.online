import { useState, useCallback } from "react";
import { useTranslation } from "react-i18next";
import { FileUp, GripVertical, Trash2, Download, Files } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { useToast } from "@/hooks/use-toast";
import { PDFDocument } from "pdf-lib";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

interface PdfFile {
  id: string;
  file: File;
  name: string;
  pageCount: number;
}

const PdfMerger = () => {
  const { t } = useTranslation();
  const [pdfFiles, setPdfFiles] = useState<PdfFile[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);
  const [draggedIndex, setDraggedIndex] = useState<number | null>(null);
  const { toast } = useToast();

  const loadPdfInfo = async (file: File): Promise<PdfFile> => {
    const arrayBuffer = await file.arrayBuffer();
    const pdfDoc = await PDFDocument.load(arrayBuffer);
    return {
      id: `${file.name}-${Date.now()}-${Math.random()}`,
      file,
      name: file.name,
      pageCount: pdfDoc.getPageCount(),
    };
  };

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;

    const pdfFilesArray = Array.from(files).filter(
      (file) => file.type === "application/pdf"
    );

    if (pdfFilesArray.length === 0) {
      toast({
        title: t('pdf_merger.toast_format_error'),
        description: t('pdf_merger.toast_format_error_desc'),
        variant: "destructive",
      });
      return;
    }

    try {
      const newPdfFiles = await Promise.all(pdfFilesArray.map(loadPdfInfo));
      setPdfFiles((prev) => [...prev, ...newPdfFiles]);
      toast({
        title: t('pdf_merger.toast_add_success'),
        description: t('pdf_merger.toast_add_success_desc', { count: newPdfFiles.length }),
      });
    } catch (error) {
      toast({
        title: t('pdf_merger.toast_read_error'),
        description: t('pdf_merger.toast_read_error_desc'),
        variant: "destructive",
      });
    }

    e.target.value = "";
  };

  const removeFile = (id: string) => {
    setPdfFiles((prev) => prev.filter((f) => f.id !== id));
  };

  const handleDragStart = (index: number) => {
    setDraggedIndex(index);
  };

  const handleDragOver = (e: React.DragEvent, index: number) => {
    e.preventDefault();
    if (draggedIndex === null || draggedIndex === index) return;

    const newFiles = [...pdfFiles];
    const draggedItem = newFiles[draggedIndex];
    newFiles.splice(draggedIndex, 1);
    newFiles.splice(index, 0, draggedItem);
    setPdfFiles(newFiles);
    setDraggedIndex(index);
  };

  const handleDragEnd = () => {
    setDraggedIndex(null);
  };

  const mergePdfs = useCallback(async () => {
    if (pdfFiles.length < 2) {
      toast({
        title: t('pdf_merger.toast_min_files'),
        description: t('pdf_merger.toast_min_files_desc'),
        variant: "destructive",
      });
      return;
    }

    setIsProcessing(true);

    try {
      const mergedPdf = await PDFDocument.create();

      for (const pdfFile of pdfFiles) {
        const arrayBuffer = await pdfFile.file.arrayBuffer();
        const pdfDoc = await PDFDocument.load(arrayBuffer);
        const copiedPages = await mergedPdf.copyPages(
          pdfDoc,
          pdfDoc.getPageIndices()
        );
        copiedPages.forEach((page) => mergedPdf.addPage(page));
      }

      const mergedPdfBytes = await mergedPdf.save();
      const blob = new Blob([new Uint8Array(mergedPdfBytes)], { type: "application/pdf" });
      const url = URL.createObjectURL(blob);

      const link = document.createElement("a");
      link.href = url;
      link.download = `merged-${Date.now()}.pdf`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);

      toast({
        title: t('pdf_merger.toast_success'),
        description: t('pdf_merger.toast_success_desc', { count: pdfFiles.length }),
      });
    } catch (error) {
      toast({
        title: t('pdf_merger.toast_merge_error'),
        description: t('pdf_merger.toast_merge_error_desc'),
        variant: "destructive",
      });
    } finally {
      setIsProcessing(false);
    }
  }, [pdfFiles, toast, t]);

  const totalPages = pdfFiles.reduce((sum, f) => sum + f.pageCount, 0);

  return (
    <ToolPageLayout
      toolNumber="04"
      title={t('pdf_merger.title')}
      subtitle={t('pdf_merger.subtitle')}
      description={t('pdf_merger.desc_page')}
    >
      <SEOHead 
        title={t('pdf_merger.meta.title')}
        description={t('pdf_merger.meta.description')}
        path="/tools/pdf"
        keywords={t('pdf_merger.meta.keywords', { returnObjects: true }) as string[]}
      />
      <div className="space-y-6">
        {/* Upload Area */}
        <Card className="animate-fade-in-up stagger-1 rounded-sm border-2 border-dashed border-border bg-card/50">
          <CardContent className="p-8">
            <label className="flex cursor-pointer flex-col items-center justify-center">
              <FileUp className="mb-4 h-12 w-12 text-muted-foreground" />
              <span className="mb-2 font-display text-lg font-medium text-foreground">
                {t('pdf_merger.card_upload_title')}
              </span>
              <span className="text-sm text-muted-foreground">
                {t('pdf_merger.card_upload_desc')}
              </span>
              <input
                type="file"
                accept="application/pdf"
                multiple
                onChange={handleFileChange}
                className="hidden"
              />
            </label>
          </CardContent>
        </Card>

        {/* File List */}
        {pdfFiles.length > 0 && (
          <div className="animate-fade-in-up stagger-2 space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="font-display font-medium text-foreground">
                {t('pdf_merger.list_title', { count: pdfFiles.length })}
              </h3>
              <span className="text-sm text-muted-foreground">
                {t('pdf_merger.list_total_pages', { count: totalPages })}
              </span>
            </div>

            <div className="space-y-2">
              {pdfFiles.map((pdfFile, index) => (
                <Card
                  key={pdfFile.id}
                  draggable
                  onDragStart={() => handleDragStart(index)}
                  onDragOver={(e) => handleDragOver(e, index)}
                  onDragEnd={handleDragEnd}
                  className={`cursor-move rounded-sm border-border bg-card transition-all ${
                    draggedIndex === index
                      ? "scale-95 opacity-50"
                      : "hover:border-primary/50"
                  }`}
                >
                  <CardContent className="flex items-center gap-3 p-3">
                    <GripVertical className="h-5 w-5 shrink-0 text-muted-foreground" />
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-sm bg-primary/10">
                      <Files className="h-5 w-5 text-primary" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate font-medium text-foreground">
                        {pdfFile.name}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        {pdfFile.pageCount} {t('pdf_merger.list_pages_suffix')}
                      </p>
                    </div>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => removeFile(pdfFile.id)}
                      className="shrink-0 text-muted-foreground hover:text-destructive"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </CardContent>
                </Card>
              ))}
            </div>

            <p className="text-center text-xs text-muted-foreground">
              {t('pdf_merger.hint_drag')}
            </p>
          </div>
        )}

        {/* Actions */}
        <div className="animate-fade-in-up stagger-3 flex gap-3">
          {pdfFiles.length > 0 && (
            <Button
              variant="outline"
              onClick={() => setPdfFiles([])}
              className="flex-1 rounded-sm"
            >
              {t('pdf_merger.btn_reset')}
            </Button>
          )}
          <Button
            onClick={mergePdfs}
            disabled={pdfFiles.length < 2 || isProcessing}
            className="flex-1 gap-2 rounded-sm"
          >
            <Download className="h-4 w-4" />
            {isProcessing ? t('pdf_merger.btn_processing') : t('pdf_merger.btn_merge')}
          </Button>
        </div>

        {/* Tips */}
        <div className="animate-fade-in-up stagger-4 border-l-2 border-primary bg-muted/30 p-4 pl-6">
          <h3 className="mb-2 font-display text-sm font-semibold uppercase tracking-wide text-foreground">{t('pdf_merger.tips_title')}</h3>
          <p className="text-sm text-muted-foreground">
            {t('pdf_merger.tips_desc')}
          </p>
        </div>
      </div>
    </ToolPageLayout>
  );
};

export default PdfMerger;
