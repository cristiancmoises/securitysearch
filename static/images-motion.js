(function () {
	"use strict";

	var grid = document.getElementById("images");
	if (
		!grid ||
		!("IntersectionObserver" in window) ||
		typeof window.requestAnimationFrame !== "function" ||
		typeof window.cancelAnimationFrame !== "function"
	) {
		return;
	}

	var reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	var saveData = navigator.connection && navigator.connection.saveData;
	var coarsePointer = window.matchMedia && window.matchMedia("(hover: none), (pointer: coarse)").matches;
	var maxActive = coarsePointer ? 2 : 3;
	var active = [];
	var waiting = [];
	var requests = new WeakMap();
	var posterStarts = new WeakMap();
	var nextGeneration = 0;
	var nextPosterGeneration = 0;

	function removeFrom(list, image) {
		var index = list.indexOf(image);
		if (index !== -1) {
			list.splice(index, 1);
		}
	}

	function queue(image) {
		if (waiting.indexOf(image) === -1) {
			waiting.push(image);
		}
	}

	function clearRequest(image) {
		var request = requests.get(image);
		if (!request) {
			return;
		}

		image.removeEventListener("load", request.onLoad);
		image.removeEventListener("error", request.onError);
		requests.delete(image);
		if (image.dataset.motionGeneration === String(request.generation)) {
			delete image.dataset.motionGeneration;
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

		if (active.indexOf(image) !== -1) {
			if (userInitiated) {
				activate(image, true);
			}
			return;
		}

		if (waiting.indexOf(image) !== -1) {
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
		removeFrom(waiting, image);
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
		removeFrom(waiting, image);
		if (active.indexOf(image) !== -1) {
			if (userInitiated) {
				removeFrom(active, image);
				active.push(image);
			}
			return;
		}

		if (active.length >= maxActive) {
			if (!userInitiated) {
				queue(image);
				return;
			}

			var evicted = active[0];
			var reprepareEvicted =
				evicted.dataset.motionIntersecting === "yes" &&
				evicted.dataset.motionAutoFailed !== "yes";
			restore(evicted, false);
			if (reprepareEvicted) {
				prepare(evicted, false);
			}
		}

		active.push(image);
		var generation = ++nextGeneration;
		image.dataset.motionGeneration = String(generation);

		function motionLoaded() {
			var request = requests.get(image);
			if (!request || request.generation !== generation) {
				return;
			}
			clearRequest(image);
			delete image.dataset.motionAutoFailed;
			var card = image.closest(".image-wrapper");
			if (card && image.getAttribute("data-motion-src")) {
				card.classList.add("motion-active");
			}
		}

		function motionFailed() {
			var request = requests.get(image);
			if (!request || request.generation !== generation) {
				return;
			}
			clearRequest(image);
			if (userInitiated) {
				image.removeAttribute("data-motion-src");
			} else {
				image.dataset.motionAutoFailed = "yes";
			}
			restore(image);
		}

		requests.set(image, {
			generation: generation,
			onLoad: motionLoaded,
			onError: motionFailed
		});
		image.addEventListener("load", motionLoaded);
		image.addEventListener("error", motionFailed);
		image.src = source;
	}

	function pump() {
		while (maxActive > 0 && active.length < maxActive && waiting.length) {
			activate(waiting.shift(), false);
		}
	}

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			var image = entry.target;
			if (entry.isIntersecting) {
				image.dataset.motionIntersecting = "yes";
				prepare(image, false);
			} else {
				delete image.dataset.motionIntersecting;
				restore(image);
			}
		});
	}, { rootMargin: "120px 0px", threshold: 0.05 });

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
