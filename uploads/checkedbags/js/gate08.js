(function () {
  function nonceHeaders(extra) {
    var headers = { 'X-WP-Nonce': cbGate08.nonce };
    if (extra) {
      for (var key in extra) headers[key] = extra[key];
    }
    return headers;
  }

  function makePhotoTile(photo) {
    var tile = document.createElement('div');
    tile.className = 'photo-tile';
    tile.innerHTML =
      '<img src="' + photo.thumb_url + '" data-full="' + photo.full_url + '" class="photo-tile-img" alt="" loading="lazy">' +
      '<button class="photo-like-btn" data-photo-id="' + photo.id + '">' +
      '<i class="ti ti-heart" aria-hidden="true"></i>' +
      '<span class="photo-like-count">0</span>' +
      '</button>';
    return tile;
  }

  document.addEventListener('change', function (e) {
    var input = e.target.closest('.cb-upload-input');
    if (!input || !input.files || !input.files[0]) return;

    var label = input.closest('.cb-upload-btn');
    var tripId = label.getAttribute('data-trip-id');
    var grid = document.querySelector('.gallery-grid[data-trip-id="' + tripId + '"]');
    var originalHtml = label.innerHTML;

    label.classList.add('is-uploading');

    var formData = new FormData();
    formData.append('photo', input.files[0]);

    fetch(cbGate08.restUrl + 'trips/' + tripId + '/photos', {
      method: 'POST',
      headers: nonceHeaders(),
      body: formData
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        label.classList.remove('is-uploading');
        input.value = '';

        if (!result.ok) {
          alert(result.data.message || 'Could not upload this photo.');
          return;
        }

        if (grid) {
          var empty = grid.querySelector('.gallery-empty');
          if (empty) empty.remove();
          grid.insertBefore(makePhotoTile(result.data), grid.firstChild);
        }
      })
      .catch(function () {
        label.classList.remove('is-uploading');
        label.innerHTML = originalHtml;
        alert('Something went wrong. Please try again.');
      });
  });

  document.addEventListener('click', function (e) {
    var likeBtn = e.target.closest('.photo-like-btn');
    if (likeBtn) {
      var photoId = likeBtn.getAttribute('data-photo-id');
      likeBtn.disabled = true;

      fetch(cbGate08.restUrl + 'photos/' + photoId + '/like', {
        method: 'POST',
        headers: nonceHeaders()
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          likeBtn.disabled = false;
          if (!result.ok) return;

          var icon = likeBtn.querySelector('i');
          var count = likeBtn.querySelector('.photo-like-count');
          likeBtn.classList.toggle('is-liked', result.data.liked);
          icon.className = 'ti ' + (result.data.liked ? 'ti-heart-filled' : 'ti-heart');
          if (count) count.textContent = result.data.count;
        })
        .catch(function () {
          likeBtn.disabled = false;
        });
      return;
    }

    var img = e.target.closest('.photo-tile-img');
    if (img) {
      var lightbox = document.getElementById('cb-lightbox');
      if (!lightbox) return;
      var lightboxImg = lightbox.querySelector('img');
      lightboxImg.src = img.getAttribute('data-full');
      lightbox.classList.add('is-open');
      return;
    }

    var lightboxEl = e.target.closest('.cb-lightbox');
    if (lightboxEl && (e.target.closest('.cb-lightbox-close') || e.target === lightboxEl)) {
      lightboxEl.classList.remove('is-open');
      lightboxEl.querySelector('img').src = '';
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var lightbox = document.getElementById('cb-lightbox');
    if (lightbox && lightbox.classList.contains('is-open')) {
      lightbox.classList.remove('is-open');
      lightbox.querySelector('img').src = '';
    }
  });
})();
