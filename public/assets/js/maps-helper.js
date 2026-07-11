/* Backend-powered Google Maps/Places helper
   - Loads map with a domain-restricted MAP API key (already on the page)
   - Uses backend endpoints with IP-restricted PLACE API key for:
     - Autocomplete:  GET /api/get-map-places-list?input=...
     - Details/Geocode: GET /api/get-map-place-details?place_id=... OR ?latitude=..&longitude=..
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
        // Ensure container is positioned
        var computedStyle = window.getComputedStyle(mapElement);
        if (computedStyle.position === 'static') {
            mapElement.style.position = 'relative';
        }
        mapElement.appendChild(overlay);
        return overlay;
    }

    function showLoader(map) {
        var el = (map && map.getDiv) ? map.getDiv() : map;
        if (!el) return;
        var overlay = getOrCreateLoader(el);
        overlay.style.display = 'flex';
    }

    function hideLoader(map) {
        var el = (map && map.getDiv) ? map.getDiv() : map;
        if (!el) return;
        var overlay = el.querySelector('.map-loading-overlay');
        if (overlay) { overlay.style.display = 'none'; }
    }

    function createSuggestionsContainer($input) {
        var $existing = $input.next('#places-suggestions');
        if ($existing.length) { return $existing; }
        var $suggestions = $('<div id="places-suggestions" class="list-group" style="position:absolute; z-index: 2000; width: 100%;"></div>');
        $input.after($suggestions); // positioned in normal flow; page should handle layout
        return $suggestions;
    }

    function setFromGeocodeResult(result, latLngObj, selectors, map, marker) {
        var address_components = (result && result.address_components) ? result.address_components : [];
        var city, state, country, full_address;

        for (var i = 0; i < address_components.length; i++) {
            var types = address_components[i].types || [];

            if (types.indexOf('locality') !== -1) {
                city = address_components[i].long_name;
            }
            else if (types.indexOf('administrative_area_level_2') !== -1 && !city) {
                // fallback when locality is missing
                city = address_components[i].long_name;
            }
            else if (types.indexOf('administrative_area_level_3') !== -1 && !city) {
                // secondary fallback
                city = address_components[i].long_name;
            }
            else if (types.indexOf('administrative_area_level_1') !== -1) {
                state = address_components[i].long_name;
            }
            else if (types.indexOf('country') !== -1) {
                country = address_components[i].long_name;
            }
        }

        full_address = result && result.formatted_address ? result.formatted_address : '';

        if (selectors.inputSelector) { $(selectors.inputSelector).val(city || ''); }
        if (selectors.citySelector) { $(selectors.citySelector).val(city || ''); }
        if (selectors.countrySelector) { $(selectors.countrySelector).val(country || ''); }
        if (selectors.stateSelector) { $(selectors.stateSelector).val(state || ''); }
        if (selectors.addressSelector) { $(selectors.addressSelector).val(full_address || ''); }
        if (selectors.latitudeSelector) { $(selectors.latitudeSelector).val(latLngObj.lat); }
        if (selectors.longitudeSelector) { $(selectors.longitudeSelector).val(latLngObj.lng); }

        var gLatLng = new google.maps.LatLng(latLngObj.lat, latLngObj.lng);
        map.setCenter(gLatLng);
        map.setZoom(17);
        marker.position = gLatLng;
        marker.map = map; // Show marker
    }

    function fetchPlaceDetails(params) {
        return $.get('/api/get-map-place-details', params);
    }

    function attachAutocomplete($input, selectors, map, marker) {
        var $suggestions = createSuggestionsContainer($input);
        var debounceTimer;
        $input.on('input', function () {
            clearTimeout(debounceTimer);
            var q = $(this).val();
            if (!q || q.length < 3) { $suggestions.empty().hide(); return; }
            debounceTimer = setTimeout(function () {
                showLoader(map);
                $.get('/api/get-map-places-list', { input: q })
                    .done(function (resp) {
                        var data = resp && resp.data ? resp.data : {};
                        var preds = data.predictions || [];
                        $suggestions.empty();
                        preds.slice(0, 7).forEach(function (p) {
                            var text = p.description || (p.structured_formatting && p.structured_formatting.main_text) || '';
                            var $item = $('<a href="#" class="list-group-item list-group-item-action"></a>');
                            $item.text(text);
                            $item.on('click', function (e) {
                                e.preventDefault();
                                $suggestions.empty().hide();
                                if (p.place_id) {
                                    showLoader(map);
                                    fetchPlaceDetails({ place_id: p.place_id }).done(function (r) {
                                        var d = r && r.data ? r.data : {};
                                        var results = d.result ? [d.result] : (d.results || []);
                                        if (results.length) {
                                            var geo = (results[0].geometry && results[0].geometry.location) || {};
                                            if (typeof geo.lat === 'number' && typeof geo.lng === 'number') {
                                                setFromGeocodeResult(results[0], { lat: geo.lat, lng: geo.lng }, selectors, map, marker);
                                            }
                                        }
                                    }).always(function () { hideLoader(map); });
                                }
                            });
                            $suggestions.append($item);
                        });
                        if (preds.length) { $suggestions.show(); } else { $suggestions.hide(); }
                    }).always(function () { hideLoader(map); });
            }, 300);
        });
    }

    function initBackendPlacesMap(options) {
        var selectors = options || {};
        var defaultLat = parseFloat($(selectors.defaultLatitudeSelector || '#default-latitude').val() || -33.8688);
        var defaultLng = parseFloat($(selectors.defaultLongitudeSelector || '#default-longitude').val() || 151.2195);
        if (isNaN(defaultLat)) defaultLat = -33.8688;
        if (isNaN(defaultLng)) defaultLng = 151.2195;

        var mapEl = document.getElementById(selectors.mapElementId || 'map');
        var map = new google.maps.Map(mapEl, {
            mapId: GOOGLE_MAP_ID, // Required for AdvancedMarkerElement
            center: { lat: defaultLat, lng: defaultLng },
            zoom: 15
        });
        var marker = new google.maps.marker.AdvancedMarkerElement({
            gmpDraggable: true,
            position: { lat: defaultLat, lng: defaultLng },
            map: map
        });

        // Marker drag → reverse geocode via backend
        google.maps.event.addListener(marker, 'dragend', function (event) {
            var lat = event.latLng.lat();
            var lng = event.latLng.lng();
            showLoader(map);
            fetchPlaceDetails({ latitude: lat, longitude: lng })
                .done(function (resp) {
                    var d = resp && resp.data ? resp.data : {};
                    var results = d.result ? [d.result] : (d.results || []);
                    if (results.length) {
                        setFromGeocodeResult(results[0], { lat: lat, lng: lng }, selectors, map, marker);
                    } else {
                        // Fallback: at least update lat/lng and marker position
                        if (selectors.latitudeSelector) { $(selectors.latitudeSelector).val(lat); }
                        if (selectors.longitudeSelector) { $(selectors.longitudeSelector).val(lng); }
                        var gLatLng = new google.maps.LatLng(lat, lng);
                        map.setCenter(gLatLng);
                        marker.position = gLatLng;
                        marker.map = map; // Show marker
                    }
                }).always(function () { hideLoader(map); });
        });

        // Text input → backend autocomplete + details
        if (selectors.inputSelector) {
            attachAutocomplete($(selectors.inputSelector), selectors, map, marker);
        }

        return { map: map, marker: marker };
    }

    global.initBackendPlacesMap = initBackendPlacesMap;
})(window);


