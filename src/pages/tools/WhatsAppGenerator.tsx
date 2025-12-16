import { useState, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { Copy, ExternalLink, Check } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
	CardTitle,
} from "@/components/ui/card";
import { useToast } from "@/hooks/use-toast";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

const WhatsAppGenerator = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [phoneNumber, setPhoneNumber] = useState("");
	const [message, setMessage] = useState("");
	const [copied, setCopied] = useState(false);
	const { toast } = useToast();

	// Format phone number: replace leading 0 with 62
	const formattedPhone = useMemo((): string => {
		const cleaned = phoneNumber.replace(/\D/g, "");
		if (cleaned.startsWith("0")) {
			return "62" + cleaned.slice(1);
		}
		return cleaned;
	}, [phoneNumber]);

	// Generate WhatsApp link in real-time
	const whatsappLink = useMemo((): string => {
		if (!formattedPhone) return "";
		const encodedMessage = encodeURIComponent(message.trim());
		return `https://wa.me/${formattedPhone}${encodedMessage ? `?text=${encodedMessage}` : ""}`;
	}, [formattedPhone, message]);

	const handleCopyLink = async (): Promise<void> => {
		if (!whatsappLink) {
			toast({
				title: t("whatsapp.err_phone_empty"),
				description: t("whatsapp.err_phone_desc"),
				variant: "destructive",
			});
			return;
		}

		try {
			await navigator.clipboard.writeText(whatsappLink);
			setCopied(true);
			toast({
				title: t("common.copied"),
				description: t("whatsapp.label_result") + " " + t("common.success"),
			});
			setTimeout((): void => {
				setCopied(false);
			}, 2000);
		} catch {
			toast({
				title: t("whatsapp.err_copy_fail"),
				description: t("whatsapp.err_manual_copy"),
				variant: "destructive",
			});
		}
	};

	const handleTestLink = (): void => {
		if (!whatsappLink) {
			toast({
				title: t("whatsapp.err_phone_empty"),
				description: t("whatsapp.err_phone_desc"),
				variant: "destructive",
			});
			return;
		}
		window.open(whatsappLink, "_blank", "noopener,noreferrer");
	};

	return (
		<ToolPageLayout
			description={t("tool_items.whatsapp.desc")}
			subtitle="Generator"
			title={t("tool_items.whatsapp.title")}
			toolNumber="01"
		>
			<SEOHead
				description={t("whatsapp.meta.description")}
				path="/tools/whatsapp"
				title={t("whatsapp.meta.title")}
				keywords={
					t("whatsapp.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="space-y-8">
				<Card className="animate-fade-in-up stagger-1 rounded-sm border-border bg-card">
					<CardHeader>
						<CardTitle className="font-display text-xl">
							{t("whatsapp.title")}
						</CardTitle>
						<CardDescription>{t("whatsapp.desc")}</CardDescription>
					</CardHeader>
					<CardContent className="space-y-6">
						{/* Phone Number Input */}
						<div className="space-y-2">
							<Label className="font-medium" htmlFor="phone">
								{t("whatsapp.label_phone")}
							</Label>
							<Input
								className="rounded-sm border-border bg-background"
								id="phone"
								placeholder={t("whatsapp.placeholder_phone")}
								type="tel"
								value={phoneNumber}
								onChange={(
									event: React.ChangeEvent<HTMLInputElement>
								): void => {
									setPhoneNumber(event.target.value);
								}}
							/>
							{formattedPhone && (
								<p className="text-sm text-muted-foreground">
									{t("whatsapp.hint_phone")}{" "}
									<span className="font-medium text-primary">
										+{formattedPhone}
									</span>
								</p>
							)}
						</div>

						{/* Message Textarea */}
						<div className="space-y-2">
							<Label className="font-medium" htmlFor="message">
								{t("whatsapp.label_message")}
							</Label>
							<Textarea
								className="resize-none rounded-sm border-border bg-background"
								id="message"
								placeholder={t("whatsapp.placeholder_message")}
								rows={4}
								value={message}
								onChange={(
									event: React.ChangeEvent<HTMLTextAreaElement>
								): void => {
									setMessage(event.target.value);
								}}
							/>
						</div>

						{/* Generated Link Preview */}
						{whatsappLink && (
							<div className="space-y-2">
								<Label className="font-medium">
									{t("whatsapp.label_result")}
								</Label>
								<div className="rounded-sm border border-border bg-muted/50 p-3">
									<p className="break-all font-mono text-sm text-muted-foreground">
										{whatsappLink}
									</p>
								</div>
							</div>
						)}

						{/* Action Buttons */}
						<div className="flex flex-col gap-3 sm:flex-row">
							<Button
								className="flex-1 gap-2 rounded-sm"
								disabled={!formattedPhone}
								onClick={handleCopyLink}
							>
								{copied ? (
									<Check className="h-4 w-4" />
								) : (
									<Copy className="h-4 w-4" />
								)}
								{copied ? t("whatsapp.btn_copied") : t("whatsapp.btn_copy")}
							</Button>
							<Button
								className="flex-1 gap-2 rounded-sm"
								disabled={!formattedPhone}
								variant="outline"
								onClick={handleTestLink}
							>
								<ExternalLink className="h-4 w-4" />
								{t("whatsapp.btn_test")}
							</Button>
						</div>
					</CardContent>
				</Card>

				{/* Tips Section */}
				<div className="animate-fade-in-up stagger-2 border-l-2 border-primary bg-muted/30 p-4 pl-6">
					<h3 className="mb-2 font-display text-sm font-semibold uppercase tracking-wide text-foreground">
						{t("whatsapp.tips_title")}
					</h3>
					<ul className="space-y-1 text-sm text-muted-foreground">
						<li>{t("whatsapp.tips_1")}</li>
						<li>{t("whatsapp.tips_2")}</li>
						<li>{t("whatsapp.tips_3")}</li>
					</ul>
				</div>
			</div>
		</ToolPageLayout>
	);
};

export default WhatsAppGenerator;
