<?php
/**
 * Waveform Manager: generates and stores waveform peaks per attachment
 *
 * @package   Sleek_Audio_Player
 * @author    Martin Gräbing <kontakt@duesseldorp.de>
 * @copyright 2025-2026 Martin Gräbing
 * @license   GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 */
defined('ABSPATH') || exit;

/**
 * Waveform Manager Class
 * Generates and stores real waveform data for audio files
 */
class SleekAudio_Waveform_Manager {
    
    private static $instance = null;
    const PEAKS_COUNT = 100;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_waveform_menu'));
        add_action('wp_ajax_sap_analyze_waveform', array($this, 'ajax_analyze_waveform'));
        add_action('wp_ajax_sap_save_waveform', array($this, 'ajax_save_waveform'));
        add_action('wp_ajax_sap_get_pending_waveforms', array($this, 'ajax_get_pending_waveforms'));
        add_action('wp_ajax_sap_auto_analyze_waveform', array($this, 'ajax_auto_analyze_waveform'));
        add_action('wp_ajax_sap_save_auto_waveform', array($this, 'ajax_save_auto_waveform'));
    }
    
    /**
     * Add waveform menu
     */
    public function add_waveform_menu() {
        add_submenu_page(
            'edit.php?post_type=sap_playlist',
            __('Waveform Analysis', 'sleek-audio-player'),
            __('Waveforms', 'sleek-audio-player'),
            'manage_options',
            'sap-waveforms',
            array($this, 'render_waveform_page')
        );
    }
    
    /**
     * Get all audio attachments used in playlists
     */
    public function get_playlist_audio_attachments() {
        global $wpdb;
        
        $playlists = get_posts(array(
            'post_type' => 'sap_playlist',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));
        
        $attachments = array();
        
        foreach ($playlists as $playlist) {
            $tracks = get_post_meta($playlist->ID, '_sap_tracks', true);
            if (!empty($tracks) && is_array($tracks)) {
                foreach ($tracks as $track) {
                    if (!empty($track['attachment_id'])) {
                        $att_id = absint($track['attachment_id']);
                        if (!isset($attachments[$att_id])) {
                            $file_path = get_attached_file($att_id);
                            $waveform = get_post_meta($att_id, '_sap_waveform', true);
                            $attachments[$att_id] = array(
                                'id' => $att_id,
                                'title' => $track['title'] ?? get_the_title($att_id),
                                'url' => wp_get_attachment_url($att_id),
                                'file_path' => $file_path,
                                'has_waveform' => !empty($waveform),
                                'playlist' => $playlist->post_title,
                            );
                        }
                    }
                }
            }
        }
        
        return array_values($attachments);
    }
    
    /**
     * Get waveform data for an attachment
     */
    public static function get_waveform($attachment_id) {
        $waveform = get_post_meta($attachment_id, '_sap_waveform', true);
        if (!empty($waveform) && is_array($waveform)) {
            return $waveform;
        }
        return null;
    }
    
    /**
     * Save waveform data for an attachment
     */
    public static function save_waveform($attachment_id, $peaks) {
        if (!is_array($peaks) || count($peaks) < 10) {
            return false;
        }
        
        // Normalize peaks to 0-1 range
        $max = max($peaks);
        if ($max > 0) {
            $peaks = array_map(function($p) use ($max) {
                return round($p / $max, 3);
            }, $peaks);
        }
        
        update_post_meta($attachment_id, '_sap_waveform', $peaks);
        return true;
    }
    
    /**
     * AJAX: Get pending waveforms
     */
    public function ajax_get_pending_waveforms() {
        check_ajax_referer('sap_waveform_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachments = $this->get_playlist_audio_attachments();
        $pending = array_filter($attachments, function($a) {
            return !$a['has_waveform'];
        });
        
        wp_send_json_success(array(
            'pending' => array_values($pending),
            'total' => count($attachments),
            'analyzed' => count($attachments) - count($pending),
        ));
    }
    
    /**
     * AJAX: Save waveform from client-side analysis
     */
    public function ajax_save_waveform() {
        check_ajax_referer('sap_waveform_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; decoded and every element cast to float below
        $peaks = isset($_POST['peaks']) ? json_decode(wp_unslash($_POST['peaks']), true) : array();

        if (!$attachment_id || !is_array($peaks)) {
            wp_send_json_error(__('Invalid data', 'sleek-audio-player'));
        }

        $peaks = array_map('floatval', $peaks);
        
        if (self::save_waveform($attachment_id, $peaks)) {
            wp_send_json_success(__('Waveform saved', 'sleek-audio-player'));
        } else {
            wp_send_json_error(__('Failed to save waveform', 'sleek-audio-player'));
        }
    }
    
    /**
     * AJAX: Analyze single waveform (server-side with getID3)
     */
    public function ajax_analyze_waveform() {
        check_ajax_referer('sap_waveform_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(__('File not found', 'sleek-audio-player'));
        }
        
        // Try server-side analysis (basic approach using file sampling)
        $peaks = $this->analyze_audio_file($file_path);
        
        if ($peaks) {
            self::save_waveform($attachment_id, $peaks);
            wp_send_json_success(array('peaks' => $peaks, 'method' => 'server'));
        } else {
            // Return URL for client-side analysis
            wp_send_json_success(array(
                'needs_client_analysis' => true,
                'url' => wp_get_attachment_url($attachment_id),
            ));
        }
    }
    
    /**
     * Analyze audio file server-side
     * Basic approach: read MP3 frame headers to estimate amplitude
     */
    private function analyze_audio_file($file_path) {
        // Check for getID3 (comes with WordPress)
        if (!function_exists('wp_read_audio_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // For now, return null to trigger client-side analysis
        // Server-side MP3 parsing is complex without FFmpeg
        return null;
    }
    
    /**
     * AJAX: Auto-analyze waveform from playlist editor
     * Uses standard admin nonce for easier integration
     */
    public function ajax_auto_analyze_waveform() {
        check_ajax_referer('sap_admin_waveform', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error('Invalid attachment ID');
        }
        
        // Check if waveform already exists
        $existing = self::get_waveform($attachment_id);
        if ($existing) {
            wp_send_json_success(array(
                'already_analyzed' => true,
                'peaks' => $existing
            ));
            return;
        }
        
        // Return URL for client-side analysis
        wp_send_json_success(array(
            'needs_client_analysis' => true,
            'url' => wp_get_attachment_url($attachment_id),
            'attachment_id' => $attachment_id
        ));
    }
    
    /**
     * AJAX: Save waveform from auto-analysis
     */
    public function ajax_save_auto_waveform() {
        check_ajax_referer('sap_admin_waveform', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; size-limited, decoded, validated and every element clamped to a 0-1 float below
        $peaks_raw = isset($_POST['peaks']) ? wp_unslash($_POST['peaks']) : '';

        // Limit JSON size to prevent DoS (max ~5KB for 100 peaks)
        if (strlen($peaks_raw) > 5000) {
            wp_send_json_error('Data too large');
        }
        
        $peaks = json_decode($peaks_raw, true);
        
        // Validate peaks array
        if (!$attachment_id || !is_array($peaks) || count($peaks) < 10 || count($peaks) > 200) {
            wp_send_json_error(__('Invalid data', 'sleek-audio-player'));
        }
        
        // Verify attachment exists and is audio
        $mime_type = get_post_mime_type($attachment_id);
        if (!$mime_type || strpos($mime_type, 'audio/') !== 0) {
            wp_send_json_error('Invalid attachment type');
        }
        
        // Sanitize peaks: ensure all values are floats between 0 and 1
        $peaks = array_map(function($p) {
            $val = floatval($p);
            return max(0, min(1, $val));
        }, $peaks);
        
        if (self::save_waveform($attachment_id, $peaks)) {
            wp_send_json_success(array('saved' => true, 'attachment_id' => $attachment_id));
        } else {
            wp_send_json_error(__('Failed to save waveform', 'sleek-audio-player'));
        }
    }
    
    /**
     * Render waveform management page
     */
    public function render_waveform_page() {
        $attachments = $this->get_playlist_audio_attachments();
        $pending_count = count(array_filter($attachments, function($a) { return !$a['has_waveform']; }));
        ?>
        <div class="wrap">
            <h1>🎵 <?php echo esc_html__('Waveform Analysis', 'sleek-audio-player'); ?></h1>
            <p><?php echo esc_html__('Generate real waveform data from your audio files for an authentic waveform display in the player.', 'sleek-audio-player'); ?></p>
            
            <div class="sap-waveform-stats" style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;margin:20px 0;">
                <h2 style="margin-top:0;"><?php echo esc_html__('Status', 'sleek-audio-player'); ?></h2>
                <p>
                    <?php
                    /* translators: %s is the number of audio files, wrapped in <strong> */
                    echo wp_kses(sprintf(_n('%s audio file found in playlists', '%s audio files found in playlists', count($attachments), 'sleek-audio-player'), '<strong>' . esc_html(number_format_i18n(count($attachments))) . '</strong>'), array('strong' => array()));
                    ?><br>
                    <?php
                    /* translators: %s is the number of analyzed files, wrapped in <strong> */
                    echo wp_kses(sprintf(__('%s analyzed', 'sleek-audio-player'), '<strong style="color:#46b450;">' . esc_html(number_format_i18n(count($attachments) - $pending_count)) . '</strong>'), array('strong' => array('style' => array())));
                    ?><br>
                    <?php
                    /* translators: %s is the number of pending files, wrapped in <strong> */
                    echo wp_kses(sprintf(__('%s pending', 'sleek-audio-player'), '<strong style="color:#dc3232;">' . esc_html(number_format_i18n($pending_count)) . '</strong>'), array('strong' => array('style' => array())));
                    ?>
                </p>
                
                <?php if ($pending_count > 0) : ?>
                <button type="button" id="sap-analyze-all" class="button button-primary button-hero" data-mode="pending">
                    🔬 <?php
                    /* translators: %s is the number of files waiting to be analyzed */
                    printf(esc_html(_n('Analyze %s pending file', 'Analyze %s pending files', $pending_count, 'sleek-audio-player')), esc_html(number_format_i18n($pending_count)));
                    ?>
                </button>
                <?php else : ?>
                <p style="color:#46b450;font-weight:600;">✓ <?php echo esc_html__('All files have been analyzed!', 'sleek-audio-player'); ?></p>
                <?php endif; ?>
                
                <?php if (count($attachments) > 0) : ?>
                <button type="button" id="sap-reanalyze-all" class="button" style="margin-left:10px;">
                    🔄 <?php
                    /* translators: %s is the total number of audio files */
                    printf(esc_html(_n('Re-analyze the %s file', 'Re-analyze all %s files', count($attachments), 'sleek-audio-player')), esc_html(number_format_i18n(count($attachments))));
                    ?>
                </button>
                <?php endif; ?>
                
                <div id="sap-analysis-progress" style="display:none;margin-top:20px;">
                    <div style="background:#e0e0e0;border-radius:4px;height:24px;overflow:hidden;">
                        <div id="sap-progress-bar" style="background:#0073aa;height:100%;width:0%;transition:width 0.3s;"></div>
                    </div>
                    <p id="sap-progress-text" style="margin-top:10px;"><?php echo esc_html__('Analyzing...', 'sleek-audio-player'); ?></p>
                </div>
            </div>
            
            <h2><?php echo esc_html__('Audio Files', 'sleek-audio-player'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px;">ID</th>
                        <th><?php echo esc_html__('Title', 'sleek-audio-player'); ?></th>
                        <th><?php echo esc_html__('Playlist', 'sleek-audio-player'); ?></th>
                        <th style="width:120px;"><?php echo esc_html__('Status', 'sleek-audio-player'); ?></th>
                        <th style="width:200px;"><?php echo esc_html__('Waveform', 'sleek-audio-player'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attachments as $att) : ?>
                    <tr data-id="<?php echo esc_attr( $att['id'] ); ?>">
                        <td><?php echo esc_html( $att['id'] ); ?></td>
                        <td><?php echo esc_html($att['title']); ?></td>
                        <td><?php echo esc_html($att['playlist']); ?></td>
                        <td data-status="<?php echo esc_attr($att['has_waveform'] ? 'analyzed' : 'pending'); ?>">
                            <?php if ($att['has_waveform']) : ?>
                                <span style="color:#46b450;">✓ <?php echo esc_html__('Analyzed', 'sleek-audio-player'); ?></span>
                            <?php else : ?>
                                <span style="color:#dc3232;">⏳ <?php echo esc_html__('Pending', 'sleek-audio-player'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <canvas class="sap-mini-waveform" data-id="<?php echo esc_attr( $att['id'] ); ?>" 
                                    style="width:180px;height:30px;background:#f0f0f0;border-radius:4px;"
                                    data-peaks='<?php echo esc_attr(wp_json_encode(self::get_waveform($att['id']) ?: [])); ?>'></canvas>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const nonce = '<?php echo esc_attr( wp_create_nonce('sap_waveform_nonce') ); ?>';

            // Texts come from PHP so this page follows the site language too.
            const sapI18n = {
                analyzed: '<?php echo esc_js(__('Analyzed', 'sleek-audio-player')); ?>',
                /* translators: %1$s is the current file number, %2$s the total */
                analyzing: '<?php echo esc_js(__('Analyzing %1$s of %2$s...', 'sleek-audio-player')); ?>',
                complete: '<?php echo esc_js(__('Analysis complete!', 'sleek-audio-player')); ?>',
                done: '<?php echo esc_js(__('Done', 'sleek-audio-player')); ?>',
                /* translators: %s is the number of files */
                reanalyzed: '<?php echo esc_js(__('All %s files re-analyzed!', 'sleek-audio-player')); ?>'
            };

            function sapMarkAnalyzed($row) {
                $row.find('td[data-status]')
                    .attr('data-status', 'analyzed')
                    .html('<span style="color:#46b450;">✓ ' + sapI18n.analyzed + '</span>');
            }
            
            // Draw existing waveforms
            $('.sap-mini-waveform').each(function() {
                const canvas = this;
                const peaks = JSON.parse($(this).attr('data-peaks') || '[]');
                if (peaks.length > 0) {
                    drawMiniWaveform(canvas, peaks);
                }
            });
            
            function drawMiniWaveform(canvas, peaks) {
                const ctx = canvas.getContext('2d');
                const width = canvas.offsetWidth;
                const height = canvas.offsetHeight;
                canvas.width = width * 2;
                canvas.height = height * 2;
                ctx.scale(2, 2);
                
                ctx.clearRect(0, 0, width, height);
                const barWidth = width / peaks.length;
                
                for (let i = 0; i < peaks.length; i++) {
                    const barHeight = peaks[i] * height * 0.8;
                    const x = i * barWidth;
                    const y = (height - barHeight) / 2;
                    ctx.fillStyle = '#0073aa';
                    ctx.fillRect(x, y, barWidth - 1, barHeight);
                }
            }
            
            // Analyze all button
            $('#sap-analyze-all').on('click', async function() {
                const $btn = $(this);
                const $progress = $('#sap-analysis-progress');
                const $bar = $('#sap-progress-bar');
                const $text = $('#sap-progress-text');
                
                $btn.prop('disabled', true);
                $progress.show();
                
                // Get pending files
                const pending = [];
                $('tr[data-id]').each(function() {
                    if ($(this).find('td[data-status="pending"]').length) {
                        pending.push({
                            id: $(this).data('id'),
                            row: $(this)
                        });
                    }
                });
                
                for (let i = 0; i < pending.length; i++) {
                    const item = pending[i];
                    const percent = Math.round((i / pending.length) * 100);
                    $bar.css('width', percent + '%');
                    $text.text(sapI18n.analyzing.replace('%1$s', i + 1).replace('%2$s', pending.length));
                    
                    try {
                        // First try server-side
                        const response = await $.post(ajaxurl, {
                            action: 'sap_analyze_waveform',
                            nonce: nonce,
                            attachment_id: item.id
                        });
                        
                        if (response.success && response.data.needs_client_analysis) {
                            // Client-side analysis needed
                            const peaks = await analyzeAudioClient(response.data.url);
                            if (peaks) {
                                await $.post(ajaxurl, {
                                    action: 'sap_save_waveform',
                                    nonce: nonce,
                                    attachment_id: item.id,
                                    peaks: JSON.stringify(peaks)
                                });
                                
                                // Update UI
                                const canvas = item.row.find('.sap-mini-waveform')[0];
                                drawMiniWaveform(canvas, peaks);
                                sapMarkAnalyzed(item.row);
                            }
                        } else if (response.success && response.data.peaks) {
                            // Server-side worked
                            const canvas = item.row.find('.sap-mini-waveform')[0];
                            drawMiniWaveform(canvas, response.data.peaks);
                            sapMarkAnalyzed(item.row);
                        }
                    } catch (e) {
                        console.error('Error analyzing', item.id, e);
                    }
                }
                
                $bar.css('width', '100%');
                $text.text('✓ ' + sapI18n.complete);
                $btn.text('✓ ' + sapI18n.done).prop('disabled', true);
            });
            
            // Re-analyze ALL files button
            $('#sap-reanalyze-all').on('click', async function() {
                const $btn = $(this);
                const $progress = $('#sap-analysis-progress');
                const $bar = $('#sap-progress-bar');
                const $text = $('#sap-progress-text');
                
                $btn.prop('disabled', true);
                $('#sap-analyze-all').prop('disabled', true);
                $progress.show();
                
                // Get ALL files (not just pending)
                const allFiles = [];
                $('tr[data-id]').each(function() {
                    allFiles.push({
                        id: $(this).data('id'),
                        row: $(this)
                    });
                });
                
                for (let i = 0; i < allFiles.length; i++) {
                    const item = allFiles[i];
                    const percent = Math.round((i / allFiles.length) * 100);
                    $bar.css('width', percent + '%');
                    $text.text(sapI18n.analyzing.replace('%1$s', i + 1).replace('%2$s', allFiles.length));
                    
                    try {
                        // First try server-side
                        const response = await $.post(ajaxurl, {
                            action: 'sap_analyze_waveform',
                            nonce: nonce,
                            attachment_id: item.id
                        });
                        
                        if (response.success && response.data.needs_client_analysis) {
                            // Client-side analysis needed
                            const peaks = await analyzeAudioClient(response.data.url);
                            if (peaks) {
                                await $.post(ajaxurl, {
                                    action: 'sap_save_waveform',
                                    nonce: nonce,
                                    attachment_id: item.id,
                                    peaks: JSON.stringify(peaks)
                                });
                                
                                // Update UI
                                const canvas = item.row.find('.sap-mini-waveform')[0];
                                drawMiniWaveform(canvas, peaks);
                                sapMarkAnalyzed(item.row);
                            }
                        } else if (response.success && response.data.peaks) {
                            // Server-side worked
                            const canvas = item.row.find('.sap-mini-waveform')[0];
                            drawMiniWaveform(canvas, response.data.peaks);
                            sapMarkAnalyzed(item.row);
                        }
                    } catch (e) {
                        console.error('Error analyzing', item.id, e);
                    }
                }
                
                $bar.css('width', '100%');
                $text.text('✓ ' + sapI18n.reanalyzed.replace('%s', allFiles.length));
                $btn.text('✓ ' + sapI18n.done).prop('disabled', true);
            });
            
            // Client-side audio analysis using Web Audio API
            async function analyzeAudioClient(url) {
                return new Promise((resolve) => {
                    fetch(url)
                        .then(response => response.arrayBuffer())
                        .then(arrayBuffer => {
                            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                            audioContext.decodeAudioData(arrayBuffer, function(audioBuffer) {
                                const peaks = extractPeaks(audioBuffer, 150);
                                audioContext.close();
                                resolve(peaks);
                            }, function(e) {
                                console.error('Decode error', e);
                                resolve(null);
                            });
                        })
                        .catch(e => {
                            console.error('Fetch error', e);
                            resolve(null);
                        });
                });
            }
            
            function extractPeaks(audioBuffer, peakCount) {
                // Use both channels if stereo
                const channelData = audioBuffer.getChannelData(0);
                const channelData2 = audioBuffer.numberOfChannels > 1 ? audioBuffer.getChannelData(1) : null;
                const samplesPerPeak = Math.floor(channelData.length / peakCount);
                const peaks = [];
                
                for (let i = 0; i < peakCount; i++) {
                    const start = i * samplesPerPeak;
                    const end = start + samplesPerPeak;
                    let sum = 0;
                    let max = 0;
                    
                    // Calculate RMS and peak for this segment
                    for (let j = start; j < end; j++) {
                        let sample = Math.abs(channelData[j]);
                        if (channelData2) {
                            sample = Math.max(sample, Math.abs(channelData2[j]));
                        }
                        sum += sample * sample;
                        if (sample > max) max = sample;
                    }
                    
                    // Blend RMS and peak for natural look (70% RMS, 30% peak)
                    const rms = Math.sqrt(sum / (end - start));
                    const blended = rms * 0.7 + max * 0.3;
                    peaks.push(blended);
                }
                
                return peaks;
            }
        });
        </script>
        <?php
    }
}
