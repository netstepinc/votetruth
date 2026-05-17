document.addEventListener("DOMContentLoaded", function () {
    gsap.registerPlugin(ScrollTrigger);

    let elements = document.querySelectorAll("[class*='gsap-']");

    elements.forEach(el => {
        let animationType = el.classList.contains("gsap-fade-in") ? { opacity: 0 } :
                            el.classList.contains("gsap-slide-left") ? { x: -100, opacity: 0 } :
                            el.classList.contains("gsap-slide-right") ? { x: 100, opacity: 0 } :
                            el.classList.contains("gsap-zoom-in") ? { scale: 0.8, opacity: 0 } : null;

        if (animationType) {
            let duration = parseFloat(el.className.match(/gsap-duration-(\d+(\.\d+)?)/)?.[1] || "1");
            let delay = parseFloat(el.className.match(/gsap-delay-(\d+(\.\d+)?)/)?.[1] || "0");
            let ease = el.className.match(/gsap-ease-([\w-]+)/)?.[1]?.replace("-", ".") || "power2.out";
            let staggerDelay = parseFloat(el.className.match(/stagger-(\d+(\.\d+)?)/)?.[1] || "0");

            let animation = gsap.from(el, {
                ...animationType,
                duration: duration,
                delay: delay,
                ease: ease
            });

            // Staggered animations for child elements
            if (staggerDelay) {
                let children = el.querySelectorAll("[data-gsap-child]");
                gsap.from(children, {
                    opacity: 0,
                    y: 10,
                    duration: duration,
                    ease: ease,
                    stagger: staggerDelay
                });
            }

            // ScrollTrigger support
            if (el.classList.contains("gsap-scroll")) {
                ScrollTrigger.create({
                    trigger: el,
                    start: "top 80%",
                    animation: animation,
                    toggleActions: "play none none none"
                });
            }
        }
    });

/*
	// Hero Animation
    const heroContainer = document.querySelector('.hero-container') || null;
    if (heroContainer) {  // Only run the hero animation if the container exists
        let slides = heroContainer.querySelectorAll(".hero-slide");
        let tl = gsap.timeline({ repeat: 2, repeatDelay: 2 });

        slides.forEach((slide, index) => {
            if (index === 0) {
                // First slide should be visible immediately
                slide.style.opacity = 1;
                slide.style.visibility = 'visible';
            }

            tl.to(slide, { opacity: 1, visibility: "visible", duration: 0.5 }) 
              .from(slide.querySelector(".hero-text"), { x: -50, opacity: 0, duration: 1 }, "-=0.3")
              .from(slide.querySelector(".hero-image"), { x: 50, opacity: 0, duration: 1 }, "-=0.8")
              .to(slide, { opacity: 0, visibility: "hidden", duration: 0.5, delay: 2 });
        });

        heroContainer.classList.add('js-loaded');
    }
*/

	// Home Page Animations
    // Fade & Slide in from Left
/*
    gsap.from(".fade-left", {
        x: -50,
        opacity: 0,
        duration: 1,
        scrollTrigger: {
            trigger: ".fade-left",
            start: "top 80%",
            toggleActions: "play none none reverse"
        }
    });

    // Fade & Slide in from Right
    gsap.from(".fade-right", {
        x: 50,
        opacity: 0,
        duration: 1,
        scrollTrigger: {
            trigger: ".fade-right",
            start: "top 80%",
            toggleActions: "play none none reverse"
        }
    });

    // Image Sequential Animation
    gsap.utils.toArray(".stagger-images img").forEach((img, i) => {
        gsap.from(img, {
            opacity: 0,
            y: 50,
            duration: 1,
            delay: i * 0.2, // Stagger effect
            scrollTrigger: {
                trigger: img,
                start: "top 85%",
                toggleActions: "play none none reverse"
            }
        });
    });

    // Staggered Text Animation
    gsap.from(".stagger-text p, .stagger-text h2", {
        opacity: 0,
        y: 20,
        duration: 1,
        stagger: 0.2,
        scrollTrigger: {
            trigger: ".stagger-text",
            start: "top 85%",
            toggleActions: "play none none reverse"
        }
    });
*/
});