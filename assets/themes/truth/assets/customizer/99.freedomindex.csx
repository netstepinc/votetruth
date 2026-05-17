/* Home Page 2025 */
h1.page-header {font-size: 2rem;}
#content{min-height:87vh;}
.container-xl {max-width: 1370px;}
.bg-navcustom {background: var(--bs-header-background);}
.bg-footercustom {background-color: var(--bs-footer-custom);}
.bg-warning{background-color: rgb(250, 221, 104) !important;}
.bg-info{background-color: rgb(127, 183, 252) !important;}

.border-success-3d{border-right:4px solid var(--bs-green); border-bottom:2px solid var(--bs-green);}
.border-primary-3d{border-right:4px solid var(--bs-primary); border-bottom:2px solid var(--bs-primary);}

h1>a, h2>a, h3>a, h4>a, h5>a, h6>a, .h1>a, .h2>a, .h3>a, .h4>a, .h5>a, .h6>a {color: var(--bs-primary);}
h1>a:hover, h2>a:hover, h3>a:hover, h4>a:hover, h5>a:hover, h6>a:hover, .h1>a:hover, .h2>a:hover, .h3>a:hover, .h4>a:hover, .h5>a:hover, .h6>a:hover {color: var(--bs-secondary);}

.card.text-white h1,
.card.text-white h2,
.card.text-white h3,
.card.text-white h4,
.card.text-white h5,
.card.text-white h6,
.card.text-white p{
    color: #ffffff !important;
}

@media (max-width: 1299.98px) {
	.dn1300{display: none;}
}

/* Tiny-state pill stack positioned over the “Atlantic” to the right */
#fi-us-map-wrapper {
    position: relative;
}

#fi-us-map-tiny {
    position: absolute;
    top: 40%;
    right: 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    z-index: 9999; /* VERY IMPORTANT: ensure pills sit above SVG */
    pointer-events: auto; /* allow clicking */
}

#fi-us-map-tiny button {
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid #ffffff;
    background-color: #c41425;
    color: #ffffff;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1.1;
    pointer-events: auto; /* ensure button receives clicks */
}

#fi-us-map-tiny button:hover {
    background-color: #cccccc;
    color: #000000;
}

/* Make the map itself scale nicely */
#fi-us-map svg {
    width: 100% !important;
    height: 100% !important;
}


/* FRONT PAGE STYLES */
.page-id-1 .card p{font-size: 1.125rem;}
.breadcrumb-item{font-family: var(--bs-font-headings);}
.breadcrumb-item+.breadcrumb-item {padding-left: 0.375rem;}
.breadcrumb-item+.breadcrumb-item::before {padding-right: 0.375rem;}

.brushfire {
    font-family: georgia, serif;
    font-size: 2.5rem;
    font-style: normal;
    font-weight: 600;
    color: #777;
    padding: 0;
}
@media (min-width:768px) and (max-width: 991.98px) {
	.brushfire {font-size: 1.5rem;}
}
@media (min-width:576px) and (max-width: 767.98px) {
	.brushfire {font-size: 1.375rem;}
}
@media (max-width: 575.98px) {
	.brushfire {font-size: 1.25rem;}
}


.offcanvas.offcanvas-start{
	width: 300px !important;
}

/* Mobile logo sizing */
@media (max-width: 991.98px) {
	.navbar-brand .logo-six {
		max-width: 200px;
		max-height: 30px;
	}
}

/* Desktop sidebar styles */
@media (min-width: 992px) {
	/* Fixed left sidebar */
	#desktopSidebar {
		position: fixed;
		left: 0;
		top: 0;
		height: 100vh;
		z-index: 1000;
		background-color: var(--bs-bluedk-rgb);
		border-right: 1px solid rgba(255, 255, 255, 0.2);
		overflow-y: auto;
		overflow-x: hidden;
		transition: width 0.3s ease-in-out;
		box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
	}
	
	/* Custom scrollbar - hidden by default, shown on hover */
	#desktopSidebar {
		scrollbar-width: thin;
		scrollbar-color: transparent transparent;
	}
	
	#desktopSidebar:hover {
		scrollbar-color: rgba(255, 255, 255, 0.3) var(--bs-blue);
	}
	
	#desktopSidebar::-webkit-scrollbar {
		width: 8px;
	}
	
	#desktopSidebar::-webkit-scrollbar-track {
		background: transparent;
	}
	
	#desktopSidebar:hover::-webkit-scrollbar-track {
		background: var(--bs-blue);
	}
	
	#desktopSidebar::-webkit-scrollbar-thumb {
		background: transparent;
		border-radius: 4px;
		transition: background 0.3s ease;
	}
	
	#desktopSidebar:hover::-webkit-scrollbar-thumb {
		background: rgba(255, 255, 255, 0.3);
	}
	
	#desktopSidebar::-webkit-scrollbar-thumb:hover {
		background: rgba(255, 255, 255, 0.5);
	}
	
	/* Expanded state (default on xl+, or on hover on lg) */
	#desktopSidebar:not(.collapsed) {
		width: 160px;
	}
	
	/* Collapsed state (default on lg, or when toggled) */
	#desktopSidebar.collapsed {
		width: 80px;
	}
	
	/* LG only: Hover to expand when collapsed */
	@media (min-width: 992px) and (max-width: 1199.98px) {
		/* Expand sidebar on hover when collapsed */
		#desktopSidebar.collapsed:hover {
			width: 160px;
			z-index: 1002; /* Above toggle button on hover */
		}
		
		/* Show full names on hover */
		#desktopSidebar.collapsed:hover .sidebar-gov-links .gov-name {
			display: block;
		}
		
		#desktopSidebar.collapsed:hover .sidebar-gov-links .gov-code {
			display: none;
		}
		
		/* Show footer on hover */
		#desktopSidebar.collapsed:hover .sidebar-footer {
			display: block;
		}
		
		/* Adjust toggle button position when sidebar is hovered (moves with expanded sidebar) */
		#desktopSidebar.collapsed:hover ~ .sidebar-toggle {
			left: 160px;
		}
	}
	
	/* Top bar logo */
	.top-menu .top-logo {
		padding: 0.5rem 0;
		margin-left: 0;
		transition: margin-left 0.3s ease-in-out;
		display: flex;
		align-items: center;
	}
	
	.top-menu .top-logo img {
		max-height: 50px;
		width: auto;
		height: auto;
	}

	@media (min-width: 1500px) {
		.top-menu .top-logo {padding-left:0 !important;}
	}

	/* Toggle button - tab/earmark extending over header */
	.sidebar-toggle {
		position: fixed;
		left: 160px;
		top: 12px;
		width: 30px;
		height: 42px;
		background-color: #fff;
		border: 1px solid rgba(255, 255, 255, 0.2);
		border-left: none;
		border-radius: 0 8px 8px 0;
		box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.12);
		color: var(--bs-blue);
		padding: 0;
		cursor: pointer;
		z-index: 1001;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: left 0.3s ease-in-out, background-color 0.2s, box-shadow 0.2s, transform 0.2s;
	}
	
	.sidebar-toggle:hover {
		background-color: rgba(173, 216, 230, 0.3);
		box-shadow: 2px 2px 12px rgba(0, 0, 0, 0.18);
		color: #fff;
		transform: translateX(2px);
	}
	
	.sidebar-toggle:active {
		background-color: rgba(173, 216, 230, 0.5);
		transform: translateX(1px);
	}
	
	.sidebar-toggle:focus {
		outline: 2px solid #0d6efd;
		outline-offset: 2px;
	}
	
	/* Adjust toggle position when sidebar is collapsed */
	body.sidebar-collapsed .sidebar-toggle {
		left: 80px;
	}
	
	/* Toggle icon rotation */
	#toggleIcon {
		transition: transform 0.3s ease-in-out;
		width: 24px;
		height: 24px;
	}
	
	/* Icon points right when collapsed (to expand), left when expanded (to collapse) */
	body.sidebar-collapsed #toggleIcon {
		transform: rotate(180deg);
	}
	
	/* Government links */
	.sidebar-gov-links {
		list-style: none;
		padding: 0;
		margin: 0;
	}
	
	.sidebar-gov-links li {
		border-bottom: 1px solid rgba(255, 255, 255, 0.2);
	}

	.sidebar-gov-links a {
		display: block;
		padding: 0.5rem;
		color: #fff;
		text-decoration: none;
		font-weight: 500;
		transition: background-color 0.2s, color 0.2s;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	
	.sidebar-gov-links a:hover {
		background-color: rgba(173, 216, 230, 0.3);
		color: #fff;
	}
	
	/* Collapsed state - show abbreviation */
	#desktopSidebar.collapsed .sidebar-gov-links .gov-name {
		display: none;
	}
	
	#desktopSidebar.collapsed .sidebar-gov-links .gov-code {
		display: block;
		text-align: center;
		font-size: 1.1rem;
	}
	
	/* Expanded state - show full name */
	#desktopSidebar:not(.collapsed) .sidebar-gov-links .gov-code {
		display: none;
	}
	
	#desktopSidebar:not(.collapsed) .sidebar-gov-links .gov-name {
		display: block;
		font-size: 1.125rem;
	}
	
	/* Sidebar footer */
	.sidebar-footer {
		padding: 1.5rem 1rem;
		border-top: 1px solid rgba(255, 255, 255, 0.2);
		margin-top: auto;
		font-size: 0.85rem;
		color: #fff;
		text-align: center;
		background-color: var(--bs-bluedk-rgb);
	}
	
	.sidebar-footer a {
		color: #fff;
		text-decoration: none;
	}
	
	.sidebar-footer a:hover {
		color: rgba(255, 255, 255, 0.8);
		text-decoration: underline;
	}
	
	#desktopSidebar.collapsed .sidebar-footer {
		display: none;
	}
	
	/* Main content adjustment for sidebar */
	body.has-desktop-sidebar {
		padding-left: 160px;
		transition: padding-left 0.3s ease-in-out;
	}
	
	body.has-desktop-sidebar.sidebar-collapsed {
		padding-left: 80px;
	}
	
	/* Header adjustment */
	.header {
		position: relative;
		z-index: 999;
	}
	
	/* Top menu full width on desktop */
	.top-menu {
		width: 100%;
	}
	
	/* Top bar - blue background */
	.top-menu.bg-dark {
		background: var(--bs-bluedk-rgb) !important;
		min-height: 60px;
		padding: 0; /*0.75rem 0;*/
	}
	.bg-navcustom {
		background: var(--bs-bluedk-rgb) !important;
	}

	/* Top navigation with inline search on desktop */
	.top-menu .navbar-nav {
		align-items: center;
		gap: 0.5rem;
	}
	
	.top-menu .navbar-nav .nav-link {
		padding: 0.5rem 1rem;
		font-size: 1rem;
		border-left: 2px solid rgba(255, 255, 255, 0.2);
		color: #fff;
		transition: background-color 0.2s, color 0.2s;
		border-radius: 4px;
	}
	
	.top-menu .navbar-nav .nav-link:hover {
		background-color: rgba(173, 216, 230, 0.3);
		color: #fff;
	}
	
	/* Inline search in top bar (desktop) */
	.top-menu .top-search-form {
		margin-left: 1rem;
	}
	
	.top-menu .top-search-form .input-group {
		max-width: 300px;
	}

	.top-menu .top-search-form .form-control {
		border: 1px solid rgba(255, 255, 255, 0.5);
		background-color: rgba(255, 255, 255, 0.1);
		color: #fff;
		font-weight:500; 
		padding: .5rem .5rem;
		font-size: 1.2rem;
	}
	
	.top-menu .top-search-form .form-control::placeholder {
		color: rgba(255, 255, 255, 0.8);
	}
	
	.top-menu .top-search-form .form-control:focus {
		background-color: rgba(255, 255, 255, 0.2);
		border-color: rgba(255, 255, 255, 0.5);
		color: #fff;
		box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
	}
	
	.top-menu .top-search-form .btn-outline-light {
		border-color: rgba(255, 255, 255, 0.5);
	}
	
	.top-menu .top-search-form .btn-outline-light:hover {
		background-color: rgba(255, 255, 255, 0.2);
		border-color: rgba(255, 255, 255, 0.5);
	}
}

/* Scroll hide/show behavior for mobile navigation */
#showbacktop {
	transition: transform 0.3s ease-in-out;
}
#showbacktop.scroll-hide {
	transform: translateY(-100%);
}

/* Mobile navigation prompt (SM/MD only) */
@media (max-width: 991.98px) {
	.mobile-nav-prompt {
		display: block;
		position: relative;
		background: linear-gradient(135deg, var(--bs-blue) 0%, var(--bs-red) 100%);
		color: #fff;
		padding: 0.75rem;
		border-bottom: 2px solid rgba(255, 255, 255, 0.2);
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		animation: slideDown 0.4s ease-out forwards;
	}
	
	.mobile-nav-prompt.hidden {
		display: none;
	}
	
	.mobile-nav-prompt-content {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		position: relative;
	}
	
	.mobile-nav-prompt-icon {
		flex-shrink: 0;
		width: 24px;
		height: 24px;
		animation: pulse 2s ease-in-out infinite;
	}
	
	.mobile-nav-prompt-text {
		flex: 1;
		font-size: 0.9rem;
		font-weight: 500;
		line-height: 1.4;
	}
	
	.mobile-nav-prompt-arrow {
		flex-shrink: 0;
		width: 20px;
		height: 20px;
		animation: pointRight 1.5s ease-in-out infinite;
	}
	
	.mobile-nav-prompt-close {
		flex-shrink: 0;
		background: rgba(255, 255, 255, 0.2);
		border: none;
		color: #fff;
		width: 24px;
		height: 24px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		transition: background-color 0.2s, transform 0.2s;
		padding: 0;
	}
	
	.mobile-nav-prompt-close:hover {
		background: rgba(255, 255, 255, 0.3);
		transform: scale(1.1);
	}
	
	.mobile-nav-prompt-close:active {
		transform: scale(0.95);
	}
	
	@keyframes slideDown {
		from {
			opacity: 0;
			transform: translateY(-100%);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}
	
	@keyframes pulse {
		0%, 100% {
			transform: scale(1);
			opacity: 1;
		}
		50% {
			transform: scale(1.1);
			opacity: 0.8;
		}
	}
	
	@keyframes pointRight {
		0%, 100% {
			transform: translateX(0);
		}
		50% {
			transform: translateX(4px);
		}
	}
}

#footer small{font-size: 0.75rem;}
#fi-vote-nav-collapse .accordion-collapse.show .list-group-item{background-color: var(--bs-gray-100)}
#fi-vote-nav-collapse .accordion-collapse.show .list-group-item a{font-weight:600;} 