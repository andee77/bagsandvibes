/*
 * Public Trip Landing (redesign): hero background video control.
 * Loaded only by the new landing template. The video has no source until
 * this script decides to load one, so phones, visitors who prefer reduced
 * motion and data-saver users never download it and see the poster picture.
 * A Pause / Play button (WCAG 2.2.2) appears once the video is playing.
 */
(function () {
	'use strict';

	var video = document.querySelector('.cbv-lp-hero-video[data-src]');
	if (!video) { return; }

	var mq = function (q) { return !!(window.matchMedia && window.matchMedia(q).matches); };
	var saveData = !!(navigator.connection && navigator.connection.saveData);
	if (mq('(prefers-reduced-motion: reduce)') || mq('(max-width: 767px)') || saveData) { return; }

	var button = document.querySelector('.cbv-lp-video-toggle');
	var small = video.getAttribute('data-src-small');
	var big = video.getAttribute('data-src');
	video.src = (small && window.innerWidth < 1400) ? small : big;

	video.addEventListener('playing', function () {
		video.hidden = false;
		if (button) { button.hidden = false; }
	}, { once: true });

	var play = video.play();
	if (play && typeof play.catch === 'function') {
		play.catch(function () { /* autoplay blocked: the poster stays */ });
	}

	if (button) {
		button.addEventListener('click', function () {
			if (video.paused) {
				video.play();
				button.textContent = 'Pause background video';
				button.setAttribute('aria-pressed', 'false');
			} else {
				video.pause();
				button.textContent = 'Play background video';
				button.setAttribute('aria-pressed', 'true');
			}
		});
	}
})();
