# Design Guide


## Brand Colors

From the logos, the actual brand colors are:

Brand Red: #c41425 (new domain logo) / #d31628 (icon) — these are very close, splitting the difference: #c52029 (which you already had as TNA Red — confirmed correct)
Brand Blue: #0055a4 (new domain logo) / #20459e (icon dark blue) — the new domain blue is cleaner and more web-friendly

--
Role	Token	Value	Rationale
Primary blue	--fi-blue	#0055a4	Exact new domain blue
Dark navy	--fi-navy	#002b62	Deep shade of brand blue for hero/nav
Deeper navy	--fi-navy-deep	#001840	Darkest hero bg
Brand red	--fi-red	#c52029	Exact TNA red — accent only
Red dark	--fi-red-dark	#9e1520	Hover states
White	--fi-white	#ffffff	—
Light gray	--fi-gray-1	#f4f5f7	Page background
Mid gray	--fi-gray-2	#e2e5ea	Borders, dividers
Text dark	--fi-ink	#1a1c1e	Body text
Text mid	--fi-ink-mid	#444b54	Secondary text
Text light	--fi-ink-light	#6b7280	Labels, metadata
Where red gets used (sparingly):

Search button CTA → this is the single most important action on the page
"My Account" nav CTA
Active chip border accent
Score scale F grade (already using a similar red)


CSS DEFINITIONS
:root {
	--fi-blue:	#0055a4;		/* Primary blue: Exact new domain blue */
	--fi-navy:	#002b62;		/* Dark navy: Deep shade of brand blue for hero/nav */
	--fi-navy-deep:	#001840;	/* Deeper navy: Darkest hero bg */
	--fi-red:	#c52029;		/* Brand red: Exact TNA red — accent only */
	--fi-red-dark:	#9e1520;	/* Red dark: Hover states */
	--fi-white:	#ffffff;		/* White */
	--fi-gray-1: #f4f5f7;		/* Light gray: Page background */
	--fi-gray-2: #e2e5ea;		/* Mid gray: Borders, dividers */
	--fi-ink:	#1a1c1e;		/* Text dark: Body text */
	--fi-ink-mid: #444b54;		/* Text mid: Secondary text */
	--fi-ink-light:	#6b7280;	/* Text light: Labels, metadata */
}
