<?php
// dashboard.php - Secure Dashboard with Authorization
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Access-Control-Allow-Origin: *");

// Enhanced server-side authentication check
if (!isset($_SESSION['user_authenticated']) || $_SESSION['user_authenticated'] !== true) {
    // Clear any existing session data
    session_destroy();
    header("Location: index.php");
    exit();
}

// Set session variables to prevent back button cache issues
$_SESSION['user_authenticated'] = true;
$_SESSION['last_activity'] = time();

// Add no-cache headers specifically for back button prevention
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Musicfy - Spotify Music Player</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Enhanced cache control meta tags -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        /* Your existing CSS styles remain the same */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 30px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 28px;
            font-weight: 700;
            color: #1DB954;
        }

        .logo i {
            font-size: 32px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-welcome {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }

        .logout-btn {
            background: #ff4757;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .logout-btn:hover {
            background: #ff6b81;
            transform: scale(1.05);
        }

        .search-container {
            display: flex;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border-radius: 50px;
            overflow: hidden;
        }

        .search-input {
            flex: 1;
            padding: 15px 25px;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 16px;
            outline: none;
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .search-button {
            padding: 0 30px;
            background: #1DB954;
            border: none;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-button:hover {
            background: #1ed760;
        }

        .results-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .track-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .track-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .track-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .track-info {
            padding: 15px;
        }

        .track-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .track-artist {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .player-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(30, 30, 46, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 1000;
        }

        .player-active {
            display: block;
        }

        .player-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .player-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
        }

        .player-text {
            flex: 1;
        }

        .player-track-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .player-track-artist {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .player-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
        }

        .control-button {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .control-button:hover {
            color: #1DB954;
        }

        .play-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #1DB954;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
        }

        .play-button:hover {
            background: #1ed760;
            color: white;
        }

        .progress-container {
            margin-top: 15px;
        }

        .progress-bar {
            width: 100%;
            height: 5px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            overflow: hidden;
            cursor: pointer;
        }

        .progress {
            height: 100%;
            background: #1DB954;
            width: 0%;
            border-radius: 5px;
            transition: width 0.1s ease;
        }

        .time-info {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .loading {
            text-align: center;
            padding: 40px;
            font-size: 18px;
            color: rgba(255, 255, 255, 0.7);
        }

        .error-message {
            text-align: center;
            padding: 40px;
            color: #ff6b6b;
            font-size: 18px;
        }

        .preview-notice {
            font-size: 12px;
            color: #1DB954;
            margin-top: 5px;
        }

        .no-preview {
            font-size: 12px;
            color: #ff6b6b;
            margin-top: 5px;
        }

        .play-btn {
            background: #1DB954;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            transition: all 0.3s ease;
        }

        .play-btn:hover:not(:disabled) {
            background: #1ed760;
            transform: scale(1.05);
        }

        .play-btn:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
        }

        .preview-btn {
            background: #334155;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            transition: all 0.3s ease;
        }

        .preview-btn:hover:not(:disabled) {
            background: #475569;
            transform: scale(1.05);
        }

        .preview-btn:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
        }

        .play-full-btn {
            background: #f59e0b;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            transition: all 0.3s ease;
        }

        .play-full-btn:hover {
            background: #fbbf24;
            transform: scale(1.05);
        }

        .youtube-btn {
            background: #ff0000;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            transition: all 0.3s ease;
        }

        .youtube-btn:hover {
            background: #ff3333;
            transform: scale(1.05);
        }

        .download-btn {
            background: #0ea5e9;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            transition: all 0.3s ease;
        }

        .download-btn:hover:not(:disabled) {
            background: #38bdf8;
            transform: scale(1.05);
        }

        .download-btn:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
        }

        .track-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .track-actions button {
            width: 100%;
            margin-top: 0;
            margin-left: 0;
            text-align: center;
        }

        .stats {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.5);
        }

        .debug-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #1DB954;
        }

        .message-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1DB954;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 1001;
            animation: slideIn 0.3s ease;
            font-weight: 600;
            max-width: 300px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .youtube-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
        }

        .youtube-modal.active {
            display: flex;
        }

        .youtube-modal-content {
            width: min(960px, 100%);
            background: #0f172a;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }

        .youtube-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
        }

        .youtube-modal-title {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .youtube-close-btn {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            line-height: 1;
        }

        .youtube-iframe-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            background: #000;
        }

        .youtube-iframe-wrap iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .results-container {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .player-info {
                flex-direction: column;
                text-align: center;
            }
            
            .player-controls {
                gap: 20px;
            }
            
            .track-actions {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                flex-direction: column;
                gap: 10px;
                text-align: right;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fab fa-spotify"></i>
                <span>Musicfy</span>
            </div>
            <div class="user-info">
                <div class="user-welcome" id="userWelcome">Welcome, User!</div>
                <button class="logout-btn" id="logoutBtn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </header>

        <div id="debug-info" class="debug-info" style="display: none;">
            <strong>Debug Info:</strong> <span id="debug-text">Loading...</span>
        </div>

        <div class="search-container">
            <input type="text" class="search-input" id="search-input" placeholder="Search for any song, artist, or album...">
            <button class="search-button" id="search-button">Search</button>
        </div>

        <div id="results-container" class="results-container">
            <div class="loading">
                <i class="fas fa-music"></i>
                <p>Search for any music from Spotify's database</p>
                <p style="font-size: 14px; margin-top: 10px; color: #1DB954;">All songs from Spotify will appear here</p>
            </div>
        </div>

        <div id="player-container" class="player-container">
            <div class="player-info">
                <img id="player-image" class="player-image" src="" alt="Track Image">
                <div class="player-text">
                    <div id="player-track-name" class="player-track-name">Track Name</div>
                    <div id="player-track-artist" class="player-track-artist">Artist Name</div>
                </div>
            </div>
            <div class="player-controls">
                <button class="control-button" id="prev-button">
                    <i class="fas fa-step-backward"></i>
                </button>
                <button class="control-button play-button" id="play-button">
                    <i class="fas fa-play"></i>
                </button>
                <button class="control-button" id="next-button">
                    <i class="fas fa-step-forward"></i>
                </button>
            </div>
            <div class="progress-container">
                <div class="progress-bar" id="progress-bar">
                    <div class="progress" id="progress"></div>
                </div>
                <div class="time-info">
                    <span id="current-time">0:00</span>
                    <span id="total-time">0:30</span>
                </div>
            </div>
        </div>
    </div>

    <div id="youtube-modal" class="youtube-modal" aria-hidden="true">
        <div class="youtube-modal-content" role="dialog" aria-modal="true" aria-labelledby="youtube-modal-title">
            <div class="youtube-modal-header">
                <div id="youtube-modal-title" class="youtube-modal-title">YouTube Player</div>
                <button id="youtube-close-btn" class="youtube-close-btn" type="button" aria-label="Close YouTube player">&times;</button>
            </div>
            <div class="youtube-iframe-wrap">
                <iframe
                    id="youtube-player-frame"
                    src=""
                    allow="autoplay; encrypted-media; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                    title="YouTube player"></iframe>
            </div>
        </div>
    </div>

    <script>
        if (!window.YT) {
            const ytScript = document.createElement('script');
            ytScript.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(ytScript);
        }

        // DOM Elements
        const searchInput = document.getElementById('search-input');
        const searchButton = document.getElementById('search-button');
        const resultsContainer = document.getElementById('results-container');
        const playerContainer = document.getElementById('player-container');
        const playerImage = document.getElementById('player-image');
        const playerTrackName = document.getElementById('player-track-name');
        const playerTrackArtist = document.getElementById('player-track-artist');
        const playButton = document.getElementById('play-button');
        const prevButton = document.getElementById('prev-button');
        const nextButton = document.getElementById('next-button');
        const progressBar = document.getElementById('progress-bar');
        const progress = document.getElementById('progress');
        const currentTimeEl = document.getElementById('current-time');
        const totalTimeEl = document.getElementById('total-time');
        const debugInfo = document.getElementById('debug-info');
        const debugText = document.getElementById('debug-text');
        const youtubeModal = document.getElementById('youtube-modal');
        const youtubeCloseBtn = document.getElementById('youtube-close-btn');
        const youtubePlayerFrame = document.getElementById('youtube-player-frame');
        const youtubeModalTitle = document.getElementById('youtube-modal-title');

        // Application State
        let currentTrack = null;
        let isPlaying = false;
        let currentTrackIndex = 0;
        let searchResults = [];
        let audio = new Audio();
        let progressInterval;
        let ytPlayer = null;
        let youtubeQueue = [];
        let currentYouTubeIndex = 0;

        function onYouTubeIframeAPIReady() {
            ytPlayer = new YT.Player('youtube-player-frame', {
                playerVars: {
                    autoplay: 1,
                    rel: 0
                },
                events: {
                    onError: handleYouTubePlayerError
                }
            });
        }
        window.onYouTubeIframeAPIReady = onYouTubeIframeAPIReady;

        // Browser history and navigation control
        function setupNavigationControls() {
            // Replace current history entry with dashboard to prevent back button issues
            window.history.replaceState({ page: 'dashboard' }, '', window.location.href);
            
            // Handle browser back button
            window.addEventListener('popstate', function(event) {
                if (!checkAuthentication()) {
                    return;
                }
                // User pressed back but we're already on dashboard - just reload
                window.location.reload();
            });
        }

        // Enhanced authentication check
        function checkAuthentication() {
            const userData = localStorage.getItem('musicfy_user');
            const sessionToken = localStorage.getItem('musicfy_session');
            const lastLogin = localStorage.getItem('musicfy_last_login');
            
            // Check if all required items exist
            if (!userData || !sessionToken || !lastLogin) {
                redirectToLogin();
                return null;
            }
            
            // Check if session is expired (more than 30 minutes)
            const loginTime = new Date(lastLogin);
            const currentTime = new Date();
            const sessionTimeout = 30 * 60 * 1000; // 30 minutes in milliseconds
            
            if (currentTime - loginTime > sessionTimeout) {
                showMessage('Session expired. Please login again.');
                logout();
                return null;
            }
            
            try {
                const user = JSON.parse(userData);
                return user;
            } catch (error) {
                console.error('Authentication error:', error);
                redirectToLogin();
                return null;
            }
        }

        // Redirect to login
        function redirectToLogin() {
            // Clear all stored data
            localStorage.removeItem('musicfy_session');
            localStorage.removeItem('musicfy_user');
            localStorage.removeItem('musicfy_last_login');
            
            // Use replace to prevent back button issues
            window.location.replace('index.php');
        }

        // Enhanced logout function
        function logout() {
            const sessionToken = localStorage.getItem('musicfy_session');
            
            // Notify server about logout
            if (sessionToken) {
                fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'logout',
                        session_token: sessionToken
                    })
                }).catch(error => console.error('Logout API error:', error));
            }
            
            redirectToLogin();
        }

        // Show debug info
        function showDebugInfo(message) {
            debugInfo.style.display = 'block';
            debugText.textContent = message;
            console.log('DEBUG:', message);
        }

        // Format time from seconds to MM:SS
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        }

        // Search Spotify through the PHP backend so the client secret stays server-side.
        async function searchSpotify(query) {
            showDebugInfo(`Searching for: "${query}"`);

            try {
                const response = await fetch(`config.php?action=search&query=${encodeURIComponent(query)}`);

                showDebugInfo(`Response status: ${response.status}`);

                if (!response.ok) {
                    const errorText = await response.text();
                    showDebugInfo(`Search endpoint error: ${response.status} - ${errorText}`);
                    throw new Error(`Search endpoint error: ${response.status}`);
                }

                const data = await response.json();
                if (data.error) {
                    throw new Error(data.error);
                }

                showDebugInfo(`Found ${data.tracks.items.length} tracks`);
                
                return data.tracks.items;

            } catch (error) {
                showDebugInfo(`Search failed: ${error.message}`);
                throw error;
            }
        }

        // Display search results
        function displayResults(tracks) {
            resultsContainer.innerHTML = '';
            
            if (!tracks || tracks.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>No tracks found for "${searchInput.value}"</p>
                        <p style="margin-top: 10px;">Try a different search term</p>
                    </div>
                `;
                return;
            }
            
            // Display tracks
            tracks.forEach((track) => {
                const trackElement = document.createElement('div');
                trackElement.className = 'track-card';
                
                const hasPreview = track.preview_url !== null;
                const popularity = track.popularity || 0;
                const imageUrl = track.album.images[0] ? track.album.images[0].url : 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';
                const artistNames = track.artists.map(artist => artist.name).join(', ');
                
                trackElement.innerHTML = `
                    <img src="${imageUrl}" 
                         alt="${track.name}" 
                         class="track-image">
                    <div class="track-info">
                        <div class="track-name">${track.name}</div>
                        <div class="track-artist">${artistNames}</div>
                        <div class="stats">
                            <span>Popularity: ${popularity}%</span>
                            <span>${hasPreview ? 'Preview Available' : 'No Preview'}</span>
                        </div>
                        <div class="track-actions">
                            <button class="play-btn" onclick="playTrackFromButton('${track.id}')">
                                <i class="fas fa-play"></i> Play
                            </button>
                            <button class="preview-btn" ${!hasPreview ? 'disabled' : ''} onclick="playPreviewFromButton('${track.id}')">
                                ${hasPreview ? '<i class="fas fa-music"></i> Preview 30s' : '<i class="fas fa-ban"></i> No Preview'}
                            </button>
                            <button class="download-btn" ${!hasPreview ? 'disabled' : ''} onclick="downloadTrack('${track.id}')">
                                <i class="fas fa-download"></i> Download
                            </button>
                            <button class="youtube-btn" onclick="searchOnYouTube('${track.name.replace(/'/g, "\\'")}', '${track.artists[0].name.replace(/'/g, "\\'")}')">
                                <i class="fab fa-youtube"></i> YouTube Link
                            </button>
                        </div>
                        ${hasPreview ? 
                            '<div class="preview-notice"><i class="fas fa-check-circle"></i> 30-second preview available</div>' : 
                            '<div class="no-preview"><i class="fas fa-exclamation-triangle"></i> Full song on YouTube only</div>'
                        }
                    </div>
                `;
                
                resultsContainer.appendChild(trackElement);
            });
            
            searchResults = tracks;
            showDebugInfo(`Displayed ${tracks.length} tracks`);
        }

        // Play track from button click
        function playTrackFromButton(trackId) {
            const track = searchResults.find(t => t.id === trackId);
            if (!track) {
                showMessage('❌ Track not found');
                return;
            }

            playFullMusic(track.name, track.artists[0].name);
        }

        function playPreviewFromButton(trackId) {
            const track = searchResults.find(t => t.id === trackId);
            if (!track) {
                showMessage('❌ Track not found');
                return;
            }

            if (!track.preview_url) {
                showMessage('🎵 No preview available. Click YouTube button for full song.');
                return;
            }

            showDebugInfo(`Playing: ${track.name} by ${track.artists[0].name}`);
            const index = searchResults.findIndex(t => t.id === trackId);
            playTrack(track, index);
        }

        // Play full music on YouTube inside the app.
        function searchOnYouTube(trackName, artistName) {
            const searchQuery = encodeURIComponent(`${trackName} ${artistName} official audio`);
            window.open(`https://www.youtube.com/results?search_query=${searchQuery}`, '_blank');
            showDebugInfo(`Opened YouTube link: ${trackName} by ${artistName}`);
        }

        async function playFullMusic(trackName, artistName) {
            const query = `${trackName} ${artistName} official audio`;
            showDebugInfo(`Finding full music on YouTube: ${query}`);

            try {
                const response = await fetch(`config.php?action=youtube_search&query=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (!response.ok || data.error || !data.video_ids || data.video_ids.length === 0) {
                    throw new Error(data.error || 'No playable YouTube videos found');
                }

                youtubeQueue = data.video_ids;
                currentYouTubeIndex = 0;
                youtubeModalTitle.textContent = `${trackName} - ${artistName}`;
                youtubeModal.classList.add('active');
                youtubeModal.setAttribute('aria-hidden', 'false');

                if (ytPlayer && typeof ytPlayer.loadVideoById === 'function') {
                    ytPlayer.loadVideoById(youtubeQueue[currentYouTubeIndex]);
                } else {
                    youtubePlayerFrame.src = `https://www.youtube-nocookie.com/embed/${youtubeQueue[currentYouTubeIndex]}?autoplay=1&rel=0`;
                }

                showMessage(`Playing full music: ${trackName}`);
            } catch (error) {
                showDebugInfo(`Full play failed: ${error.message}`);
                showMessage('Could not play in-app. Use YouTube Link button.');
            }
        }

        function handleYouTubePlayerError(event) {
            const blockedCodes = [100, 101, 150];
            if (!blockedCodes.includes(event.data)) {
                return;
            }

            currentYouTubeIndex += 1;
            if (currentYouTubeIndex >= youtubeQueue.length) {
                showMessage('No embeddable YouTube result found. Use YouTube Link.');
                return;
            }

            const nextVideoId = youtubeQueue[currentYouTubeIndex];
            showDebugInfo(`Trying next YouTube result (${currentYouTubeIndex + 1}/${youtubeQueue.length})`);
            ytPlayer.loadVideoById(nextVideoId);
        }

        function closeYouTubePlayer() {
            if (ytPlayer && typeof ytPlayer.stopVideo === 'function') {
                ytPlayer.stopVideo();
            } else {
                youtubePlayerFrame.src = '';
            }
            youtubeQueue = [];
            currentYouTubeIndex = 0;
            youtubeModal.classList.remove('active');
            youtubeModal.setAttribute('aria-hidden', 'true');
        }

        function sanitizeFileName(name) {
            return name.replace(/[<>:"/\\|?*\x00-\x1F]/g, '_').trim();
        }

        async function downloadTrack(trackId) {
            const track = searchResults.find(t => t.id === trackId);
            if (!track) {
                showMessage('Track not found');
                return;
            }

            if (!track.preview_url) {
                showMessage('No downloadable preview for this track');
                return;
            }

            try {
                showDebugInfo(`Downloading preview: ${track.name}`);
                const response = await fetch(track.preview_url);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const audioBlob = await response.blob();
                const objectUrl = URL.createObjectURL(audioBlob);
                const a = document.createElement('a');
                const artist = track.artists && track.artists[0] ? track.artists[0].name : 'Unknown Artist';
                const fileName = sanitizeFileName(`${track.name} - ${artist}.m4a`);
                a.href = objectUrl;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(objectUrl);
                showMessage(`Downloaded: ${track.name}`);
            } catch (error) {
                showDebugInfo(`Download failed: ${error.message}`);
                showMessage('Download failed for this preview');
            }
        }

        // Play a track
        function playTrack(track, index) {
            // Stop current audio
            if (audio) {
                audio.pause();
                clearInterval(progressInterval);
            }

            currentTrack = track;
            currentTrackIndex = index;
            
            // Update player UI
            playerImage.src = track.album.images[0]?.url || 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';
            playerTrackName.textContent = track.name;
            playerTrackArtist.textContent = track.artists.map(artist => artist.name).join(', ');
            
            // Show player
            playerContainer.classList.add('player-active');
            
            // Reset progress
            progress.style.width = '0%';
            currentTimeEl.textContent = '0:00';
            
            // Set up audio
            audio = new Audio(track.preview_url);
            
            // Set up event listeners
            audio.addEventListener('canplay', () => {
                showDebugInfo('Audio ready to play');
                playButton.disabled = false;
            });
            
            audio.addEventListener('error', (e) => {
                showDebugInfo('Audio error: ' + e);
                showMessage('❌ Error loading preview');
                playButton.innerHTML = '<i class="fas fa-play"></i>';
                isPlaying = false;
            });
            
            audio.addEventListener('ended', () => {
                pauseAudio();
                progress.style.width = '0%';
                currentTimeEl.textContent = '0:00';
                showMessage('Preview ended. Click YouTube for full song.');
            });
            
            // Play immediately
            playAudio();
        }

        // Play/pause audio
        function togglePlayPause() {
            if (!currentTrack || !audio.src) {
                showMessage('❌ No track selected');
                return;
            }

            if (isPlaying) {
                pauseAudio();
            } else {
                playAudio();
            }
        }

        // Play audio
        function playAudio() {
            if (!audio.src) return;
            
            const playPromise = audio.play();
            
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    isPlaying = true;
                    playButton.innerHTML = '<i class="fas fa-pause"></i>';
                    
                    // Start progress updates
                    progressInterval = setInterval(updateProgress, 100);
                    showMessage(`🎵 Now playing: ${currentTrack.name}`);
                    
                }).catch(error => {
                    console.error('Play failed:', error);
                    showMessage('❌ Cannot play preview. Try YouTube for full song.');
                    playButton.innerHTML = '<i class="fas fa-play"></i>';
                });
            }
        }

        // Pause audio
        function pauseAudio() {
            audio.pause();
            isPlaying = false;
            playButton.innerHTML = '<i class="fas fa-play"></i>';
            clearInterval(progressInterval);
        }

        // Update progress bar
        function updateProgress() {
            const currentTime = audio.currentTime || 0;
            const progressPercent = (currentTime / 30) * 100; // 30-second previews
            progress.style.width = `${progressPercent}%`;
            currentTimeEl.textContent = formatTime(currentTime);
        }

        // Play next track
        function playNextTrack() {
            if (searchResults.length === 0) return;
            
            let nextIndex = currentTrackIndex;
            let attempts = 0;
            
            while (attempts < searchResults.length) {
                nextIndex = (nextIndex + 1) % searchResults.length;
                if (searchResults[nextIndex].preview_url) {
                    playTrack(searchResults[nextIndex], nextIndex);
                    return;
                }
                attempts++;
            }
            
            showMessage('⏭️ No more tracks with previews available');
        }

        // Play previous track
        function playPreviousTrack() {
            if (searchResults.length === 0) return;
            
            let prevIndex = currentTrackIndex;
            let attempts = 0;
            
            while (attempts < searchResults.length) {
                prevIndex = (prevIndex - 1 + searchResults.length) % searchResults.length;
                if (searchResults[prevIndex].preview_url) {
                    playTrack(searchResults[prevIndex], prevIndex);
                    return;
                }
                attempts++;
            }
            
            showMessage('⏮️ No previous tracks with previews available');
        }

        // Search function
        async function performSearch() {
            const query = searchInput.value.trim();
            
            if (query === '') {
                showMessage('Please enter a search term');
                return;
            }
            
            // Show loading state
            resultsContainer.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Searching for "${query}"...</p>
                </div>
            `;
            
            try {
                const tracks = await searchSpotify(query);
                
                if (tracks.length > 0) {
                    displayResults(tracks);
                    const previewCount = tracks.filter(track => track.preview_url).length;
                    showMessage(`🎵 Found ${tracks.length} tracks (${previewCount} with previews)`);
                } else {
                    resultsContainer.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>No tracks found for "${query}"</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Search error:', error);
                resultsContainer.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Search failed: ${error.message}</p>
                        <p style="margin-top: 10px;">Please try a different search term</p>
                    </div>
                `;
            }
        }

        // Show message
        function showMessage(message) {
            // Remove existing messages
            const existingMessages = document.querySelectorAll('.message-toast');
            existingMessages.forEach(msg => msg.remove());
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message-toast';
            messageDiv.textContent = message;
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                if (document.body.contains(messageDiv)) {
                    document.body.removeChild(messageDiv);
                }
            }, 4000);
        }

        // Event Listeners
        searchButton.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') performSearch();
        });
        youtubeCloseBtn.addEventListener('click', closeYouTubePlayer);
        youtubeModal.addEventListener('click', (e) => {
            if (e.target === youtubeModal) {
                closeYouTubePlayer();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && youtubeModal.classList.contains('active')) {
                closeYouTubePlayer();
            }
        });

        playButton.addEventListener('click', togglePlayPause);
        prevButton.addEventListener('click', playPreviousTrack);
        nextButton.addEventListener('click', playNextTrack);
        logoutBtn.addEventListener('click', logout);

        // Seek in the track
        progressBar.addEventListener('click', (e) => {
            if (!audio.src) return;
            
            const progressBarWidth = progressBar.clientWidth;
            const clickX = e.offsetX;
            const duration = 30; // 30-second previews
            
            audio.currentTime = (clickX / progressBarWidth) * duration;
        });

        // Initialize when page loads
        window.addEventListener('load', async () => {
            // Setup navigation controls first
            setupNavigationControls();
            
            // Check authentication
            const user = checkAuthentication();
            if (!user) {
                return; // Redirect will happen in checkAuthentication
            }
            
            // Update UI with user info
            userWelcome.textContent = `Welcome, ${user.username || user.full_name || 'User'}!`;

            showDebugInfo('Initializing Musicfy...');
            
            // Test the API connection
            try {
                await searchSpotify('test');
                const loadingDiv = document.querySelector('.loading');
                if (loadingDiv) {
                    loadingDiv.innerHTML = `
                        <i class="fas fa-music"></i>
                        <p>Ready to search! Enter a song name above.</p>
                        <p style="font-size: 14px; margin-top: 10px; color: #1DB954;">Try searching for "Blinding Lights" or "Shape of You"</p>
                    `;
                }
            } catch (error) {
                showDebugInfo('Initialization failed: ' + error.message);
            }
        });

        // Make functions global for onclick handlers
        window.playTrackFromButton = playTrackFromButton;
        window.playPreviewFromButton = playPreviewFromButton;
        window.searchOnYouTube = searchOnYouTube;
        window.playFullMusic = playFullMusic;
        window.downloadTrack = downloadTrack;
        window.closeYouTubePlayer = closeYouTubePlayer;
    </script>
</body>
</html>
