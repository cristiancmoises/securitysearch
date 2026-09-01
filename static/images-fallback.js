(function () {
	"use strict";

	var grid = document.getElementById("images");
	if (!grid) {
		return;
	}

	var transparentPixel = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==";

	function absolute(url) {
		try {
			return new URL(url, document.baseURI).href;
		} catch (error) {
			return "";
		}
	}

	function useFallback(image, attribute, marker) {
		var fallback = image.getAttribute(attribute);
		if (!fallback || image.dataset[marker] === "yes" || image.src === absolute(fallback)) {
			return false;
		}

		image.dataset[marker] = "yes";
		image.removeAttribute("data-image-unavailable");
		var card = image.closest(".image-wrapper");
		if (card) {
			card.classList.remove("image-unavailable");
		}

		// The motion controller waits for the current poster. Keep its reference
		// aligned when the provider thumbnail has to fall back to another source.
		if (image.hasAttribute("data-poster-src")) {
			image.setAttribute("data-poster-src", fallback);
		}
		image.src = fallback;
		return true;
	}

	function markUnavailable(image) {
		image.dataset.imageUnavailable = "yes";
		image.removeAttribute("data-motion-src");
		image.removeAttribute("data-motion-fallback-src");
		image.src = transparentPixel;
		var card = image.closest(".image-wrapper");
		if (card) {
			card.classList.add("image-unavailable");
		}
	}

	function handleError(image) {
		if (!image || image.tagName !== "IMG") {
			return;
		}

		// Motion failures are owned by images-motion.js so it can try the
		// provider's alternate animated source and bounded retry path.
		if (image.dataset.motionGeneration) {
			return;
		}

		if (
			useFallback(image, "data-image-fallback-src", "imageFallbackTried") ||
			useFallback(image, "data-image-fallback-secondary-src", "imageFallbackSecondaryTried")
		) {
			return;
		}

		// A broken poster may still have a working animation. Let the motion
		// controller settle that request before displaying the local placeholder.
		if (
			image.hasAttribute("data-motion-src") &&
			image.dataset.motionAutoFailed !== "yes" &&
			grid.dataset.motionAutoDisabled !== "yes"
		) {
			return;
		}

		markUnavailable(image);
	}

	grid.addEventListener("error", function (event) {
		handleError(event.target);
	}, true);

	grid.addEventListener("load", function (event) {
		var image = event.target;
		if (!image || image.tagName !== "IMG" || image.dataset.imageUnavailable === "yes") {
			return;
		}
		var card = image.closest(".image-wrapper");
		if (card) {
			card.classList.remove("image-unavailable");
		}
	}, true);

	// Visible cached failures can settle before deferred controllers execute.
	// Process them once so every card still gets its bounded fallback chain.
	grid.querySelectorAll("img").forEach(function (image) {
		if (image.complete && image.naturalWidth === 0) {
			handleError(image);
		}
	});
})();
