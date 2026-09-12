/* Free map helper — OpenStreetMap (Leaflet) + GeoNames backend.
   Independent of the Google maps-helper.js. Takes the SAME options object as
   initBackendPlacesMap so the blade markup stays identical, but renders with Leaflet
   and talks to the backend GeoNames endpoints:
     - Autocomplete:       GET /api/get-osm-places-list?input=...
     - Details/Reverse:    GET /api/get-osm-place-details?place_id=... OR ?latitude=..&longitude=..
*/
(function (global) {
    function getOrCreateLoader(mapElement) {
        var existing = mapElement.querySelector('.map-loading-overlay');
        if (existing) { return existing; }
        var overlay = document.createElement('div');
        overlay.className = 'map-loading-overlay';
        overlay.style.position = 'absolute';
        overlay.style.inset = '0px';
        overlay.style.background = 'rgba(255,255,255,0.6)';
        overlay.style.display = 'none';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = '2001';
        var text = document.createElement('div');
        text.style.padding = '8px 12px';
        text.style.background = '#fff';
        text.style.border = '1px solid #ddd';
        text.style.borderRadius = '4px';
        text.style.boxShadow = '0 2px 6px rgba(0,0,0,0.15)';
        text.style.color = '#333';
        text.style.fontSize = '13px';
        text.textContent = 'Loading...';
        overlay.appendChild(text);
        var computedStyle = window.getComputedStyle(mapElement);
        if (computedStyle.position === 'static') {
            mapElement.style.position = 'relative';
        }
        mapElement.appendChild(overlay);
        return overlay;
    }

    function showLoader(mapEl) {
        if (!mapEl) return;
        getOrCreateLoader(mapEl).style.display = 'flex';
    }

    function hideLoader(mapEl) {
        if (!mapEl) return;
        var overlay = mapEl.querySelector('.map-loading-overlay');
        if (overlay) { overlay.style.display = 'none'; }
    }

    function createSuggestionsContainer($input) {
        var $existing = $input.next('#places-suggestions');
        if ($existing.length) { return $existing; }
        var $suggestions = $('<div id="places-suggestions" class="list-group" style="position:absolute; z-index: 2000; width: 100%;"></div>');
        $input.after($suggestions);
        return $suggestions;
    }

    // Write a resolved place into the form fields and recentre the map/marker.
    function applyResult(fields, latLng, selectors, ctx) {
        if (selectors.citySelector) { $(selectors.citySelector).val(fields.city || ''); }
        if (selectors.inputSelector) { $(selectors.inputSelector).val(fields.city || ''); }
        if (selectors.stateSelector) { $(selectors.stateSelector).val(fields.state || ''); }
        if (selectors.countrySelector) { $(selectors.countrySelector).val(fields.country || ''); }
        if (selectors.addressSelector) { $(selectors.addressSelector).val(fields.address || ''); }
        if (selectors.latitudeSelector) { $(selectors.latitudeSelector).val(latLng.lat); }
        if (selectors.longitudeSelector) { $(selectors.longitudeSelector).val(latLng.lng); }

        ctx.map.setView([latLng.lat, latLng.lng], 17);
        ctx.marker.setLatLng([latLng.lat, latLng.lng]);
    }

    function getLocaleHeader() {
        return { 'Content-Language': (window.currentLocale || 'en') };
    }

    function reverseGeocode(latLng, selectors, ctx) {
        showLoader(ctx.mapEl);
        $.ajax({ url: '/api/get-osm-place-details', data: { latitude: latLng.lat, longitude: latLng.lng }, headers: getLocaleHeader() })
            .done(function (resp) {
                var d = (resp && resp.data) ? resp.data : {};
                var result = d.result || null;
                if (result) {
                    var loc = (result.geometry && result.geometry.location) || latLng;
                    applyResult(result, { lat: loc.lat || latLng.lat, lng: loc.lng || latLng.lng }, selectors, ctx);
                } else {
                    // Fallback: keep the dragged coordinates even if no place was found
                    if (selectors.latitudeSelector) { $(selectors.latitudeSelector).val(latLng.lat); }
                    if (selectors.longitudeSelector) { $(selectors.longitudeSelector).val(latLng.lng); }
                }
            })
            .always(function () { hideLoader(ctx.mapEl); });
    }

    function attachAutocomplete($input, selectors, ctx) {
        var $suggestions = createSuggestionsContainer($input);
        var debounceTimer;
        $input.on('input', function () {
            clearTimeout(debounceTimer);
            var q = $(this).val();
            if (!q || q.length < 3) { $suggestions.empty().hide(); return; }
            debounceTimer = setTimeout(function () {
                showLoader(ctx.mapEl);
                $.ajax({ url: '/api/get-osm-places-list', data: { input: q }, headers: getLocaleHeader() })
                    .done(function (resp) {
                        var data = (resp && resp.data) ? resp.data : {};
                        var preds = data.predictions || [];
                        $suggestions.empty();
                        preds.slice(0, 7).forEach(function (p) {
                            var $item = $('<a href="#" class="list-group-item list-group-item-action"></a>');
                            $item.text(p.description || '');
                            $item.on('click', function (e) {
                                e.preventDefault();
                                $suggestions.empty().hide();
                                // GeoNames search already returns coordinates + admin names,
                                // so we can fill everything without a second request.
                                if (typeof p.lat === 'number' && typeof p.lng === 'number') {
                                    applyResult({
                                        city: p.city,
                                        state: p.state,
                                        country: p.country,
                                        address: p.description
                                    }, { lat: p.lat, lng: p.lng }, selectors, ctx);
                                }
                            });
                            $suggestions.append($item);
                        });
                        if (preds.length) { $suggestions.show(); } else { $suggestions.hide(); }
                    })
                    .always(function () { hideLoader(ctx.mapEl); });
            }, 300);
        });
    }

    function initOsmPlacesMap(options) {
        var selectors = options || {};
        var defaultLat = parseFloat($(selectors.defaultLatitudeSelector || '#default-latitude').val() || -33.8688);
        var defaultLng = parseFloat($(selectors.defaultLongitudeSelector || '#default-longitude').val() || 151.2195);
        if (isNaN(defaultLat)) defaultLat = -33.8688;
        if (isNaN(defaultLng)) defaultLng = 151.2195;

        var mapEl = document.getElementById(selectors.mapElementId || 'map');
        // Guard against double initialisation (some forms call initMap() more than once).
        if (!mapEl || mapEl._leaflet_id) { return; }
        var map = L.map(mapEl).setView([defaultLat, defaultLng], 15);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

        var ctx = { map: map, marker: marker, mapEl: mapEl };

        // Marker drag → reverse geocode via backend
        marker.on('dragend', function () {
            var latLng = marker.getLatLng();
            reverseGeocode({ lat: latLng.lat, lng: latLng.lng }, selectors, ctx);
        });

        // Text input → backend autocomplete
        if (selectors.inputSelector) {
            attachAutocomplete($(selectors.inputSelector), selectors, ctx);
        }

        // Leaflet needs a size recalculation when the container becomes visible
        setTimeout(function () { map.invalidateSize(); }, 200);

        return ctx;
    }

    global.initOsmPlacesMap = initOsmPlacesMap;
})(window);
