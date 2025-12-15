import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { ThemeProvider } from "next-themes";
import { HelmetProvider } from "react-helmet-async";
import { lazy, Suspense } from "react";
import Index from "./pages/Index";
import NotFound from "./pages/NotFound";
import InstallPrompt from "./components/InstallPrompt";
import OfflineIndicator from "./components/OfflineIndicator";

// Lazy load heavy tool pages for better LCP
const WhatsAppGenerator = lazy(() => import("./pages/tools/WhatsAppGenerator"));
const ImageCompressor = lazy(() => import("./pages/tools/ImageCompressor"));
const PasFoto = lazy(() => import("./pages/tools/PasFoto"));
const PdfMerger = lazy(() => import("./pages/tools/PdfMerger"));
const QRCodeGenerator = lazy(() => import("./pages/tools/QRCodeGenerator"));
const TextCaseConverter = lazy(() => import("./pages/tools/TextCaseConverter"));
const WordCounter = lazy(() => import("./pages/tools/WordCounter"));
const AgeCalculator = lazy(() => import("./pages/tools/AgeCalculator"));
const JsonFormatter = lazy(() => import("./pages/tools/JsonFormatter"));
const ImageToBase64 = lazy(() => import("./pages/tools/ImageToBase64"));
const ScreenshotToPdf = lazy(() => import("./pages/tools/ScreenshotToPdf"));
const PasswordGenerator = lazy(() => import("./pages/tools/PasswordGenerator"));
const ColorPicker = lazy(() => import("./pages/tools/ColorPicker"));
const LoremIpsum = lazy(() => import("./pages/tools/LoremIpsum"));
const UnitConverter = lazy(() => import("./pages/tools/UnitConverter"));
const InvoiceGenerator = lazy(() => import("./pages/tools/InvoiceGenerator"));
const RandomPicker = lazy(() => import("./pages/tools/RandomPicker"));
const Terbilang = lazy(() => import("./pages/tools/Terbilang"));
const SignaturePad = lazy(() => import("./pages/tools/SignaturePad"));
const ImageConverter = lazy(() => import("./pages/tools/ImageConverter"));
const Watermark = lazy(() => import("./pages/tools/Watermark"));
const DiffChecker = lazy(() => import("./pages/tools/DiffChecker"));

// Minimal loading fallback to prevent CLS
const PageLoader = () => (
  <div className="flex min-h-screen items-center justify-center">
    <div className="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
  </div>
);

const queryClient = new QueryClient();

const App = () => (
  <HelmetProvider>
    <ThemeProvider attribute="class" defaultTheme="system" storageKey="bantuin-theme">
      <QueryClientProvider client={queryClient}>
        <TooltipProvider>
          <Toaster />
          <Sonner />
          <OfflineIndicator />
          <InstallPrompt />
          <BrowserRouter>
            <Suspense fallback={<PageLoader />}>
              <Routes>
                <Route path="/" element={<Index />} />
                <Route path="/tools/whatsapp" element={<WhatsAppGenerator />} />
                <Route path="/tools/compress" element={<ImageCompressor />} />
                <Route path="/tools/pas-foto" element={<PasFoto />} />
                <Route path="/tools/pdf-merge" element={<PdfMerger />} />
                <Route path="/tools/qr-code" element={<QRCodeGenerator />} />
                <Route path="/tools/text-case" element={<TextCaseConverter />} />
                <Route path="/tools/word-counter" element={<WordCounter />} />
                <Route path="/tools/age-calculator" element={<AgeCalculator />} />
                <Route path="/tools/json-formatter" element={<JsonFormatter />} />
                <Route path="/tools/image-to-base64" element={<ImageToBase64 />} />
                <Route path="/tools/screenshot-to-pdf" element={<ScreenshotToPdf />} />
                <Route path="/tools/password-generator" element={<PasswordGenerator />} />
                <Route path="/tools/color-picker" element={<ColorPicker />} />
                <Route path="/tools/lorem-ipsum" element={<LoremIpsum />} />
                <Route path="/tools/unit-converter" element={<UnitConverter />} />
                <Route path="/tools/invoice-generator" element={<InvoiceGenerator />} />
                <Route path="/tools/random-picker" element={<RandomPicker />} />
                <Route path="/tools/terbilang" element={<Terbilang />} />
                <Route path="/tools/signature-pad" element={<SignaturePad />} />
                <Route path="/tools/image-converter" element={<ImageConverter />} />
                <Route path="/tools/watermark" element={<Watermark />} />
                <Route path="/tools/diff-checker" element={<DiffChecker />} />
                {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
                <Route path="*" element={<NotFound />} />
              </Routes>
            </Suspense>
          </BrowserRouter>
        </TooltipProvider>
      </QueryClientProvider>
    </ThemeProvider>
  </HelmetProvider>
);

export default App;
