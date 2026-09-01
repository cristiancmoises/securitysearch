(function () {
	"use strict";

	var grid = document.getElementById("images");
	if (!grid) {
		return;
	}

	var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	var saveData = navigator.connection && navigator.connection.saveData;
	var supportsMotion =
		("IntersectionObserver" in window) &&
		typeof window.requestAnimationFrame === "function" &&
		typeof window.cancelAnimationFrame === "function";
	function notifyFailure(image) {
		var errorEvent;
		if (typeof window.Event === "function") {
			errorEvent = new Event("error");
		} else {
			errorEvent = document.createEvent("Event");
			errorEvent.initEvent("error", false, false);
		}
		image.dispatchEvent(errorEvent);
	}
	// Keep broken posters deterministic when automatic playback is unavailable.
	if (!supportsMotion || reducedMotion || saveData) {
		grid.dataset.motionAutoDisabled = "yes";
	}
	if (supportsMotion && !reducedMotion && saveData) {
		grid.querySelectorAll("img[data-motion-src]").forEach(function (image) {
			if (image.complete && image.naturalWidth === 0) {
				notifyFailure(image);
			}
		});
	}
	if (!supportsMotion || reducedMotion) {
		grid.querySelectorAll("img[data-motion-src]").forEach(function (image) {
			image.removeAttribute("data-motion-src");
			image.removeAttribute("data-motion-fallback-src");
			if (image.complete && image.naturalWidth === 0) {
				notifyFailure(image);
			}
		});
		return;
	}

	var coarsePointer = window.matchMedia && window.matchMedia("(hover: none), (pointer: coarse)").matches;
	// Validate only a small number of originals concurrently, but do not cap
	// how many already-loaded, visible animations may keep playing.
	var maxConcurrentLoads = coarsePointer ? 2 : 3;
	var maxRetainedAnimations = coarsePointer ? 18 : 36;
	var loading = 0;
	var active = [];
	var waiting = [];
	var requests = new WeakMap();
	var posterStarts = new WeakMap();
	var retryTimers = new WeakMap();
	var nextGeneration = 0;
	var nextPosterGeneration = 0;

	function removeFrom(list, image) {
		var index = list.indexOf(image);
		if (index !== -1) {
			list.splice(index, 1);
		}
	}

	function waitingIndex(image) {
		for (var index = 0; index < waiting.length; index++) {
			if (waiting[index].image === image) {
				return index;
			}
		}
		return -1;
	}

	function removeWaiting(image) {
		var index = waitingIndex(image);
		if (index !== -1) {
			waiting.splice(index, 1);
		}
	}

	function motionPriority(image) {
		var format = (image.getAttribute("data-motion-format") || "").toUpperCase();
		return format === "GIF" || format === "APNG" ? 0 : 1;
	}

	function queue(image, userInitiated) {
		var index = waitingIndex(image);
		if (index !== -1) {
			if (userInitiated) {
				var promoted = waiting.splice(index, 1)[0];
				promoted.userInitiated = true;
				waiting.unshift(promoted);
			}
			return;
		}

		var entry = { image: image, userInitiated: userInitiated };
		if (userInitiated) {
			waiting.unshift(entry);
			return;
		}
		var priority = motionPriority(image);
		var insertAt = waiting.length;
		for (var waitIndex = 0; waitIndex < waiting.length; waitIndex++) {
			if (!waiting[waitIndex].userInitiated && motionPriority(waiting[waitIndex].image) > priority) {
				insertAt = waitIndex;
				break;
			}
		}
		waiting.splice(insertAt, 0, entry);
	}

	function clearRequest(image) {
		var request = requests.get(image);
		if (!request) {
			return;
		}

		image.removeEventListener("load", request.onLoad);
		image.removeEventListener("error", request.onError);
		if (request.loading) {
			request.loading = false;
			loading = Math.max(0, loading - 1);
		}
		requests.delete(image);
		if (image.dataset.motionGeneration === String(request.generation)) {
			delete image.dataset.motionGeneration;
		}
	}

	function retryUrl(source) {
		try {
			var url = new URL(source, document.baseURI);
			url.searchParams.set("motion_retry", String(Date.now()));
			return url.href;
		} catch (error) {
			return source;
		}
	}

	function cancelPosterStart(image) {
		var start = posterStarts.get(image);
		if (!start) {
			return;
		}

		if (start.onLoad) {
			image.removeEventListener("load", start.onLoad);
			image.removeEventListener("error", start.onError);
		}
		if (start.firstFrame !== null) {
			window.cancelAnimationFrame(start.firstFrame);
		}
		if (start.secondFrame !== null) {
			window.cancelAnimationFrame(start.secondFrame);
		}
		posterStarts.delete(image);
		if (image.dataset.motionPosterGeneration === String(start.generation)) {
			delete image.dataset.motionPosterGeneration;
		}
	}

	function automaticAllowed(image) {
		return (
			!reducedMotion &&
			!saveData &&
			image.dataset.motionIntersecting === "yes" &&
			image.dataset.motionAutoFailed !== "yes" &&
			!!image.getAttribute("data-motion-src")
		);
	}

	function preparationAllowed(image, userInitiated) {
		if (!image || reducedMotion || !image.getAttribute("data-motion-src")) {
			return false;
		}

		return userInitiated ? isVisiblyPresent(image) : automaticAllowed(image);
	}

	function posterStartIsCurrent(image, generation, poster) {
		var start = posterStarts.get(image);
		return (
			start &&
			start.generation === generation &&
			preparationAllowed(image, start.userInitiated) &&
			image.src === new URL(poster, document.baseURI).href
		);
	}

	function prepare(image, userInitiated) {
		if (!preparationAllowed(image, userInitiated)) {
			return;
		}
		if (image.dataset.motionRetryPending === "yes") {
			return;
		}

		if (active.indexOf(image) !== -1) {
			if (userInitiated) {
				activate(image, true);
			}
			return;
		}

		if (waitingIndex(image) !== -1) {
			if (userInitiated) {
				activate(image, true);
			}
			return;
		}

		var existing = posterStarts.get(image);
		if (existing) {
			if (userInitiated) {
				existing.userInitiated = true;
			}
			return;
		}

		var poster = image.getAttribute("data-poster-src");
		if (!poster || image.src !== new URL(poster, document.baseURI).href) {
			return;
		}

		var generation = ++nextPosterGeneration;
		var start = {
			generation: generation,
			userInitiated: userInitiated,
			onLoad: null,
			onError: null,
			firstFrame: null,
			secondFrame: null
		};
		posterStarts.set(image, start);
		image.dataset.motionPosterGeneration = String(generation);

		function startMotion() {
			if (!posterStartIsCurrent(image, generation, poster)) {
				cancelPosterStart(image);
				return;
			}

			var requestedByUser = start.userInitiated;
			cancelPosterStart(image);
			activate(image, requestedByUser);
		}

		function posterSettled(canPaint) {
			if (!posterStartIsCurrent(image, generation, poster)) {
				cancelPosterStart(image);
				return;
			}

			if (start.onLoad) {
				image.removeEventListener("load", start.onLoad);
				image.removeEventListener("error", start.onError);
				start.onLoad = null;
				start.onError = null;
			}

			// A failed poster has settled but cannot be painted. Continue to the
			// requested motion source after the same final eligibility checks.
			if (!canPaint) {
				startMotion();
				return;
			}

			start.firstFrame = window.requestAnimationFrame(function () {
				start.firstFrame = null;
				if (!posterStartIsCurrent(image, generation, poster)) {
					cancelPosterStart(image);
					return;
				}

				start.secondFrame = window.requestAnimationFrame(function () {
					start.secondFrame = null;
					if (!posterStartIsCurrent(image, generation, poster)) {
						cancelPosterStart(image);
						return;
					}

					startMotion();
				});
			});
		}

		if (image.complete) {
			posterSettled(image.naturalWidth > 0);
		} else {
			start.onLoad = function () {
				posterSettled(true);
			};
			start.onError = function () {
				posterSettled(false);
			};
			image.addEventListener("load", start.onLoad);
			image.addEventListener("error", start.onError);
		}
	}

	function isVisiblyPresent(image) {
		if (!image) {
			return false;
		}

		var card = image.closest(".image-wrapper") || image;
		var rect = card.getBoundingClientRect();
		return (
			rect.width > 0 &&
			rect.height > 0 &&
			rect.bottom > 0 &&
			rect.right > 0 &&
			rect.top < window.innerHeight &&
			rect.left < window.innerWidth
		);
	}

	function restore(image, shouldPump) {
		cancelPosterStart(image);
		clearRequest(image);
		removeFrom(active, image);
		removeWaiting(image);
		var poster = image.getAttribute("data-poster-src");
		if (poster && image.src !== new URL(poster, document.baseURI).href) {
			image.src = poster;
		}
		var card = image.closest(".image-wrapper");
		if (card) {
			card.classList.remove("motion-active");
		}
		if (shouldPump !== false) {
			pump();
		}
	}

	function trimSettledAnimations(protectedImage) {
		while (active.length > maxRetainedAnimations) {
			var evicted = false;
			for (var index = 0; index < active.length; index++) {
				var candidate = active[index];
				if (
					candidate !== protectedImage &&
					candidate.dataset.motionIntersecting !== "yes" &&
					!requests.get(candidate)
				) {
					restore(candidate, false);
					evicted = true;
					break;
				}
			}
			if (!evicted) {
				break;
			}
		}
	}

	function activate(image, userInitiated) {
		var source = image && image.getAttribute("data-motion-src");
		if (
			!source ||
			reducedMotion ||
			(userInitiated && !isVisiblyPresent(image)) ||
			(!userInitiated && (
				saveData ||
				image.dataset.motionIntersecting !== "yes" ||
				image.dataset.motionAutoFailed === "yes"
			))
		) {
			return;
		}

		cancelPosterStart(image);
		removeWaiting(image);
		if (active.indexOf(image) !== -1) {
			if (userInitiated) {
				var activeRequest = requests.get(image);
				if (activeRequest) {
					activeRequest.userInitiated = true;
				}
			}
			return;
		}

		if (loading >= maxConcurrentLoads) {
			queue(image, userInitiated);
			return;
		}

		active.push(image);
		loading++;
		var generation = ++nextGeneration;
		var requestSource = image.dataset.motionUseRetry === "yes" ? retryUrl(source) : source;
		delete image.dataset.motionUseRetry;
		image.dataset.motionGeneration = String(generation);

		function motionLoaded() {
			var request = requests.get(image);
			if (!request || request.generation !== generation) {
				return;
			}
			if (image.src !== new URL(request.source, document.baseURI).href) {
				restore(image);
				return;
			}
			clearRequest(image);
			delete image.dataset.motionAutoFailed;
			delete image.dataset.motionRetryPending;
			var card = image.closest(".image-wrapper");
			if (card && image.getAttribute("data-motion-src")) {
				card.classList.add("motion-active");
			}
			trimSettledAnimations(image);
			pump();
		}

		function motionFailed() {
			var request = requests.get(image);
			if (!request || request.generation !== generation) {
				return;
			}
			var requestedByUser = request.userInitiated;
			clearRequest(image);
			removeFrom(active, image);

			var fallback = image.getAttribute("data-motion-fallback-src");
			if (fallback && image.dataset.motionFallbackTried !== "yes") {
				image.dataset.motionFallbackTried = "yes";
				image.setAttribute("data-motion-primary-src", source);
				image.setAttribute("data-motion-src", fallback);
				image.removeAttribute("data-motion-fallback-src");
				restore(image, false);
				queue(image, requestedByUser);
				pump();
				return;
			}

			if (
				image.getAttribute("data-motion-retry") === "yes" &&
				image.dataset.motionRetried !== "yes"
			) {
				image.dataset.motionRetried = "yes";
				image.dataset.motionRetryPending = "yes";
				restore(image, false);
				pump();
				var timer = window.setTimeout(function () {
					retryTimers.delete(image);
					delete image.dataset.motionRetryPending;
					// Preserve the cache-busted retry across a temporary observer
					// exit; the next eligible prepare must not reuse the failed URL.
					image.dataset.motionUseRetry = "yes";
					if (preparationAllowed(image, requestedByUser)) {
						queue(image, requestedByUser);
						pump();
					}
				}, 2200 + Math.floor(Math.random() * 800));
				retryTimers.set(image, timer);
				return;
			}

			if (requestedByUser) {
				image.removeAttribute("data-motion-src");
			} else {
				image.dataset.motionAutoFailed = "yes";
			}
			restore(image);
		}

		requests.set(image, {
			generation: generation,
			onLoad: motionLoaded,
			onError: motionFailed,
			loading: true,
			userInitiated: userInitiated,
			source: requestSource
		});
		image.addEventListener("load", motionLoaded);
		image.addEventListener("error", motionFailed);
		image.src = requestSource;
	}

	function pump() {
		while (maxConcurrentLoads > 0 && loading < maxConcurrentLoads && waiting.length) {
			var next = waiting.shift();
			activate(next.image, next.userInitiated);
		}
	}

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			var image = entry.target;
			if (entry.isIntersecting && entry.intersectionRatio > 0) {
				image.dataset.motionIntersecting = "yes";
				prepare(image, false);
			} else {
				delete image.dataset.motionIntersecting;
				trimSettledAnimations(null);
			}
		});
	}, { rootMargin: "700px 0px", threshold: [0, 0.01] });

	function register(root) {
		root.querySelectorAll("img[data-motion-src]").forEach(function (image) {
			if (!image.dataset.motionObserved) {
				image.dataset.motionObserved = "yes";
				observer.observe(image);
			}
		});
	}

	grid.addEventListener("pointerover", function (event) {
		var image = event.target.closest && event.target.closest("img[data-motion-src]");
		if (image) {
			prepare(image, true);
		}
	});
	grid.addEventListener("focusin", function (event) {
		var card = event.target.closest && event.target.closest(".animated-result");
		if (card) {
			prepare(card.querySelector("img[data-motion-src]"), true);
		}
	});
	grid.addEventListener("load", function (event) {
		var image = event.target;
		if (
			image &&
			image.tagName === "IMG" &&
			image.hasAttribute("data-motion-src") &&
			!image.dataset.motionGeneration
		) {
			prepare(image, false);
		}
	}, true);
	grid.addEventListener("error", function (event) {
		var image = event.target;
		if (!image || image.tagName !== "IMG") {
			return;
		}
		// The fallback controller runs first in the capture phase and may replace
		// the poster URL. Wait until the old per-image error listener has settled,
		// then prepare whichever poster/fallback is current.
		window.setTimeout(function () {
			if (image.hasAttribute("data-motion-src") && !image.dataset.motionGeneration) {
				prepare(image, false);
			}
		}, 0);
	}, true);

	register(grid);
	new MutationObserver(function (mutations) {
		mutations.forEach(function (mutation) {
			mutation.addedNodes.forEach(function (node) {
				if (node.nodeType === 1) {
					if (node.matches && node.matches("img[data-motion-src]")) {
						register(node.parentElement || grid);
					} else {
						register(node);
					}
				}
			});
		});
	}).observe(grid, { childList: true, subtree: true });
})();
