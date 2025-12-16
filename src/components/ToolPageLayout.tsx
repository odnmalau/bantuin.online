// ToolPageLayout - Editorial Clean design wrapper for tool pages
import { Link } from "react-router-dom";
import { ArrowLeft } from "lucide-react";
import { ReactNode, forwardRef } from "react";
import ShareButton from "./ShareButton";
import Footer from "@/components/Footer";
import Header from "@/components/Header";
import { useTranslation } from "react-i18next";

export interface ToolPageLayoutProps {
  children: ReactNode;
  toolNumber: string;
  title: string;
  subtitle: string;
  description: string;
}

const ToolPageLayout = forwardRef<HTMLDivElement, ToolPageLayoutProps>(
  ({ children, toolNumber, title, subtitle, description }, ref) => {
    
    const { t } = useTranslation();
    
    // Custom Left Nav: Back Button
    const backButton = (
        <Link
            to="/"
            className="group flex items-center gap-2 pl-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
        >
            <div className="flex h-8 w-8 items-center justify-center rounded-full border border-border bg-background transition-transform group-hover:-translate-x-1">
                <ArrowLeft className="h-4 w-4" />
            </div>
            <span className="hidden sm:inline">{t('common.back')}</span>
        </Link>
    );

    // Custom Right Action: Share Button
    const shareAction = (
        <ShareButton title={title} description={description} />
    );

    return (
      <div ref={ref} className="min-h-screen bg-background flex flex-col">
        
        {/* Dynamic Header - Title Removed, Back Button shifted */}
        <Header navLeft={backButton} actionRight={shareAction} />
        
        {/* Main Content with top padding for Fixed Header */}
        <main className="container mx-auto px-4 pt-24 pb-8 md:pt-32 md:pb-12 flex-1">
          
          {/* Detailed Title Section (Still useful for context, but simplified) */}
          <div className="mx-auto mb-12 max-w-2xl text-center">
             <div className="mb-4 flex justify-center">
                <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">{subtitle}</span>
             </div>
             <h1 className="font-display text-3xl font-bold tracking-tight text-foreground md:text-5xl">
               {title}
             </h1>
             <p className="mt-4 text-muted-foreground">{description}</p>
          </div>

          {/* Tool Interaction Area */}
          <div className="mx-auto max-w-3xl">
              {children}
          </div>

        </main>
        
        <Footer />
      </div>
    );
  }
);

ToolPageLayout.displayName = "ToolPageLayout";

export default ToolPageLayout;
