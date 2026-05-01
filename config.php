<?php
// config.php - Backend configuration for Musicfy with Spotify API

// Enable CORS for frontend requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Spotify API Configuration
define('SPOTIFY_CLIENT_ID', 'a0e607acf5a5406ca5b1d4d53aa6135d');
define('SPOTIFY_CLIENT_SECRET', '1819873c0ff34f0dba3198b1fa960c9e');
define('SPOTIFY_API_URL', 'https://api.spotify.com/v1/');

function makeHttpRequest($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'body' => $response,
        'error' => $curlError
    ];
}

// Get Spotify Access Token
function getSpotifyAccessToken() {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, 'https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET),
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    error_log('Spotify token error: HTTP ' . $httpCode . ' ' . $curlError . ' ' . $response);
    return null;
}

// Function to make Spotify API requests
function makeSpotifyRequest($endpoint, $accessToken) {
    $result = makeHttpRequest(SPOTIFY_API_URL . $endpoint, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    if ($result['status'] === 200) {
        return json_decode($result['body'], true);
    } else {
        error_log('Spotify API error: HTTP ' . $result['status'] . ' ' . $result['error'] . ' ' . $result['body']);
        return ['error' => 'Spotify API request failed with code: ' . $result['status']];
    }
}

function searchItunesFallback($query) {
    $url = 'https://itunes.apple.com/search?term=' . urlencode($query) . '&entity=song&limit=20';
    $result = makeHttpRequest($url, ['Accept: application/json']);

    if ($result['status'] !== 200) {
        error_log('iTunes fallback error: HTTP ' . $result['status'] . ' ' . $result['error'] . ' ' . $result['body']);
        return ['error' => 'Fallback provider request failed with code: ' . $result['status']];
    }

    $data = json_decode($result['body'], true);
    if (!isset($data['results']) || !is_array($data['results'])) {
        return ['error' => 'Fallback provider returned invalid data'];
    }

    $items = [];
    foreach ($data['results'] as $row) {
        $trackId = isset($row['trackId']) ? (string)$row['trackId'] : md5(($row['trackName'] ?? '') . ($row['artistName'] ?? ''));
        $imageUrl = $row['artworkUrl100'] ?? '';
        if (!empty($imageUrl)) {
            $imageUrl = str_replace('100x100bb', '600x600bb', $imageUrl);
        }

        $items[] = [
            'id' => $trackId,
            'name' => $row['trackName'] ?? 'Unknown Track',
            'artists' => [
                ['name' => $row['artistName'] ?? 'Unknown Artist']
            ],
            'album' => [
                'name' => $row['collectionName'] ?? '',
                'images' => !empty($imageUrl) ? [['url' => $imageUrl]] : []
            ],
            'preview_url' => $row['previewUrl'] ?? null,
            'popularity' => 0,
            'external_urls' => [
                'spotify' => $row['trackViewUrl'] ?? ''
            ]
        ];
    }

    return [
        'tracks' => [
            'items' => $items
        ],
        'source' => 'itunes'
    ];
}

function searchYouTubeVideoIds($query) {
    $url = 'https://www.youtube.com/results?search_query=' . urlencode($query);
    $result = makeHttpRequest($url, [
        'Accept-Language: en-US,en;q=0.9',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'
    ]);

    if ($result['status'] !== 200 || empty($result['body'])) {
        return ['error' => 'YouTube search request failed'];
    }

    preg_match_all('/"videoId":"([a-zA-Z0-9_-]{11})"/', $result['body'], $matches);
    $ids = [];
    if (!empty($matches[1])) {
        foreach ($matches[1] as $id) {
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            if (count($ids) >= 12) {
                break;
            }
        }
    }

    if (empty($ids)) {
        return ['error' => 'No YouTube videos found'];
    }

    return ['video_ids' => $ids];
}

// Function to search music on Spotify
function searchSpotify($query) {
    $accessToken = getSpotifyAccessToken();
    
    if (!$accessToken) {
        return searchItunesFallback($query);
    }
    
    $endpoint = 'search?q=' . urlencode($query) . '&type=track&limit=20&market=US';
    $spotifyResult = makeSpotifyRequest($endpoint, $accessToken);

    if (isset($spotifyResult['error'])) {
        return searchItunesFallback($query);
    }

    return $spotifyResult;
}

// Handle search request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'search') {
    $query = $_GET['query'] ?? '';
    
    if (!empty($query)) {
        $results = searchSpotify($query);
        header('Content-Type: application/json');
        echo json_encode($results);
    } else {
        echo json_encode(['error' => 'Empty query']);
    }
    exit;
}

// Handle get track request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_track') {
    $trackId = $_GET['track_id'] ?? '';
    
    if (!empty($trackId)) {
        $accessToken = getSpotifyAccessToken();
        if ($accessToken) {
            $results = makeSpotifyRequest('tracks/' . $trackId, $accessToken);
            header('Content-Type: application/json');
            echo json_encode($results);
        } else {
            echo json_encode(['error' => 'Failed to get access token']);
        }
    } else {
        echo json_encode(['error' => 'Empty track ID']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'youtube_search') {
    $query = $_GET['query'] ?? '';
    header('Content-Type: application/json');

    if (empty($query)) {
        echo json_encode(['error' => 'Empty query']);
        exit;
    }

    echo json_encode(searchYouTubeVideoIds($query));
    exit;
}
?>
