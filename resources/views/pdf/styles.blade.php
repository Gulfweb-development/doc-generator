body { font-family: freeserif; font-size: {{ $direction === 'rtl' ? '12pt' : '11pt' }}; line-height: 1.5; color: #1c2430; }
p { margin: 0 0 7pt 0; }
li p { margin: 0; }
ul, ol { margin: 0 0 7pt 0; }
h1, h2, h3 { font-family: freesans; font-weight: bold; color: #1c2430; line-height: 1.2; }
h1 { font-size: 20pt; margin: 0 0 10pt 0; }
h2 { font-size: 14pt; margin: 14pt 0 6pt 0; }
h3 { font-size: 11pt; margin: 12pt 0 4pt 0; }
a { color: {{ $accent }}; }
table { width: 100%; border-collapse: collapse; margin: 4pt 0 12pt 0; font-family: freesans; font-size: 9pt; line-height: 1.35; }
th { text-align: {{ $direction === 'rtl' ? 'right' : 'left' }}; font-weight: bold; padding: 5pt 6pt 5pt 0; border-bottom: 1pt solid {{ $accent }}; vertical-align: bottom; }
td { padding: 4.5pt 6pt 4.5pt 0; border-bottom: 0.4pt solid #c9ced6; vertical-align: top; }
th p, td p { margin: 0; }
table.plain { margin: 0; font-family: freesans; }
table.plain td { border: none; padding: 0; }
.letterhead h1, .letterhead h2, .letterhead h3 { font-size: 13pt; margin: 0 0 1pt 0; color: {{ $accent }}; }
.letterhead p { font-family: freesans; font-size: 8.5pt; line-height: 1.35; color: #5b6470; margin: 0; }
.letterhead-rule { border-bottom: 0.4pt solid #c9ced6; height: 3mm; }
table.footline { font-size: 7.5pt; color: #5b6470; margin: 0; }
table.footline p { margin: 0; }
table.signoff { margin-top: 22pt; page-break-inside: avoid; }
table.signoff td.signature-line { border-top: 0.4pt solid #5b6470; padding-top: 3pt; font-size: 8pt; color: #5b6470; text-align: {{ $direction === 'rtl' ? 'right' : 'left' }}; }
th h1, th h2, th h3, td h1, td h2, td h3 { margin: 0; }
