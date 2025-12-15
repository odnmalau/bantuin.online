import Header from "@/components/Header";
import ToolsGrid from "@/components/ToolsGrid";
import Footer from "@/components/Footer";

const Index = () => {
  return (
    <div className="flex min-h-screen flex-col bg-background">
      <Header />
      <main className="flex-1 pt-10 md:pt-10">
        <ToolsGrid />
      </main>
      <Footer />
    </div>
  );
};

export default Index;
