/**
 * Simple Audio Player - Admin JavaScript
 * WordPress Media Library Integration
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Prüfen ob wp.media verfügbar ist
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            console.error('SAP Admin: wp.media nicht verfügbar');
            return;
        }
        
        var trackIndex = parseInt($('#sap-track-index').val()) || 0;

        // ===== Einzelnen Track auswählen =====
        $(document).on('click', '.sap-select-audio', function(e) {
            e.preventDefault();
            var $row = $(this).closest('.sap-track-row');
            
            var mediaFrame = wp.media({
                title: 'Audio-Datei auswählen',
                button: { text: 'Auswählen' },
                library: { type: 'audio' },
                multiple: false
            });

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                
                $row.find('.sap-track-url').val(attachment.url);
                $row.find('.sap-track-id').val(attachment.id);
                $row.find('.sap-track-filename').text(attachment.filename);
                
                // Titel automatisch setzen, wenn leer
                if (!$row.find('.sap-track-title').val()) {
                    var title = attachment.title || attachment.filename.replace(/\.[^/.]+$/, '');
                    $row.find('.sap-track-title').val(title);
                }
                
                // Artist aus ID3-Tags auslesen
                if (attachment.meta && attachment.meta.artist && !$row.find('.sap-track-artist').val()) {
                    $row.find('.sap-track-artist').val(attachment.meta.artist);
                }
                
                // Album aus ID3-Tags auslesen
                if (attachment.meta && attachment.meta.album && !$row.find('.sap-track-album').val()) {
                    $row.find('.sap-track-album').val(attachment.meta.album);
                }
                
                // Dauer automatisch setzen wenn verfügbar
                if (attachment.fileLength) {
                    $row.find('.sap-track-duration').val(attachment.fileLength);
                }
            });

            mediaFrame.open();
        });

        // ===== Cover pro Track auswählen =====
        $(document).on('click', '.sap-select-cover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var $row = $btn.closest('.sap-track-row');
            
            console.log('Cover-Button geklickt', $row.length);
            
            // Neuen Frame für jeden Klick erstellen
            var frame = wp.media({
                title: 'Cover-Bild für Track auswählen',
                button: { text: 'Als Cover verwenden' },
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                console.log('Bild ausgewählt:', attachment);
                
                var thumbUrl = attachment.sizes && attachment.sizes.thumbnail 
                    ? attachment.sizes.thumbnail.url 
                    : attachment.url;
                
                $row.find('.sap-track-cover-id').val(attachment.id);
                $row.find('.sap-track-cover-url').val(attachment.url);
                $row.find('.sap-track-cover-preview').html('<img src="' + thumbUrl + '" />');
                
                console.log('Cover gesetzt für Row:', $row.data('index'));
            });

            frame.open();
        });

        // ===== Neuen Track hinzufügen =====
        $('#sap-add-track').on('click', function() {
            addTrackRow();
        });

        // ===== Mehrere Tracks aus Mediathek =====
        $('#sap-bulk-add').on('click', function(e) {
            e.preventDefault();
            
            var mediaFrame = wp.media({
                title: 'Audio-Dateien auswählen',
                button: { text: 'Hinzufügen' },
                library: { type: 'audio' },
                multiple: true
            });

            mediaFrame.on('select', function() {
                var attachments = mediaFrame.state().get('selection').toJSON();
                
                attachments.forEach(function(attachment) {
                    var title = attachment.title || attachment.filename.replace(/\.[^/.]+$/, '');
                    addTrackRow({
                        title: title,
                        url: attachment.url,
                        attachment_id: attachment.id,
                        filename: attachment.filename
                    });
                });
            });

            mediaFrame.open();
        });

        // ===== Track entfernen =====
        $(document).on('click', '.sap-remove-track', function() {
            $(this).closest('.sap-track-row').fadeOut(200, function() {
                $(this).remove();
            });
        });

        // ===== Hilfsfunktion: Track-Zeile hinzufügen =====
        function addTrackRow(data) {
            data = data || {};
            
            var html = '<div class="sap-track-row" data-index="' + trackIndex + '">' +
                '<span class="sap-track-handle">☰</span>' +
                '<div class="sap-track-cover-preview"><span class="sap-no-cover">🎵</span></div>' +
                '<input type="hidden" name="sap_tracks[' + trackIndex + '][cover_id]" class="sap-track-cover-id" value="" />' +
                '<input type="hidden" name="sap_tracks[' + trackIndex + '][cover_url]" class="sap-track-cover-url" value="" />' +
                '<button type="button" class="button sap-select-cover" title="Cover auswählen">🖼️</button>' +
                '<input type="text" name="sap_tracks[' + trackIndex + '][title]" ' +
                    'class="sap-track-title" placeholder="Titel" value="' + (data.title || '') + '" />' +
                '<input type="text" name="sap_tracks[' + trackIndex + '][artist]" ' +
                    'class="sap-track-artist" placeholder="Artist" value="' + (data.artist || '') + '" />' +
                '<input type="hidden" name="sap_tracks[' + trackIndex + '][url]" ' +
                    'class="sap-track-url" value="' + (data.url || '') + '" />' +
                '<input type="hidden" name="sap_tracks[' + trackIndex + '][attachment_id]" ' +
                    'class="sap-track-id" value="' + (data.attachment_id || '') + '" />' +
                '<span class="sap-track-filename">' + (data.filename || 'Keine Datei') + '</span>' +
                '<button type="button" class="button sap-select-audio">🎵 Audio</button>' +
                '<input type="url" name="sap_tracks[' + trackIndex + '][spotify]" ' +
                    'class="sap-track-link sap-link-spotify" placeholder="🎵 Spotify" value="" />' +
                '<input type="url" name="sap_tracks[' + trackIndex + '][apple]" ' +
                    'class="sap-track-link sap-link-apple" placeholder="🍎 Apple Music" value="" />' +
                '<input type="url" name="sap_tracks[' + trackIndex + '][amazon]" ' +
                    'class="sap-track-link sap-link-amazon" placeholder="📦 Amazon" value="" />' +
                '<label class="sap-download-label">' +
                    '<input type="checkbox" name="sap_tracks[' + trackIndex + '][downloadable]" value="1" /> DL' +
                '</label>' +
                '<button type="button" class="button sap-remove-track">✕</button>' +
                '</div>';
            
            $('#sap-tracks-container').append(html);
            trackIndex++;
            $('#sap-track-index').val(trackIndex);
        }

        // ===== Drag & Drop Sortierung (optional, mit jQuery UI) =====
        if ($.fn.sortable) {
            $('#sap-tracks-container').sortable({
                handle: '.sap-track-handle',
                placeholder: 'sap-track-placeholder',
                opacity: 0.7
            });
        }

    });

})(jQuery);
