(function () {
	"use strict";

	var grid = document.getElementById("images");
	var next = document.querySelector("a.nextpage.img");

	// Keep the normal Next page link as the progressive fallback.
	if (!grid || !next || !("IntersectionObserver" in window) || !("fetch" in window)) {
		return;
	}

	var loading = false;
	var automaticPages = 0;
	// Save-Data users retain ordinary pagination; never prefetch a paid connection.
	if (navigator.connection && navigator.connection.saveData) { return; }
	var status = document.createElement("div");
	status.className = "infinite-status";
	status.setAttribute("role", "status");
	status.setAttribute("aria-live", "polite");
	next.parentNode.insertBefore(status, next);
	next.addEventListener("click", function (event) {
		if (loading) {
			event.preventDefault();
		}
	});

	var observer = new IntersectionObserver(function (entries) {
		if (entries[0].isIntersecting) {
			loadNextPage();
		}
	}, {
		rootMargin: "200px 0px"
	});

	function offerRestart(message) {
		observer.disconnect();
		loading = false;
		var restart = new URL(window.location.href);
		restart.searchParams.delete("npt");
		next.href = restart.href;
		next.textContent = "Restart image search";
		next.setAttribute("aria-label", "Restart image search from the first page");
		next.removeAttribute("aria-disabled");
		next.classList.remove("loading");
		status.textContent = message;
	}

	async function loadNextPage() {
		if (loading) {
			return;
		}

		loading = true;
		observer.unobserve(next);
		next.setAttribute("aria-disabled", "true");
		next.classList.add("loading");
		status.textContent = "Loading more images…";
		var controller = typeof AbortController === "function" ? new AbortController() : null;
		var deadline = controller ? setTimeout(function () { controller.abort(); }, 25000) : null;

		try {
			if (new URL(next.href).origin !== window.location.origin) { throw new Error("Foreign pagination URL"); }
			var response = await fetch(next.href, {
				signal: controller ? controller.signal : undefined,
				credentials: "same-origin",
				cache: "no-store",
				headers: { "Accept": "text/html" }
			});

			if (!response.ok) {
				throw new Error("HTTP " + response.status);
			}

			var page = new DOMParser().parseFromString(await response.text(), "text/html");
			var items = page.querySelectorAll("#images > .image-wrapper");

			if (items.length === 0) {
				throw new Error("No image results in response");
			}

			var fragment = document.createDocumentFragment();
			items.forEach(function (item) {
				fragment.appendChild(document.importNode(item, true));
			});
			grid.appendChild(fragment);

			var following = page.querySelector("a.nextpage.img");
			if (!following) {
				next.remove();
				observer.disconnect();
				status.textContent = "All image results loaded.";
				return;
			}

			var followingUrl = new URL(following.getAttribute("href"), response.url);
			if (followingUrl.origin !== window.location.origin) { throw new Error("Foreign pagination URL"); }
			next.href = followingUrl.href;
			next.removeAttribute("aria-disabled");
			next.classList.remove("loading");
			status.textContent = "";
			loading = false;
			automaticPages++;
			if (automaticPages < 3) { observer.observe(next); }
			else { observer.disconnect(); status.textContent = "Continue with Next page to keep this page lightweight."; }
		} catch (error) {
			// The dispatched next-page token is one-time use, even when its
			// upstream request fails. Never expose that consumed URL again.
			offerRestart("Automatic loading stopped. Restart the search to continue.");
		} finally {
			if (deadline !== null) { clearTimeout(deadline); }
		}
	}

	observer.observe(next);
})();
