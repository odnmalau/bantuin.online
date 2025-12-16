import { useState, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { Cake, Calendar } from "lucide-react";

import { Input } from "@/components/ui/input";
import ToolPageLayout from "@/components/ToolPageLayout";
import { SEOHead } from "@/components/SEOHead";

const AgeCalculator = (): React.JSX.Element => {
	const { t } = useTranslation();
	const [birthDate, setBirthDate] = useState("");

	const result = useMemo(() => {
		if (!birthDate) return null;

		const birth = new Date(birthDate);
		const today = new Date();

		if (birth > today) return null;

		// Calculate age
		let years = today.getFullYear() - birth.getFullYear();
		let months = today.getMonth() - birth.getMonth();
		let days = today.getDate() - birth.getDate();

		if (days < 0) {
			months--;
			const lastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
			days += lastMonth.getDate();
		}

		if (months < 0) {
			years--;
			months += 12;
		}

		// Total days lived
		const totalDays = Math.floor(
			(today.getTime() - birth.getTime()) / (1000 * 60 * 60 * 24)
		);

		// Next birthday
		const nextBirthday = new Date(
			today.getFullYear(),
			birth.getMonth(),
			birth.getDate()
		);
		if (nextBirthday <= today) {
			nextBirthday.setFullYear(today.getFullYear() + 1);
		}
		const daysUntilBirthday = Math.ceil(
			(nextBirthday.getTime() - today.getTime()) / (1000 * 60 * 60 * 24)
		);

		// Western Zodiac
		const month = birth.getMonth() + 1;
		const day = birth.getDate();
		// Using t() inside loop or map might be inefficient if re-render frequent,
		// but here it is memoized. Better to construct the array using t().
		const zodiacSigns = [
			{
				name: t("age.zodiac_data.western.capricorn"),
				emoji: "♑",
				start: [1, 1],
				end: [1, 19],
			},
			{
				name: t("age.zodiac_data.western.aquarius"),
				emoji: "♒",
				start: [1, 20],
				end: [2, 18],
			},
			{
				name: t("age.zodiac_data.western.pisces"),
				emoji: "♓",
				start: [2, 19],
				end: [3, 20],
			},
			{
				name: t("age.zodiac_data.western.aries"),
				emoji: "♈",
				start: [3, 21],
				end: [4, 19],
			},
			{
				name: t("age.zodiac_data.western.taurus"),
				emoji: "♉",
				start: [4, 20],
				end: [5, 20],
			},
			{
				name: t("age.zodiac_data.western.gemini"),
				emoji: "♊",
				start: [5, 21],
				end: [6, 20],
			},
			{
				name: t("age.zodiac_data.western.cancer"),
				emoji: "♋",
				start: [6, 21],
				end: [7, 22],
			},
			{
				name: t("age.zodiac_data.western.leo"),
				emoji: "♌",
				start: [7, 23],
				end: [8, 22],
			},
			{
				name: t("age.zodiac_data.western.virgo"),
				emoji: "♍",
				start: [8, 23],
				end: [9, 22],
			},
			{
				name: t("age.zodiac_data.western.libra"),
				emoji: "♎",
				start: [9, 23],
				end: [10, 22],
			},
			{
				name: t("age.zodiac_data.western.scorpio"),
				emoji: "♏",
				start: [10, 23],
				end: [11, 21],
			},
			{
				name: t("age.zodiac_data.western.sagittarius"),
				emoji: "♐",
				start: [11, 22],
				end: [12, 21],
			},
			{
				name: t("age.zodiac_data.western.capricorn"),
				emoji: "♑",
				start: [12, 22],
				end: [12, 31],
			},
		];

		let westernZodiac = zodiacSigns[0]!;
		for (const sign of zodiacSigns) {
			const startM = sign.start[0]!;
			const startD = sign.start[1]!;
			const endM = sign.end[0]!;
			const endD = sign.end[1]!;
			if (
				(month === startM && day >= startD) ||
				(month === endM && day <= endD)
			) {
				westernZodiac = sign;
				break;
			}
		}

		// Chinese Zodiac (Shio)
		const chineseZodiacs = [
			{ name: t("age.zodiac_data.chinese.rat"), emoji: "🐀" },
			{ name: t("age.zodiac_data.chinese.ox"), emoji: "🐂" },
			{ name: t("age.zodiac_data.chinese.tiger"), emoji: "🐅" },
			{ name: t("age.zodiac_data.chinese.rabbit"), emoji: "🐇" },
			{ name: t("age.zodiac_data.chinese.dragon"), emoji: "🐉" },
			{ name: t("age.zodiac_data.chinese.snake"), emoji: "🐍" },
			{ name: t("age.zodiac_data.chinese.horse"), emoji: "🐴" },
			{ name: t("age.zodiac_data.chinese.goat"), emoji: "🐐" },
			{ name: t("age.zodiac_data.chinese.monkey"), emoji: "🐵" },
			{ name: t("age.zodiac_data.chinese.rooster"), emoji: "🐓" },
			{ name: t("age.zodiac_data.chinese.dog"), emoji: "🐕" },
			{ name: t("age.zodiac_data.chinese.pig"), emoji: "🐷" },
		];
		const offset = birth.getFullYear() - 4;
		const chineseZodiacIndex = ((offset % 12) + 12) % 12;
		const chineseZodiac = chineseZodiacs[chineseZodiacIndex]!;

		return {
			years,
			months,
			days,
			totalDays,
			daysUntilBirthday,
			westernZodiac,
			chineseZodiac,
		};
	}, [birthDate, t]);

	return (
		<ToolPageLayout
			description={t("tool_items.age_calculator.desc")}
			subtitle={t("age.subtitle")}
			title={t("tool_items.age_calculator.title")}
			toolNumber="08"
		>
			<SEOHead
				description={t("age.meta.description")}
				path="/tools/age"
				title={t("age.meta.title")}
				keywords={
					t("age.meta.keywords", { returnObjects: true }) as Array<string>
				}
			/>
			<div className="space-y-6">
				{/* Date Input */}
				<div className="space-y-2">
					<label className="text-sm font-medium text-foreground">
						{t("age.label_date")}
					</label>
					<Input
						className="font-body"
						max={new Date().toISOString().split("T")[0]}
						type="date"
						value={birthDate}
						onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
							setBirthDate(event.target.value);
						}}
					/>
				</div>

				{/* Results */}
				{result && (
					<div className="animate-fade-in space-y-4">
						{/* Main Age Display */}
						<div className="rounded-lg border border-primary/20 bg-primary/5 p-6 text-center">
							<p className="mb-2 text-sm text-muted-foreground">
								{t("age.res_age")}
							</p>
							<div className="flex items-center justify-center gap-4">
								<div>
									<span className="font-display text-4xl font-bold text-primary">
										{result.years}
									</span>
									<span className="ml-1 text-sm text-muted-foreground">
										{t("age.res_year")}
									</span>
								</div>
								<div>
									<span className="font-display text-2xl font-semibold text-foreground">
										{result.months}
									</span>
									<span className="ml-1 text-sm text-muted-foreground">
										{t("age.res_month")}
									</span>
								</div>
								<div>
									<span className="font-display text-2xl font-semibold text-foreground">
										{result.days}
									</span>
									<span className="ml-1 text-sm text-muted-foreground">
										{t("age.res_day")}
									</span>
								</div>
							</div>
						</div>

						{/* Stats Grid */}
						<div className="grid grid-cols-2 gap-3">
							<div className="rounded-lg border border-border bg-card p-4 text-center">
								<Calendar className="mx-auto mb-2 h-5 w-5 text-primary" />
								<p className="font-display text-xl font-bold text-foreground">
									{result.totalDays.toLocaleString()}
								</p>
								<p className="text-xs text-muted-foreground">
									{t("age.stat_life")}
								</p>
							</div>
							<div className="rounded-lg border border-border bg-card p-4 text-center">
								<Cake className="mx-auto mb-2 h-5 w-5 text-primary" />
								<p className="font-display text-xl font-bold text-foreground">
									{result.daysUntilBirthday}
								</p>
								<p className="text-xs text-muted-foreground">
									{t("age.stat_bday")}
								</p>
							</div>
						</div>

						{/* Zodiac */}
						<div className="rounded-lg border border-border bg-secondary/30 p-4">
							<h3 className="mb-3 font-display text-sm font-semibold text-foreground">
								{t("age.zodiac")}
							</h3>
							<div className="grid grid-cols-2 gap-4">
								<div className="flex items-center gap-3">
									<span className="text-2xl">{result.westernZodiac.emoji}</span>
									<div>
										<p className="font-medium text-foreground">
											{result.westernZodiac.name}
										</p>
										<p className="text-xs text-muted-foreground">
											{t("age.zodiac_w")}
										</p>
									</div>
								</div>
								<div className="flex items-center gap-3">
									<span className="text-2xl">{result.chineseZodiac.emoji}</span>
									<div>
										<p className="font-medium text-foreground">
											{result.chineseZodiac.name}
										</p>
										<p className="text-xs text-muted-foreground">
											{t("age.zodiac_c")}
										</p>
									</div>
								</div>
							</div>
						</div>
					</div>
				)}

				{/* Empty State */}
				{!result && (
					<div className="rounded-lg border border-dashed border-border p-8 text-center">
						<Cake className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
						<p className="text-sm text-muted-foreground">
							{t("age.empty_state")}
						</p>
					</div>
				)}
			</div>
		</ToolPageLayout>
	);
};

export default AgeCalculator;
