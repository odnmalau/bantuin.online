import { Helmet } from 'react-helmet-async';

interface SEOHeadProps {
  title: string;
  description: string;
  path: string;
  keywords?: string[];
  ogImage?: string;
}

const BASE_URL = 'https://bantuin.online';

export const SEOHead = ({ 
  title, 
  description, 
  path, 
  keywords = [],
  ogImage = '/og-default.png'
}: SEOHeadProps) => {
  const fullTitle = `${title} - Bantuin.online`;
  const canonicalUrl = `${BASE_URL}${path}`;
  
  const structuredData = {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": title,
    "description": description,
    "url": canonicalUrl,
    "applicationCategory": "UtilityApplication",
    "operatingSystem": "Web Browser",
    "offers": {
      "@type": "Offer",
      "price": "0",
      "priceCurrency": "IDR"
    },
    "creator": {
      "@type": "Organization",
      "name": "Bantuin.online"
    }
  };

  return (
    <Helmet>
      <title>{fullTitle}</title>
      <meta name="description" content={description} />
      {keywords.length > 0 && (
        <meta name="keywords" content={keywords.join(', ')} />
      )}
      <link rel="canonical" href={canonicalUrl} />
      
      {/* OpenGraph */}
      <meta property="og:type" content="website" />
      <meta property="og:title" content={fullTitle} />
      <meta property="og:description" content={description} />
      <meta property="og:url" content={canonicalUrl} />
      <meta property="og:image" content={`${BASE_URL}${ogImage}`} />
      <meta property="og:site_name" content="Bantuin.online" />
      <meta property="og:locale" content="id_ID" />
      
      {/* Twitter Card */}
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={fullTitle} />
      <meta name="twitter:description" content={description} />
      <meta name="twitter:image" content={`${BASE_URL}${ogImage}`} />
      
      {/* Structured Data */}
      <script type="application/ld+json">
        {JSON.stringify(structuredData)}
      </script>
    </Helmet>
  );
};
