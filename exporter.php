<?php
$apiKey = getenv("API_KEY");
$host = getenv("SYNCTHING_HOST");

function callSyncthingApi(string $endpoint, string $apiKey): ?array {
    $opts = [
        "http" => [
            "header" => "X-API-Key: $apiKey\r\n",
            "method" => "GET",
            "timeout" => 5,
        ],
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ],
    ];
    $context = stream_context_create($opts);
    $data = @file_get_contents($endpoint, false, $context);
    return $data !== false ? json_decode($data, true) : null;
}

// Headers Prometheus
header('Content-Type: text/plain; version=0.0.4');

// Appels API Syncthing
$systemStatus = callSyncthingApi("$host/rest/system/status", $apiKey);
$config = callSyncthingApi("$host/rest/system/config", $apiKey);

// === 1. syncthing_up ===
echo "# HELP syncthing_up Est-ce que Syncthing répond\n";
echo "# TYPE syncthing_up gauge\n";
if (!$systemStatus || !$config) {
    echo "syncthing_up 0\n";
    exit;
}
echo "syncthing_up 1\n";

// === 2. Uptime ===
$uptimeSeconds = $systemStatus['uptime'] ?? 0;
echo "# HELP syncthing_uptime_seconds Uptime de Syncthing (secondes)\n";
echo "# TYPE syncthing_uptime_seconds gauge\n";
echo "syncthing_uptime_seconds $uptimeSeconds\n";

// === 3. Nombre de dossiers ===
$folderCount = isset($config['folders']) ? count($config['folders']) : 0;
echo "# HELP syncthing_folder_count Nombre total de dossiers configurés\n";
echo "# TYPE syncthing_folder_count gauge\n";
echo "syncthing_folder_count $folderCount\n";

// === 4. Données totales (globalBytes) ===
echo "# HELP syncthing_data_total_bytes Données globales cumulées de tous les dossiers\n";
echo "# TYPE syncthing_data_total_bytes gauge\n";

$totalBytes = 0;
$folderStatuses = callSyncthingApi("$host/rest/db/status", $apiKey);

if (is_array($folderStatuses)) {
    foreach ($folderStatuses as $folder) {
        $totalBytes += $folder['globalBytes'] ?? 0;
    }
}
echo "syncthing_data_total_bytes $totalBytes\n";

// === 5. Statut pause par dossier ===
echo "# HELP syncthing_folder_paused Dossier en pause (1) ou actif (0)\n";
echo "# TYPE syncthing_folder_paused gauge\n";

if (is_array($config['folders'])) {
    foreach ($config['folders'] as $folder) {
        $folderId = $folder['id'];
        $paused = !empty($folder['paused']) && $folder['paused'] === true ? 1 : 0;
        $label = $folder['label'] ?? $folderId;

        echo 'syncthing_folder_paused{folder_id="' . $folderId . '",folder_label="' . addslashes($label) . "\"} $paused\n";
    }
}
