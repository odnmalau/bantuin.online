import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Button } from "@/components/ui/button";
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Share2, MessageCircle, Twitter, Link, Check } from "lucide-react";
import { toast } from "sonner";

interface ShareButtonProps {
	title: string;
	description?: string;
}

const ShareButton = ({
	title,
	description,
}: ShareButtonProps): React.JSX.Element => {
	const { t } = useTranslation();
	const [copied, setCopied] = useState(false);
	const url = window.location.href;
	const text = description ? `${title} - ${description}` : title;

	const shareToWhatsApp = (): void => {
		const waUrl = `https://wa.me/?text=${encodeURIComponent(`${text}\n${url}`)}`;
		window.open(waUrl, "_blank", "noopener,noreferrer");
	};

	const shareToTwitter = (): void => {
		const twitterUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`;
		window.open(twitterUrl, "_blank", "noopener,noreferrer");
	};

	const copyLink = async (): Promise<void> => {
		await navigator.clipboard.writeText(url);
		setCopied(true);
		toast.success(t("common.link_copied"));
		setTimeout(() => {
			setCopied(false);
		}, 2000);
	};

	const handleNativeShare = async (): Promise<void> => {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment
		const nav = navigator as any;
		// eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
		if (nav.share) {
			try {
				// eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
				await nav.share({
					title,
					text: description,
					url,
				});
			} catch {
				// User cancelled or error
			}
		}
	};

	// eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access
	if ((navigator as any).share) {
		return (
			<Button size="sm" variant="outline" onClick={handleNativeShare}>
				<Share2 className="mr-2 h-4 w-4" />
				{t("common.share")}
			</Button>
		);
	}

	return (
		<DropdownMenu>
			<DropdownMenuTrigger asChild>
				<Button size="sm" variant="outline">
					<Share2 className="mr-2 h-4 w-4" />
					{t("common.share")}
				</Button>
			</DropdownMenuTrigger>
			<DropdownMenuContent align="end" className="w-48 bg-popover">
				<DropdownMenuItem className="cursor-pointer" onClick={shareToWhatsApp}>
					<MessageCircle className="mr-2 h-4 w-4 text-green-500" />
					WhatsApp
				</DropdownMenuItem>
				<DropdownMenuItem className="cursor-pointer" onClick={shareToTwitter}>
					<Twitter className="mr-2 h-4 w-4 text-sky-500" />
					Twitter / X
				</DropdownMenuItem>
				<DropdownMenuItem className="cursor-pointer" onClick={copyLink}>
					{copied ? (
						<Check className="mr-2 h-4 w-4 text-green-500" />
					) : (
						<Link className="mr-2 h-4 w-4" />
					)}
					{copied ? t("common.copied") : t("common.copy_link")}
				</DropdownMenuItem>
			</DropdownMenuContent>
		</DropdownMenu>
	);
};

export default ShareButton;
