<?php

use OCI\Identity\IdentityClient;
use OCI\ObjectStorage\ObjectStorageClient;
use OCI\ObjectStorage\Requests\PutObjectRequest;

function fetchItunesMetadata(string $title, string $artist): array {
    $query = urlencode("$title $artist");
    $url = "https://itunes.apple.com/search?term=$query&entity=album&limit=1";
    
    $response = @file_get_contents($url);
    if (!$response) {
        return [];
    }
    
    $data = json_decode($response, true);
    if (empty($data['results'])) {
        return [];
    }
    
    $album = $data['results'][0];
    
    $releaseDate = '';
    if (!empty($album['releaseDate'])) {
        $releaseDate = explode('T', $album['releaseDate'])[0];
    }
    
    return [
        'release_date' => $releaseDate,
        'itunes_id'    => $album['collectionId'] ?? '',
        'genre'        => $album['primaryGenreName'] ?? ''
    ];
}

function uploadUploadedFileToOracle(array $fileArray, int $vinyl_id): string {
    $fallback = 'images/default-cover.png';
    
    if (!isset($fileArray['tmp_name']) || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return $fallback;
    }

    $fileTmpPath = $fileArray['tmp_name'];
    $fileName = $fileArray['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Read the binary stream data directly from the uploaded file temporary location
    $imageBinary = @file_get_contents($fileTmpPath);
    if (!$imageBinary) {
        return $fallback;
    }

    // Determine content type header mapping dynamically based on extension
    $contentType = 'image/jpeg';
    if ($fileExtension === 'png') {
        $contentType = 'image/png';
    } elseif ($fileExtension === 'webp') {
        $contentType = 'image/webp';
    }

    $filename = 'vinyl_' . $vinyl_id . '_' . time() . '.' . $fileExtension;
    
    $parBaseUrl = getenv('OCI_PAR_URL') ?: ''; // set in the environment, never commit it
    $targetUrl = $parBaseUrl . $filename;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $imageBinary);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: $contentType"
    ]);

    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        return rtrim(getenv('OCI_PUBLIC_BASE_URL') ?: '', '/') . "/$filename";
    }

    return $fallback;
}

function downloadAndStoreCoverToOracle(string $title, string $artist, int $vinyl_id): string {
    $query = urlencode("$title $artist");
    $url = "https://itunes.apple.com/search?term=$query&entity=album&limit=1";
    
    $fallback = 'images/default-cover.png';
    
    $response = @file_get_contents($url);
    if (!$response) {
        return $fallback;
    }
    
    $data = json_decode($response, true);
    if (empty($data['results'])) {
        return $fallback;
    }
    
    $imgUrl100 = $data['results'][0]['artworkUrl100'];
    $highResUrl = str_replace('100x100bb.jpg', '600x600bb.jpg', $imgUrl100);
    
    $imageBinary = @file_get_contents($highResUrl);
    if (!$imageBinary) {
        return $fallback;
    }
    
    $filename = 'vinyl_' . $vinyl_id . '_' . time() . '.jpg';
    
    $parBaseUrl = getenv('OCI_PAR_URL') ?: ''; // set in the environment, never commit it
    $targetUrl = $parBaseUrl . $filename;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $imageBinary);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: image/jpeg'
    ]);
    
    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 201) {
        return rtrim(getenv('OCI_PUBLIC_BASE_URL') ?: '', '/') . "/$filename";
    }
    
    return $fallback;
}

function discoverItunesId(string $title, string $artist): string {
    $query = urlencode("$artist $title");
    $response = @file_get_contents("https://itunes.apple.com/search?term=$query&entity=album&limit=1");

    if ($response === false) return '';

    $data = json_decode($response, true);
    return (string)($data['results'][0]['collectionId'] ?? '');
}

function renderAdminFormAutomation(): void {
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const titleInput = document.getElementById('vinyl_title');
    const artistSelect = document.getElementById('artist_select');
    const releaseDateInput = document.getElementById('release_date');
    const itunesIdInput = document.getElementById('itunes_id');
    const genreSelect = document.getElementById('genre_select');
    const newGenresInput = document.getElementById('new_genres');

    if (!titleInput || !artistSelect) return;

    const autoFetchMetadata = async () => {
        const title = titleInput.value.trim();
        const artistName = artistSelect.options[artistSelect.selectedIndex]?.text || '';

        if (!title || artistSelect.value === '') {
            return;
        }

        const searchTerm = encodeURIComponent(`${title} ${artistName}`);
        const url = `https://itunes.apple.com/search?term=${searchTerm}&entity=album&limit=1`;

        try {
            const response = await fetch(url);
            if (!response.ok) return;
            
            const data = await response.json();
            if (!data.results || data.results.length === 0) return;

            const album = data.results[0];

            if (!releaseDateInput.value && album.releaseDate) {
                releaseDateInput.value = album.releaseDate.split('T')[0];
            }

            if (!itunesIdInput.value && album.collectionId) {
                itunesIdInput.value = album.collectionId;
            }

            if (album.primaryGenreName) {
                const targetGenre = album.primaryGenreName.trim().toLowerCase();
                let foundMatch = false;

                // Loop through the multi-select dropdown options
                for (let i = 0; i < genreSelect.options.length; i++) {
                    const optionText = genreSelect.options[i].text.toLowerCase();
                    
                    // Flexible matching: check if names contain or match each other
                    if (optionText === targetGenre || optionText.includes(targetGenre) || targetGenre.includes(optionText)) {
                        genreSelect.options[i].selected = true;
                        foundMatch = true;
                    }
                }

                if (!foundMatch) {
                    const currentNewText = newGenresInput.value.trim();
                    const properGenreName = album.primaryGenreName.trim();
                    
                    if (currentNewText === '') {
                        newGenresInput.value = properGenreName;
                    } else {
                        const existingTags = currentNewText.split(',').map(tag => tag.trim().toLowerCase());
                        if (!existingTags.includes(targetGenre)) {
                            newGenresInput.value = `${currentNewText}, ${properGenreName}`;
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Metadata lookup failed:', error);
        }
    };

    titleInput.addEventListener('blur', autoFetchMetadata);
    artistSelect.addEventListener('change', autoFetchMetadata);
});
</script>
<?php
}