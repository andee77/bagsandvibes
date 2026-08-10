(function () {
	"use strict";

	if ( typeof cbMemberProfile === 'undefined' ) {
		return;
	}

	// Composer + Timeline delete -- only present for a viewer who can see
	// the wall (the gated part of the profile). Guarded independently of
	// the Follow button below, which is part of the PUBLIC header and can
	// be present even when this section isn't (gate split: header is
	// public, Timeline/composer stay gated).
	var composer = document.getElementById( 'cb-wall-composer' );
	if ( composer ) {
		var wallOwnerId = composer.getAttribute( 'data-wall-owner-id' );
		var textarea     = document.getElementById( 'cb-wall-composer-text' );
		var resultEl     = document.getElementById( 'cb-wall-composer-result' );

		var postToWall = function ( showInFeed, btn ) {
			var content = textarea.value.trim();
			if ( ! content ) {
				resultEl.textContent = 'Write something before posting.';
				return;
			}

			btn.disabled = true;
			resultEl.textContent = 'Posting…';

			fetch( cbMemberProfile.restUrl + 'members/' + wallOwnerId + '/wall-posts', {
				method: 'POST',
				headers: { 'X-WP-Nonce': cbMemberProfile.nonce, 'Content-Type': 'application/json' },
				body: JSON.stringify( { content: content, show_in_feed: showInFeed } )
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( res ) {
					btn.disabled = false;
					if ( res.ok && res.body.success ) {
						textarea.value = '';
						resultEl.textContent = 'Posted!';
					} else {
						resultEl.textContent = 'Error: ' + ( res.body.message || 'Something went wrong.' );
					}
				} )
				.catch( function () {
					btn.disabled = false;
					resultEl.textContent = 'Request failed — please try again.';
				} );
		};

		document.getElementById( 'cb-wall-post-profile-btn' ).addEventListener( 'click', function () {
			postToWall( false, this );
		} );
		document.getElementById( 'cb-wall-post-profile-feed-btn' ).addEventListener( 'click', function () {
			postToWall( true, this );
		} );

		document.addEventListener( 'click', function ( e ) {
			var deleteBtn = e.target.closest( '.member-profile-post-delete' );
			if ( ! deleteBtn ) {
				return;
			}

			if ( ! confirm( 'Delete this post? This cannot be undone.' ) ) {
				return;
			}

			var topicId = deleteBtn.getAttribute( 'data-topic-id' );
			deleteBtn.disabled = true;

			fetch( cbMemberProfile.restUrl + 'members/' + wallOwnerId + '/wall-posts/' + topicId, {
				method: 'DELETE',
				headers: { 'X-WP-Nonce': cbMemberProfile.nonce }
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( res ) {
					if ( res.ok && res.body.success ) {
						deleteBtn.closest( '.member-profile-post' ).remove();
					} else {
						deleteBtn.disabled = false;
						alert( 'Error: ' + ( res.body.message || 'Could not delete this post.' ) );
					}
				} )
				.catch( function () {
					deleteBtn.disabled = false;
					alert( 'Request failed — please try again.' );
				} );
		} );
	}

	// Follow/Unfollow -- part of the public header on the profile page, and
	// one-per-card on Find Members, so this is event-delegated to the class
	// (not a single getElementById) to handle any number of buttons on one
	// page, not just the profile page's single instance.
	document.addEventListener( 'click', function ( e ) {
		var followBtn = e.target.closest( '.cb-follow-btn' );
		if ( ! followBtn ) {
			return;
		}

		var profileUserId = followBtn.getAttribute( 'data-profile-user-id' );
		var isFollowing   = followBtn.getAttribute( 'data-following' ) === '1';
		var method        = isFollowing ? 'DELETE' : 'POST';

		followBtn.disabled = true;

		fetch( cbMemberProfile.restUrl + 'members/' + profileUserId + '/follow', {
			method: method,
			headers: { 'X-WP-Nonce': cbMemberProfile.nonce }
		} )
			.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
			.then( function ( res ) {
				followBtn.disabled = false;
				if ( res.ok && res.body.success ) {
					var nowFollowing = !! res.body.following;
					followBtn.setAttribute( 'data-following', nowFollowing ? '1' : '0' );
					followBtn.textContent = nowFollowing ? 'Unfollow' : 'Follow';
					followBtn.classList.toggle( 'btn-ghost', nowFollowing );
					followBtn.classList.toggle( 'btn-ticket', ! nowFollowing );
				} else {
					alert( 'Error: ' + ( res.body.message || 'Something went wrong.' ) );
				}
			} )
			.catch( function () {
				followBtn.disabled = false;
				alert( 'Request failed — please try again.' );
			} );
	} );
})();
