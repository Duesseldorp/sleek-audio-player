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
        
        // Progress
        const progressContainer = playerEl.querySelector('.sap-progress');
        const progressBar = playerEl.querySelector('.sap-progress-bar');
        const currentTimeEl = playerEl.querySelector('.sap-current');
        const durationEl = playerEl.querySelector('.sap-duration');
        
        // Info
        const nowPlayingEl = playerEl.querySelector('.sap-now-playing');
        const artistEl = playerEl.querySelector('.sap-artist');
        const metaEl = playerEl.querySelector('.sap-meta');
        
        // State
        let currentIndex = 0;
        let isShuffled = false;
        let shuffledOrder = [];

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
            
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            analyser = audioContext.createAnalyser();
            analyser.fftSize = 256;
            analyser.smoothingTimeConstant = 0.8;
            
            source = audioContext.createMediaElementSource(audio);
            source.connect(analyser);
            analyser.connect(audioContext.destination);
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
            
            for (let i = 0; i < barCount; i++) {
                const dataIndex = Math.floor(i * bufferLength / barCount);
                const value = dataArray[dataIndex];
                const barHeight = (value / 255) * height * 0.9;
                
                const x = i * barWidth;
                const y = height - barHeight;
                
                // Semi-transparent gradient bars - Orange accent
                const gradient = visualizerCtx.createLinearGradient(0, height, 0, 0);
                gradient.addColorStop(0, 'rgba(232, 93, 61, 0.6)');
                gradient.addColorStop(0.5, 'rgba(244, 121, 93, 0.7)');
                gradient.addColorStop(1, 'rgba(255, 200, 180, 0.9)');
                
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
        audio.crossOrigin = 'anonymous';  // Für Visualizer + CORS
        
        // Preload-Audio für nächsten Track (Gapless Playback)
        let preloadAudio = new Audio();
        preloadAudio.preload = 'auto';
        preloadAudio.crossOrigin = 'anonymous';
        
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
            preloadEl.crossOrigin = 'anonymous';
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
            
            // Zweiten Track vorpuffern
            if (playlist.length > 1) {
                preloadAudio.src = playlist[1].url;
                preloadAudio.load();
            }
        }

        // --- Event Listeners ---

        if (playBtn) playBtn.addEventListener('click', togglePlay);
        if (prevBtn) prevBtn.addEventListener('click', playPrev);
        if (nextBtn) nextBtn.addEventListener('click', playNext);
        if (shuffleBtn) shuffleBtn.addEventListener('click', toggleShuffle);
        
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
        
        if (progressContainer) progressContainer.addEventListener('click', seek);
        
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
            
            // Debug: Log if Umami not found
            console.log('SAP Analytics:', eventName, data);
        }
        
        // --- Functions ---

        let isLoading = false;
        
        function playTrack(index) {
            // Prevent rapid multiple calls
            if (isLoading) return;
            
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
            
            // Set new source and play when ready
            audio.src = track.url;
            audio.load();
            
            const playWhenReady = function() {
                audio.removeEventListener('canplay', playWhenReady);
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
                    console.warn('Playback failed:', err);
                    isLoading = false;
                });
            };
            
            audio.addEventListener('canplay', playWhenReady, { once: true });
            
            // Fallback timeout in case canplay doesn't fire
            setTimeout(() => {
                if (isLoading) {
                    isLoading = false;
                }
            }, 5000);
            
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
                    audio.play().catch(err => {
                        console.warn('Play failed:', err);
                    });
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
        }

        function updateDuration() {
            if (durationEl) durationEl.textContent = formatTime(audio.duration);
        }

        function seek(e) {
            if (!progressContainer || !audio.duration) return;
            const rect = progressContainer.getBoundingClientRect();
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
        document.addEventListener('keydown', function(e) {
            // Ignore if typing in input/textarea
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }
            
            // Only respond if this player is visible/in viewport
            const rect = playerEl.getBoundingClientRect();
            const inViewport = rect.top < window.innerHeight && rect.bottom > 0;
            if (!inViewport) return;
            
            switch(e.code) {
                case 'Space':
                    e.preventDefault();
                    togglePlay();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    if (audio.duration) {
                        audio.currentTime = Math.max(0, audio.currentTime - 10);
                    }
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    if (audio.duration) {
                        audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    audio.volume = Math.min(1, audio.volume + 0.1);
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    audio.volume = Math.max(0, audio.volume - 0.1);
                    break;
                case 'KeyN':
                    playNext();
                    break;
                case 'KeyP':
                    playPrev();
                    break;
                case 'KeyM':
                    audio.muted = !audio.muted;
                    break;
                case 'KeyS':
                    toggleShuffle();
                    break;
            }
        });
    }

})();
