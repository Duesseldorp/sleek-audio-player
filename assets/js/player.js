/**
 * Simple Audio Player - JavaScript
 * Vanilla JS - kein jQuery nötig
 */

(function() {
    'use strict';
    
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
        const audio = playerEl.querySelector('.sap-audio');
        
        // Register audio element globally
        if (audio && !allAudioElements.includes(audio)) {
            allAudioElements.push(audio);
            
            // Pause other players when this one starts
            audio.addEventListener('play', function() {
                pauseAllExcept(audio);
            });
        }
        const playlist = JSON.parse(playerEl.dataset.playlist || '[]');
        const tracks = playerEl.querySelectorAll('.sap-track');
        
        // Buttons
        const playBtn = playerEl.querySelector('.sap-play');
        const prevBtn = playerEl.querySelector('.sap-prev');
        const nextBtn = playerEl.querySelector('.sap-next');
        const shuffleBtn = playerEl.querySelector('.sap-shuffle');
        const downloadBtn = playerEl.querySelector('.sap-download');
        const carousel = playerEl.querySelector('.sap-cover-carousel');
        const coverTrack = playerEl.querySelector('.sap-cover-track');
        const coverSlides = playerEl.querySelectorAll('.sap-cover-slide');
                const visualizerCanvas = playerEl.querySelector('.sap-visualizer');
        
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
            if (!corsEnabled) return; // Visualizer requires CORS
            
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                analyser.smoothingTimeConstant = 0.8;
                
                source = audioContext.createMediaElementSource(audio);
                source.connect(analyser);
                analyser.connect(audioContext.destination);
            } catch (e) {
                audioContext = null;
                analyser = null;
            }
        }

        function drawVisualizer() {
            if (!visualizerCtx || !analyser) return;
            
            const bufferLength = analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);
            analyser.getByteFrequencyData(dataArray);
            
            const width = visualizerCanvas.width;
            const height = visualizerCanvas.height;
            
            // Clear with transparency (shows cover underneath)
            visualizerCtx.clearRect(0, 0, width, height);
            
            // Draw bars
            const barCount = 64;
            const barWidth = width / barCount;
            const gap = 2;
            
            // Get visualizer color from CSS variable (from :root)
            const computedStyle = getComputedStyle(document.documentElement);
            const vizColor = computedStyle.getPropertyValue('--sap-visualizer').trim() || '#e85d3d';
            
            // Convert hex to rgba for gradient
            const hexToRgba = (hex, alpha) => {
                const r = parseInt(hex.slice(1, 3), 16);
                const g = parseInt(hex.slice(3, 5), 16);
                const b = parseInt(hex.slice(5, 7), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            };
            
            for (let i = 0; i < barCount; i++) {
                const dataIndex = Math.floor(i * bufferLength / barCount);
                const value = dataArray[dataIndex];
                const barHeight = (value / 255) * height * 0.9;
                
                const x = i * barWidth;
                const y = height - barHeight;
                
                // Semi-transparent gradient bars using theme color
                const gradient = visualizerCtx.createLinearGradient(0, height, 0, 0);
                gradient.addColorStop(0, hexToRgba(vizColor, 0.6));
                gradient.addColorStop(0.5, hexToRgba(vizColor, 0.75));
                gradient.addColorStop(1, hexToRgba(vizColor, 0.95));
                
                visualizerCtx.fillStyle = gradient;
                visualizerCtx.fillRect(x + gap/2, y, barWidth - gap, barHeight);
            }
            
            animationId = requestAnimationFrame(drawVisualizer);
        }

        function startVisualizer() {
            initAudioContext();
            if (audioContext && audioContext.state === 'suspended') {
                audioContext.resume();
            }
            drawVisualizer();
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

        // === Audio-Qualitätsoptimierung ===
        audio.preload = 'auto';           // Vollständiges Preloading
        
        // Preload-Audio für nächsten Track (Gapless Playback)
        let preloadAudio = new Audio();
        preloadAudio.preload = 'auto';
        if (corsEnabled) {
            preloadAudio.crossOrigin = 'anonymous';
        }
        
        // Preload Cache für mehrere Tracks
        const audioCache = new Map();
        const MAX_CACHED_TRACKS = 3;
        
        function preloadTrack(index) {
            if (index < 0 || index >= playlist.length) return;
            const track = playlist[index];
            if (!track || audioCache.has(track.url)) return;
            
            // Cache-Limit einhalten
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
            // Nächste 2 Tracks vorpuffern
            preloadTrack(currentIndex + 1);
            preloadTrack(currentIndex + 2);
        }
        
        // Buffering-Status anzeigen
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
            
            // Download-Button sofort anzeigen wenn Track downloadbar ist
            if (downloadBtn) {
                if (firstTrack.downloadable) {
                    downloadBtn.style.display = 'flex';
                    downloadBtn.onclick = () => downloadTrack(firstTrack);
                } else {
                    downloadBtn.style.display = 'none';
                }
            }
            
            // Initialize waveform for first track
            updateWaveformForTrack(firstTrack);
            
            // Zweiten Track vorpuffern
            if (playlist.length > 1) {
                preloadAudio.src = playlist[1].url;
                preloadAudio.load();
            }
        }

        // --- Event Listeners ---

        if (playBtn) playBtn.addEventListener('click', function() {
            togglePlay();
            this.blur();
        });
        if (prevBtn) prevBtn.addEventListener('click', function() {
            playPrev();
            this.blur();
        });
        if (nextBtn) nextBtn.addEventListener('click', function() {
            playNext();
            this.blur();
        });
        if (shuffleBtn) shuffleBtn.addEventListener('click', function() {
            toggleShuffle();
            this.blur();
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
                    if (diffX > 10 || diffY > 10) {
                        isHorizontalSwipe = diffX > diffY;
                    }
                }
                
                // Only handle horizontal swipes
                if (isHorizontalSwipe === false) {
                    return;
                }
                
                if (isHorizontalSwipe) {
                    e.preventDefault();
                    didMove = true;
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
                    // Simple tap - start playback
                    playTrack(currentIndex);
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
                if (Math.abs(currentX - startX) > 5) {
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
                    // Simple click - start playback
                    playTrack(currentIndex);
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
        
        if (waveformContainer) waveformContainer.addEventListener('click', seek);
        else if (progressContainer) progressContainer.addEventListener('click', seek);
        
        audio.addEventListener('timeupdate', updateProgress);
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
            playNext();
        });
        audio.addEventListener('play', onPlay);
        audio.addEventListener('pause', onPause);

        tracks.forEach((track, index) => {
            track.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                playTrack(index);
            });
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
        
        function playTrack(index) {
            
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
            
            // Stop current playback first
            audio.pause();
            audio.currentTime = 0;
            
            // Update UI immediately
            tracks.forEach(t => t.classList.remove('active'));
            if (tracks[index]) tracks[index].classList.add('active');
            if (nowPlayingEl) nowPlayingEl.textContent = track.title;
            if (artistEl) artistEl.textContent = track.artist || '';
            
            // Carousel aktualisieren
            updateCarousel();
            
            // Update waveform for new track
            updateWaveformForTrack(track);
            
            // Set new source and play directly
            audio.src = track.url;
            
            // Try to play immediately - browser will buffer as needed
            audio.play().then(() => {
                isLoading = false;
                preloadNextTrack();
                
                // Track play event in Umami
                trackEvent('audio-play', {
                    title: track.title,
                    artist: track.artist || '',
                    index: index + 1,
                    total: playlist.length
                });
            }).catch(err => {
                isLoading = false;
                
                // If autoplay blocked, wait for user interaction
                if (err.name === 'NotAllowedError') {
                    // Autoplay blocked - waiting for user interaction
                }
            });
            
            // Handle load errors
            audio.onerror = function(e) {
                isLoading = false;
            };
            
            // Download button
            if (downloadBtn) {
                if (track.downloadable) {
                    downloadBtn.style.display = 'flex';
                    downloadBtn.onclick = () => downloadTrack(track);
                } else {
                    downloadBtn.style.display = 'none';
                }
            }
        }

        function togglePlay() {
            if (isLoading) return;
            
            if (audio.paused) {
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

        function playNext() {
            if (isLoading) return;
            
            let newIndex;
            if (isShuffled) {
                const currentShufflePos = shuffledOrder.indexOf(currentIndex);
                const nextPos = currentShufflePos < shuffledOrder.length - 1 ? currentShufflePos + 1 : 0;
                newIndex = shuffledOrder[nextPos];
            } else {
                newIndex = currentIndex < playlist.length - 1 ? currentIndex + 1 : 0;
            }
            playTrack(newIndex);
        }

        function toggleShuffle() {
            isShuffled = !isShuffled;
            shuffleBtn.classList.toggle('active', isShuffled);
            
            if (isShuffled) {
                // Create shuffled order
                shuffledOrder = [...Array(playlist.length).keys()];
                for (let i = shuffledOrder.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [shuffledOrder[i], shuffledOrder[j]] = [shuffledOrder[j], shuffledOrder[i]];
                }
            }
        }

        function updateProgress() {
            const percent = (audio.currentTime / audio.duration) * 100;
            if (progressBar) progressBar.style.width = percent + '%';
            if (currentTimeEl) currentTimeEl.textContent = formatTime(audio.currentTime);
            
            // Update waveform progress
            drawWaveform(percent / 100);
        }

        function updateDuration() {
            if (durationEl) durationEl.textContent = formatTime(audio.duration);
        }

        function seek(e) {
            const container = waveformContainer || progressContainer;
            if (!container || !audio.duration) return;
            const rect = container.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            audio.currentTime = percent * audio.duration;
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
                
                switch(e.code) {
                    case 'Space':
                        e.preventDefault();
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
                        targetAudio.volume = Math.min(1, targetAudio.volume + 0.1);
                        handled = true;
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        targetAudio.volume = Math.max(0, targetAudio.volume - 0.1);
                        handled = true;
                        break;
                    case 'KeyM':
                        targetAudio.muted = !targetAudio.muted;
                        handled = true;
                        break;
                    case 'KeyN':
                        // Trigger next button click
                        const nextBtn = targetPlayer.querySelector('.sap-next');
                        if (nextBtn) nextBtn.click();
                        handled = true;
                        break;
                    case 'KeyP':
                        // Trigger prev button click
                        const prevBtn = targetPlayer.querySelector('.sap-prev');
                        if (prevBtn) prevBtn.click();
                        handled = true;
                        break;
                    case 'KeyS':
                        // Trigger shuffle button click
                        const shuffleBtn = targetPlayer.querySelector('.sap-shuffle');
                        if (shuffleBtn) shuffleBtn.click();
                        handled = true;
                        break;
                }
            });
        }
    }

})();
