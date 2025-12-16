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
const PageLoader = (): React.JSX.Element => (
	<div className="flex min-h-screen items-center justify-center">
		<div className="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
	</div>
);

const queryClient = new QueryClient();

const App = (): React.JSX.Element => (
	<HelmetProvider>
		<ThemeProvider
			attribute="class"
			defaultTheme="system"
			storageKey="bantuin-theme"
		>
			<QueryClientProvider client={queryClient}>
				<TooltipProvider>
					<Toaster />
					<Sonner />
					<OfflineIndicator />
					<InstallPrompt />
					<BrowserRouter>
						<Suspense fallback={<PageLoader />}>
							<Routes>
								<Route element={<Index />} path="/" />
								<Route element={<WhatsAppGenerator />} path="/tools/whatsapp" />
								<Route element={<ImageCompressor />} path="/tools/compress" />
								<Route element={<PasFoto />} path="/tools/pas-foto" />
								<Route element={<PdfMerger />} path="/tools/pdf-merge" />
								<Route element={<QRCodeGenerator />} path="/tools/qr-code" />
								<Route
									element={<TextCaseConverter />}
									path="/tools/text-case"
								/>
								<Route element={<WordCounter />} path="/tools/word-counter" />
								<Route
									element={<AgeCalculator />}
									path="/tools/age-calculator"
								/>
								<Route
									element={<JsonFormatter />}
									path="/tools/json-formatter"
								/>
								<Route
									element={<ImageToBase64 />}
									path="/tools/image-to-base64"
								/>
								<Route
									element={<ScreenshotToPdf />}
									path="/tools/screenshot-to-pdf"
								/>
								<Route
									element={<PasswordGenerator />}
									path="/tools/password-generator"
								/>
								<Route element={<ColorPicker />} path="/tools/color-picker" />
								<Route element={<LoremIpsum />} path="/tools/lorem-ipsum" />
								<Route
									element={<UnitConverter />}
									path="/tools/unit-converter"
								/>
								<Route
									element={<InvoiceGenerator />}
									path="/tools/invoice-generator"
								/>
								<Route element={<RandomPicker />} path="/tools/random-picker" />
								<Route element={<Terbilang />} path="/tools/terbilang" />
								<Route element={<SignaturePad />} path="/tools/signature-pad" />
								<Route
									element={<ImageConverter />}
									path="/tools/image-converter"
								/>
								<Route element={<Watermark />} path="/tools/watermark" />
								<Route element={<DiffChecker />} path="/tools/diff-checker" />
								{/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
								<Route element={<NotFound />} path="*" />
							</Routes>
						</Suspense>
					</BrowserRouter>
				</TooltipProvider>
			</QueryClientProvider>
		</ThemeProvider>
	</HelmetProvider>
);

export default App;
