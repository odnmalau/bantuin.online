import { Helmet } from "react-helmet-async";
import { useTranslation } from "react-i18next";

interface SEOHeadProps {
	title: string;
	description: string;
	path: string;
	keywords?: Array<string>;
	ogImage?: string;
}

const BASE_URL = "https://bantuin.online";

export const SEOHead = ({
	title,
	description,
	path,
	keywords = [],
	ogImage = "/og-default.png",
}: SEOHeadProps): React.JSX.Element => {
	const fullTitle = `${title} - Bantuin.online`;
	const canonicalUrl = `${BASE_URL}${path}`;

	const structuredData = {
		"@context": "https://schema.org",
		"@type": "WebApplication",
		name: title,
		description: description,
		url: canonicalUrl,
		applicationCategory: "UtilityApplication",
		operatingSystem: "Web Browser",
		offers: {
			"@type": "Offer",
			price: "0",
			priceCurrency: "IDR",
		},
		creator: {
			"@type": "Organization",
			name: "Bantuin.online",
		},
	};

	const { i18n } = useTranslation();
	const currentLocale = i18n.language === "id" ? "id_ID" : "en_US";

	return (
		<Helmet>
			<title>{fullTitle}</title>
			<meta content={description} name="description" />
			{keywords.length > 0 && (
				<meta content={keywords.join(", ")} name="keywords" />
			)}
			<link href={canonicalUrl} rel="canonical" />

			{/* OpenGraph */}
			<meta content="website" property="og:type" />
			<meta content={fullTitle} property="og:title" />
			<meta content={description} property="og:description" />
			<meta content={canonicalUrl} property="og:url" />
			<meta content={`${BASE_URL}${ogImage}`} property="og:image" />
			<meta content="Bantuin.online" property="og:site_name" />
			<meta content={currentLocale} property="og:locale" />

			{/* Twitter Card */}
			<meta content="summary_large_image" name="twitter:card" />
			<meta content={fullTitle} name="twitter:title" />
			<meta content={description} name="twitter:description" />
			<meta content={`${BASE_URL}${ogImage}`} name="twitter:image" />

			{/* Structured Data */}
			<script type="application/ld+json">
				{JSON.stringify(structuredData)}
			</script>
		</Helmet>
	);
};
