/**
 * Sleek Audio Player - JavaScript
 * Vanilla JS - no jQuery required
 * 
 * @author Martin Gräbing
 * @link https://www.duesseldorp.de
 * @license GPL-2.0-or-later
 */

(function() {
    'use strict';
    
    // === Stability: Error logging helper ===
    const SAP_DEBUG = false; // Set to true for debugging
    function sapLog(message, data) {
        if (!SAP_DEBUG) return;
        console.log('[SAP]', message, data || '');
    }
    function sapError(message, error) {
        console.error('[SAP Error]', message, error || '');
    }
    
    // === Stability: Safe JSON parse ===
    function safeJsonParse(str, fallback) {
        if (!str) return fallback;
        try {
            return JSON.parse(str);
        } catch (e) {
            sapError('JSON parse failed', e);
            return fallback;
        }
    }
    
    // Global registry for all audio elements - ensures only one plays at a time
    const allAudioElements = [];
    
    function pauseAllExcept(currentAudio) {
        allAudioElements.forEach(audio => {
            if (audio !== currentAudio && !audio.paused) {
                audio.pause();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const players = document.querySelectorAll('.sap-player');
        players.forEach(initPlayer);
    });

    function initPlayer(playerEl) {
        // Helper: Remove focus after click
        function blurButton(btn) {
            if (btn) {
                btn.blur();
            }
        }
        
        // Press handling for buttons - registers BOTH mouse and touch events
        function setupPressButton(btn) {
            if (!btn) return;
            
            btn.classList.remove('sap-pressed');
            
            // Mouse events
            btn.addEventListener('mousedown', function(e) {
                // Skip emulated mouse events from touch (they fire ~300ms after touch)
                if (e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents) return;
                this.classList.remove('sap-touched'); // Clear touch state on real mouse click
                this.classList.add('sap-pressed');
            });
            btn.addEventListener('mouseup', function() {
                this.classList.remove('sap-pressed');
                this.blur();
            });
            btn.addEventListener('mouseleave', function() {
                this.classList.remove('sap-pressed');
            });
            
            // Click event - ensures reset after full click cycle (fires after touchend on mobile)
            btn.addEventListener('click', function() {
                this.classList.remove('sap-pressed');
                this.blur();
                // Force style recalculation
                void this.offsetHeight;
            });
            
            // Touch events - add sap-touched class to override hover states via CSS
            btn.addEventListener('touchstart', function() {
                // Remove sap-touched from ALL buttons first
                playerEl.querySelectorAll('.sap-touched').forEach(function(el) {
                    el.classList.remove('sap-touched');
                });
                this.classList.add('sap-pressed');
                this.classList.add('sap-touched');
            }, { passive: true });
            
            btn.addEventListener('touchend', function() {
                var self = this;
                // Remove pressed state IMMEDIATELY
                self.classList.remove('sap-pressed');
                self.blur();
                
                // Force immediate style recalculation
                void self.offsetHeight;
                
                // Clear any lingering :hover/:active states
                requestAnimationFrame(function() {
                    self.style.pointerEvents = 'none';
                    requestAnimationFrame(function() {
                        self.style.pointerEvents = '';
                    });
                });
            }, { passive: true });
            
            btn.addEventListener('touchcancel', function() {
                this.classList.remove('sap-pressed');
                this.blur();
            }, { passive: true });
        }
        
        const audio = playerEl.querySelector('.sap-audio');
        
        // Register audio element globally
        if (audio && !allAudioElements.includes(audio)) {
            allAudioElements.push(audio);
            
            // Pause other players when this one starts
            audio.addEventListener('play', function() {
                pauseAllExcept(audio);
                
                // Remove play overlay when playback starts
                const overlay = playerEl.querySelector('.sap-play-overlay');
                if (overlay) {
                    overlay.remove();
                }
            });
        }
        const playlist = safeJsonParse(playerEl.dataset.playlist, []);
        
        // Validate playlist
        if (!Array.isArray(playlist) || playlist.length === 0) {
            sapLog('Empty or invalid playlist');
        }
        const tracks = playerEl.querySelectorAll('.sap-track');
        
        // Buttons
        const playBtn = playerEl.querySelector('.sap-play');
        const prevBtn = playerEl.querySelector('.sap-prev');
        const nextBtn = playerEl.querySelector('.sap-next');
        const moreWrapper = playerEl.querySelector('.sap-more-wrapper');
        const moreBtn = playerEl.querySelector('.sap-more-btn');
        const moreMenu = playerEl.querySelector('.sap-more-menu');
        const downloadBtn = moreMenu ? moreMenu.querySelector('.sap-download') : null;
        const repeatBtn = moreMenu ? moreMenu.querySelector('.sap-repeat') : null;
        const speedBtn = moreMenu ? moreMenu.querySelector('.sap-speed') : null;
        const menuShareBtn = moreMenu ? moreMenu.querySelector('.sap-menu-share') : null;
        const menuShuffleBtn = moreMenu ? moreMenu.querySelector('.sap-menu-shuffle') : null;
        const streamDivider = moreMenu ? moreMenu.querySelector('.sap-stream-divider') : null;
        const streamSpotify = moreMenu ? moreMenu.querySelector('.sap-stream-spotify') : null;
        const streamApple = moreMenu ? moreMenu.querySelector('.sap-stream-apple') : null;
        const streamAmazon = moreMenu ? moreMenu.querySelector('.sap-stream-amazon') : null;
        const streamSoundcloud = moreMenu ? moreMenu.querySelector('.sap-stream-soundcloud') : null;
        const volumeWrapper = playerEl.querySelector('.sap-volume-wrapper');
        const volumeBtn = playerEl.querySelector('.sap-volume-btn');
        const volumeSlider = playerEl.querySelector('.sap-volume-slider');
        const volumeTrack = playerEl.querySelector('.sap-volume-track');
        const volumeFill = playerEl.querySelector('.sap-volume-fill');
        const volumeHandle = playerEl.querySelector('.sap-volume-handle');
        const carousel = playerEl.querySelector('.sap-cover-carousel');
        const coverTrack = playerEl.querySelector('.sap-cover-track');
        const coverSlides = playerEl.querySelectorAll('.sap-cover-slide');
                const visualizerCanvas = playerEl.querySelector('.sap-visualizer');
        
        // Setup press handlers for all control buttons (unified desktop + mobile)
        [playBtn, prevBtn, nextBtn, moreBtn, volumeBtn].forEach(setupPressButton);
        
        // Setup press handlers for more menu items
        [downloadBtn, repeatBtn, speedBtn, menuShareBtn, menuShuffleBtn, streamSpotify, streamApple, streamAmazon, streamSoundcloud].forEach(setupPressButton);
        
        // Setup touch handlers for streaming links (prevent sticky hover)
        const streamingLinks = playerEl.querySelectorAll('.sap-link');
        streamingLinks.forEach(function(link) {
            link.addEventListener('touchstart', function() {
                streamingLinks.forEach(l => l.classList.remove('sap-touched'));
                this.classList.add('sap-touched');
            }, { passive: true });
            
            link.addEventListener('touchend', function() {
                // Keep sap-touched to prevent sticky hover
            }, { passive: true });
        });
        
        // Icons
        const iconPlay = playerEl.querySelector('.sap-icon-play');
        const iconPause = playerEl.querySelector('.sap-icon-pause');
        
        // Progress & Waveform
        const waveformContainer = playerEl.querySelector('.sap-waveform-container');
        const waveformCanvas = playerEl.querySelector('.sap-waveform');
        const progressContainer = playerEl.querySelector('.sap-progress');
        const progressBar = playerEl.querySelector('.sap-progress-bar');
        const currentTimeEl = playerEl.querySelector('.sap-current');
        const durationEl = playerEl.querySelector('.sap-duration');
        let waveformCtx = waveformCanvas ? waveformCanvas.getContext('2d') : null;
        let currentWaveformData = null;
        
        // Info
        const nowPlayingEl = playerEl.querySelector('.sap-now-playing');
        const artistEl = playerEl.querySelector('.sap-artist');
        const metaEl = playerEl.querySelector('.sap-meta');
        
        // State
        let currentIndex = 0;
        let isShuffled = false;
        let shuffledOrder = [];
        let repeatMode = 'off'; // off, track, playlist
        const playbackSpeeds = [1, 1.25, 1.5, 2];
        let currentSpeedIndex = 0;
        let showRemainingTime = localStorage.getItem('sap_show_remaining') === 'true';
        let userHasInteracted = false;
        
        // Sleep Timer State
        let sleepTimeout = null;
        let sleepEndOfTrack = false;
        let sleepCountdownInterval = null;
        let sleepEndTime = null;
        
        // Progress Memory (for podcasts/long tracks)
        const playlistId = playerEl.dataset.playlistId || 'default';
        const progressKey = 'sap_progress_' + playlistId;
        let lastProgressSave = 0;
        
        function saveProgress() {
            // Check if feature is enabled
            if (typeof sapSettings === 'undefined' || !sapSettings.rememberPosition) return;
            
            // Throttle saves to every 5 seconds
            const now = Date.now();
            if (now - lastProgressSave < 5000) return;
            lastProgressSave = now;
            
            // Only save if position is meaningful (> 5 seconds)
            if (audio.currentTime > 5 && audio.duration > 30) {
                const data = {
                    track: currentIndex,
                    position: Math.floor(audio.currentTime),
                    timestamp: now
                };
                localStorage.setItem(progressKey, JSON.stringify(data));
            }
        }
        
        function loadProgress() {
            // Check if feature is enabled
            if (typeof sapSettings === 'undefined' || !sapSettings.rememberPosition) return null;
            
            try {
                const saved = localStorage.getItem(progressKey);
                if (!saved) return null;
                
                const data = JSON.parse(saved);
                // Ignore if older than 30 days
                if (Date.now() - data.timestamp > 30 * 24 * 60 * 60 * 1000) {
                    localStorage.removeItem(progressKey);
                    return null;
                }
                return data;
            } catch (e) {
                return null;
            }
        }
        
        function clearProgress() {
            localStorage.removeItem(progressKey);
        }
        
        // Update streaming links in More menu based on current track
        function updateStreamingLinks(track) {
            if (!moreMenu) return;
            
            const hasAnyLink = track.spotify || track.apple || track.amazon || track.soundcloud;
            
            if (streamDivider) {
                streamDivider.style.display = hasAnyLink ? '' : 'none';
            }
            if (streamSpotify) {
                streamSpotify.style.display = track.spotify ? '' : 'none';
                if (track.spotify) streamSpotify.href = track.spotify;
            }
            if (streamApple) {
                streamApple.style.display = track.apple ? '' : 'none';
                if (track.apple) streamApple.href = track.apple;
            }
            if (streamAmazon) {
                streamAmazon.style.display = track.amazon ? '' : 'none';
                if (track.amazon) streamAmazon.href = track.amazon;
            }
            if (streamSoundcloud) {
                streamSoundcloud.style.display = track.soundcloud ? '' : 'none';
                if (track.soundcloud) streamSoundcloud.href = track.soundcloud;
            }
        }
        
        // CORS for visualizer - enable for same origin or trusted CDN domains
        let corsEnabled = false;
        const trustedCDNs = ['b-cdn.net', 'bunnycdn.com', 'cloudfront.net', 'jsdelivr.net'];
        
        if (playlist.length > 0 && playlist[0].url) {
            try {
                const audioUrl = new URL(playlist[0].url, window.location.href);
                const isSameOrigin = audioUrl.origin === window.location.origin;
                const isTrustedCDN = trustedCDNs.some(cdn => audioUrl.hostname.endsWith(cdn));
                
                if (isSameOrigin || isTrustedCDN) {
                    audio.crossOrigin = 'anonymous';
                    corsEnabled = true;
                }
            } catch (e) {
                // Invalid URL, skip CORS
            }
        }

        // === Audio Visualizer (Winamp-Style) ===
        let audioContext = null;
        let analyser = null;
        let source = null;
        let animationId = null;
        let visualizerCtx = null;
        
        if (visualizerCanvas) {
            visualizerCtx = visualizerCanvas.getContext('2d');
            
            // Resize canvas
            function resizeCanvas() {
                visualizerCanvas.width = visualizerCanvas.offsetWidth * 2;
                visualizerCanvas.height = visualizerCanvas.offsetHeight * 2;
            }
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
        }

        function initAudioContext() {
            if (audioContext) return;
            if (!corsEnabled) {
                sapLog('CORS not enabled, visualizer disabled');
                return;
            }
            
            try {
                // Check for Web Audio API support
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) {
                    sapLog('Web Audio API not supported');
                    return;
                }
                
                // Optimized AudioContext for better Bluetooth/playback quality
                audioContext = new AudioCtx({
                    sampleRate: 48000,        // Standard for Bluetooth audio
                    latencyHint: 'playback'   // Prioritize quality over latency
                });
                
                if (!audioContext) {
                    sapError('Failed to create AudioContext');
                    return;
                }
                
                analyser = audioContext.createAnalyser();
                analyser.fftSize = 2048;                  // Larger buffer for stability
                analyser.smoothingTimeConstant = 0.85;    // Smoother transitions
                
                // Only create source if audio element exists and has no existing source
                if (audio && !source) {
                    source = audioContext.createMediaElementSource(audio);
                    source.connect(analyser);
                    analyser.connect(audioContext.destination);
                }
                
                sapLog('AudioContext initialized successfully');
            } catch (e) {
                sapError('AudioContext initialization failed', e);
                audioContext = null;
                analyser = null;
                source = null;
            }
        }

        // Get visualizer type from settings or localStorage (user preference)
        const visualizerTypes = ['bars', 'mirror', 'circular', 'oscilloscope', 'dots', 'wave', 'pulse', 'circular_bars', 'particles', 'starburst', 'orbits'];
        let visualizerType = localStorage.getItem('sap_visualizer_type') 
            || (typeof sapSettings !== 'undefined' && sapSettings.visualizerType) 
            || 'bars';
        
        // Cycle to next visualizer type
        function cycleVisualizer() {
            const currentIndex = visualizerTypes.indexOf(visualizerType);
            visualizerType = visualizerTypes[(currentIndex + 1) % visualizerTypes.length];
            localStorage.setItem('sap_visualizer_type', visualizerType);
            
            // Show brief feedback
            showVisualizerFeedback(visualizerType);
        }
        
        // Show feedback when visualizer changes
        function showVisualizerFeedback(type) {
            const names = { bars: 'Bars', mirror: 'Mirror', circular: 'Circular', oscilloscope: 'Oscilloscope', dots: 'Dots', wave: 'Wave', pulse: 'Pulse', circular_bars: 'Circular Bars', particles: 'Particles', starburst: 'Starburst', orbits: 'Orbits' };
            const feedback = document.createElement('div');
            feedback.className = 'sap-viz-feedback';
            feedback.textContent = '🎵 ' + names[type];
            feedback.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,0.8);color:#fff;padding:8px 16px;border-radius:20px;font-size:14px;z-index:100;pointer-events:none;';
            
            const coverSection = playerEl.querySelector('.sap-cover-section');
            if (coverSection) {
                coverSection.appendChild(feedback);
                setTimeout(() => feedback.remove(), 1000);
            }
        }
        
        // Helper: Convert hex to rgba
        function hexToRgba(hex, alpha) {
            if (!hex || hex.charAt(0) !== '#') return `rgba(232, 93, 61, ${alpha})`;
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
        
        // Get visualizer color - adaptive or from CSS variable
        function getVizColor() {
            // Check for adaptive color first
            const adaptiveColor = playerEl.getAdaptiveColor ? playerEl.getAdaptiveColor() : null;
            if (adaptiveColor) {
                return adaptiveColor;
            }
            // Fallback to CSS variable
            const computedStyle = getComputedStyle(document.documentElement);
            return computedStyle.getPropertyValue('--sap-visualizer').trim() || '#e85d3d';
        }

        function drawVisualizer() {
            if (!visualizerCtx || !analyser) return;
            if (visualizerType === 'off') return;
            
            try {
                const bufferLength = analyser.frequencyBinCount;
                const dataArray = new Uint8Array(bufferLength);
                analyser.getByteFrequencyData(dataArray);
            
            const width = visualizerCanvas.width;
            const height = visualizerCanvas.height;
            const vizColor = getVizColor();
            
            // Clear canvas
            visualizerCtx.clearRect(0, 0, width, height);
            
            // Draw based on type
            switch(visualizerType) {
                case 'mirror':
                    drawMirrorBars(dataArray, width, height, vizColor);
                    break;
                case 'circular':
                    drawCircular(dataArray, width, height, vizColor);
                    break;
                case 'oscilloscope':
                    drawOscilloscope(width, height, vizColor);
                    break;
                case 'dots':
                    drawDots(dataArray, width, height, vizColor);
                    break;
                case 'wave':
                    drawWave(dataArray, width, height, vizColor);
                    break;
                case 'pulse':
                    drawPulse(dataArray, width, height, vizColor);
                    break;
                case 'circular_bars':
                    drawCircularBars(dataArray, width, height, vizColor);
                    break;
                case 'particles':
                    drawParticles(dataArray, width, height, vizColor);
                    break;
                case 'starburst':
                    drawStarburst(dataArray, width, height, vizColor);
                    break;
                case 'orbits':
                    drawOrbits(dataArray, width, height, vizColor);
                    break;
                case 'bars':
                default:
                    drawBars(dataArray, width, height, vizColor);
                    break;
            }
            
                animationId = requestAnimationFrame(drawVisualizer);
            } catch (e) {
                sapError('Visualizer draw error', e);
                stopVisualizer();
            }
        }
        
        // Classic frequency bars - drawn at bottom of cover
        function drawBars(dataArray, width, height, vizColor) {
            const barCount = 64;
            const barWidth = width / barCount;
            const gap = 2;
            const maxBarHeight = height * 0.30; // Limit bar height to 30% of cover
            
            for (let i = 0; i < barCount; i++) {
                const dataIndex = Math.floor(i * dataArray.length / barCount);
                const value = dataArray[dataIndex];
                const barHeight = (value / 255) * maxBarHeight;
                
                const x = i * barWidth;
                const y = height - barHeight;
                
                const gradient = visualizerCtx.createLinearGradient(0, height, 0, 0);
                gradient.addColorStop(0, hexToRgba(vizColor, 0.6));
                gradient.addColorStop(0.5, hexToRgba(vizColor, 0.75));
                gradient.addColorStop(1, hexToRgba(vizColor, 0.95));
                
                visualizerCtx.fillStyle = gradient;
                visualizerCtx.fillRect(x + gap/2, y, barWidth - gap, barHeight);
            }
        }
        
        // Mirror bars (top & bottom) - full width, vertically centered
        function drawMirrorBars(dataArray, width, height, vizColor) {
            const barCount = 64;
            const gap = 2;
            const barWidth = width / barCount;
            const startX = 0;
            const centerY = height / 2;
            
            for (let i = 0; i < barCount; i++) {
                const dataIndex = Math.floor(i * dataArray.length / barCount);
                const value = dataArray[dataIndex];
                const barHeight = (value / 255) * centerY * 0.4;
                
                const x = startX + (i * barWidth);
                
                // Top half (going up from center)
                const gradientTop = visualizerCtx.createLinearGradient(0, centerY, 0, centerY - barHeight);
                gradientTop.addColorStop(0, hexToRgba(vizColor, 0.5));
                gradientTop.addColorStop(1, hexToRgba(vizColor, 0.9));
                visualizerCtx.fillStyle = gradientTop;
                visualizerCtx.fillRect(x + gap/2, centerY - barHeight, barWidth - gap, barHeight);
                
                // Bottom half (going down from center)
                const gradientBottom = visualizerCtx.createLinearGradient(0, centerY, 0, centerY + barHeight);
                gradientBottom.addColorStop(0, hexToRgba(vizColor, 0.5));
                gradientBottom.addColorStop(1, hexToRgba(vizColor, 0.9));
                visualizerCtx.fillStyle = gradientBottom;
                visualizerCtx.fillRect(x + gap/2, centerY, barWidth - gap, barHeight);
            }
        }
        
        // Circular visualizer - centered over cover, double size
        function drawCircular(dataArray, width, height, vizColor) {
            const centerX = width / 2;
            const centerY = height / 2;
            const radius = Math.min(width, height) * 0.15;
            const barCount = 96;
            
            for (let i = 0; i < barCount; i++) {
                const dataIndex = Math.floor(i * dataArray.length / barCount);
                const value = dataArray[dataIndex];
                const barHeight = (value / 255) * radius * 2.0;
                
                const angle = (i / barCount) * Math.PI * 2 - Math.PI / 2;
                const x1 = centerX + Math.cos(angle) * radius;
                const y1 = centerY + Math.sin(angle) * radius;
                const x2 = centerX + Math.cos(angle) * (radius + barHeight);
                const y2 = centerY + Math.sin(angle) * (radius + barHeight);
                
                const alpha = 0.5 + (value / 255) * 0.5;
                visualizerCtx.strokeStyle = hexToRgba(vizColor, alpha);
                visualizerCtx.lineWidth = (width / barCount) * 0.7;
                visualizerCtx.lineCap = 'round';
                
                visualizerCtx.beginPath();
                visualizerCtx.moveTo(x1, y1);
                visualizerCtx.lineTo(x2, y2);
                visualizerCtx.stroke();
            }
        }
        
        // Oscilloscope waveform - higher amplitude
        function drawOscilloscope(width, height, vizColor) {
            const timeDataArray = new Uint8Array(analyser.frequencyBinCount);
            analyser.getByteTimeDomainData(timeDataArray);
            
            visualizerCtx.lineWidth = 3;
            visualizerCtx.strokeStyle = hexToRgba(vizColor, 0.85);
            visualizerCtx.beginPath();
            
            const sliceWidth = width / timeDataArray.length;
            const amplitudeScale = 2.5; // Higher amplitude
            let x = 0;
            
            for (let i = 0; i < timeDataArray.length; i++) {
                const v = (timeDataArray[i] - 128) / 128.0; // Center around 0
                const y = (height / 2) + (v * height * 0.4 * amplitudeScale);
                
                if (i === 0) {
                    visualizerCtx.moveTo(x, y);
                } else {
                    visualizerCtx.lineTo(x, y);
                }
                x += sliceWidth;
            }
            
            visualizerCtx.stroke();
        }
        
        // Dots visualization - dancing dots
        function drawDots(dataArray, width, height, vizColor) {
            const dotCount = 32;
            const maxDotSize = 20;
            
            for (let i = 0; i < dotCount; i++) {
                const dataIndex = Math.floor(i * dataArray.length / dotCount);
                const value = dataArray[dataIndex];
                const dotSize = (value / 255) * maxDotSize + 3;
                
                const x = (i / dotCount) * width + (width / dotCount / 2);
                const y = height - (value / 255) * height * 0.7 - dotSize;
                
                const alpha = 0.5 + (value / 255) * 0.5;
                visualizerCtx.fillStyle = hexToRgba(vizColor, alpha);
                visualizerCtx.beginPath();
                visualizerCtx.arc(x, y, dotSize, 0, Math.PI * 2);
                visualizerCtx.fill();
            }
        }
        
        // Wave visualization - filled waveform (smoothed)
        function drawWave(dataArray, width, height, vizColor) {
            const timeDataArray = new Uint8Array(analyser.frequencyBinCount);
            analyser.getByteTimeDomainData(timeDataArray);
            
            // Downsample for smoother appearance
            const sampleCount = 64;
            const sampleSize = Math.floor(timeDataArray.length / sampleCount);
            const smoothedData = [];
            
            for (let i = 0; i < sampleCount; i++) {
                let sum = 0;
                for (let j = 0; j < sampleSize; j++) {
                    sum += timeDataArray[i * sampleSize + j];
                }
                smoothedData.push(sum / sampleSize);
            }
            
            visualizerCtx.fillStyle = hexToRgba(vizColor, 0.6);
            visualizerCtx.beginPath();
            visualizerCtx.moveTo(0, height);
            
            const sliceWidth = width / sampleCount;
            
            // First point
            const v0 = (smoothedData[0] - 128) / 128.0;
            const y0 = (height / 2) + (v0 * height * 0.4 * 2);
            visualizerCtx.lineTo(0, y0);
            
            // Draw smooth curves through points
            for (let i = 1; i < sampleCount; i++) {
                const v = (smoothedData[i] - 128) / 128.0;
                const y = (height / 2) + (v * height * 0.4 * 2);
                const x = i * sliceWidth;
                const xPrev = (i - 1) * sliceWidth;
                const xMid = (xPrev + x) / 2;
                
                visualizerCtx.quadraticCurveTo(xPrev, (height / 2) + ((smoothedData[i-1] - 128) / 128.0 * height * 0.4 * 2), xMid, (y + (height / 2) + ((smoothedData[i-1] - 128) / 128.0 * height * 0.4 * 2)) / 2);
            }
            
            // Last point
            const vLast = (smoothedData[sampleCount - 1] - 128) / 128.0;
            const yLast = (height / 2) + (vLast * height * 0.4 * 2);
            visualizerCtx.lineTo(width, yLast);
            
            visualizerCtx.lineTo(width, height);
            visualizerCtx.closePath();
            visualizerCtx.fill();
        }
        
        // Pulse visualization - multiple pulsing rings reacting to bass
        function drawPulse(dataArray, width, height, vizColor) {
            const centerX = width / 2;
            const centerY = height / 2;
            
            // Get bass frequencies (first few values)
            let bass = 0;
            for (let i = 0; i < 10; i++) {
                bass += dataArray[i];
            }
            bass = bass / 10 / 255;
            
            // Get mid frequencies
            let mid = 0;
            for (let i = 10; i < 40; i++) {
                mid += dataArray[i];
            }
            mid = mid / 30 / 255;
            
            const maxRadius = Math.min(width, height) * 0.4;
            
            // Draw multiple rings with stronger pulse
            const rings = 4;
            for (let r = 0; r < rings; r++) {
                const baseRadius = maxRadius * (0.2 + r * 0.2);
                const pulseAmount = (r % 2 === 0 ? bass : mid) * 80; // Much stronger pulse
                const radius = baseRadius + pulseAmount;
                const alpha = 0.8 - (r * 0.12);
                
                visualizerCtx.strokeStyle = hexToRgba(vizColor, alpha);
                visualizerCtx.lineWidth = 3 + bass * 5;
                visualizerCtx.beginPath();
                visualizerCtx.arc(centerX, centerY, radius, 0, Math.PI * 2);
                visualizerCtx.stroke();
            }
            
            // Center ring (pulsing)
            const ringSize = 8 + bass * 20;
            visualizerCtx.strokeStyle = hexToRgba(vizColor, 0.9);
            visualizerCtx.lineWidth = 2 + bass * 3;
            visualizerCtx.beginPath();
            visualizerCtx.arc(centerX, centerY, ringSize, 0, Math.PI * 2);
            visualizerCtx.stroke();
        }
        
        // Circular Bars - bars arranged in a circle
        function drawCircularBars(dataArray, width, height, vizColor) {
            const centerX = width / 2;
            const centerY = height / 2;
            const barCount = 64;
            const innerRadius = Math.min(width, height) * 0.15;
            const maxBarLength = Math.min(width, height) * 0.28;
            
            visualizerCtx.shadowBlur = 12;
            visualizerCtx.shadowColor = vizColor;
            
            for (let i = 0; i < barCount; i++) {
                const dataIndex = Math.floor(i * dataArray.length / barCount);
                const value = dataArray[dataIndex] / 255;
                const barLength = (0.1 + value * 0.9) * maxBarLength;
                const angle = (i / barCount) * Math.PI * 2 - Math.PI / 2;
                
                const x1 = centerX + Math.cos(angle) * innerRadius;
                const y1 = centerY + Math.sin(angle) * innerRadius;
                const x2 = centerX + Math.cos(angle) * (innerRadius + barLength);
                const y2 = centerY + Math.sin(angle) * (innerRadius + barLength);
                
                const gradient = visualizerCtx.createLinearGradient(x1, y1, x2, y2);
                gradient.addColorStop(0, hexToRgba(vizColor, 0.4));
                gradient.addColorStop(1, hexToRgba(vizColor, 0.9));
                
                visualizerCtx.strokeStyle = gradient;
                visualizerCtx.lineWidth = 3;
                visualizerCtx.lineCap = 'round';
                visualizerCtx.beginPath();
                visualizerCtx.moveTo(x1, y1);
                visualizerCtx.lineTo(x2, y2);
                visualizerCtx.stroke();
            }
            
            visualizerCtx.shadowBlur = 0;
        }
        
        // Particles - floating particles reacting to music
        let particles = [];
        let prevBass = 0;
        
        function drawParticles(dataArray, width, height, vizColor) {
            const bass = dataArray.slice(0, 10).reduce((a, b) => a + b, 0) / 10 / 255;
            const mid = dataArray.slice(10, 100).reduce((a, b) => a + b, 0) / 90 / 255;
            const high = dataArray.slice(100, 200).reduce((a, b) => a + b, 0) / 100 / 255;
            
            // Detect bass hits
            const bassChange = Math.max(0, bass - prevBass);
            prevBass = bass * 0.8 + prevBass * 0.2;
            
            // Continuous emission + burst on bass
            const baseEmit = particles.length < 15 ? 1 : 0;
            const burstEmit = bassChange > 0.08 ? 2 : 0;
            const emitCount = baseEmit + burstEmit;
            
            for (let i = 0; i < emitCount && particles.length < 25; i++) {
                const angle = Math.random() * Math.PI * 2;
                const speed = 0.6 + Math.random() * 0.8;
                particles.push({
                    x: width / 2,
                    y: height / 2,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    baseSize: 6 + Math.random() * 6 + bass * 8,
                    life: 1,
                    decay: 0.004 + Math.random() * 0.003
                });
            }
            
            visualizerCtx.shadowBlur = 30;
            visualizerCtx.shadowColor = vizColor;
            
            // Update and draw particles
            particles = particles.filter(p => {
                // Very slow drift outward
                p.x += p.vx;
                p.y += p.vy;
                p.life -= p.decay;
                
                if (p.life <= 0) return false;
                
                // Size pulses strongly with bass
                const pulse = 1 + bass * 2.5;
                const size = p.baseSize * pulse * (0.7 + p.life * 0.5);
                
                // Opacity reacts to mid frequencies
                const opacity = 0.15 + mid * 0.4;
                const strokeOpacity = 0.4 + mid * 0.5 + p.life * 0.2;
                
                visualizerCtx.fillStyle = hexToRgba(vizColor, opacity * p.life);
                visualizerCtx.strokeStyle = hexToRgba(vizColor, strokeOpacity);
                visualizerCtx.lineWidth = 2 + bass * 2;
                visualizerCtx.beginPath();
                visualizerCtx.arc(p.x, p.y, size, 0, Math.PI * 2);
                visualizerCtx.fill();
                visualizerCtx.stroke();
                
                return p.x > -50 && p.x < width + 50 && p.y > -50 && p.y < height + 50;
            });
            
            visualizerCtx.shadowBlur = 0;
        }
        
        // Starburst - rays emanating from center
        function drawStarburst(dataArray, width, height, vizColor) {
            const centerX = width / 2;
            const centerY = height / 2;
            const rayCount = 48;
            const maxLength = Math.min(width, height) * 0.55;
            const bass = dataArray.slice(0, 10).reduce((a, b) => a + b, 0) / 10 / 255;
            const mid = dataArray.slice(10, 100).reduce((a, b) => a + b, 0) / 90 / 255;
            
            // Global pulse based on bass - reduced to 15%
            const pulse = 1 + bass * 0.15;
            
            visualizerCtx.shadowBlur = 25 + bass * 15;
            visualizerCtx.shadowColor = vizColor;
            
            for (let i = 0; i < rayCount; i++) {
                const dataIndex = Math.floor(i * dataArray.length / rayCount);
                const value = dataArray[dataIndex] / 255;
                const angle = (i / rayCount) * Math.PI * 2;
                const length = (0.15 + value * 0.85) * maxLength * pulse;
                
                const innerRadius = 5 + bass * 12;
                const x1 = centerX + Math.cos(angle) * innerRadius;
                const y1 = centerY + Math.sin(angle) * innerRadius;
                const x2 = centerX + Math.cos(angle) * length;
                const y2 = centerY + Math.sin(angle) * length;
                
                const gradient = visualizerCtx.createLinearGradient(x1, y1, x2, y2);
                gradient.addColorStop(0, hexToRgba(vizColor, 0.9));
                gradient.addColorStop(0.4, hexToRgba(vizColor, 0.5 + mid * 0.3));
                gradient.addColorStop(1, hexToRgba(vizColor, 0.02));
                
                visualizerCtx.strokeStyle = gradient;
                visualizerCtx.lineWidth = 2 + value * 4 + bass * 2;
                visualizerCtx.lineCap = 'round';
                visualizerCtx.beginPath();
                visualizerCtx.moveTo(x1, y1);
                visualizerCtx.lineTo(x2, y2);
                visualizerCtx.stroke();
            }
            
            // Center glow - smaller
            const glowSize = 15 + bass * 20;
            const glowGradient = visualizerCtx.createRadialGradient(centerX, centerY, 0, centerX, centerY, glowSize);
            glowGradient.addColorStop(0, hexToRgba(vizColor, 0.7 + mid * 0.2));
            glowGradient.addColorStop(0.5, hexToRgba(vizColor, 0.3));
            glowGradient.addColorStop(1, hexToRgba(vizColor, 0));
            visualizerCtx.fillStyle = glowGradient;
            visualizerCtx.beginPath();
            visualizerCtx.arc(centerX, centerY, glowSize, 0, Math.PI * 2);
            visualizerCtx.fill();
            
            visualizerCtx.shadowBlur = 0;
        }
        
        // Orbits - rotating rings with audio reaction
        let orbitAngle = 0;
        
        // Reset visualizer state on track change
        function resetVisualizerState() {
            particles = [];
            prevBass = 0;
            orbitAngle = 0;
        }
        
        function drawOrbits(dataArray, width, height, vizColor) {
            const centerX = width / 2;
            const centerY = height / 2;
            const maxRadius = Math.min(width, height) * 0.48;
            const bass = dataArray.slice(0, 10).reduce((a, b) => a + b, 0) / 10 / 255;
            const mid = dataArray.slice(10, 100).reduce((a, b) => a + b, 0) / 90 / 255;
            const high = dataArray.slice(100, 200).reduce((a, b) => a + b, 0) / 100 / 255;
            
            orbitAngle += 0.003 + bass * 0.009;
            
            visualizerCtx.shadowBlur = 18;
            visualizerCtx.shadowColor = vizColor;
            
            const orbits = [
                { radius: maxRadius * 0.35, rotation: orbitAngle, dots: 10, size: 6, freqBand: bass },
                { radius: maxRadius * 0.6, rotation: -orbitAngle * 0.7, dots: 14, size: 5, freqBand: mid },
                { radius: maxRadius * 0.85, rotation: orbitAngle * 0.5, dots: 20, size: 4, freqBand: high }
            ];
            
            orbits.forEach((orbit, idx) => {
                const pulse = 1 + orbit.freqBand * 0.5;
                const radius = orbit.radius * pulse;
                
                // Draw orbit path - pulsing with music
                visualizerCtx.strokeStyle = hexToRgba(vizColor, 0.15 + orbit.freqBand * 0.2);
                visualizerCtx.lineWidth = 1.5 + orbit.freqBand * 2;
                visualizerCtx.beginPath();
                visualizerCtx.arc(centerX, centerY, radius, 0, Math.PI * 2);
                visualizerCtx.stroke();
                
                // Draw dots on orbit
                for (let i = 0; i < orbit.dots; i++) {
                    const angle = orbit.rotation + (i / orbit.dots) * Math.PI * 2;
                    const dataIndex = Math.floor((idx * orbit.dots + i) * dataArray.length / 44);
                    const value = dataArray[dataIndex] / 255;
                    
                    const x = centerX + Math.cos(angle) * radius;
                    const y = centerY + Math.sin(angle) * radius;
                    const size = orbit.size * (1 + value * 2);
                    
                    visualizerCtx.fillStyle = hexToRgba(vizColor, 0.5 + value * 0.5);
                    visualizerCtx.beginPath();
                    visualizerCtx.arc(x, y, size, 0, Math.PI * 2);
                    visualizerCtx.fill();
                }
            });
            
            // Center dot
            visualizerCtx.fillStyle = hexToRgba(vizColor, 0.8);
            visualizerCtx.beginPath();
            visualizerCtx.arc(centerX, centerY, 5 + bass * 8, 0, Math.PI * 2);
            visualizerCtx.fill();
            
            visualizerCtx.shadowBlur = 0;
        }

        function startVisualizer() {
            try {
                // Stop any existing animation loop first to prevent multiple loops
                stopVisualizer();
                
                initAudioContext();
                if (audioContext && audioContext.state === 'suspended') {
                    audioContext.resume().catch(e => {
                        sapError('Failed to resume AudioContext', e);
                    });
                }
                drawVisualizer();
            } catch (e) {
                sapError('startVisualizer failed', e);
            }
        }

        function stopVisualizer() {
            if (animationId) {
                cancelAnimationFrame(animationId);
                animationId = null;
            }
        }

        // === Waveform Generation ===
        function generateWaveformData(seed, barCount = 100) {
            // Generate pseudo-random waveform based on seed (track title hash)
            const data = [];
            let hash = 0;
            for (let i = 0; i < seed.length; i++) {
                hash = ((hash << 5) - hash) + seed.charCodeAt(i);
                hash = hash & hash;
            }
            
            // Use seeded random for consistent waveform per track
            const seededRandom = () => {
                hash = (hash * 1103515245 + 12345) & 0x7fffffff;
                return (hash / 0x7fffffff);
            };
            
            for (let i = 0; i < barCount; i++) {
                // Create natural-looking waveform with peaks and valleys
                const base = 0.3 + seededRandom() * 0.5;
                const variation = Math.sin(i * 0.15) * 0.15;
                data.push(Math.min(1, Math.max(0.1, base + variation)));
            }
            return data;
        }

        function drawWaveform(progress = 0) {
            if (!waveformCtx || !waveformCanvas || !currentWaveformData) return;
            
            // Use container dimensions (not scaled canvas dimensions)
            const container = waveformCanvas.parentElement;
            if (!container) return;
            
            const width = container.offsetWidth;
            const height = container.offsetHeight;
            const barCount = currentWaveformData.length;
            const barWidth = width / barCount;
            const gap = 1;
            const minBarHeight = 2; // Minimum height for visibility
            const cornerRadius = 1; // Rounded corners
            
            // Get colors from CSS
            const computedStyle = getComputedStyle(document.documentElement);
            const accentColor = computedStyle.getPropertyValue('--sap-visualizer').trim() || '#e85d3d';
            const grayColor = computedStyle.getPropertyValue('--sap-waveform-inactive').trim() || 'rgba(120, 150, 170, 0.4)';
            
            waveformCtx.clearRect(0, 0, width, height);
            
            for (let i = 0; i < barCount; i++) {
                // Apply slight curve to enhance peaks (power of 0.8 boosts quiet parts, 1.2 would compress them)
                const value = Math.pow(currentWaveformData[i], 0.85);
                const barHeight = Math.max(minBarHeight, value * height * 0.9);
                const x = i * barWidth + gap / 2;
                const y = (height - barHeight) / 2;
                const w = Math.max(1, barWidth - gap);
                const progressPoint = (i + 0.5) / barCount;
                
                // Color based on progress
                waveformCtx.fillStyle = progressPoint <= progress ? accentColor : grayColor;
                
                // Draw rounded rectangle
                if (cornerRadius > 0 && barHeight > cornerRadius * 2) {
                    waveformCtx.beginPath();
                    waveformCtx.roundRect(x, y, w, barHeight, cornerRadius);
                    waveformCtx.fill();
                } else {
                    waveformCtx.fillRect(x, y, w, barHeight);
                }
            }
        }

        function initWaveform() {
            if (!waveformCanvas || !waveformCtx) return;
            
            // Get container size
            const container = waveformCanvas.parentElement;
            if (!container) return;
            
            const width = container.offsetWidth;
            const height = container.offsetHeight;
            
            // Set canvas size for retina
            waveformCanvas.width = width * 2;
            waveformCanvas.height = height * 2;
            waveformCanvas.style.width = width + 'px';
            waveformCanvas.style.height = height + 'px';
            
            // Reset and scale context
            waveformCtx.setTransform(1, 0, 0, 1, 0, 0);
            waveformCtx.scale(2, 2);
        }

        function updateWaveformForTrack(track) {
            if (!waveformCanvas) return;
            
            // Use real waveform data if available, otherwise generate pseudo-random
            if (track.waveform && Array.isArray(track.waveform) && track.waveform.length > 0) {
                currentWaveformData = track.waveform;
            } else {
                // Fallback: Generate waveform based on track title as seed
                const seed = track.title + (track.artist || '');
                currentWaveformData = generateWaveformData(seed);
            }
            initWaveform();
            drawWaveform(0);
        }

        // Initialize waveform on resize
        if (waveformCanvas) {
            window.addEventListener('resize', () => {
                initWaveform();
                const percent = audio.duration ? audio.currentTime / audio.duration : 0;
                drawWaveform(percent);
            });
        }

        // === Auto-Calculate Total Duration ===
        function calculateTotalDuration() {
            let loadedCount = 0;
            let totalSeconds = 0;
            const trackDurations = [];
            
            playlist.forEach((track, index) => {
                // Check if duration already set in data
                if (track.duration) {
                    const parts = track.duration.split(':');
                    if (parts.length === 2) {
                        totalSeconds += parseInt(parts[0]) * 60 + parseInt(parts[1]);
                    } else if (parts.length === 3) {
                        totalSeconds += parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60 + parseInt(parts[2]);
                    }
                    loadedCount++;
                    trackDurations[index] = track.duration;
                    checkComplete();
                } else {
                    // Load audio to get duration
                    const tempAudio = new Audio();
                    tempAudio.preload = 'metadata';
                    tempAudio.src = track.url;
                    
                    tempAudio.addEventListener('loadedmetadata', function() {
                        const duration = tempAudio.duration;
                        totalSeconds += duration;
                        trackDurations[index] = formatTime(duration);
                        loadedCount++;
                        
                        // Update track duration in playlist UI
                        const trackEl = tracks[index];
                        if (trackEl) {
                            let durationEl = trackEl.querySelector('.sap-track-duration');
                            if (!durationEl) {
                                durationEl = document.createElement('span');
                                durationEl.className = 'sap-track-duration';
                                trackEl.appendChild(durationEl);
                            }
                            durationEl.textContent = trackDurations[index];
                        }
                        
                        checkComplete();
                    });
                    
                    tempAudio.addEventListener('error', function() {
                        loadedCount++;
                        checkComplete();
                    });
                }
            });
            
            function checkComplete() {
                if (loadedCount === playlist.length && metaEl) {
                    const totalFormatted = formatTime(totalSeconds);
                    const spans = metaEl.querySelectorAll('span');
                    if (spans.length >= 3) {
                        spans[2].textContent = totalFormatted;
                    }
                }
            }
        }
        
        // Start calculation on load
        calculateTotalDuration();

        // === Audio quality optimization ===
        audio.preload = 'auto';           // Full preloading
        audio.fetchPriority = 'high';     // Prioritize audio loading
        
        // MediaSession API for Bluetooth/lock screen metadata
        function updateMediaSession(track) {
            if (!('mediaSession' in navigator)) return;
            
            const defaultCover = playerEl.dataset.defaultCover || '';
            const coverUrl = track.cover_url || defaultCover;
            
            navigator.mediaSession.metadata = new MediaMetadata({
                title: track.title || 'Unknown Track',
                artist: track.artist || '',
                album: playerEl.dataset.playlistId ? 'Playlist ' + playerEl.dataset.playlistId : '',
                artwork: coverUrl ? [
                    { src: coverUrl, sizes: '96x96', type: 'image/jpeg' },
                    { src: coverUrl, sizes: '128x128', type: 'image/jpeg' },
                    { src: coverUrl, sizes: '192x192', type: 'image/jpeg' },
                    { src: coverUrl, sizes: '256x256', type: 'image/jpeg' },
                    { src: coverUrl, sizes: '384x384', type: 'image/jpeg' },
                    { src: coverUrl, sizes: '512x512', type: 'image/jpeg' }
                ] : []
            });
            
            // Media controls for Bluetooth/lock screen
            navigator.mediaSession.setActionHandler('play', () => audio.play());
            navigator.mediaSession.setActionHandler('pause', () => audio.pause());
            navigator.mediaSession.setActionHandler('previoustrack', () => {
                if (currentIndex > 0) playTrack(currentIndex - 1);
            });
            navigator.mediaSession.setActionHandler('nexttrack', () => {
                if (currentIndex < playlist.length - 1) playTrack(currentIndex + 1);
            });
            navigator.mediaSession.setActionHandler('seekto', (details) => {
                if (details.seekTime !== undefined) {
                    audio.currentTime = details.seekTime;
                }
            });
        }
        
        // Preload audio for next track (Gapless Playback)
        let preloadAudio = new Audio();
        preloadAudio.preload = 'auto';
        if (corsEnabled) {
            preloadAudio.crossOrigin = 'anonymous';
        }
        
        // Preload cache for multiple tracks
        const audioCache = new Map();
        const MAX_CACHED_TRACKS = 2;
        
        function preloadTrack(index) {
            if (index < 0 || index >= playlist.length) return;
            const track = playlist[index];
            if (!track || audioCache.has(track.url)) return;
            
            // Respect cache limit
            if (audioCache.size >= MAX_CACHED_TRACKS) {
                const firstKey = audioCache.keys().next().value;
                audioCache.delete(firstKey);
            }
            
            const preloadEl = new Audio();
            preloadEl.preload = 'auto';
            if (corsEnabled) {
                preloadEl.crossOrigin = 'anonymous';
            }
            preloadEl.src = track.url;
            preloadEl.load();
            audioCache.set(track.url, preloadEl);
        }
        
        function preloadNextTrack() {
            // Preload next 2 tracks
            preloadTrack(currentIndex + 1);
            preloadTrack(currentIndex + 2);
        }
        
        // Show buffering status
        let bufferingTimeout = null;
        
        audio.addEventListener('waiting', function() {
            bufferingTimeout = setTimeout(() => {
                playerEl.classList.add('is-buffering');
            }, 200);
        });
        
        audio.addEventListener('playing', function() {
            clearTimeout(bufferingTimeout);
            playerEl.classList.remove('is-buffering');
        });
        
        audio.addEventListener('canplaythrough', function() {
            clearTimeout(bufferingTimeout);
            playerEl.classList.remove('is-buffering');
        });

        // Initial: Ersten Track laden (ohne Abspielen)
        if (playlist.length > 0) {
            const firstTrack = playlist[0];
            audio.src = firstTrack.url;
            audio.load();
            if (nowPlayingEl) nowPlayingEl.textContent = firstTrack.title;
            if (artistEl) artistEl.textContent = firstTrack.artist || '';
            if (tracks[0]) tracks[0].classList.add('active');
            updateCarousel();
            updateStreamingLinks(firstTrack);
            
            // Check for share parameters in URL (?track=X&play=1) or data-autoplay attribute
            const urlParams = new URLSearchParams(window.location.search);
            const sharedTrack = parseInt(urlParams.get('track'));
            const dataAutoplay = playerEl.dataset.autoplay === 'true';
            const shouldAutoplay = urlParams.get('play') === '1' || dataAutoplay;
            
            // Check for saved progress (only if no URL parameters)
            const savedProgress = loadProgress();
            const hasUrlParams = sharedTrack && sharedTrack > 0;
            
            if (!hasUrlParams && savedProgress && savedProgress.track < playlist.length) {
                // Restore saved position
                currentIndex = savedProgress.track;
                const track = playlist[currentIndex];
                audio.src = track.url;
                if (nowPlayingEl) nowPlayingEl.textContent = track.title;
                if (artistEl) artistEl.textContent = track.artist || '';
                tracks.forEach(t => t.classList.remove('active'));
                if (tracks[currentIndex]) tracks[currentIndex].classList.add('active');
                updateCarousel();
                updateWaveformForTrack(track);
                updateStreamingLinks(track);
                if (downloadBtn) {
                    downloadBtn.classList.toggle('visible', !!track.downloadable);
                }
                
                // Set position after metadata loads
                audio.addEventListener('loadedmetadata', function restorePosition() {
                    if (savedProgress.position < audio.duration - 5) {
                        audio.currentTime = savedProgress.position;
                    }
                    audio.removeEventListener('loadedmetadata', restorePosition);
                }, { once: true });
                
                sapLog('Restored progress', savedProgress);
            }
            
            // If a specific track was shared, load and play it (URL params override saved progress)
            if (sharedTrack && sharedTrack > 0 && sharedTrack <= playlist.length) {
                const trackIndex = sharedTrack - 1; // Convert to 0-indexed
                currentIndex = trackIndex;
                const track = playlist[trackIndex];
                audio.src = track.url;
                if (nowPlayingEl) nowPlayingEl.textContent = track.title;
                if (artistEl) artistEl.textContent = track.artist || '';
                tracks.forEach(t => t.classList.remove('active'));
                if (tracks[trackIndex]) tracks[trackIndex].classList.add('active');
                updateCarousel();
                updateWaveformForTrack(track);
                
                // Update download button in menu
                if (downloadBtn) {
                    downloadBtn.classList.toggle('visible', !!track.downloadable);
                }
                updateStreamingLinks(track);
                
                // Scroll to player
                playerEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Try autoplay from beginning
                if (shouldAutoplay) {
                    audio.currentTime = 0;
                    const playPromise = audio.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(() => {
                            // Autoplay blocked - show big play overlay
                            showPlayOverlay();
                        });
                    }
                }
            }
            
            // Autoplay if data-autoplay is set (and no shared track)
            if (shouldAutoplay && !hasUrlParams && !sharedTrack) {
                audio.addEventListener('loadedmetadata', function autoplayOnLoad() {
                    const playPromise = audio.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(() => {
                            // Autoplay blocked - show big play overlay
                            showPlayOverlay();
                        });
                    }
                    audio.removeEventListener('loadedmetadata', autoplayOnLoad);
                }, { once: true });
            }
            
            // Show download button in menu if track is downloadable
            if (downloadBtn) {
                downloadBtn.classList.toggle('visible', !!firstTrack.downloadable);
            }
            
            // Initialize waveform for first track
            updateWaveformForTrack(firstTrack);
            
            // Initialize MediaSession for first track
            updateMediaSession(firstTrack);
            
            // Zweiten Track vorpuffern
            if (playlist.length > 1) {
                preloadAudio.src = playlist[1].url;
                preloadAudio.load();
            }
        }
        
        // Big play overlay for shared links when autoplay is blocked
        function showPlayOverlay() {
            const existing = playerEl.querySelector('.sap-play-overlay');
            if (existing) return;
            
            const overlay = document.createElement('div');
            overlay.className = 'sap-play-overlay';
            overlay.innerHTML = '<div class="sap-play-overlay-btn"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div><div class="sap-play-overlay-text">Tap to Play</div>';
            overlay.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(10,17,24,0.35);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:200;cursor:pointer;overflow:hidden;backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);';
            
            const btn = overlay.querySelector('.sap-play-overlay-btn');
            btn.style.cssText = 'width:80px;height:80px;background:var(--sap-accent,#e85d3d);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:16px;transition:transform 0.2s ease,background 0.2s ease;';
            
            const svg = btn.querySelector('svg');
            svg.style.cssText = 'width:36px;height:36px;fill:#fff;margin-left:4px;';
            
            const text = overlay.querySelector('.sap-play-overlay-text');
            text.style.cssText = 'font-size:14px;color:var(--sap-gray-200,rgba(200,215,225,0.8));font-weight:500;letter-spacing:0.5px;';
            
            const coverCarousel = playerEl.querySelector('.sap-cover-carousel');
            if (coverCarousel) {
                coverCarousel.style.position = 'relative';
                coverCarousel.appendChild(overlay);
                
                overlay.addEventListener('click', function() {
                    overlay.remove();
                    audio.play().catch(() => {});
                });
            }
        }

        // --- Event Listeners ---

        if (playBtn) playBtn.addEventListener('click', function() {
            togglePlay();
            blurButton(this);
        });
        if (prevBtn) prevBtn.addEventListener('click', function() {
            playPrev();
            blurButton(this);
        });
        if (nextBtn) nextBtn.addEventListener('click', function() {
            playNext();
            blurButton(this);
        });
        
        // === More Menu ===
        if (moreBtn && moreWrapper && moreMenu) {
            // Find ancestor that creates a containing block for position:fixed
            // (transform, filter, perspective, will-change, backdrop-filter, contain)
            // Without this, the fixed menu is mispositioned inside animated/transformed
            // theme sections (e.g. page builder entrance animations on the homepage)
            function getFixedContainingBlock(el) {
                let p = el.parentElement;
                while (p && p !== document.documentElement) {
                    const s = window.getComputedStyle(p);
                    if (
                        (s.transform && s.transform !== 'none') ||
                        (s.perspective && s.perspective !== 'none') ||
                        (s.filter && s.filter !== 'none') ||
                        (s.backdropFilter && s.backdropFilter !== 'none') ||
                        (s.willChange && /transform|perspective|filter/.test(s.willChange)) ||
                        (s.contain && /paint|layout|strict|content/.test(s.contain))
                    ) {
                        return p;
                    }
                    p = p.parentElement;
                }
                return null;
            }
            
            // Position menu dynamically to ensure full visibility
            function positionMenu() {
                // Make menu temporarily visible to measure it
                moreMenu.style.visibility = 'hidden';
                moreMenu.style.opacity = '1';
                moreMenu.style.display = 'block';
                
                const btnRect = moreBtn.getBoundingClientRect();
                const menuHeight = moreMenu.scrollHeight;
                const menuWidth = moreMenu.offsetWidth || 180;
                const viewportHeight = window.innerHeight;
                const viewportWidth = window.innerWidth;
                const padding = 10;
                
                // Reset temporary styles
                moreMenu.style.display = '';
                moreMenu.style.visibility = '';
                moreMenu.style.opacity = '';
                
                // Calculate best position
                let top, left;
                
                // Prefer opening upward (above button)
                if (btnRect.top > menuHeight + padding) {
                    top = btnRect.top - menuHeight - 8;
                    moreMenu.style.transformOrigin = 'bottom right';
                } else {
                    // Open downward if no space above
                    top = btnRect.bottom + 8;
                    moreMenu.style.transformOrigin = 'top right';
                }
                
                // Horizontal position - align to right edge of button
                left = btnRect.right - menuWidth;
                
                // Ensure menu stays in viewport
                if (left < padding) left = padding;
                if (left + menuWidth > viewportWidth - padding) {
                    left = viewportWidth - menuWidth - padding;
                }
                if (top < 0) top = 0;
                if (top + menuHeight > viewportHeight) {
                    top = Math.max(0, viewportHeight - menuHeight);
                }
                
                // If an ancestor creates a containing block for position:fixed,
                // convert viewport coordinates to that ancestor's coordinate space
                const containingBlock = getFixedContainingBlock(moreMenu);
                if (containingBlock) {
                    const cbRect = containingBlock.getBoundingClientRect();
                    const cbStyle = window.getComputedStyle(containingBlock);
                    top -= cbRect.top + (parseFloat(cbStyle.borderTopWidth) || 0);
                    left -= cbRect.left + (parseFloat(cbStyle.borderLeftWidth) || 0);
                }
                
                moreMenu.style.top = top + 'px';
                moreMenu.style.left = left + 'px';
            }
            
            moreBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isActive = moreWrapper.classList.toggle('active');
                this.setAttribute('aria-expanded', isActive);
                if (isActive) {
                    positionMenu();
                }
                blurButton(this);
            });
            
            // Close menu on scroll (menu stays fixed, closes when user scrolls)
            window.addEventListener('scroll', function() {
                if (moreWrapper.classList.contains('active')) {
                    moreWrapper.classList.remove('active');
                    moreBtn.setAttribute('aria-expanded', 'false');
                }
            }, { passive: true });
            
            // Reposition only on resize
            window.addEventListener('resize', function() {
                if (moreWrapper.classList.contains('active')) {
                    positionMenu();
                }
            }, { passive: true });
            
            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!moreWrapper.contains(e.target)) {
                    moreWrapper.classList.remove('active');
                    moreBtn.setAttribute('aria-expanded', 'false');
                    // Also hide submenus
                    const sleepSub = moreWrapper.querySelector('.sap-sleep-submenu');
                    const coverSub = moreWrapper.querySelector('.sap-cover-anim-submenu');
                    if (sleepSub) sleepSub.style.display = 'none';
                    if (coverSub) coverSub.style.display = 'none';
                }
            });
            
            // Handle menu item clicks
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    const track = playlist[currentIndex];
                    if (track && track.downloadable) {
                        downloadTrack(track);
                    }
                    moreWrapper.classList.remove('active');
                });
            }
            
            if (repeatBtn) {
                repeatBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleRepeat();
                    // Update menu label
                    const label = this.querySelector('span');
                    if (label) {
                        const modes = { off: 'Off', playlist: 'All', track: 'One' };
                        label.textContent = 'Repeat: ' + (modes[repeatMode] || 'Off');
                    }
                    this.classList.toggle('active', repeatMode !== 'off');
                });
            }
            
            if (speedBtn) {
                speedBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    cycleSpeed();
                    // Update menu label
                    const label = this.querySelector('.sap-speed-label');
                    if (label) {
                        label.textContent = 'Speed: ' + playbackSpeeds[currentSpeedIndex] + 'x';
                    }
                    this.classList.toggle('active', playbackSpeeds[currentSpeedIndex] !== 1);
                });
            }
            
            // === Sleep Timer ===
            const sleepTimerBtn = playerEl.querySelector('.sap-sleep-timer');
            const sleepSubmenu = playerEl.querySelector('.sap-sleep-submenu');
            const sleepOptions = playerEl.querySelectorAll('.sap-sleep-option');
            
            function updateSleepLabel() {
                const label = sleepTimerBtn ? sleepTimerBtn.querySelector('.sap-sleep-label') : null;
                if (!label) return;
                
                if (sleepEndOfTrack) {
                    label.textContent = 'Sleep: End of Track';
                    sleepTimerBtn.classList.add('active');
                    sleepTimerBtn.setAttribute('aria-pressed', 'true');
                } else if (sleepEndTime) {
                    const remainingMs = Math.max(0, sleepEndTime - Date.now());
                    const remainingMin = Math.floor(remainingMs / 60000);
                    const remainingSec = Math.floor((remainingMs % 60000) / 1000);
                    
                    if (remainingMin > 0) {
                        label.textContent = 'Sleep: ' + remainingMin + ':' + String(remainingSec).padStart(2, '0');
                    } else {
                        label.textContent = 'Sleep: ' + remainingSec + 's';
                    }
                    sleepTimerBtn.classList.add('active');
                    sleepTimerBtn.setAttribute('aria-pressed', 'true');
                } else {
                    label.textContent = 'Sleep Timer: Off';
                    sleepTimerBtn.classList.remove('active');
                    sleepTimerBtn.setAttribute('aria-pressed', 'false');
                }
            }
            
            function clearSleepTimer() {
                if (sleepTimeout) {
                    clearTimeout(sleepTimeout);
                    sleepTimeout = null;
                }
                if (sleepCountdownInterval) {
                    clearInterval(sleepCountdownInterval);
                    sleepCountdownInterval = null;
                }
                sleepEndTime = null;
                sleepEndOfTrack = false;
                updateSleepLabel();
            }
            
            function setSleepTimer(minutes, endOfTrack = false) {
                clearSleepTimer();
                
                if (endOfTrack) {
                    sleepEndOfTrack = true;
                    updateSleepLabel();
                    sapLog('Sleep timer set: end of track');
                    return;
                }
                
                if (minutes <= 0) {
                    sapLog('Sleep timer disabled');
                    return;
                }
                
                sleepEndTime = Date.now() + (minutes * 60 * 1000);
                sleepTimeout = setTimeout(function() {
                    audio.pause();
                    clearSleepTimer();
                    sapLog('Sleep timer triggered - playback paused');
                }, minutes * 60 * 1000);
                
                // Update countdown every second for live display
                sleepCountdownInterval = setInterval(updateSleepLabel, 1000);
                updateSleepLabel();
                sapLog('Sleep timer set', { minutes: minutes });
            }
            
            if (sleepTimerBtn) {
                sleepTimerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Toggle submenu
                    if (sleepSubmenu) {
                        const isVisible = sleepSubmenu.style.display !== 'none';
                        sleepSubmenu.style.display = isVisible ? 'none' : 'block';
                    }
                });
            }
            
            sleepOptions.forEach(function(option) {
                option.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const minutes = parseInt(this.dataset.minutes) || 0;
                    const endOfTrack = this.dataset.endOfTrack === 'true';
                    
                    setSleepTimer(minutes, endOfTrack);
                    
                    // Mark active option
                    sleepOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Hide submenu
                    if (sleepSubmenu) {
                        sleepSubmenu.style.display = 'none';
                    }
                });
            });
            
            // === Cover Animation ===
            const coverAnimBtn = playerEl.querySelector('.sap-cover-anim');
            const coverAnimSubmenu = playerEl.querySelector('.sap-cover-anim-submenu');
            const coverAnimOptions = playerEl.querySelectorAll('.sap-cover-anim-option');
            const coverAnimModes = ['none', 'kenburns', 'vinyl'];
            let currentCoverAnim = localStorage.getItem('sap_cover_anim') || 'kenburns';
            
            function setCoverAnimation(mode) {
                currentCoverAnim = mode;
                localStorage.setItem('sap_cover_anim', mode);
                
                // Remove all animation classes
                playerEl.classList.remove('sap-anim-none', 'sap-anim-kenburns', 'sap-vinyl');
                
                // Add appropriate class
                if (mode === 'none') {
                    playerEl.classList.add('sap-anim-none');
                } else if (mode === 'vinyl') {
                    playerEl.classList.add('sap-vinyl');
                }
                // kenburns is default (no extra class needed)
                
                // Update label and aria-pressed
                const label = coverAnimBtn ? coverAnimBtn.querySelector('.sap-cover-anim-label') : null;
                if (label) {
                    const modeNames = { none: 'Off', kenburns: 'Ken Burns', vinyl: 'Vinyl' };
                    label.textContent = 'Cover: ' + (modeNames[mode] || mode);
                }
                if (coverAnimBtn) {
                    coverAnimBtn.setAttribute('aria-pressed', mode !== 'none');
                }
                
                // Mark active option
                coverAnimOptions.forEach(function(opt) {
                    opt.classList.toggle('active', opt.dataset.anim === mode);
                });
                
                sapLog('Cover animation set', { mode: mode });
            }
            
            // Initialize cover animation from localStorage
            setCoverAnimation(currentCoverAnim);
            
            if (coverAnimBtn) {
                coverAnimBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Toggle submenu
                    if (coverAnimSubmenu) {
                        const isVisible = coverAnimSubmenu.style.display !== 'none';
                        coverAnimSubmenu.style.display = isVisible ? 'none' : 'block';
                        // Hide sleep submenu if open
                        if (sleepSubmenu) sleepSubmenu.style.display = 'none';
                    }
                });
            }
            
            coverAnimOptions.forEach(function(option) {
                option.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const anim = this.dataset.anim || 'kenburns';
                    setCoverAnimation(anim);
                    
                    // Hide submenu
                    if (coverAnimSubmenu) {
                        coverAnimSubmenu.style.display = 'none';
                    }
                });
            });
            
            // === Adaptive Visualizer Colors ===
            const adaptiveColorBtn = playerEl.querySelector('.sap-adaptive-color');
            let adaptiveColorsEnabled = localStorage.getItem('sap_adaptive_colors') === 'true';
            let currentAdaptiveColor = null;
            let colorCache = {}; // Cache extracted colors per image URL
            
            function updateAdaptiveColorLabel() {
                const label = adaptiveColorBtn ? adaptiveColorBtn.querySelector('.sap-adaptive-color-label') : null;
                if (label) {
                    label.textContent = 'Adaptive Colors: ' + (adaptiveColorsEnabled ? 'On' : 'Off');
                }
                if (adaptiveColorBtn) {
                    adaptiveColorBtn.classList.toggle('active', adaptiveColorsEnabled);
                    adaptiveColorBtn.setAttribute('aria-pressed', adaptiveColorsEnabled);
                }
            }
            
            function toggleAdaptiveColors() {
                adaptiveColorsEnabled = !adaptiveColorsEnabled;
                localStorage.setItem('sap_adaptive_colors', adaptiveColorsEnabled);
                updateAdaptiveColorLabel();
                
                if (adaptiveColorsEnabled) {
                    extractColorFromCurrentCover();
                } else {
                    currentAdaptiveColor = null;
                }
                
                sapLog('Adaptive colors', { enabled: adaptiveColorsEnabled });
            }
            
            // Extract dominant vibrant color from image
            function extractDominantColor(img, callback) {
                if (!img || !img.complete || !img.naturalWidth) {
                    callback(null);
                    return;
                }
                
                // Check cache first
                const src = img.src;
                if (colorCache[src]) {
                    callback(colorCache[src]);
                    return;
                }
                
                try {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    const size = 50; // Small size for performance
                    canvas.width = size;
                    canvas.height = size;
                    
                    ctx.drawImage(img, 0, 0, size, size);
                    
                    const imageData = ctx.getImageData(0, 0, size, size).data;
                    const colorCounts = {};
                    
                    // Sample pixels and find vibrant colors
                    for (let i = 0; i < imageData.length; i += 16) { // Sample every 4th pixel
                        const r = imageData[i];
                        const g = imageData[i + 1];
                        const b = imageData[i + 2];
                        
                        // Skip very dark or very light colors
                        const brightness = (r + g + b) / 3;
                        if (brightness < 30 || brightness > 220) continue;
                        
                        // Calculate saturation
                        const max = Math.max(r, g, b);
                        const min = Math.min(r, g, b);
                        const saturation = max === 0 ? 0 : (max - min) / max;
                        
                        // Only count sufficiently saturated colors
                        if (saturation < 0.3) continue;
                        
                        // Quantize to reduce color space
                        const key = (Math.round(r / 32) * 32) + ',' + 
                                    (Math.round(g / 32) * 32) + ',' + 
                                    (Math.round(b / 32) * 32);
                        
                        colorCounts[key] = (colorCounts[key] || 0) + 1;
                    }
                    
                    // Find most common vibrant color
                    let maxCount = 0;
                    let dominantColor = null;
                    
                    for (const key in colorCounts) {
                        if (colorCounts[key] > maxCount) {
                            maxCount = colorCounts[key];
                            dominantColor = key;
                        }
                    }
                    
                    if (dominantColor) {
                        const parts = dominantColor.split(',');
                        const color = '#' + 
                            parseInt(parts[0]).toString(16).padStart(2, '0') +
                            parseInt(parts[1]).toString(16).padStart(2, '0') +
                            parseInt(parts[2]).toString(16).padStart(2, '0');
                        
                        // Boost saturation for better visibility
                        const boostedColor = boostColorSaturation(color);
                        colorCache[src] = boostedColor;
                        callback(boostedColor);
                    } else {
                        callback(null);
                    }
                } catch (e) {
                    // CORS or other error - fail silently
                    sapLog('Color extraction failed (CORS?)', e.message);
                    callback(null);
                }
            }
            
            // Boost color saturation for better visibility
            function boostColorSaturation(hex) {
                const r = parseInt(hex.slice(1, 3), 16) / 255;
                const g = parseInt(hex.slice(3, 5), 16) / 255;
                const b = parseInt(hex.slice(5, 7), 16) / 255;
                
                const max = Math.max(r, g, b);
                const min = Math.min(r, g, b);
                let h, s, l = (max + min) / 2;
                
                if (max === min) {
                    h = s = 0;
                } else {
                    const d = max - min;
                    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                    switch (max) {
                        case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                        case g: h = ((b - r) / d + 2) / 6; break;
                        case b: h = ((r - g) / d + 4) / 6; break;
                    }
                }
                
                // Boost saturation (min 65%) and ensure good lightness (50-65%)
                // This ensures visibility even on dark covers
                s = Math.max(0.65, Math.min(1, s * 1.4));
                l = Math.max(0.5, Math.min(0.65, l * 1.3));
                
                // HSL to RGB
                function hue2rgb(p, q, t) {
                    if (t < 0) t += 1;
                    if (t > 1) t -= 1;
                    if (t < 1/6) return p + (q - p) * 6 * t;
                    if (t < 1/2) return q;
                    if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                    return p;
                }
                
                const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                const p = 2 * l - q;
                const rNew = Math.round(hue2rgb(p, q, h + 1/3) * 255);
                const gNew = Math.round(hue2rgb(p, q, h) * 255);
                const bNew = Math.round(hue2rgb(p, q, h - 1/3) * 255);
                
                return '#' + rNew.toString(16).padStart(2, '0') +
                             gNew.toString(16).padStart(2, '0') +
                             bNew.toString(16).padStart(2, '0');
            }
            
            function extractColorFromCurrentCover() {
                if (!adaptiveColorsEnabled) return;
                
                const activeSlide = playerEl.querySelector('.sap-cover-slide.active img');
                if (activeSlide) {
                    extractDominantColor(activeSlide, function(color) {
                        if (color) {
                            currentAdaptiveColor = color;
                            sapLog('Adaptive color extracted', color);
                        }
                    });
                }
            }
            
            // Initialize adaptive colors
            updateAdaptiveColorLabel();
            if (adaptiveColorsEnabled) {
                // Wait for cover image to load
                setTimeout(extractColorFromCurrentCover, 500);
            }
            
            if (adaptiveColorBtn) {
                adaptiveColorBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleAdaptiveColors();
                });
            }
            
            // Re-extract color on track change (hook into existing updateCover function)
            const originalUpdateCover = typeof updateCover === 'function' ? updateCover : null;
            if (originalUpdateCover) {
                // Color extraction happens after cover update via setTimeout
            }
            
            // Export functions for use elsewhere in player
            playerEl.getAdaptiveColor = function() {
                return adaptiveColorsEnabled ? currentAdaptiveColor : null;
            };
            
            playerEl.extractAdaptiveColor = extractColorFromCurrentCover;
            
            // Menu Share button
            if (menuShareBtn) {
                menuShareBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    shareTrack();
                    closeMoreMenu();
                    blurButton(this);
                });
            }
            
            // Menu Shuffle button
            if (menuShuffleBtn) {
                menuShuffleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleShuffle();
                    blurButton(this);
                });
            }
        }
        
        function closeMoreMenu() {
            if (moreWrapper) {
                moreWrapper.classList.remove('active');
                if (moreBtn) moreBtn.setAttribute('aria-expanded', 'false');
            }
        }
        
        // === Embed Modal ===
        const embedBtn = moreMenu ? moreMenu.querySelector('.sap-embed-btn') : null;
        const embedModal = playerEl.querySelector('.sap-embed-modal');
        const embedBackdrop = embedModal ? embedModal.querySelector('.sap-embed-backdrop') : null;
        const embedClose = embedModal ? embedModal.querySelector('.sap-embed-close') : null;
        const embedLayouts = embedModal ? embedModal.querySelectorAll('.sap-embed-layout') : [];
        const embedCode = embedModal ? embedModal.querySelector('.sap-embed-code') : null;
        const embedCopy = embedModal ? embedModal.querySelector('.sap-embed-copy') : null;
        const embedPreview = embedModal ? embedModal.querySelector('.sap-embed-preview') : null;
        
        // Debug logging
        sapLog('Embed Modal Init', { 
            moreMenu: !!moreMenu, 
            embedBtn: !!embedBtn, 
            embedModal: !!embedModal 
        });
        
        function openEmbedModal() {
            const embedBtnEl = moreMenu ? moreMenu.querySelector('.sap-embed-btn') : null;
            sapLog('openEmbedModal called', { embedModal: !!embedModal, embedBtnEl: !!embedBtnEl });
            if (!embedModal || !embedBtnEl) return;
            
            const baseUrl = embedBtnEl.dataset.embedUrl;
            embedModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Set default layout (wide)
            updateEmbedCode('wide', 280, baseUrl);
            
            // Close more menu
            if (moreWrapper) moreWrapper.classList.remove('active');
            if (moreBtn) moreBtn.setAttribute('aria-expanded', 'false');
        }
        
        function closeEmbedModal() {
            if (!embedModal) return;
            embedModal.style.display = 'none';
            document.body.style.overflow = '';
        }
        
        function updateEmbedCode(layout, height, baseUrl) {
            if (!embedCode) return;
            
            let url = baseUrl;
            if (layout === 'wide') {
                url += (url.includes('?') ? '&' : '?') + 'layout=wide';
            } else if (layout === 'mini') {
                url += (url.includes('?') ? '&' : '?') + 'layout=mini';
            }
            
            const code = `<iframe src="${url}" width="100%" height="${height}" frameborder="0" allow="autoplay" style="border-radius:16px;"></iframe>`;
            embedCode.value = code;
        }
        
        // Use event delegation on moreMenu for embed button
        if (moreMenu) {
            moreMenu.addEventListener('click', function(e) {
                const embedTarget = e.target.closest('.sap-embed-btn');
                if (embedTarget) {
                    e.preventDefault();
                    e.stopPropagation();
                    openEmbedModal();
                }
            });
        }
        
        if (embedBackdrop) {
            embedBackdrop.addEventListener('click', closeEmbedModal);
        }
        
        if (embedClose) {
            embedClose.addEventListener('click', closeEmbedModal);
        }
        
        embedLayouts.forEach(function(btn) {
            btn.addEventListener('click', function() {
                embedLayouts.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const layout = this.dataset.layout;
                const height = parseInt(this.dataset.height) || 500;
                const embedBtnEl = moreMenu ? moreMenu.querySelector('.sap-embed-btn') : null;
                const baseUrl = embedBtnEl ? embedBtnEl.dataset.embedUrl : '';
                updateEmbedCode(layout, height, baseUrl);
            });
        });
        
        if (embedCopy) {
            embedCopy.addEventListener('click', function() {
                if (!embedCode) return;
                
                embedCode.select();
                navigator.clipboard.writeText(embedCode.value).then(() => {
                    const span = this.querySelector('span');
                    const originalText = span.textContent;
                    span.textContent = '✓ Copied!';
                    this.classList.add('copied');
                    setTimeout(() => {
                        span.textContent = originalText;
                        this.classList.remove('copied');
                    }, 2000);
                }).catch(() => {
                    document.execCommand('copy');
                });
            });
        }
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && embedModal && embedModal.style.display === 'flex') {
                closeEmbedModal();
            }
        });
        
        function shareTrack() {
            const track = playlist[currentIndex];
            if (!track) return;
            
            // Umami tracking
            if (typeof umami !== 'undefined') {
                umami.track('share-button', {
                    track: track.title || '',
                    artist: track.artist || '',
                    playlist: playerEl.dataset.playlistId || ''
                });
            }
            
            // Get playlist ID for sharing (needed for OG tags on embedded playlists)
            const playlistId = playerEl.dataset.playlistId || '';
            
            // Build share URL with track, playlist ID and autoplay
            const baseUrl = window.location.href.split('?')[0].split('#')[0];
            const shareUrl = playlistId 
                ? `${baseUrl}?playlist=${playlistId}&track=${currentIndex + 1}&play=1`
                : `${baseUrl}?track=${currentIndex + 1}&play=1`;
            
            // Format: "Title" by Artist (no emoji)
            const shareTitle = track.title + (track.artist ? ' - ' + track.artist : '');
            const shareText = track.artist 
                ? `"${track.title}" by ${track.artist}` 
                : track.title;
            
            // Try Native Share API (Mobile)
            if (navigator.share) {
                navigator.share({
                    title: shareTitle,
                    text: shareText,
                    url: shareUrl
                }).catch(() => {
                    // User cancelled or error - fallback to clipboard
                    copyToClipboard(shareUrl, shareTitle);
                });
            } else {
                // Desktop fallback - copy to clipboard
                copyToClipboard(shareUrl, shareTitle);
            }
        }
        
        function copyToClipboard(text, title) {
            navigator.clipboard.writeText(text).then(() => {
                showShareFeedback('Link copied! 🔗');
            }).catch(() => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showShareFeedback('Link copied! 🔗');
            });
        }
        
        function showShareFeedback(message) {
            const feedback = document.createElement('div');
            feedback.className = 'sap-share-feedback';
            feedback.textContent = message;
            feedback.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(40,167,69,0.95);color:#fff;padding:12px 20px;border-radius:25px;font-size:14px;font-weight:600;z-index:100;pointer-events:none;';
            
            const coverSection = playerEl.querySelector('.sap-cover-section');
            if (coverSection) {
                coverSection.appendChild(feedback);
                setTimeout(() => feedback.remove(), 2000);
            }
        }
        
        // === Volume Control ===
        let currentVolume = parseFloat(localStorage.getItem('sap_volume')) || 0.7;
        let isMuted = false;
        let volumeDragging = false;
        
        // Initialize volume
        audio.volume = currentVolume;
        updateVolumeUI(currentVolume);
        
        function updateVolumeUI(vol) {
            if (!volumeFill || !volumeHandle || !volumeBtn) return;
            
            const percent = vol * 100;
            const isMobile = window.innerWidth <= 480;
            
            if (isMobile) {
                volumeFill.style.width = percent + '%';
                volumeFill.style.height = '100%';
                volumeHandle.style.left = percent + '%';
                volumeHandle.style.bottom = '50%';
            } else {
                volumeFill.style.height = percent + '%';
                volumeFill.style.width = '100%';
                volumeHandle.style.bottom = percent + '%';
                volumeHandle.style.left = '50%';
            }
            
            // Update icon
            volumeBtn.classList.remove('low', 'muted');
            if (vol === 0 || isMuted) {
                volumeBtn.classList.add('muted');
            } else if (vol < 0.5) {
                volumeBtn.classList.add('low');
            }
        }
        
        function setVolume(vol) {
            vol = Math.max(0, Math.min(1, vol));
            currentVolume = vol;
            audio.volume = vol;
            isMuted = vol === 0;
            localStorage.setItem('sap_volume', vol.toString());
            updateVolumeUI(vol);
        }
        
        function getVolumeFromEvent(e, rect) {
            const isMobile = window.innerWidth <= 480;
            if (isMobile) {
                const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
                return Math.max(0, Math.min(1, x / rect.width));
            } else {
                const y = rect.bottom - (e.touches ? e.touches[0].clientY : e.clientY);
                return Math.max(0, Math.min(1, y / rect.height));
            }
        }
        
        // Volume button click: toggle mute
        if (volumeBtn) {
            volumeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (isMuted) {
                    isMuted = false;
                    audio.volume = currentVolume || 0.7;
                    updateVolumeUI(currentVolume || 0.7);
                } else {
                    isMuted = true;
                    audio.volume = 0;
                    updateVolumeUI(0);
                }
                this.blur();
            });
        }
        
        // Volume slider interaction
        if (volumeTrack) {
            // Mouse events
            volumeTrack.addEventListener('mousedown', function(e) {
                e.preventDefault();
                volumeDragging = true;
                volumeWrapper.classList.add('active');
                const rect = volumeTrack.getBoundingClientRect();
                setVolume(getVolumeFromEvent(e, rect));
            });
            
            document.addEventListener('mousemove', function(e) {
                if (!volumeDragging) return;
                const rect = volumeTrack.getBoundingClientRect();
                setVolume(getVolumeFromEvent(e, rect));
            });
            
            document.addEventListener('mouseup', function() {
                if (volumeDragging) {
                    volumeDragging = false;
                    setTimeout(() => volumeWrapper.classList.remove('active'), 100);
                }
            });
            
            // Touch events
            volumeTrack.addEventListener('touchstart', function(e) {
                e.preventDefault();
                volumeDragging = true;
                volumeWrapper.classList.add('active');
                const rect = volumeTrack.getBoundingClientRect();
                setVolume(getVolumeFromEvent(e, rect));
            }, { passive: false });
            
            volumeTrack.addEventListener('touchmove', function(e) {
                if (!volumeDragging) return;
                e.preventDefault();
                const rect = volumeTrack.getBoundingClientRect();
                setVolume(getVolumeFromEvent(e, rect));
            }, { passive: false });
            
            volumeTrack.addEventListener('touchend', function() {
                volumeDragging = false;
                setTimeout(() => volumeWrapper.classList.remove('active'), 300);
            });
        }
        
        // Close slider when clicking outside
        document.addEventListener('click', function(e) {
            if (volumeWrapper && !volumeWrapper.contains(e.target)) {
                volumeWrapper.classList.remove('active');
            }
        });
        
        // === Swipe Gesture Support & Cover Click ===
        if (carousel && coverTrack) {
            let startX = 0;
            let startY = 0;
            let currentX = 0;
            let isDragging = false;
            let startTime = 0;
            let isHorizontalSwipe = null;
            let didMove = false;
            
            const swipeThreshold = 50;    // Min distance
            const velocityThreshold = 0.3; // Min velocity (px/ms)

            carousel.addEventListener('touchstart', handleTouchStart, { passive: true });
            carousel.addEventListener('touchmove', handleTouchMove, { passive: false });
            carousel.addEventListener('touchend', handleTouchEnd);
            
            // Mouse support for desktop
            carousel.addEventListener('mousedown', handleMouseDown);
            document.addEventListener('mousemove', handleMouseMove);
            document.addEventListener('mouseup', handleMouseUp);
            
            // Double-click/tap to cycle visualizer
            carousel.addEventListener('dblclick', function(e) {
                e.preventDefault();
                cycleVisualizer();
            });

            function handleTouchStart(e) {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
                currentX = startX;
                startTime = Date.now();
                isDragging = true;
                isHorizontalSwipe = null;
                didMove = false;
                coverTrack.classList.add('dragging');
            }

            function handleTouchMove(e) {
                if (!isDragging) return;
                
                const touchX = e.touches[0].clientX;
                const touchY = e.touches[0].clientY;
                
                // Detect swipe direction on first significant move
                if (isHorizontalSwipe === null) {
                    const diffX = Math.abs(touchX - startX);
                    const diffY = Math.abs(touchY - startY);
                    // Use higher threshold when cover click is enabled (default) to allow tap-to-play
                    let coverClickEnabled = true;
                    if (typeof sapSettings !== 'undefined' && sapSettings.coverClickPlay !== undefined) {
                        coverClickEnabled = sapSettings.coverClickPlay === true || 
                                           sapSettings.coverClickPlay === '1' || 
                                           sapSettings.coverClickPlay === 1 ||
                                           sapSettings.coverClickPlay === 'true';
                    }
                    const moveThreshold = coverClickEnabled ? 20 : 5;
                    if (diffX > moveThreshold || diffY > moveThreshold) {
                        didMove = true;
                        isHorizontalSwipe = diffX > diffY;
                    }
                }
                
                // Only handle horizontal swipes for carousel navigation
                if (isHorizontalSwipe === false) {
                    return;
                }
                
                if (isHorizontalSwipe) {
                    e.preventDefault();
                }
                
                currentX = touchX;
                const diff = currentX - startX;
                // Add resistance at edges
                const resistance = (currentIndex === 0 && diff > 0) || 
                                   (currentIndex === playlist.length - 1 && diff < 0) ? 0.3 : 1;
                const offset = -currentIndex * 100 + (diff / carousel.offsetWidth) * 100 * resistance;
                coverTrack.style.transform = `translateX(${offset}%)`;
            }

            function handleTouchEnd() {
                if (!isDragging) return;
                isDragging = false;
                isHorizontalSwipe = null;
                coverTrack.classList.remove('dragging');
                
                const diff = currentX - startX;
                const elapsed = Date.now() - startTime;
                const velocity = Math.abs(diff) / elapsed;
                
                // Trigger swipe by velocity OR distance
                const shouldSwipe = Math.abs(diff) >= swipeThreshold || velocity >= velocityThreshold;
                
                if (shouldSwipe && diff !== 0) {
                    if (diff < 0 && currentIndex < playlist.length - 1) {
                        playTrack(currentIndex + 1);
                    } else if (diff > 0 && currentIndex > 0) {
                        playTrack(currentIndex - 1);
                    } else {
                        updateCarousel();
                    }
                } else if (!didMove && audio.paused && playlist.length > 0) {
                    // Simple tap - start playback (default: enabled, can be disabled in settings)
                    // If sapSettings not defined, default to enabled (original behavior)
                    let canClickPlay = true;
                    if (typeof sapSettings !== 'undefined' && sapSettings.coverClickPlay !== undefined) {
                        canClickPlay = sapSettings.coverClickPlay === true || 
                                       sapSettings.coverClickPlay === '1' || 
                                       sapSettings.coverClickPlay === 1 ||
                                       sapSettings.coverClickPlay === 'true';
                    }
                    if (canClickPlay) {
                        playTrack(currentIndex);
                    }
                } else {
                    updateCarousel();
                }
                startX = 0;
                currentX = 0;
            }

            function handleMouseDown(e) {
                startX = e.clientX;
                currentX = startX;
                startTime = Date.now();
                isDragging = true;
                didMove = false;
                coverTrack.classList.add('dragging');
                e.preventDefault();
            }

            function handleMouseMove(e) {
                if (!isDragging) return;
                currentX = e.clientX;
                // Use higher threshold when cover click is enabled (default)
                let coverClickEnabled = true;
                if (typeof sapSettings !== 'undefined' && sapSettings.coverClickPlay !== undefined) {
                    coverClickEnabled = sapSettings.coverClickPlay === true || 
                                       sapSettings.coverClickPlay === '1' || 
                                       sapSettings.coverClickPlay === 1 ||
                                       sapSettings.coverClickPlay === 'true';
                }
                const moveThreshold = coverClickEnabled ? 5 : 3;
                if (Math.abs(currentX - startX) > moveThreshold) {
                    didMove = true;
                }
                const diff = currentX - startX;
                // Add resistance at edges
                const resistance = (currentIndex === 0 && diff > 0) || 
                                   (currentIndex === playlist.length - 1 && diff < 0) ? 0.3 : 1;
                const offset = -currentIndex * 100 + (diff / carousel.offsetWidth) * 100 * resistance;
                coverTrack.style.transform = `translateX(${offset}%)`;
            }

            function handleMouseUp() {
                if (!isDragging) return;
                isDragging = false;
                coverTrack.classList.remove('dragging');
                
                const diff = currentX - startX;
                const elapsed = Date.now() - startTime;
                const velocity = Math.abs(diff) / elapsed;
                
                const shouldSwipe = Math.abs(diff) >= swipeThreshold || velocity >= velocityThreshold;
                
                if (shouldSwipe && diff !== 0) {
                    if (diff < 0 && currentIndex < playlist.length - 1) {
                        playTrack(currentIndex + 1);
                    } else if (diff > 0 && currentIndex > 0) {
                        playTrack(currentIndex - 1);
                    } else {
                        updateCarousel();
                    }
                } else if (!didMove && audio.paused && playlist.length > 0) {
                    // Simple click - start playback (default: enabled, can be disabled in settings)
                    let canClickPlay = true;
                    if (typeof sapSettings !== 'undefined' && sapSettings.coverClickPlay !== undefined) {
                        canClickPlay = sapSettings.coverClickPlay === true || 
                                       sapSettings.coverClickPlay === '1' || 
                                       sapSettings.coverClickPlay === 1 ||
                                       sapSettings.coverClickPlay === 'true';
                    }
                    if (canClickPlay) {
                        playTrack(currentIndex);
                    }
                } else {
                    updateCarousel();
                }
                startX = 0;
                currentX = 0;
            }
        }

        function updateCarousel() {
            if (coverTrack) {
                coverTrack.style.transform = `translateX(-${currentIndex * 100}%)`;
            }
            coverSlides.forEach((slide, i) => {
                const isActive = i === currentIndex;
                slide.classList.toggle('active', isActive);
                
                // Add pulse animation on track change
                if (isActive) {
                    slide.classList.remove('sap-pulse');
                    // Force reflow to restart animation
                    void slide.offsetWidth;
                    slide.classList.add('sap-pulse');
                }
            });
        }
        
        // Waveform/Progress seeking - click and touch drag support
        const seekContainer = waveformContainer || progressContainer;
        if (seekContainer) {
            seekContainer.addEventListener('click', seek);
            
            // Touch drag support for seeking
            let seekDragging = false;
            
            seekContainer.addEventListener('touchstart', function(e) {
                seekDragging = true;
                audio.pause(); // Pause during drag
                seekFromTouch(e);
            }, { passive: true });
            
            seekContainer.addEventListener('touchmove', function(e) {
                if (!seekDragging) return;
                e.preventDefault();
                seekFromTouch(e);
            }, { passive: false });
            
            seekContainer.addEventListener('touchend', function() {
                if (seekDragging && audio.duration && !isNaN(audio.duration)) {
                    // Auto-play after seek drag
                    audio.play().catch(() => {});
                }
                seekDragging = false;
            }, { passive: true });
            
            seekContainer.addEventListener('touchcancel', function() {
                seekDragging = false;
            }, { passive: true });
            
            function seekFromTouch(e) {
                if (!e.touches || !e.touches[0]) return;
                const rect = seekContainer.getBoundingClientRect();
                const x = e.touches[0].clientX - rect.left;
                const percent = Math.max(0, Math.min(1, x / rect.width));
                
                if (audio.duration && !isNaN(audio.duration)) {
                    audio.currentTime = percent * audio.duration;
                    updateProgress();
                    drawWaveform(percent);
                }
            }
        }
        
        // Click on duration toggles between total and remaining time
        if (durationEl) {
            durationEl.addEventListener('click', toggleTimeDisplay);
            durationEl.title = 'Click to toggle remaining time';
        }
        
        audio.addEventListener('timeupdate', function() {
            updateProgress();
            saveProgress();
        });
        audio.addEventListener('loadedmetadata', updateDuration);
        audio.addEventListener('ended', function() {
            // Track completed song in Umami
            const track = playlist[currentIndex];
            if (track) {
                trackEvent('audio-complete', {
                    title: track.title,
                    artist: track.artist || ''
                });
            }
            
            // Check Sleep Timer - End of Track mode
            if (sleepEndOfTrack) {
                sapLog('Sleep timer: end of track - stopping playback');
                sleepEndOfTrack = false;
                sleepEndTime = null;
                // Update label
                const sleepLabel = playerEl.querySelector('.sap-sleep-label');
                const sleepBtn = playerEl.querySelector('.sap-sleep-timer');
                if (sleepLabel) sleepLabel.textContent = 'Sleep Timer: Off';
                if (sleepBtn) {
                    sleepBtn.classList.remove('active');
                    sleepBtn.setAttribute('aria-pressed', 'false');
                }
                // Don't play next track - just stop
                return;
            }
            
            // Handle repeat modes
            if (repeatMode === 'track') {
                // Repeat current track
                audio.currentTime = 0;
                const repeatPromise = audio.play();
                if (repeatPromise !== undefined) {
                    repeatPromise.catch(err => {
                        sapLog('Repeat play failed', err);
                        showPlayOverlay();
                    });
                }
            } else if (repeatMode === 'playlist') {
                // Play next, loop to start if at end
                sapLog('Playlist repeat mode - playing next track');
                if (currentIndex >= playlist.length - 1) {
                    playTrack(0, true);
                } else {
                    playNext(true);
                }
            } else {
                // No repeat - play next unless at end
                if (currentIndex < playlist.length - 1) {
                    sapLog('Auto-playing next track after ended event');
                    playNext(true);
                } else {
                    // Playlist finished - clear saved progress
                    sapLog('Playlist finished');
                    clearProgress();
                }
            }
        });
        audio.addEventListener('play', onPlay);
        audio.addEventListener('pause', onPause);

        tracks.forEach((track, index) => {
            track.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                userHasInteracted = true;
                playTrack(index);
            });
            
            // Touch events for tracks - prevent sticky hover on mobile
            track.addEventListener('touchstart', function() {
                // Remove sap-touched from all tracks first
                tracks.forEach(t => t.classList.remove('sap-touched'));
                this.classList.add('sap-touched');
            }, { passive: true });
            
            track.addEventListener('touchend', function() {
                // Keep sap-touched class to prevent sticky hover
            }, { passive: true });
            
            // Keyboard support for accessibility
            track.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    playTrack(index);
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const nextTrack = tracks[index + 1];
                    if (nextTrack) nextTrack.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prevTrack = tracks[index - 1];
                    if (prevTrack) prevTrack.focus();
                }
            });
        });
        
        // Global keyboard shortcuts for player
        playerEl.addEventListener('keydown', function(e) {
            // Only handle if not in an input field
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            switch(e.key) {
                case ' ':
                    if (e.target.classList.contains('sap-track')) return; // Let track handler deal with it
                    e.preventDefault();
                    togglePlay();
                    break;
                case 'ArrowLeft':
                    if (!e.target.classList.contains('sap-track')) {
                        e.preventDefault();
                        audio.currentTime = Math.max(0, audio.currentTime - 5);
                    }
                    break;
                case 'ArrowRight':
                    if (!e.target.classList.contains('sap-track')) {
                        e.preventDefault();
                        audio.currentTime = Math.min(audio.duration || 0, audio.currentTime + 5);
                    }
                    break;
                case 'm':
                case 'M':
                    // Toggle mute
                    if (volumeBtn) volumeBtn.click();
                    break;
            }
        });

        // --- Umami Analytics ---
        
        function trackEvent(eventName, data = {}) {
            // Check if tracking is enabled in settings
            if (typeof sapSettings === 'undefined' || !sapSettings.umamiTracking) {
                return;
            }
            
            // Umami v2 API
            if (typeof umami !== 'undefined' && typeof umami.track === 'function') {
                umami.track(eventName, data);
                return;
            }
            
            // Umami v1 API (data attribute method)
            if (typeof window.umami !== 'undefined') {
                window.umami(eventName);
                return;
            }
            
        }
        
        // --- Functions ---

        let isLoading = false;
        let loadingTimeout = null;
        
        // Loading spinner functions
        function showLoadingSpinner() {
            const existing = playerEl.querySelector('.sap-loading-spinner');
            if (existing) return;
            
            const spinner = document.createElement('div');
            spinner.className = 'sap-loading-spinner';
            spinner.innerHTML = '<div class="sap-spinner"></div>';
            spinner.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:50;';
            
            const coverSection = playerEl.querySelector('.sap-cover-section');
            if (coverSection) coverSection.appendChild(spinner);
        }
        
        function hideLoadingSpinner() {
            const spinner = playerEl.querySelector('.sap-loading-spinner');
            if (spinner) spinner.remove();
        }
        
        // Error feedback function
        function showErrorFeedback(message) {
            const feedback = document.createElement('div');
            feedback.className = 'sap-error-feedback';
            feedback.textContent = '⚠️ ' + message;
            feedback.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(220,53,69,0.9);color:#fff;padding:10px 16px;border-radius:8px;font-size:13px;z-index:100;pointer-events:none;text-align:center;max-width:80%;';
            
            const coverSection = playerEl.querySelector('.sap-cover-section');
            if (coverSection) {
                coverSection.appendChild(feedback);
                setTimeout(() => feedback.remove(), 3000);
            }
        }
        
        function playTrack(index, isAutoplay = false) {
            
            // Clear any pending timeout
            if (loadingTimeout) {
                clearTimeout(loadingTimeout);
                loadingTimeout = null;
            }
            
            // Reset loading state for new track request
            isLoading = false;
            
            currentIndex = index;
            const track = playlist[index];
            
            if (!track) return;
            
            isLoading = true;
            showLoadingSpinner();
            
            // Stop current playback first
            audio.pause();
            audio.currentTime = 0;
            
            // Reset visualizer state for new track
            resetVisualizerState();
            
            // Update UI immediately
            tracks.forEach(t => t.classList.remove('active'));
            if (tracks[index]) tracks[index].classList.add('active');
            if (nowPlayingEl) nowPlayingEl.textContent = track.title;
            if (artistEl) artistEl.textContent = track.artist || '';
            
            // Carousel aktualisieren
            updateCarousel();
            
            // Extract adaptive color from new cover (after carousel update)
            if (playerEl.extractAdaptiveColor) {
                setTimeout(playerEl.extractAdaptiveColor, 100);
            }
            
            // Update waveform for new track
            updateWaveformForTrack(track);
            
            // Set new source and load
            audio.src = track.url;
            
            // Mobile fix: Wait for audio to be ready before playing
            // This ensures smooth playback on mobile browsers, especially after track ends
            const attemptPlay = function() {
                const playPromise = audio.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        isLoading = false;
                        hideLoadingSpinner();
                        preloadNextTrack();
                        userHasInteracted = true;
                        
                        // Track play event in Umami
                        trackEvent('audio-play', {
                            title: track.title,
                            artist: track.artist || '',
                            index: index + 1,
                            total: playlist.length
                        });
                    }).catch(err => {
                        isLoading = false;
                        hideLoadingSpinner();
                        
                        // If autoplay blocked, show overlay only if not already playing
                        if (err.name === 'NotAllowedError') {
                            sapLog('Autoplay blocked - waiting for user interaction');
                            // Only show overlay if this is the first track or user hasn't interacted yet
                            if (!userHasInteracted && !isAutoplay) {
                                showPlayOverlay();
                            } else if (isAutoplay) {
                                sapLog('Mobile autoplay blocked during track transition - this is expected on some devices');
                            }
                        } else {
                            sapError('Play failed', err);
                            showErrorFeedback('Playback failed');
                        }
                    });
                } else {
                    isLoading = false;
                    hideLoadingSpinner();
                }
            };
            
            // Mobile fix: Use loadeddata event which is more reliable than canplay
            // Add timeout fallback in case event doesn't fire
            let playAttempted = false;
            let playTimeout = null;
            
            const tryPlay = function() {
                if (playAttempted) return;
                playAttempted = true;
                
                if (playTimeout) {
                    clearTimeout(playTimeout);
                    playTimeout = null;
                }
                
                attemptPlay();
            };
            
            // Clean up any existing listeners before adding new ones
            const cleanupListeners = function() {
                if (playTimeout) {
                    clearTimeout(playTimeout);
                    playTimeout = null;
                }
            };
            
            // Listen for loadeddata event (fires when first frame is loaded)
            audio.addEventListener('loadeddata', function onLoadedData() {
                cleanupListeners();
                tryPlay();
            }, { once: true });
            
            // For autoplay on mobile, also listen to canplaythrough for better reliability
            if (isAutoplay) {
                audio.addEventListener('canplaythrough', function onCanPlayThrough() {
                    sapLog('canplaythrough event fired for autoplay');
                    cleanupListeners();
                    tryPlay();
                }, { once: true });
            }
            
            // Fallback: Try to play after timeout even if event doesn't fire
            // For autoplay (track transitions), use shorter timeout for faster response
            const timeout = isAutoplay ? 200 : 500;
            playTimeout = setTimeout(function() {
                sapLog('Fallback play timeout triggered');
                tryPlay();
            }, timeout);
            
            // Start loading
            audio.load();
            
            // Handle load errors with detailed error messages
            audio.onerror = function(e) {
                isLoading = false;
                hideLoadingSpinner();
                
                // Get detailed error info
                let errorMsg = 'Track could not be loaded';
                if (audio.error) {
                    switch(audio.error.code) {
                        case MediaError.MEDIA_ERR_ABORTED:
                            errorMsg = 'Playback aborted';
                            break;
                        case MediaError.MEDIA_ERR_NETWORK:
                            errorMsg = 'Network error';
                            break;
                        case MediaError.MEDIA_ERR_DECODE:
                            errorMsg = 'Audio decode error';
                            break;
                        case MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED:
                            errorMsg = 'Format not supported';
                            break;
                    }
                }
                sapError('Audio load failed', { error: audio.error, track: track.title });
                showErrorFeedback(errorMsg);
            };
            
            // Download button in menu
            if (downloadBtn) {
                downloadBtn.classList.toggle('visible', !!track.downloadable);
            }
            
            // Update streaming links for current track
            updateStreamingLinks(track);
            
            // MediaSession API for Bluetooth/lock screen metadata
            updateMediaSession(track);
        }

        function togglePlay() {
            if (isLoading) return;
            
            if (audio.paused) {
                userHasInteracted = true;
                if (!audio.src && playlist.length > 0) {
                    playTrack(0);
                } else {
                    audio.play().catch(() => {});
                }
            } else {
                audio.pause();
            }
        }

        function onPlay() {
            if (iconPlay) iconPlay.style.display = 'none';
            if (iconPause) iconPause.style.display = 'block';
            playerEl.classList.add('is-playing');
            startVisualizer();
        }

        function onPause() {
            if (iconPlay) iconPlay.style.display = 'block';
            if (iconPause) iconPause.style.display = 'none';
            playerEl.classList.remove('is-playing');
            stopVisualizer();
        }

        function playPrev() {
            if (isLoading) return;
            
            let newIndex;
            if (isShuffled) {
                const currentShufflePos = shuffledOrder.indexOf(currentIndex);
                const prevPos = currentShufflePos > 0 ? currentShufflePos - 1 : shuffledOrder.length - 1;
                newIndex = shuffledOrder[prevPos];
            } else {
                newIndex = currentIndex > 0 ? currentIndex - 1 : playlist.length - 1;
            }
            playTrack(newIndex);
        }

        function playNext(isAutoplay = false) {
            if (isLoading) return;
            
            let newIndex;
            if (isShuffled) {
                const currentShufflePos = shuffledOrder.indexOf(currentIndex);
                const nextPos = currentShufflePos < shuffledOrder.length - 1 ? currentShufflePos + 1 : 0;
                newIndex = shuffledOrder[nextPos];
            } else {
                newIndex = currentIndex < playlist.length - 1 ? currentIndex + 1 : 0;
            }
            playTrack(newIndex, isAutoplay);
        }

        function toggleShuffle() {
            isShuffled = !isShuffled;
            if (menuShuffleBtn) {
                menuShuffleBtn.classList.toggle('active', isShuffled);
                menuShuffleBtn.setAttribute('aria-pressed', isShuffled);
            }
            
            if (isShuffled) {
                // Create shuffled order
                shuffledOrder = [...Array(playlist.length).keys()];
                for (let i = shuffledOrder.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [shuffledOrder[i], shuffledOrder[j]] = [shuffledOrder[j], shuffledOrder[i]];
                }
            }
        }

        function toggleRepeat() {
            const modes = ['off', 'playlist', 'track'];
            const currentModeIndex = modes.indexOf(repeatMode);
            repeatMode = modes[(currentModeIndex + 1) % modes.length];
            
            if (repeatBtn) {
                repeatBtn.dataset.mode = repeatMode;
                repeatBtn.classList.toggle('active', repeatMode !== 'off');
                repeatBtn.setAttribute('aria-pressed', repeatMode !== 'off');
                
                // Show/hide the "1" badge for single track repeat
                const badge = repeatBtn.querySelector('.sap-repeat-badge');
                if (badge) {
                    badge.style.display = repeatMode === 'track' ? 'flex' : 'none';
                }
                
                // Update title
                const titles = { off: 'Repeat: Off', playlist: 'Repeat: All', track: 'Repeat: One' };
                repeatBtn.title = titles[repeatMode];
            }
        }

        function cycleSpeed() {
            currentSpeedIndex = (currentSpeedIndex + 1) % playbackSpeeds.length;
            const speed = playbackSpeeds[currentSpeedIndex];
            audio.playbackRate = speed;
            
            if (speedBtn) {
                const label = speedBtn.querySelector('.sap-speed-label');
                if (label) {
                    label.textContent = speed + 'x';
                }
                speedBtn.classList.toggle('active', speed !== 1);
                speedBtn.setAttribute('aria-pressed', speed !== 1);
            }
        }

        function updateProgress() {
            const percent = (audio.currentTime / audio.duration) * 100;
            if (progressBar) progressBar.style.width = percent + '%';
            if (currentTimeEl) currentTimeEl.textContent = formatTime(audio.currentTime);
            
            // Update remaining time if in that mode
            if (showRemainingTime) {
                updateDuration();
            }
            
            // Update waveform progress
            drawWaveform(percent / 100);
        }

        function updateDuration() {
            if (durationEl) {
                if (showRemainingTime && !isNaN(audio.duration) && !isNaN(audio.currentTime)) {
                    const remaining = audio.duration - audio.currentTime;
                    durationEl.textContent = '-' + formatTime(remaining);
                } else {
                    durationEl.textContent = formatTime(audio.duration);
                }
            }
        }
        
        function toggleTimeDisplay() {
            showRemainingTime = !showRemainingTime;
            localStorage.setItem('sap_show_remaining', showRemainingTime);
            updateDuration();
        }

        function seek(e) {
            const container = waveformContainer || progressContainer;
            if (!container) return;
            
            const rect = container.getBoundingClientRect();
            const percent = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            
            // If no track loaded yet, load the current track first
            if (!audio.src && playlist.length > 0) {
                const track = playlist[currentIndex];
                audio.src = track.url;
            }
            
            // If audio duration is available, seek immediately
            if (audio.duration && !isNaN(audio.duration)) {
                audio.currentTime = percent * audio.duration;
                
                // Auto-play if paused
                if (audio.paused) {
                    audio.play().catch(() => {});
                }
            } else {
                // Duration not yet available - wait for metadata then seek and play
                const seekPercent = percent;
                
                const onMetadataLoaded = function() {
                    audio.currentTime = seekPercent * audio.duration;
                    audio.play().catch(() => {});
                    audio.removeEventListener('loadedmetadata', onMetadataLoaded);
                };
                
                audio.addEventListener('loadedmetadata', onMetadataLoaded);
                audio.load();
            }
        }

        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return mins + ':' + (secs < 10 ? '0' : '') + secs;
        }

        function downloadTrack(track) {
            // Track download in Umami
            trackEvent('audio-download', {
                title: track.title,
                artist: track.artist || ''
            });
            
            const a = document.createElement('a');
            a.href = track.url;
            a.download = track.title + '.mp3';
            a.target = '_blank';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // === Keyboard Shortcuts ===
        // Only add listener once globally, track active player
        if (!window.sapKeyboardInitialized) {
            window.sapKeyboardInitialized = true;
            
            document.addEventListener('keydown', function(e) {
                // Ignore if typing in input/textarea/contentEditable
                const tag = e.target.tagName;
                if (tag === 'INPUT' || tag === 'TEXTAREA' || e.target.isContentEditable) {
                    return;
                }
                
                // Find the active/playing player, or the first visible one
                let targetPlayer = null;
                let targetAudio = null;
                
                // First priority: currently playing player
                const allPlayers = document.querySelectorAll('.sap-player');
                for (const p of allPlayers) {
                    const a = p.querySelector('.sap-audio');
                    if (a && !a.paused) {
                        targetPlayer = p;
                        targetAudio = a;
                        break;
                    }
                }
                
                // Second priority: first visible player
                if (!targetPlayer) {
                    for (const p of allPlayers) {
                        const rect = p.getBoundingClientRect();
                        const inViewport = rect.top < window.innerHeight && rect.bottom > 0;
                        if (inViewport) {
                            targetPlayer = p;
                            targetAudio = p.querySelector('.sap-audio');
                            break;
                        }
                    }
                }
                
                if (!targetPlayer || !targetAudio) return;
                
                // Get the player's control functions via custom event
                let handled = false;
                
                // Visual feedback for keyboard shortcuts
                function flashButton(btn) {
                    if (!btn) return;
                    btn.classList.add('sap-key-pressed');
                    setTimeout(() => btn.classList.remove('sap-key-pressed'), 150);
                }
                
                switch(e.code) {
                    case 'Space':
                        e.preventDefault();
                        const playBtn = targetPlayer.querySelector('.sap-play');
                        flashButton(playBtn);
                        if (targetAudio.paused) {
                            targetAudio.play().catch(() => {});
                        } else {
                            targetAudio.pause();
                        }
                        handled = true;
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        if (targetAudio.duration) {
                            targetAudio.currentTime = Math.max(0, targetAudio.currentTime - 10);
                        }
                        handled = true;
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        if (targetAudio.duration) {
                            targetAudio.currentTime = Math.min(targetAudio.duration, targetAudio.currentTime + 10);
                        }
                        handled = true;
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        const volUpBtn = targetPlayer.querySelector('.sap-volume-btn');
                        flashButton(volUpBtn);
                        targetAudio.volume = Math.min(1, targetAudio.volume + 0.1);
                        handled = true;
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        const volDownBtn = targetPlayer.querySelector('.sap-volume-btn');
                        flashButton(volDownBtn);
                        targetAudio.volume = Math.max(0, targetAudio.volume - 0.1);
                        handled = true;
                        break;
                    case 'KeyM':
                        const muteBtn = targetPlayer.querySelector('.sap-volume-btn');
                        flashButton(muteBtn);
                        targetAudio.muted = !targetAudio.muted;
                        handled = true;
                        break;
                    case 'KeyN':
                        const nextBtn = targetPlayer.querySelector('.sap-next');
                        flashButton(nextBtn);
                        if (nextBtn) nextBtn.click();
                        handled = true;
                        break;
                    case 'KeyP':
                        const prevBtn = targetPlayer.querySelector('.sap-prev');
                        flashButton(prevBtn);
                        if (prevBtn) prevBtn.click();
                        handled = true;
                        break;
                    case 'KeyS':
                        const menuShuffleBtn = targetPlayer.querySelector('.sap-menu-shuffle');
                        flashButton(menuShuffleBtn);
                        if (menuShuffleBtn) menuShuffleBtn.click();
                        handled = true;
                        break;
                    case 'KeyR':
                        const repeatBtn = targetPlayer.querySelector('.sap-repeat');
                        flashButton(repeatBtn);
                        if (repeatBtn) repeatBtn.click();
                        handled = true;
                        break;
                    case 'KeyL':
                        const speedBtn = targetPlayer.querySelector('.sap-speed');
                        flashButton(speedBtn);
                        if (speedBtn) speedBtn.click();
                        handled = true;
                        break;
                    case 'KeyV':
                        // Cycle visualizer type - flash cover area
                        const cover = targetPlayer.querySelector('.sap-cover-carousel');
                        flashButton(cover);
                        if (typeof cycleVisualizer === 'function') {
                            cycleVisualizer();
                        }
                        handled = true;
                        break;
                }
            });
        }
    }

})();
