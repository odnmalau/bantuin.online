import { useState } from "react";
import { useTranslation } from "react-i18next";
import ToolPageLayout from "@/components/ToolPageLayout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { Plus, Trash2, Download } from "lucide-react";
import { toast } from "sonner";
import { PDFDocument, rgb, StandardFonts } from "pdf-lib";
import { SEOHead } from "@/components/SEOHead";

interface InvoiceItem {
	id: string;
	name: string;
	quantity: number;
	price: number;
}

const InvoiceGenerator = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [shopName, setShopName] = useState("");
	const [shopAddress, setShopAddress] = useState("");
	const [customerName, setCustomerName] = useState("");
	const [invoiceDate, setInvoiceDate] = useState(
		new Date().toISOString().split("T")[0]
	);
	const [items, setItems] = useState<Array<InvoiceItem>>([
		{ id: crypto.randomUUID(), name: "", quantity: 1, price: 0 },
	]);
	const [discount, setDiscount] = useState(0);

	const addItem = (): void => {
		setItems([
			...items,
			{ id: crypto.randomUUID(), name: "", quantity: 1, price: 0 },
		]);
	};

	const removeItem = (id: string): void => {
		if (items.length === 1) return;
		setItems(items.filter((item) => item.id !== id));
	};

	const updateItem = (
		id: string,
		field: keyof InvoiceItem,
		value: string | number
	): void => {
		setItems(
			items.map((item) => (item.id === id ? { ...item, [field]: value } : item))
		);
	};

	const subtotal = items.reduce(
		(sum, item) => sum + item.quantity * item.price,
		0
	);
	const discountAmount = (subtotal * discount) / 100;
	const total = subtotal - discountAmount;

	const formatCurrency = (amount: number): string =>
		new Intl.NumberFormat(t("invoice.currency_locale"), {
			style: "currency",
			currency: t("invoice.currency_code"),
			minimumFractionDigits: 0,
		}).format(amount);

	const formatCurrencyShort = (amount: number): string =>
		new Intl.NumberFormat(t("invoice.currency_locale"), {
			minimumFractionDigits: 0,
		}).format(amount);

	const generatePDF = async (): Promise<void> => {
		if (!shopName.trim()) {
			toast.error(t("invoice.toast_shop_name_required"));
			return;
		}
		if (items.some((item) => !item.name.trim())) {
			toast.error(t("invoice.toast_item_name_required"));
			return;
		}

		try {
			const pdfDocument = await PDFDocument.create();
			const page = pdfDocument.addPage([400, 600]);
			const font = await pdfDocument.embedFont(StandardFonts.Helvetica);
			const fontBold = await pdfDocument.embedFont(StandardFonts.HelveticaBold);

			const black = rgb(0, 0, 0);
			const gray = rgb(0.5, 0.5, 0.5);

			let y = 560;
			const leftMargin = 30;
			const rightMargin = 370;

			// Header
			page.drawText(shopName.toUpperCase(), {
				x: leftMargin,
				y,
				font: fontBold,
				size: 16,
				color: black,
			});
			y -= 18;
			if (shopAddress) {
				page.drawText(shopAddress, {
					x: leftMargin,
					y,
					font,
					size: 9,
					color: gray,
				});
				y -= 14;
			}

			// Separator
			y -= 10;
			page.drawLine({
				start: { x: leftMargin, y },
				end: { x: rightMargin, y },
				thickness: 1,
				color: gray,
			});
			y -= 20;

			// Invoice Info
			page.drawText(t("invoice.pdf.title"), {
				x: leftMargin,
				y,
				font: fontBold,
				size: 12,
				color: black,
			});
			y -= 16;
			page.drawText(
				t("invoice.pdf.date") +
					new Date(invoiceDate || new Date().toISOString()).toLocaleDateString(
						t("invoice.currency_locale")
					),
				{ x: leftMargin, y, font, size: 9, color: gray }
			);
			y -= 12;
			if (customerName) {
				page.drawText(t("invoice.pdf.customer") + customerName, {
					x: leftMargin,
					y,
					font,
					size: 9,
					color: gray,
				});
				y -= 12;
			}
			y -= 10;

			// Table Header
			page.drawText(t("invoice.pdf.header_item"), {
				x: leftMargin,
				y,
				font: fontBold,
				size: 9,
				color: black,
			});
			page.drawText(t("invoice.pdf.header_qty"), {
				x: 220,
				y,
				font: fontBold,
				size: 9,
				color: black,
			});
			page.drawText(t("invoice.pdf.header_price"), {
				x: 260,
				y,
				font: fontBold,
				size: 9,
				color: black,
			});
			page.drawText(t("invoice.pdf.header_total"), {
				x: 320,
				y,
				font: fontBold,
				size: 9,
				color: black,
			});
			y -= 6;
			page.drawLine({
				start: { x: leftMargin, y },
				end: { x: rightMargin, y },
				thickness: 0.5,
				color: gray,
			});
			y -= 14;

			// Items
			for (const item of items) {
				const itemTotal = item.quantity * item.price;
				const truncatedName =
					item.name.length > 25 ? item.name.slice(0, 25) + "..." : item.name;
				page.drawText(truncatedName, {
					x: leftMargin,
					y,
					font,
					size: 9,
					color: black,
				});
				page.drawText(String(item.quantity), {
					x: 220,
					y,
					font,
					size: 9,
					color: black,
				});
				page.drawText(formatCurrencyShort(item.price), {
					x: 260,
					y,
					font,
					size: 9,
					color: black,
				});
				page.drawText(formatCurrencyShort(itemTotal), {
					x: 320,
					y,
					font,
					size: 9,
					color: black,
				});
				y -= 16;
			}

			// Separator
			y -= 4;
			page.drawLine({
				start: { x: leftMargin, y },
				end: { x: rightMargin, y },
				thickness: 0.5,
				color: gray,
			});
			y -= 16;

			// Subtotal
			page.drawText(t("invoice.pdf.subtotal"), {
				x: 250,
				y,
				font,
				size: 9,
				color: gray,
			});
			page.drawText(formatCurrencyShort(subtotal), {
				x: 320,
				y,
				font,
				size: 9,
				color: black,
			});
			y -= 14;

			// Discount
			if (discount > 0) {
				page.drawText(`${t("invoice.pdf.discount")} (${discount}%):`, {
					x: 250,
					y,
					font,
					size: 9,
					color: gray,
				});
				page.drawText("-" + formatCurrencyShort(discountAmount), {
					x: 320,
					y,
					font,
					size: 9,
					color: black,
				});
				y -= 14;
			}

			// Total
			page.drawLine({
				start: { x: 250, y: y + 4 },
				end: { x: rightMargin, y: y + 4 },
				thickness: 1,
				color: black,
			});
			y -= 4;
			page.drawText(t("invoice.pdf.total"), {
				x: 250,
				y,
				font: fontBold,
				size: 11,
				color: black,
			});
			page.drawText(formatCurrencyShort(total), {
				x: 320,
				y,
				font: fontBold,
				size: 11,
				color: black,
			});

			// Footer
			y = 40;
			page.drawText(t("invoice.pdf.footer_thanks"), {
				x: leftMargin,
				y,
				font,
				size: 8,
				color: gray,
			});
			y -= 12;
			page.drawText(t("invoice.pdf.footer_credit"), {
				x: leftMargin,
				y,
				font,
				size: 7,
				color: gray,
			});

			const pdfBytes = await pdfDocument.save();
			const blob = new Blob([pdfBytes as BlobPart], {
				type: "application/pdf",
			});
			const url = URL.createObjectURL(blob);
			const a = document.createElement("a");
			a.href = url;
			a.download =
				"nota-" +
				shopName.replace(/\s+/g, "-").toLowerCase() +
				"-" +
				invoiceDate +
				".pdf";
			a.click();
			URL.revokeObjectURL(url);

			toast.success(t("invoice.toast_pdf_success"));
		} catch {
			toast.error(t("invoice.toast_pdf_error"));
		}
	};

	return (
		<ToolPageLayout
			description={t("invoice.desc_page")}
			subtitle={t("invoice.subtitle")}
			title={t("invoice.title")}
			toolNumber="16"
		>
			<SEOHead
				description={t("invoice.meta.description")}
				path="/tools/invoice-generator"
				title={t("invoice.meta.title")}
				keywords={
					t("invoice.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="mx-auto max-w-2xl space-y-6">
				{/* Shop Info */}
				<Card className="p-6 space-y-4">
					<h3 className="font-semibold text-foreground">
						{t("invoice.card_shop_info")}
					</h3>
					<div className="grid gap-4 sm:grid-cols-2">
						<div className="space-y-2">
							<Label htmlFor="shopName">{t("invoice.label_shop_name")}</Label>
							<Input
								id="shopName"
								placeholder={t("invoice.placeholder_shop_name")}
								value={shopName}
								onChange={(
									event: React.ChangeEvent<HTMLInputElement>
								): void => {
									setShopName(event.target.value);
								}}
							/>
						</div>
						<div className="space-y-2">
							<Label htmlFor="shopAddress">{t("invoice.label_address")}</Label>
							<Input
								id="shopAddress"
								placeholder={t("invoice.placeholder_address")}
								value={shopAddress}
								onChange={(
									event: React.ChangeEvent<HTMLInputElement>
								): void => {
									setShopAddress(event.target.value);
								}}
							/>
						</div>
					</div>
					<div className="grid gap-4 sm:grid-cols-2">
						<div className="space-y-2">
							<Label htmlFor="customerName">
								{t("invoice.label_customer_name")}
							</Label>
							<Input
								id="customerName"
								placeholder={t("invoice.placeholder_customer_name")}
								value={customerName}
								onChange={(
									event: React.ChangeEvent<HTMLInputElement>
								): void => {
									setCustomerName(event.target.value);
								}}
							/>
						</div>
						<div className="space-y-2">
							<Label htmlFor="invoiceDate">{t("invoice.label_date")}</Label>
							<Input
								id="invoiceDate"
								type="date"
								value={invoiceDate}
								onChange={(
									event: React.ChangeEvent<HTMLInputElement>
								): void => {
									setInvoiceDate(event.target.value);
								}}
							/>
						</div>
					</div>
				</Card>

				{/* Items */}
				<Card className="p-6 space-y-4">
					<div className="flex items-center justify-between">
						<h3 className="font-semibold text-foreground">
							{t("invoice.card_items")}
						</h3>
						<Button size="sm" variant="outline" onClick={addItem}>
							<Plus className="mr-2 h-4 w-4" />
							{t("invoice.btn_add_item")}
						</Button>
					</div>
					<div className="space-y-3">
						{items.map((item) => (
							<div key={item.id} className="flex gap-2 items-end">
								<div className="flex-1 space-y-1">
									<Label className="text-xs text-muted-foreground">
										{t("invoice.label_item_name")}
									</Label>
									<Input
										placeholder={t("invoice.placeholder_item_name")}
										value={item.name}
										onChange={(
											event: React.ChangeEvent<HTMLInputElement>
										): void => {
											updateItem(item.id, "name", event.target.value);
										}}
									/>
								</div>
								<div className="w-20 space-y-1">
									<Label className="text-xs text-muted-foreground">
										{t("invoice.label_qty")}
									</Label>
									<Input
										min={1}
										type="number"
										value={item.quantity}
										onChange={(
											event: React.ChangeEvent<HTMLInputElement>
										): void => {
											updateItem(
												item.id,
												"quantity",
												parseInt(event.target.value) || 1
											);
										}}
									/>
								</div>
								<div className="w-28 space-y-1">
									<Label className="text-xs text-muted-foreground">
										{t("invoice.label_price")}
									</Label>
									<Input
										min={0}
										type="number"
										value={item.price}
										onChange={(
											event: React.ChangeEvent<HTMLInputElement>
										): void => {
											updateItem(
												item.id,
												"price",
												parseInt(event.target.value) || 0
											);
										}}
									/>
								</div>
								<Button
									className="text-destructive hover:text-destructive"
									disabled={items.length === 1}
									size="icon"
									variant="ghost"
									onClick={(): void => {
										removeItem(item.id);
									}}
								>
									<Trash2 className="h-4 w-4" />
								</Button>
							</div>
						))}
					</div>
				</Card>

				{/* Summary */}
				<Card className="p-6 space-y-4">
					<h3 className="font-semibold text-foreground">
						{t("invoice.card_summary")}
					</h3>
					<div className="space-y-2">
						<div className="flex justify-between text-sm">
							<span className="text-muted-foreground">
								{t("invoice.label_subtotal")}
							</span>
							<span className="text-foreground">
								{formatCurrency(subtotal)}
							</span>
						</div>
						<div className="flex items-center justify-between gap-4">
							<span className="text-sm text-muted-foreground">
								{t("invoice.label_discount")}
							</span>
							<Input
								className="w-20 text-right"
								max={100}
								min={0}
								type="number"
								value={discount}
								onChange={(
									event: React.ChangeEvent<HTMLInputElement>
								): void => {
									setDiscount(
										Math.min(
											100,
											Math.max(0, parseInt(event.target.value) || 0)
										)
									);
								}}
							/>
						</div>
						{discount > 0 && (
							<div className="flex justify-between text-sm">
								<span className="text-muted-foreground">
									{t("invoice.label_discount_amount")}
								</span>
								<span className="text-destructive">
									-{formatCurrency(discountAmount)}
								</span>
							</div>
						)}
						<Separator />
						<div className="flex justify-between text-lg font-bold">
							<span className="text-foreground">
								{t("invoice.label_total")}
							</span>
							<span className="text-primary">{formatCurrency(total)}</span>
						</div>
					</div>

					<div className="flex gap-3 pt-2">
						<Button className="flex-1" size="lg" onClick={generatePDF}>
							<Download className="mr-2 h-4 w-4" />
							{t("invoice.btn_download_pdf")}
						</Button>
					</div>
				</Card>
			</div>
		</ToolPageLayout>
	);
};

export default InvoiceGenerator;
