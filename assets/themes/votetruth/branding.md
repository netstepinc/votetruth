# Design Guide


## Brand Colors

From the logos, the actual brand colors are:

Brand Red: #c41425 (new domain logo) / #d31628 (icon) — these are very close, splitting the difference: #c52029 (which you already had as TNA Red — confirmed correct)
Brand Blue: #0055a4 (new domain logo) / #20459e (icon dark blue) — the new domain blue is cleaner and more web-friendly

--
Role	Token	Value	Rationale
Primary blue	--vt-blue	#0055a4	Exact new domain blue
Dark navy	--vt-navy	#002b62	Deep shade of brand blue for hero/nav
Deeper navy	--vt-navy-deep	#001840	Darkest hero bg
Brand red	--vt-red	#c52029	Exact TNA red — accent only
Red dark	--vt-red-dark	#9e1520	Hover states
White	--vt-white	#ffffff	—
Light gray	--vt-gray-1	#f4f5f7	Page background
Mid gray	--vt-gray-2	#e2e5ea	Borders, dividers
Text dark	--vt-ink	#1a1c1e	Body text
Text mid	--vt-ink-mid	#444b54	Secondary text
Text light	--vt-ink-light	#6b7280	Labels, metadata
Where red gets used (sparingly):

Search button CTA → this is the single most important action on the page
"My Account" nav CTA
Active chip border accent
Score scale F grade (already using a similar red)


CSS DEFINITIONS
:root {
	--vt-blue:	#0055a4;		/* Primary blue: Exact new domain blue */
	--vt-navy:	#002b62;		/* Dark navy: Deep shade of brand blue for hero/nav */
	--vt-navy-deep:	#001840;	/* Deeper navy: Darkest hero bg */
	--vt-red:	#c52029;		/* Brand red: Exact TNA red — accent only */
	--vt-red-dark:	#9e1520;	/* Red dark: Hover states */
	--vt-white:	#ffffff;		/* White */
	--vt-gray-1: #f4f5f7;		/* Light gray: Page background */
	--vt-gray-2: #e2e5ea;		/* Mid gray: Borders, dividers */
	--vt-ink:	#1a1c1e;		/* Text dark: Body text */
	--vt-ink-mid: #444b54;		/* Text mid: Secondary text */
	--vt-ink-light:	#6b7280;	/* Text light: Labels, metadata */
}
