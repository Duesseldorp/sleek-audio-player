/**
 * Sleek Audio Player - Admin JavaScript
 * WordPress Media Library Integration
 * 
 * @author Martin Gräbing
 * @link https://www.duesseldorp.de
 * @license GPL-2.0-or-later
 */

(function($) {
    'use strict';
    
    // === Stability: Error logging ===
    function sapLog(message, data) {
        if (console && console.log) {
            console.log('[SAP Admin]', message, data || '');
        }
    }
    
    function sapError(message, error) {
        if (console && console.error) {
            console.error('[SAP Admin Error]', message, error || '');
        }
    }

    // === Waveform Auto-Analysis ===
    
    // Queue to prevent race conditions and browser overload
    var waveformQueue = [];
    var isProcessingQueue = false;
    var analyzedIds = {}; // Prevent duplicate analysis in same session
    
    async function analyzeWaveformForTrack(attachmentId, audioUrl) {
        if (!window.sapWaveform || !sapWaveform.autoAnalyze) {
            sapLog('Waveform auto-analysis disabled');
            return;
        }
        
        // Validate inputs
        if (!attachmentId || attachmentId <= 0) {
            sapError('Invalid attachment ID:', attachmentId);
            return;
        }
        
        // Prevent duplicate analysis in same session
        if (analyzedIds[attachmentId]) {
            sapLog('Already queued/analyzed in this session:', attachmentId);
            return;
        }
        analyzedIds[attachmentId] = true;
        
        // Add to queue instead of immediate processing
        waveformQueue.push({ id: attachmentId, url: audioUrl });
        sapLog('Queued for analysis:', attachmentId, '(Queue size:', waveformQueue.length + ')');
        
        processWaveformQueue();
    }
    
    async function processWaveformQueue() {
        if (isProcessingQueue || waveformQueue.length === 0) {
            return;
        }
        
        isProcessingQueue = true;
        
        while (waveformQueue.length > 0) {
            var item = waveformQueue.shift();
            await processWaveformItem(item.id, item.url);
            
            // Small delay between analyses to prevent browser overload
            if (waveformQueue.length > 0) {
                await new Promise(function(r) { setTimeout(r, 200); });
            }
        }
        
        isProcessingQueue = false;
    }
    
    async function processWaveformItem(attachmentId, audioUrl) {
        sapLog('Processing waveform for attachment', attachmentId);
        
        try {
            // First check if waveform already exists
            var checkResponse = await $.post(sapWaveform.ajaxUrl, {
                action: 'sap_auto_analyze_waveform',
                nonce: sapWaveform.nonce,
                attachment_id: attachmentId
            });
            
            if (checkResponse.success && checkResponse.data.already_analyzed) {
                sapLog('Waveform already exists for', attachmentId);
                return;
            }
            
            if (checkResponse.success && checkResponse.data.needs_client_analysis) {
                var urlToAnalyze = checkResponse.data.url || audioUrl;
                
                // Validate URL before fetch
                if (!urlToAnalyze || typeof urlToAnalyze !== 'string') {
                    sapError('No valid URL for attachment', attachmentId);
                    return;
                }
                
                // Perform client-side analysis
                var peaks = await analyzeAudioClient(urlToAnalyze);
                
                if (peaks && peaks.length > 0) {
                    // Save the waveform
                    await $.post(sapWaveform.ajaxUrl, {
                        action: 'sap_save_auto_waveform',
                        nonce: sapWaveform.nonce,
                        attachment_id: attachmentId,
                        peaks: JSON.stringify(peaks)
                    });
                    sapLog('Waveform saved for attachment', attachmentId);
                }
            }
        } catch (e) {
            sapError('Waveform analysis failed for ' + attachmentId, e);
        }
    }
    
    // Client-side audio analysis using Web Audio API
    async function analyzeAudioClient(url) {
        return new Promise(function(resolve) {
            // Check for Web Audio API support
            if (!window.AudioContext && !window.webkitAudioContext) {
                sapError('Web Audio API not supported');
                resolve(null);
                return;
            }
            
            var audioContext = null;
            
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            } catch (e) {
                sapError('Failed to create AudioContext', e);
                resolve(null);
                return;
            }
            
            // Cleanup helper to prevent memory leaks
            function cleanup() {
                if (audioContext && audioContext.state !== 'closed') {
                    try {
                        audioContext.close();
                    } catch (e) {
                        // Ignore close errors
                    }
                }
            }
            
            fetch(url, { credentials: 'same-origin' })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.arrayBuffer();
                })
                .then(function(arrayBuffer) {
                    // Check for empty or too small files
                    if (!arrayBuffer || arrayBuffer.byteLength < 1000) {
                        throw new Error('File too small or empty');
                    }
                    return audioContext.decodeAudioData(arrayBuffer);
                })
                .then(function(audioBuffer) {
                    var channelData = audioBuffer.getChannelData(0);
                    var peaks = extractPeaks(channelData, 100);
                    cleanup();
                    sapLog('Audio analyzed successfully, peaks:', peaks.length);
                    resolve(peaks);
                })
                .catch(function(error) {
                    sapError('Audio decode failed', error);
                    cleanup();
                    resolve(null);
                });
        });
    }
    
    // Extract peak values from audio data
    function extractPeaks(channelData, peakCount) {
        const peaks = [];
        const samplesPerPeak = Math.floor(channelData.length / peakCount);
        
        for (let i = 0; i < peakCount; i++) {
            const start = i * samplesPerPeak;
            const end = Math.min(start + samplesPerPeak, channelData.length);
            
            let max = 0;
            for (let j = start; j < end; j++) {
                const abs = Math.abs(channelData[j]);
                if (abs > max) max = abs;
            }
            peaks.push(max);
        }
        
        // Normalize to 0-1 range
        const maxPeak = Math.max(...peaks);
        if (maxPeak > 0) {
            return peaks.map(p => Math.round((p / maxPeak) * 1000) / 1000);
        }
        return peaks;
    }

    $(document).ready(function() {
        
        // Check if wp.media is available
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            sapLog('WordPress Media Library not available');
            return;
        }
        
        var trackIndex = parseInt($('#sap-track-index').val()) || 0;

        // ===== Select single track =====
        $(document).on('click', '.sap-select-audio', function(e) {
            e.preventDefault();
            var $row = $(this).closest('.sap-track-row');
            
            var mediaFrame = wp.media({
                title: 'Select audio file',
                button: { text: 'Select' },
                library: { type: 'audio' },
                multiple: false
            });

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                
                $row.find('.sap-track-url').val(attachment.url);
                $row.find('.sap-track-id').val(attachment.id);
                $row.find('.sap-track-filename').text(attachment.filename);
                
                // Auto-set title if empty
                if (!$row.find('.sap-track-title').val()) {
                    var title = attachment.title || attachment.filename.replace(/\.[^/.]+$/, '');
                    $row.find('.sap-track-title').val(title);
                }
                
                // Read artist from ID3 tags
                if (attachment.meta && attachment.meta.artist && !$row.find('.sap-track-artist').val()) {
                    $row.find('.sap-track-artist').val(attachment.meta.artist);
                }
                
                // Read album from ID3 tags
                if (attachment.meta && attachment.meta.album && !$row.find('.sap-track-album').val()) {
                    $row.find('.sap-track-album').val(attachment.meta.album);
                }
                
                // Auto-set duration if available
                if (attachment.fileLength) {
                    $row.find('.sap-track-duration').val(attachment.fileLength);
                }
                
                // Auto-analyze waveform in background
                analyzeWaveformForTrack(attachment.id, attachment.url);
            });

            mediaFrame.open();
        });

        // ===== Select cover per track =====
        $(document).on('click', '.sap-select-cover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var $row = $btn.closest('.sap-track-row');
            
            // Create new frame for each click
            var frame = wp.media({
                title: 'Select cover image for track',
                button: { text: 'Use as cover' },
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                
                var thumbUrl = attachment.sizes && attachment.sizes.medium 
                    ? attachment.sizes.medium.url 
                    : (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                
                $row.find('.sap-track-cover-id').val(attachment.id);
                $row.find('.sap-track-cover-url').val(attachment.url);
                $row.find('.sap-track-cover-preview').html('<img src="' + thumbUrl + '" />');
            });

            frame.open();
        });

        // ===== Add new track =====
        $('#sap-add-track').on('click', function() {
            addTrackRow();
        });

        // ===== Multiple tracks from Media Library =====
        $('#sap-bulk-add').on('click', function(e) {
            e.preventDefault();
            
            var mediaFrame = wp.media({
                title: 'Select audio files',
                button: { text: 'Add' },
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
                    
                    // Auto-analyze waveform in background
                    analyzeWaveformForTrack(attachment.id, attachment.url);
                });
            });

            mediaFrame.open();
        });

        // ===== Remove track =====
        $(document).on('click', '.sap-remove-track', function() {
            $(this).closest('.sap-track-row').fadeOut(200, function() {
                $(this).remove();
            });
        });

        // ===== Helper function: Add track row =====
        function addTrackRow(data) {
            data = data || {};
            
            var html = '<div class="sap-track-row" data-index="' + trackIndex + '">' +
                '<span class="sap-track-handle">☰</span>' +
                '<div class="sap-track-cover-preview"><span class="sap-no-cover">🎵</span></div>' +
                '<input type="hidden" name="sap_tracks[' + trackIndex + '][cover_id]" class="sap-track-cover-id" value="" />' +
                '<input type="hidden" name="sap_tracks[' + trackIndex + '][cover_url]" class="sap-track-cover-url" value="" />' +
                '<button type="button" class="button sap-select-cover" title="Select cover">🖼️</button>' +
                '<input type="text" name="sap_tracks[' + trackIndex + '][title]" ' +
                    'class="sap-track-title" placeholder="Title" value="" />' +
                '<input type="text" name="sap_tracks[' + trackIndex + '][artist]" ' +
                    'class="sap-track-artist" placeholder="Artist" value="" />' +
                '<input type="hidden" name="sap_tracks[' + trackIndex + '][url]" ' +
                    'class="sap-track-url" value="" />' +
                '<input type="hidden" name="sap_tracks[' + trackIndex + '][attachment_id]" ' +
                    'class="sap-track-id" value="" />' +
                '<input type="hidden" name="sap_tracks[' + trackIndex + '][duration]" ' +
                    'class="sap-track-duration" value="" />' +
                '<span class="sap-track-filename">No file</span>' +
                '<button type="button" class="button sap-select-audio">🎵 Audio</button>' +
                '<input type="url" name="sap_tracks[' + trackIndex + '][spotify]" ' +
                    'class="sap-track-link sap-link-spotify" placeholder="🎵 Spotify" value="" />' +
                '<input type="url" name="sap_tracks[' + trackIndex + '][apple]" ' +
                    'class="sap-track-link sap-link-apple" placeholder="🍎 Apple Music" value="" />' +
                '<input type="url" name="sap_tracks[' + trackIndex + '][amazon]" ' +
                    'class="sap-track-link sap-link-amazon" placeholder="📦 Amazon" value="" />' +
                '<input type="url" name="sap_tracks[' + trackIndex + '][soundcloud]" ' +
                    'class="sap-track-link sap-link-soundcloud" placeholder="☁️ SoundCloud" value="" />' +
                '<label class="sap-download-label">' +
                    '<input type="checkbox" name="sap_tracks[' + trackIndex + '][downloadable]" value="1" /> DL' +
                '</label>' +
                '<button type="button" class="button sap-remove-track">✕</button>' +
                '</div>';
            
            var $row = $(html);
            
            // Safe value assignment after DOM creation (XSS protection)
            if (data.title) $row.find('.sap-track-title').val(data.title);
            if (data.artist) $row.find('.sap-track-artist').val(data.artist);
            if (data.url) $row.find('.sap-track-url').val(data.url);
            if (data.attachment_id) $row.find('.sap-track-id').val(data.attachment_id);
            if (data.filename) $row.find('.sap-track-filename').text(data.filename);
            
            $('#sap-tracks-container').append($row);
            trackIndex++;
            $('#sap-track-index').val(trackIndex);
        }

        // ===== Drag & Drop sorting (optional, with jQuery UI) =====
        if ($.fn.sortable) {
            $('#sap-tracks-container').sortable({
                handle: '.sap-track-handle',
                placeholder: 'sap-track-placeholder',
                opacity: 0.7
            });
        }

    });

})(jQuery);
