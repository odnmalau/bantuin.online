<?php

test('Geist theme exposes the documented foundation tokens', function () {
    $stylesheet = file_get_contents(resource_path('css/app.css'));

    expect($stylesheet)->not->toBeFalse();

    foreach ([
        '--breakpoint-sm: 25.0625rem;',
        '--breakpoint-md: 37.5625rem;',
        '--breakpoint-lg: 60.0625rem;',
        '--breakpoint-xl: 75rem;',
        '--breakpoint-2xl: 87.5rem;',
        '--container-7xl: 75rem;',
        '--text-heading-20: 20px;',
        '--text-button-14: 14px;',
        '--text-label-14: 14px;',
        '--text-copy-14: 14px;',
        '--color-background-200: var(--background-secondary);',
        '--background-secondary: #0a0a0a;',
        '--color-gray-alpha-400: var(--geist-gray-alpha-400);',
        '--shadow-popover: var(--shadow-popover-surface);',
        '--shadow-modal: var(--shadow-modal-surface);',
        '--ease-geist: cubic-bezier(0.175, 0.885, 0.32, 1.1);',
        '--card: #000000;',
        '@media (prefers-reduced-motion: reduce)',
    ] as $token) {
        expect($stylesheet)->toContain($token);
    }
});

test('fonts are self hosted from the official Vercel release', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
    $viteConfig = file_get_contents(base_path('vite.config.ts'));

    expect(resource_path('fonts/Geist[wght].woff2'))->toBeFile()
        ->and(resource_path('fonts/GeistMono[wght].woff2'))->toBeFile()
        ->and(resource_path('fonts/OFL.txt'))->toBeFile()
        ->and($package['dependencies'])->not->toHaveKey('@fontsource-variable/geist')
        ->not->toHaveKey('@fontsource-variable/geist-mono')
        ->and($viteConfig)->not->toContain('Instrument Sans')
        ->not->toContain('laravel-vite-plugin/fonts');
});

test('wide gamut colors mirror both design documents', function (string $designFile) {
    $design = file_get_contents(base_path($designFile));
    $stylesheet = file_get_contents(resource_path('css/app.css'));

    expect($design)->not->toBeFalse()
        ->and($stylesheet)->not->toBeFalse()
        ->and($stylesheet)->toContain("font-family: 'Geist Sans';")
        ->toContain("font-family: 'Geist Mono';")
        ->toContain("url('../fonts/Geist[wght].woff2')")
        ->toContain("url('../fonts/GeistMono[wght].woff2')")
        ->toContain('@media (color-gamut: p3)');

    preg_match_all(
        '/^\s{2}([a-z]+-\d+)-p3: "([^"]+)"$/m',
        $design,
        $matches,
        PREG_SET_ORDER,
    );

    expect($matches)->toHaveCount(70);

    foreach ($matches as [, $token, $value]) {
        expect($stylesheet)->toContain("--geist-{$token}: {$value};");
    }
})->with([
    'light theme' => 'design.md',
    'dark theme' => 'design.dark.md',
]);

test('core controls preserve the documented geometry and focus treatment', function () {
    $button = file_get_contents(resource_path('js/components/ui/button.tsx'));
    $input = file_get_contents(resource_path('js/components/ui/input.tsx'));
    $select = file_get_contents(resource_path('js/components/ui/select.tsx'));

    expect($button)
        ->not->toBeFalse()
        ->toContain('focus-visible:ring-2')
        ->toContain('focus-visible:ring-offset-2')
        ->toContain('"h-10 gap-2 px-2.5')
        ->toContain('"h-8 gap-1 rounded-md px-1.5')
        ->toContain('"h-12 gap-2 px-3.5 text-button-16')
        ->toContain('bg-red-800 text-white')
        ->not->toContain('focus-visible:ring-3');

    expect($input)
        ->not->toBeFalse()
        ->toContain('data-[size=default]:h-10')
        ->toContain('data-[size=sm]:h-8')
        ->toContain('data-[size=lg]:h-12')
        ->toContain('focus-visible:ring-offset-background')
        ->not->toContain('dark:bg-input/30');

    expect($select)
        ->not->toBeFalse()
        ->toContain('data-[size=default]:h-10')
        ->toContain('data-[size=sm]:h-8')
        ->toContain('data-[size=lg]:h-12')
        ->toContain('shadow-popover')
        ->not->toContain('dark:bg-input/30');
});

test('application forms compose shared controls', function (string $page) {
    $source = file_get_contents(resource_path($page));

    expect($source)->not->toBeFalse()
        ->not->toContain('<select')
        ->not->toContain('<textarea');
})->with([
    'team settings' => 'js/pages/settings/team.tsx',
    'support team' => 'js/pages/support/teams/show.tsx',
    'candidate exam' => 'js/pages/candidate/exam.tsx',
    'campaign details' => 'js/pages/admin/campaigns/show.tsx',
]);

test('candidate assessment surfaces use wide shadcn composition', function () {
    $exam = file_get_contents(resource_path('js/pages/candidate/exam.tsx'));
    $assessment = file_get_contents(resource_path('js/pages/candidate/assessments/show.tsx'));
    $header = file_get_contents(resource_path('js/components/app-header.tsx'));
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($exam)->not->toBeFalse()
        ->toContain('flex w-full flex-col gap-6 p-4')
        ->toContain('<Card className="gap-0 overflow-hidden">')
        ->toContain('bg-background-200 py-(--card-spacing)')
        ->toContain('Answer every question in this section before continuing.')
        ->toContain('Upload a PDF resume before starting this')
        ->not->toContain('<AlertTitle>Resume required</AlertTitle>')
        ->toContain('<ItemGroup')
        ->toContain('<Alert>')
        ->toContain('<FieldGroup')
        ->not->toContain('mx-auto max-w-4xl')
        ->not->toContain('Choose an exam')
        ->and($assessment)->not->toBeFalse()
        ->toContain('flex w-full flex-col gap-6 p-4')
        ->toContain('<Card className="gap-0 overflow-hidden">')
        ->toContain('className="overflow-hidden rounded-md border bg-background"')
        ->toContain('<ItemSeparator className="my-0" />')
        ->toContain('<ItemGroup')
        ->toContain('<Accordion')
        ->toContain('groupAnswersBySection')
        ->not->toContain('border-sidebar-border')
        ->and($header)->toContain('<header data-app-header>')
        ->and($styles)->toContain("html:fullscreen[data-secure-exam-active='true'] [data-app-header]");
});

test('campaign assessment authoring uses compact disclosure and item components', function () {
    $campaign = file_get_contents(resource_path('js/pages/admin/campaigns/show.tsx'));

    expect($campaign)->not->toBeFalse()
        ->toContain('<Accordion')
        ->toContain('type="single"')
        ->toContain('<AccordionItem')
        ->toContain('[&>[data-slot=accordion-trigger-icon]]:hidden')
        ->toContain('<ItemGroup')
        ->toContain('<ItemTitle')
        ->toContain('<Empty')
        ->toContain('function SectionActions({')
        ->toContain('function AddQuestionAction({')
        ->toContain('onClick={() => setOpen(true)}')
        ->toContain('className="mt-3 flex justify-end"')
        ->toContain('Use the Add Question button to add the first question.')
        ->toContain('Discard')
        ->toContain('campaign.draft_questions_count >')
        ->not->toContain('const canApprove =')
        ->toContain('<SheetTitle>Edit Question</SheetTitle>')
        ->toContain('Edit Question…')
        ->not->toContain('Question Snapshot')
        ->not->toContain('question snapshot')
        ->not->toContain('Edit Snapshot')
        ->not->toContain('id="generate-language"')
        ->not->toContain('{section.description ? (');
});

test('interactive surfaces use native links and buttons', function () {
    $campaigns = file_get_contents(resource_path('js/pages/admin/campaigns/index.tsx'));
    $campaign = file_get_contents(resource_path('js/pages/admin/campaigns/show.tsx'));
    $rankings = file_get_contents(resource_path('js/pages/admin/rankings/index.tsx'));

    expect($campaigns)->not->toBeFalse()
        ->toContain('aria-label={`View ${campaign.title}`}')
        ->not->toContain('role="link"')
        ->and($campaign)->not->toBeFalse()
        ->toContain('<CollapsibleTrigger asChild>')
        ->toContain('<Button size="icon" variant="ghost">')
        ->not->toContain('size="icon-sm"')
        ->toContain('type="button"')
        ->and($rankings)->not->toBeFalse()
        ->not->toContain('role="link"')
        ->not->toContain('tabIndex={0}');
});

test('campaign details expose accessible interaction safeguards', function () {
    $campaign = file_get_contents(resource_path('js/pages/admin/campaigns/show.tsx'));
    $campaignForm = file_get_contents(resource_path('js/components/admin/campaign-form.tsx'));
    $appContent = file_get_contents(resource_path('js/components/app-content.tsx'));
    $input = file_get_contents(resource_path('js/components/ui/input.tsx'));
    $textarea = file_get_contents(resource_path('js/components/ui/textarea.tsx'));
    $sheet = file_get_contents(resource_path('js/components/ui/sheet.tsx'));

    expect($campaign)->not->toBeFalse()
        ->toContain('<h1')
        ->toContain('router.push({')
        ->toContain('preserveScroll: true')
        ->toContain('aria-hidden="true"')
        ->toContain('autoComplete="email"')
        ->toContain('<AlertDialogTitle>')
        ->toContain('aria-live="polite"')
        ->and($campaignForm)->not->toBeFalse()
        ->toContain('<UnsavedChangesGuard')
        ->toContain('onError={focusFirstError}')
        ->and($appContent)->not->toBeFalse()
        ->toContain('href="#main-content"')
        ->toContain('Skip to Content')
        ->and($input)->not->toBeFalse()
        ->toContain('touch-manipulation')
        ->toContain('text-label-16')
        ->and($textarea)->not->toBeFalse()
        ->toContain('event.currentTarget.form?.requestSubmit()')
        ->and($sheet)->not->toBeFalse()
        ->toContain('overscroll-contain')
        ->toContain('env(safe-area-inset-right)');
});

test('campaign assessment generation uses the shimmer button treatment', function () {
    $stylesheet = file_get_contents(resource_path('css/app.css'));
    $shimmerButton = file_get_contents(resource_path('js/components/ui/shimmer-button.tsx'));
    $campaign = file_get_contents(resource_path('js/pages/admin/campaigns/show.tsx'));
    $generationButton = str($campaign)
        ->between('function GenerateAssessmentButton(', 'function AddSectionSheet(')
        ->toString();

    expect($stylesheet)->not->toBeFalse()
        ->toContain('--animate-shimmer-slide:')
        ->toContain('--animate-spin-around:')
        ->toContain('@keyframes shimmer-slide')
        ->toContain('@keyframes spin-around')
        ->toContain('.assessment-content-shimmer')
        ->toContain('mask-composite: exclude')
        ->and($shimmerButton)->not->toBeFalse()
        ->toContain("shimmerColor = 'var(--foreground)'")
        ->toContain("shimmerSize = '0.05em'")
        ->toContain("borderRadius = 'var(--radius)'")
        ->toContain("background = 'var(--background)'")
        ->toContain('buttonVariants({ variant, size })')
        ->toContain('group-hover:shadow')
        ->toContain('group-active:shadow')
        ->and($campaign)->not->toBeFalse()
        ->toContain("import { ShimmerButton } from '@/components/ui/shimmer-button';")
        ->toContain('<ShimmerButton')
        ->toContain('variant="outline"')
        ->toContain('size="default"')
        ->toContain("'Generate Assessment'")
        ->toContain('Generating Assessment…')
        ->toContain("'--shimmer-color':")
        ->toContain('assessment-content-shimmer')
        ->toContain('bg-background-200')
        ->toContain('role="status"')
        ->toContain('Loading campaign details…')
        ->toContain('<Skeleton className="h-10')
        ->toContain('aria-busy={isGeneratingAssessment}')
        ->toContain('onStart={() => onProcessingChange(true)}')
        ->toContain('onFinish={() => onProcessingChange(false)}')
        ->toContain('type="submit"')
        ->not->toContain('Advanced Settings')
        ->not->toContain('Save Weights')
        ->not->toContain('CampaignRankingController')
        ->not->toContain('GenerateAssessmentDialog')
        ->and(substr_count($campaign, 'bg-background-200'))->toBe(4);

    expect($generationButton)
        ->not->toContain('name="question_count"')
        ->not->toContain('name="difficulty"')
        ->not->toContain('name="question_mix"');
});

test('button and dropdown actions use pointer cursors', function () {
    $button = file_get_contents(resource_path('js/components/ui/button.tsx'));
    $dropdown = file_get_contents(resource_path('js/components/ui/dropdown-menu.tsx'));

    expect($button)->not->toBeFalse()
        ->toContain('cursor-pointer')
        ->toContain('disabled:cursor-not-allowed')
        ->and($dropdown)->not->toBeFalse()
        ->toContain('cursor-pointer')
        ->not->toContain('cursor-default');
});

test('global navigation does not render the team selector', function () {
    $header = file_get_contents(resource_path('js/components/app-header.tsx'));
    $sidebar = file_get_contents(resource_path('js/components/app-sidebar.tsx'));

    expect($header)
        ->not->toContain('@/components/team-switcher')
        ->not->toContain('<TeamSwitcher')
        ->and($sidebar)
        ->not->toContain('@/components/team-switcher')
        ->not->toContain('<TeamSwitcher');
});
