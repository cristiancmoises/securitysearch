(function () {
	"use strict";

	var checkbox = document.getElementById("services-toggle");
	var toggle = document.querySelector(".svc-toggle");
	if (!checkbox || !toggle) {
		return;
	}

	// In JavaScript mode the label becomes the single keyboard control.
	checkbox.setAttribute("tabindex", "-1");
	checkbox.setAttribute("aria-hidden", "true");
	toggle.setAttribute("role", "button");
	toggle.setAttribute("tabindex", "0");

	function sync() {
		toggle.setAttribute("aria-expanded", checkbox.checked ? "true" : "false");
	}

	sync();
	checkbox.addEventListener("change", sync);
	toggle.addEventListener("keydown", function (event) {
		if (event.key === "Enter" || event.key === " " || event.key === "Spacebar") {
			event.preventDefault();
			checkbox.checked = !checkbox.checked;
			sync();
		}
	});
})();
