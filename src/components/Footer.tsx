const Footer = () => {
  return (
    <footer className="relative mt-20 w-full overflow-hidden">
      {/* Container for the Scene */}
      <div className="relative h-48 w-full md:h-64">
        
        {/* Gradient Fade from Content to Footer - Smooth Transition */}
        <div className="absolute inset-0 z-10 bg-gradient-to-b from-background via-background/50 to-transparent pointer-events-none" />
        
        {/* Sky is just the background color now for consistency */}
        <div className="absolute inset-0 bg-background" />

        {/* Layer 1: Farthest Wave - Very Subtle */}
        <div className="absolute bottom-0 h-48 w-[200%] animate-[wave_20s_linear_infinite] opacity-30">
           <svg className="h-full w-full text-muted-foreground/20" viewBox="0 0 1440 320" preserveAspectRatio="none" fill="currentColor">
              <path d="M0,192L48,197.3C96,203,192,213,288,229.3C384,245,480,267,576,250.7C672,235,768,181,864,181.3C960,181,1056,235,1152,234.7C1248,235,1344,181,1392,154.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z" />
           </svg>
        </div>

        {/* Layer 2: Middle Wave - Slightly darker */}
        <div className="absolute bottom-0 h-40 w-[200%] animate-[wave_15s_linear_infinite] opacity-40 translate-x-[-20%]">
           <svg className="h-full w-full text-muted-foreground/30" viewBox="0 0 1440 320" preserveAspectRatio="none" fill="currentColor">
              <path d="M0,128L48,144C96,160,192,192,288,186.7C384,181,480,139,576,133.3C672,128,768,160,864,181.3C960,203,1056,213,1152,208C1248,203,1344,181,1392,170.7L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z" />
           </svg>
        </div>

        {/* Layer 3: Closest Wave - Most visible but still subtle */}
        <div className="absolute bottom-0 h-32 w-[200%] animate-[wave_10s_linear_infinite] opacity-50 translate-x-[-10%]">
           <svg className="h-full w-full text-primary/10" viewBox="0 0 1440 320" preserveAspectRatio="none" fill="currentColor">
              <path d="M0,64L48,80C96,96,192,128,288,128C384,128,480,96,576,106.7C672,117,768,171,864,197.3C960,224,1056,224,1152,213.3C1248,203,1344,181,1392,170.7L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z" />
           </svg>
        </div>

      </div>
      
      {/* Inline Styles for Wave Animation to avoid external css dependencies */}
      <style>
        {`
          @keyframes wave {
            0% { transform: translateX(0); }
            50% { transform: translateX(-25%); }
            100% { transform: translateX(0); }
          }
        `}
      </style>
    </footer>
  );
};

export default Footer;