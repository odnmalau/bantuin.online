import { useState, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { Link } from "react-router-dom";

import ToolPageLayout from "@/components/ToolPageLayout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Copy, Check, Receipt, ArrowRight } from "lucide-react";
import { toast } from "sonner";
import { SEOHead } from "@/components/SEOHead";

type CaseFormat = "title" | "upper" | "lower";

const satuan = [
	"",
	"Satu",
	"Dua",
	"Tiga",
	"Empat",
	"Lima",
	"Enam",
	"Tujuh",
	"Delapan",
	"Sembilan",
];
const belasan = [
	"Sepuluh",
	"Sebelas",
	"Dua Belas",
	"Tiga Belas",
	"Empat Belas",
	"Lima Belas",
	"Enam Belas",
	"Tujuh Belas",
	"Delapan Belas",
	"Sembilan Belas",
];

const convertToWords = (number_: number): string => {
	if (number_ === 0) return "Nol";
	if (number_ < 0) return "Minus " + convertToWords(Math.abs(number_));

	if (number_ < 10) return satuan[number_]!;
	if (number_ < 20) return belasan[number_ - 10]!;
	if (number_ < 100) {
		const tens = Math.floor(number_ / 10);
		const ones = number_ % 10;
		return satuan[tens] + " Puluh" + (ones > 0 ? " " + satuan[ones] : "");
	}
	if (number_ < 200)
		return (
			"Seratus" + (number_ % 100 > 0 ? " " + convertToWords(number_ % 100) : "")
		);
	if (number_ < 1000) {
		const hundreds = Math.floor(number_ / 100);
		const remainder = number_ % 100;
		return (
			satuan[hundreds] +
			" Ratus" +
			(remainder > 0 ? " " + convertToWords(remainder) : "")
		);
	}
	if (number_ < 2000)
		return (
			"Seribu" +
			(number_ % 1000 > 0 ? " " + convertToWords(number_ % 1000) : "")
		);
	if (number_ < 1000000) {
		const thousands = Math.floor(number_ / 1000);
		const remainder = number_ % 1000;
		return (
			convertToWords(thousands) +
			" Ribu" +
			(remainder > 0 ? " " + convertToWords(remainder) : "")
		);
	}
	if (number_ < 1000000000) {
		const millions = Math.floor(number_ / 1000000);
		const remainder = number_ % 1000000;
		return (
			convertToWords(millions) +
			" Juta" +
			(remainder > 0 ? " " + convertToWords(remainder) : "")
		);
	}
	if (number_ < 1000000000000) {
		const billions = Math.floor(number_ / 1000000000);
		const remainder = number_ % 1000000000;
		return (
			convertToWords(billions) +
			" Miliar" +
			(remainder > 0 ? " " + convertToWords(remainder) : "")
		);
	}
	if (number_ < 1000000000000000) {
		const trillions = Math.floor(number_ / 1000000000000);
		const remainder = number_ % 1000000000000;
		return (
			convertToWords(trillions) +
			" Triliun" +
			(remainder > 0 ? " " + convertToWords(remainder) : "")
		);
	}

	return "Angka terlalu besar";
};

const formatCase = (text: string, format: CaseFormat): string => {
	switch (format) {
		case "upper":
			return text.toUpperCase();
		case "lower":
			return text.toLowerCase();
		case "title":
		default:
			return text;
	}
};

const formatNumber = (value: string): string => {
	const number_ = value.replace(/\D/g, "");
	return number_.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
};

const Terbilang = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [inputValue, setInputValue] = useState("");
	const [addRupiah, setAddRupiah] = useState(true);
	const [caseFormat, setCaseFormat] = useState<CaseFormat>("title");
	const [copied, setCopied] = useState(false);

	const numericValue = useMemo(() => {
		return parseInt(inputValue.replace(/\D/g, "")) || 0;
	}, [inputValue]);

	const result = useMemo(() => {
		if (numericValue === 0 && inputValue.replace(/\D/g, "").length === 0) {
			return "";
		}
		let text = convertToWords(numericValue);
		if (addRupiah) {
			text += " Rupiah";
		}
		return formatCase(text, caseFormat);
	}, [numericValue, addRupiah, caseFormat, inputValue]);

	const handleInputChange = (
		event: React.ChangeEvent<HTMLInputElement>
	): void => {
		const formatted = formatNumber(event.target.value);
		setInputValue(formatted);
	};

	const handleCopy = async (): Promise<void> => {
		if (!result) return;
		await navigator.clipboard.writeText(result);
		setCopied(true);
		toast.success(t("terbilang.toast_copy"));
		setTimeout((): void => {
			setCopied(false);
		}, 2000);
	};

	const handleClear = (): void => {
		setInputValue("");
	};

	return (
		<ToolPageLayout
			description={t("tool_items.terbilang.desc")}
			subtitle={t("terbilang.subtitle")}
			title={t("tool_items.terbilang.title")}
			toolNumber="18"
		>
			<SEOHead
				description={t("terbilang.meta.description")}
				path="/tools/terbilang"
				title={t("terbilang.meta.title")}
				keywords={
					t("terbilang.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="mx-auto max-w-2xl space-y-6">
				{/* Input Section */}
				<div className="space-y-4 rounded-lg border border-border bg-card p-6">
					<div className="flex items-center justify-between">
						<Label className="text-base font-medium" htmlFor="amount">
							{t("terbilang.label_input")}
						</Label>
						{inputValue && (
							<Button size="sm" variant="ghost" onClick={handleClear}>
								{t("terbilang.btn_clear")}
							</Button>
						)}
					</div>
					<div className="relative">
						<span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground font-medium">
							Rp
						</span>
						<Input
							className="pl-12 text-2xl font-semibold h-14"
							id="amount"
							inputMode="numeric"
							placeholder="0"
							type="text"
							value={inputValue}
							onChange={handleInputChange}
						/>
					</div>
					<p className="text-sm text-muted-foreground">
						{t("terbilang.label_value")}{" "}
						<span className="font-semibold text-foreground">
							{numericValue.toLocaleString("id-ID")}
						</span>
					</p>
				</div>

				{/* Options */}
				<div className="grid gap-4 rounded-lg border border-border bg-card p-6 sm:grid-cols-2">
					<div className="flex items-center justify-between rounded-lg border border-border p-4">
						<Label className="cursor-pointer" htmlFor="addRupiah">
							{t("terbilang.label_rupiah")}
						</Label>
						<Switch
							checked={addRupiah}
							id="addRupiah"
							onCheckedChange={setAddRupiah}
						/>
					</div>
					<div className="space-y-3">
						<Label>{t("terbilang.label_format")}</Label>
						<RadioGroup
							className="flex gap-4"
							value={caseFormat}
							onValueChange={(v: string): void => {
								setCaseFormat(v as CaseFormat);
							}}
						>
							<div className="flex items-center space-x-2">
								<RadioGroupItem id="title" value="title" />
								<Label className="cursor-pointer font-normal" htmlFor="title">
									{t("terbilang.format_title")}
								</Label>
							</div>
							<div className="flex items-center space-x-2">
								<RadioGroupItem id="upper" value="upper" />
								<Label className="cursor-pointer font-normal" htmlFor="upper">
									{t("terbilang.format_upper")}
								</Label>
							</div>
							<div className="flex items-center space-x-2">
								<RadioGroupItem id="lower" value="lower" />
								<Label className="cursor-pointer font-normal" htmlFor="lower">
									{t("terbilang.format_lower")}
								</Label>
							</div>
						</RadioGroup>
					</div>
				</div>

				{/* Result */}
				<div className="rounded-lg border border-border bg-card p-6">
					<div className="mb-4 flex items-center justify-between">
						<Label className="text-base font-medium">
							{t("terbilang.label_result")}
						</Label>
						<Button
							disabled={!result}
							size="sm"
							variant="outline"
							onClick={handleCopy}
						>
							{copied ? (
								<>
									<Check className="mr-2 h-4 w-4" />
									{t("terbilang.copied")}
								</>
							) : (
								<>
									<Copy className="mr-2 h-4 w-4" />
									{t("terbilang.btn_copy")}
								</>
							)}
						</Button>
					</div>
					<div className="min-h-[80px] rounded-lg bg-secondary/50 p-4">
						{result ? (
							<p className="text-lg font-medium leading-relaxed text-foreground">
								{result}
							</p>
						) : (
							<p className="text-muted-foreground">
								{t("terbilang.placeholder_result")}
							</p>
						)}
					</div>
				</div>

				{/* Quick Examples */}
				<div className="rounded-lg border border-border bg-card p-6">
					<Label className="mb-4 block text-base font-medium">
						{t("terbilang.label_examples")}
					</Label>
					<div className="flex flex-wrap gap-2">
						{["100.000", "500.000", "1.000.000", "2.500.000", "10.000.000"].map(
							(value) => (
								<Button
									key={value}
									size="sm"
									variant="outline"
									onClick={(): void => {
										setInputValue(value);
									}}
								>
									{value}
								</Button>
							)
						)}
					</div>
				</div>

				{/* Link to Invoice Generator */}
				<Link
					className="flex items-center justify-between rounded-lg border border-border bg-card p-4 transition-colors hover:bg-secondary/50"
					to="/tools/invoice-generator"
				>
					<div className="flex items-center gap-3">
						<Receipt className="h-5 w-5 text-primary" />
						<div>
							<p className="font-medium">{t("terbilang.link_invoice_title")}</p>
							<p className="text-sm text-muted-foreground">
								{t("terbilang.link_invoice_desc")}
							</p>
						</div>
					</div>
					<ArrowRight className="h-5 w-5 text-muted-foreground" />
				</Link>
			</div>
		</ToolPageLayout>
	);
};

export default Terbilang;
